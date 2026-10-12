<?php

declare(strict_types=1);

namespace AaiEduHr\SimbiozaModuleWorkspaceSearch\Listener;

use AaiEduHr\SimbiozaModuleWorkspace\Event\WorkspaceContentChanged;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchIndexer;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * EN: Immediately synchronizes the derived index after published Workspace
 *     content changes, without making Workspace depend on Search.
 *
 * HR: Odmah sinkronizira izvedeni indeks nakon promjene objavljenog Workspace
 *     sadržaja. Izvorni Workspace modul pritom ne ovisi o Search modulu.
 */
final readonly class SynchronizeWorkspaceSearchIndex
{
    /**
     * EN: Initializes the listener with the index maintenance service.
     *
     * HR: Inicijalizira listener servisom za održavanje indeksa.
     */
    public function __construct(
        private WorkspaceSearchIndexer $indexer,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * EN: Limits synchronization to the changed Workspace and, when known, locale.
     *
     * HR: Ograničava obnovu na promijenjeno područje i, kada je poznat, jezik.
     */
    public function __invoke(WorkspaceContentChanged $event): void
    {
        /*
         * EN: New or permanently discarded unpublished nodes have no published
         *     row, so saving the first draft must not trigger indexing.
         *
         * HR: Novi ili trajno odbačeni neobjavljeni čvorovi nemaju objavljeni
         *     redak, pa spremanje prvog nacrta ne treba pokretati indeksiranje.
         */
        if (
            in_array($event->reason, [
            'workspace_created',
            'node_created',
            'unpublished_node_deleted',
            ], true)
        ) {
            return;
        }

        try {
            if ($event->nodeId !== null && is_string($event->language) && $event->language !== '') {
                $this->indexer->synchronizeNode($event->workspaceId, $event->nodeId, $event->language);

                return;
            }

            $this->indexer->synchronizeWorkspace($event->workspaceId, $event->language);
        } catch (Throwable $throwable) {
            $this->logger?->error('Workspace search-index synchronization failed.', [
                'module' => 'workspace-search',
                'workspace_id' => $event->workspaceId,
                'page_id' => $event->nodeId,
                'language' => $event->language,
                'reason' => $event->reason,
                'exception' => $throwable,
            ]);
            throw $throwable;
        }
    }
}
