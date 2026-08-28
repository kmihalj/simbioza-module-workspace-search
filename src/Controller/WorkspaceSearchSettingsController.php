<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspaceSearch\Controller;

use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceAccessService;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspacePresentationRegistry;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceRepository;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceValue;
use AaiEduHr\HeartPhrameModuleWorkspaceSearch\Service\WorkspaceSearchIndexer;
use AaiEduHr\HeartPhrameModuleWorkspaceSearch\Service\WorkspaceSearchModuleViewRenderer;
use HeartPhrame\Alert\Alert;
use HeartPhrame\Alert\AlertHandler;
use HeartPhrame\CodeBook\AlertLevelEnum;
use HeartPhrame\Http\ResponseFactory;
use HeartPhrame\Routing\UrlGenerator;
use HeartPhrame\View\CsrfHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * HR: Administratorsko web sučelje za provjeru i ručnu obnovu indeksa.
 * EN: Administration web interface for inspecting and manually rebuilding the index.
 */
final readonly class WorkspaceSearchSettingsController
{
    /**
     * HR: Inicijalizira objekt i njegove ovisnosti.
     * EN: Initializes the object and its dependencies.
     */
    public function __construct(
        private WorkspaceSearchModuleViewRenderer $views,
        private ResponseFactory $responses,
        private WorkspaceSearchIndexer $indexer,
        private WorkspaceRepository $workspaces,
        private WorkspacePresentationRegistry $presentations,
        private WorkspaceAccessService $access,
        private UrlGenerator $urls,
        private AlertHandler $alerts,
        private CsrfHandler $csrf,
    ) {
    }

    /**
 * HR: Prikazuje izbor cijelog sitea ili jednog aktivnog područja.
 * EN: Shows the whole-site or one-active-Workspace selection.
 */
    public function index(): ResponseInterface
    {
        if (!$this->access->isAdministrator()) {
            return $this->responses->text(__('Pristup nije dozvoljen'), 403);
        }

        return $this->views->render('settings/index', [
        'title' => __('Indeks pretrage'),
        'workspaces' => $this->workspaces->tablesReady()
            ? $this->presentations->many($this->workspaces->activeWorkspaces())
            : [],
        'reindexPath' => $this->path('workspace-search.settings.reindex', '/settings/workspace-search/reindex'),
        'csrfInput' => $this->csrf->generateCsrfTokenInputField(),
        'settingsMenuActiveSection' => 'workspace-search.settings',
        ]);
    }

    /**
     * HR: Potpuno obnavlja odabrani opseg i javlja broj obrađenih i uklonjenih redaka.
     * EN: Fully rebuilds the selected scope and reports processed and removed rows.
     */
    public function reindex(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->access->isAdministrator()) {
            return $this->responses->text(__('Pristup nije dozvoljen'), 403);
        }

        $body = WorkspaceValue::stringKeyArray($request->getParsedBody());
        $workspaceId = WorkspaceValue::int($body['workspace_id'] ?? 0);
        try {
            if ($workspaceId > 0 && !is_array($this->workspaces->findWorkspaceById($workspaceId))) {
                throw new \RuntimeException(__('Odabrano područje nije pronađeno.'));
            }

            $result = $workspaceId > 0
            ? $this->indexer->rebuildWorkspace($workspaceId)
            : $this->indexer->rebuild(true);
            $this->alerts->add(new Alert(sprintf(
                __('Indeks je obnovljen. Obrađeno: %d; uklonjeno: %d.'),
                $result['indexed'],
                $result['removed'],
            ), AlertLevelEnum::Success));
        } catch (Throwable $throwable) {
            $this->alerts->add(new Alert($throwable->getMessage(), AlertLevelEnum::Danger));
        }

        return $this->responses->redirect($this->path('workspace-search.settings', '/settings/workspace-search'));
    }

    /**
     * HR: Gradi instalacijski neovisnu aplikacijsku putanju.
     * EN: Builds an installation-independent application path.
     *
     * HR: Gradi putanju rute uz bazni-path fallback. EN: Builds a route path with a base-path fallback.
     */
    private function path(string $route, string $fallback): string
    {
        try {
            return $this->urls->getPathFor($route);
        } catch (Throwable) {
            return rtrim($this->urls->getBasePath(), '/') . $fallback;
        }
    }
}
