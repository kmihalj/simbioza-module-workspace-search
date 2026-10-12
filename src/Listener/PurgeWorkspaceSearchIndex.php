<?php

declare(strict_types=1);

namespace AaiEduHr\SimbiozaModuleWorkspaceSearch\Listener;

use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\SimbiozaModuleWorkspace\Event\WorkspacePermanentlyDeleting;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\ModuleWorkspaceSearch;

/**
 * EN: Removes the derived Workspace index before its source is deleted.
 *
 * HR: Uklanja izvedeni indeks područja prije brisanja izvora.
 */
final readonly class PurgeWorkspaceSearchIndex
{
    /**
     * EN: Receives derived-index storage.
     *
     * HR: Prima spremište izvedenog indeksa.
     */
    public function __construct(private Database $database)
    {
    }

    /**
     * EN: Deletes all language-index rows for the supplied Workspace.
     *
     * HR: Briše sve jezične retke indeksa zadanog područja.
     */
    public function __invoke(WorkspacePermanentlyDeleting $event): void
    {
        if (!$this->database->schema()->hasTable(ModuleWorkspaceSearch::TABLE_INDEX)) {
            return;
        }

        $this->database->table(ModuleWorkspaceSearch::TABLE_INDEX)
            ->where('workspace_id', '=', $event->workspaceId)
            ->delete();
    }
}
