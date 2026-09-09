<?php

declare(strict_types=1);

namespace AaiEduHr\SimbiozaModuleWorkspaceSearch\Controller;

use AaiEduHr\HeartPhrameModuleAuth\Service\AuthUserService;
use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceAccessService;
use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceConfig;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Event\WorkspaceSearchPerformed;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchConfig;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchModuleViewRenderer;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchService;
use HeartPhrame\Http\ResponseFactory;
use HeartPhrame\Localization\TranslatorInterface;
use HeartPhrame\Routing\UrlGenerator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * HR: HTTP sučelje javne, ali uvijek ACL-svjesne Workspace pretrage.
 * EN: HTTP interface for public yet always ACL-aware Workspace search.
 */
final readonly class WorkspaceSearchController
{
    /**
 * HR: Prima renderiranje, ACL pretragu, lokalizaciju i generator putanja.
 * EN: Receives rendering, ACL search, localization, and route generation services.
 */
    public function __construct(
        private WorkspaceSearchModuleViewRenderer $views,
        private ResponseFactory $responses,
        private WorkspaceSearchService $search,
        private WorkspaceSearchConfig $config,
        private WorkspaceAccessService $access,
        private WorkspaceConfig $workspaceConfig,
        private TranslatorInterface $translator,
        private UrlGenerator $urls,
        private AuthUserService $users,
        private ?EventDispatcherInterface $events = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * HR: Renderira tematsku stranicu pretrage za gosta ili trenutačnog korisnika.
     * EN: Renders the themed search page for a guest or the current user.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();
        $term = $this->string($query['q'] ?? '');
        $language = $this->language($query['lang'] ?? $this->translator->getLocale());
        $user = $this->access->currentUser();
        $result = $this->search->search(
            $term,
            $language,
            $this->stringKeyArray($query),
            $user,
        );
        $this->dispatch(new WorkspaceSearchPerformed(
            $language,
            mb_strlen($term, 'UTF-8'),
            count(array_filter(preg_split('/\s+/u', $term) ?: [])),
            is_numeric($result['total'] ?? null) ? (int)$result['total'] : 0,
            implode(',', array_filter(array_map(
                $this->string(...),
                is_array($result['workspace_scopes'] ?? null) ? $result['workspace_scopes'] : [],
            ))),
            is_array($user) && is_numeric($user['id'] ?? null),
        ));

        $resultFilters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
        $authorId = is_numeric($resultFilters['author'] ?? null)
            ? (int)$resultFilters['author']
            : 0;
        $selectedAuthor = $authorId > 0 ? $this->users->findByIdIncludingInactive($authorId) : null;

        return $this->views->render('search/index', [
        'title' => __('Workspace search'),
        'themeTitleContext' => 'integrated',
        'themeHero' => [
        'is_home' => false,
        'title' => __('Workspace search'),
        'subtitle' => __('Find published content that you are allowed to view.'),
        ],
        'result' => $result,
        'minimumQueryLength' => $this->config->minimumQueryLength(),
        'assetsCssPath' => $this->path('workspace-search.assets.css', '/search/assets.css'),
        'searchPath' => $this->path('workspace-search.index', '/search'),
        'authorLookupPath' => is_array($user)
            ? $this->path('workspace-search.lookup.authors', '/search/lookups/authors')
            : '',
        'selectedAuthorLabel' => is_array($selectedAuthor)
            ? $this->userLabel($selectedAuthor, $authorId)
            : '',
        'paginationQuery' => $this->paginationQuery($query),
        ]);
    }

    /** HR: Vraća jednu ograničenu stranicu svih autora za udaljeni birač. EN: Returns one bounded page of all authors for the remote picker. */
    public function authors(ServerRequestInterface $request): ResponseInterface
    {
        if (!is_array($this->access->currentUser())) {
            return $this->responses->json(['ok' => false, 'error' => __('Pristup nije dozvoljen')], 403);
        }

        $query = $request->getQueryParams();
        $search = $this->string($query['q'] ?? '');
        $page = is_numeric($query['page'] ?? null) ? max(1, (int)$query['page']) : 1;
        $language = $this->language($query['lang'] ?? $this->translator->getLocale());

        return $this->responses->json([
            'ok' => true,
            ...$this->users->userLookupPage($search, $page, 25, [], $language),
        ], 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * HR: Vraća ACL-filtrirane prijedloge za progresivno poboljšanje tražilice.
     * EN: Returns ACL-filtered suggestions for progressive search enhancement.
     */
    public function suggest(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();
        $term = $this->string($query['q'] ?? '');
        $language = $this->language($query['lang'] ?? $this->translator->getLocale());
        $workspaceSlug = $this->string($query['workspace'] ?? '');

        return $this->responses->json([
        'data' => $this->search->suggest(
            $term,
            $language,
            $this->access->currentUser(),
            8,
            $workspaceSlug,
        ),
        ]);
    }

    /**
     * HR: Poslužuje mali tematski CSS resurs modula uz kratki javni cache.
     * EN: Serves the module's small theme-aware CSS asset with a short public cache.
     */
    public function styles(): ResponseInterface
    {
        $path = dirname(__DIR__, 2) . '/resources/assets/workspace-search.css';
        $css = is_file($path) ? file_get_contents($path) : '';

        return $this->responses->text(is_string($css) ? $css : '', headers: [
        'Content-Type' => 'text/css; charset=utf-8',
        'Cache-Control' => 'public, max-age=300',
        ]);
    }

    /** HR: Kvar opcionalnog audit slušatelja ne smije prekinuti pretragu. EN: An optional audit-listener failure must not interrupt search. */
    private function dispatch(WorkspaceSearchPerformed $event): void
    {
        if (!$this->events instanceof EventDispatcherInterface) {
            return;
        }

        try {
            $this->events->dispatch($event);
        } catch (\Throwable $throwable) {
            $this->logger?->error('Workspace-search business-event listener failed.', [
                'module' => 'workspace-search',
                'workspace_slug' => $event->workspaceSlug,
                'exception' => $throwable,
            ]);
        }
    }

    /**
     * HR: Validira locale i vraća zadani jezik sitea za neispravnu vrijednost.
     * EN: Validates the locale and returns the site default for an invalid value.
     */
    private function language(mixed $value): string
    {
        $language = strtolower($this->string($value));

        return preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $language) === 1
        ? $language
        : $this->workspaceConfig->siteDefaultLanguage();
    }

    /**
     * HR: Zadržava samo tekstualne ključeve HTTP query mape.
     * EN: Keeps only string keys from the HTTP query map.
     * @param array<mixed> $values
     * @return array<string, mixed>
     */
    private function stringKeyArray(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * HR: Normalizira ulaznu vrijednost u ograničeni string.
     * EN: Normalizes an input value into a bounded string.
     */
    private function string(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    /**
     * HR: Prikazuje odabranog autora istim redoslijedom Prezime Ime kao lookup.
     * EN: Displays the selected author in the same Surname Given-name order as the lookup.
     *
     * @param array<string,mixed> $user
     */
    private function userLabel(array $user, int $userId): string
    {
        $name = trim($this->string($user['last_name'] ?? '') . ' ' . $this->string($user['first_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        foreach (['display_name', 'login_identifier'] as $key) {
            $fallback = $this->string($user[$key] ?? '');
            if ($fallback !== '') {
                return $fallback;
            }
        }

        return __('Korisnik') . ' #' . $userId;
    }

    /**
     * HR: Gradi baznu query mapu za poveznice stranica bez oslanjanja viewa na globale.
     * EN: Builds the base query map for pagination links without view-level globals.
     * @param array<mixed,mixed> $query
     * @return array<string,scalar|list<scalar>>
     */
    private function paginationQuery(array $query): array
    {
        $result = [];
        foreach ($query as $key => $value) {
            if (is_string($key) && is_scalar($value) && $key !== 'page') {
                $result[$key] = $value;
                continue;
            }

            if (is_string($key) && is_array($value) && $key !== 'page') {
                $scalars = array_values(array_filter($value, is_scalar(...)));
                if ($scalars !== []) {
                    $result[$key] = $scalars;
                }
            }
        }

        return $result;
    }

    /**
     * HR: Generira putanju s baznim direktorijem aplikacije i sigurnim fallbackom.
     * EN: Generates a path with the application base directory and a safe fallback.
     */
    private function path(string $route, string $fallback): string
    {
        try {
            return $this->urls->getPathFor($route);
        } catch (\Throwable) {
            return rtrim($this->urls->getBasePath(), '/') . $fallback;
        }
    }
}
