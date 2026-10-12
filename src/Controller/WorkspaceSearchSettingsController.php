<?php

declare(strict_types=1);

namespace AaiEduHr\SimbiozaModuleWorkspaceSearch\Controller;

use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceAccessService;
use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceRepository;
use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceValue;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchIndexer;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchModuleViewRenderer;
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
 * EN: Administration web interface for inspecting and manually rebuilding the index.
 *
 * HR: Administratorsko web sučelje za provjeru i ručnu obnovu indeksa.
 */
final readonly class WorkspaceSearchSettingsController
{
    /**
     * EN: Initializes the object and its dependencies.
     *
     * HR: Inicijalizira objekt i njegove ovisnosti.
     */
    public function __construct(
        private WorkspaceSearchModuleViewRenderer $views,
        private ResponseFactory $responses,
        private WorkspaceSearchIndexer $indexer,
        private WorkspaceRepository $workspaces,
        private WorkspaceAccessService $access,
        private UrlGenerator $urls,
        private AlertHandler $alerts,
        private CsrfHandler $csrf,
    ) {
    }

    /**
     * EN: Shows the whole-site or one-active-Workspace selection.
     *
     * HR: Prikazuje izbor cijelog sitea ili jednog aktivnog područja.
     */
    public function index(): ResponseInterface
    {
        if (!$this->access->isAdministrator()) {
            return $this->responses->text(__('Pristup nije dozvoljen'), 403);
        }

        return $this->views->render('settings/index', [
        'title' => __('Indeks pretrage'),
        'reindexPath' => $this->path('workspace-search.settings.reindex', '/settings/workspace-search/reindex'),
        'workspaceLookupPath' => $this->path('workspace.lookup.workspaces', '/workspaces/lookups/workspaces'),
        'workspaceAssetsCssPath' => $this->path('workspace.assets.css', '/workspaces/assets.css'),
        'workspaceAssetsJsPath' => $this->path('workspace.assets.js', '/workspaces/assets.js'),
        'csrfInput' => $this->csrf->generateCsrfTokenInputField(),
        'settingsMenuActiveSection' => 'workspace-search.settings',
        ]);
    }

    /**
     * EN: Fully rebuilds the selected scope and reports processed and removed rows.
     *
     * HR: Potpuno obnavlja odabrani opseg i javlja broj obrađenih i uklonjenih redaka.
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
     * EN: Builds an installation-independent application path.
     *
     *
     * Builds a route path with a base-path fallback.
     *
     * HR: Gradi instalacijski neovisnu aplikacijsku putanju.
     *
     * Gradi putanju rute uz bazni-path fallback.
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
