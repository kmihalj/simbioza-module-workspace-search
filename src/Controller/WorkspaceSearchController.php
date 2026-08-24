<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspaceSearch\Controller;

use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceAccessService;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceConfig;
use AaiEduHr\HeartPhrameModuleWorkspaceSearch\Event\WorkspaceSearchPerformed;
use AaiEduHr\HeartPhrameModuleWorkspaceSearch\Service\WorkspaceSearchConfig;
use AaiEduHr\HeartPhrameModuleWorkspaceSearch\Service\WorkspaceSearchModuleViewRenderer;
use AaiEduHr\HeartPhrameModuleWorkspaceSearch\Service\WorkspaceSearchService;
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
            $this->string($query['workspace'] ?? ''),
            is_array($user) && is_numeric($user['id'] ?? null),
        ));

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
        'paginationQuery' => $this->paginationQuery($query),
        ]);
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
     * HR: Gradi baznu query mapu za poveznice stranica bez oslanjanja viewa na globale.
     * EN: Builds the base query map for pagination links without view-level globals.
     * @param array<mixed,mixed> $query
     * @return array<string,scalar>
     */
    private function paginationQuery(array $query): array
    {
        $result = [];
        foreach ($query as $key => $value) {
            if (is_string($key) && is_scalar($value) && $key !== 'page') {
                $result[$key] = $value;
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
