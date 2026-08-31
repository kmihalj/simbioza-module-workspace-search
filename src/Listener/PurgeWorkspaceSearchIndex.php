<?php

declare(strict_types=1);

namespace AaiEduHr\SimbiozaModuleWorkspaceSearch\Listener;

use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\SimbiozaModuleWorkspace\Event\WorkspacePermanentlyDeleting;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\ModuleWorkspaceSearch;

/** HR: Uklanja izvedeni indeks područja prije brisanja izvora. EN: Removes the derived Workspace index before its source is deleted. */
final readonly class PurgeWorkspaceSearchIndex
{
    /** HR: Prima spremište izvedenog indeksa. EN: Receives derived-index storage. */
    public function __construct(private Database $database)
    {
    }

    /** HR: Briše sve jezične retke indeksa zadanog područja. EN: Deletes all language-index rows for the supplied Workspace. */
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
