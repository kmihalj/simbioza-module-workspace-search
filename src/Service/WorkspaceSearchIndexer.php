<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspaceSearch\Service;

use AaiEduHr\HeartPhrameModuleAuth\Service\AuthUserService;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceConfig;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceRepository;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceValue;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceWorkflowService;
use AaiEduHr\HeartPhrameModuleWorkspaceSearch\ModuleWorkspaceSearch;

/**
 * HR: Sinkronizira izvedeni indeks sa stvarno objavljenim Workspace verzijama.
 *     Nacrti, arhivirane verzije i ACL podaci nikada se ne zapisuju u indeks.
 * EN: Synchronizes the derived index with actually published Workspace versions.
 *     Drafts, archived versions, and ACL data are never stored in the index.
 */
final class WorkspaceSearchIndexer
{
    private int $lastRefreshTimestamp = 0;

    /**
 * HR: Prima izvore Workspace metapodataka, objavljenih Editor verzija i autora.
 * EN: Receives sources for Workspace metadata, published Editor versions, and authors.
 */
    public function __construct(
        private readonly Database $database,
        private readonly WorkspaceRepository $workspaces,
        private readonly WorkspaceWorkflowService $workflow,
        private readonly WorkspaceSearchEditorBridge $editor,
        private readonly AuthUserService $users,
        private readonly WorkspaceConfig $workspaceConfig,
        private readonly WorkspaceSearchConfig $config,
    ) {
    }

    /**
     * HR: Osvježava indeks najviše jednom u podešenom intervalu po PHP procesu.
     * EN: Refreshes the index at most once per configured interval per PHP process.
     */
    public function refreshIfDue(): void
    {
        $interval = $this->config->refreshSeconds();
        if ($interval <= 0) {
            return;
        }

        if (time() - $this->lastRefreshTimestamp < $interval) {
            return;
        }

        /*
HR: Zadnja oznaka iz baze sprječava da svaki PHP-FPM proces zasebno
    obnavlja isti indeks. Statička oznaka iznad pokriva ponavljanja
    unutar jednog zahtjeva/procesa.
EN: The database timestamp prevents every PHP-FPM process from
    rebuilding the same index. The static timestamp above covers
    repetitions inside one request/process.
         */
        $newest = $this->database->table(ModuleWorkspaceSearch::TABLE_INDEX)
        ->orderBy('indexed_at', 'DESC')
        ->limit(1)
        ->first();
        $indexedAt = is_array($newest)
        ? strtotime(WorkspaceValue::string($newest['indexed_at'] ?? ''))
        : false;
        if (is_int($indexedAt) && $indexedAt > 0 && time() - $indexedAt < $interval) {
            $this->lastRefreshTimestamp = $indexedAt;

            return;
        }

        $this->rebuild(false);
        $this->lastRefreshTimestamp = time();
    }

    /**
     * HR: Ponovno gradi indeks; `full=true` prvo uklanja sve izvedene retke.
     * EN: Rebuilds the index; `full=true` first removes all derived rows.
     * @return array{indexed:int,removed:int}
     */
    public function rebuild(
        bool $full = true,
        ?int $workspaceId = null,
        ?string $language = null,
    ): array {
        if (!$this->database->schema()->hasTable(ModuleWorkspaceSearch::TABLE_INDEX)) {
            return ['indexed' => 0, 'removed' => 0];
        }

        $language = is_string($language) && trim($language) !== ''
        ? strtolower(trim($language))
        : null;
        $existingQuery = $this->database->table(ModuleWorkspaceSearch::TABLE_INDEX);
        if ($workspaceId !== null) {
            $existingQuery->where('workspace_id', '=', $workspaceId);
        }

        if ($language !== null) {
            $existingQuery->where('language_code', '=', $language);
        }

        $existingRows = WorkspaceValue::rows($existingQuery->get());
        $removed = 0;
        if ($full) {
            $deleteQuery = $this->database->table(ModuleWorkspaceSearch::TABLE_INDEX);
            if ($workspaceId !== null) {
                $deleteQuery->where('workspace_id', '=', $workspaceId);
            }

            if ($language !== null) {
                $deleteQuery->where('language_code', '=', $language);
            }

            $removed = $deleteQuery->delete();
        }

        $liveKeys = [];
        $indexed = 0;
        $authorNames = [];
        $primaryLanguage = $this->workspaceConfig->siteDefaultLanguage();
        $existingByKey = [];
        foreach ($full ? [] : $existingRows as $existing) {
            $existingKey = WorkspaceValue::int($existing['node_id'] ?? 0) . ':'
            . strtolower(WorkspaceValue::string($existing['language_code'] ?? ''));
            $existingByKey[$existingKey] = $existing;
        }

        foreach ($this->workspaces->activeWorkspaces() as $workspace) {
            $currentWorkspaceId = WorkspaceValue::int($workspace['id'] ?? 0);
            if ($workspaceId !== null && $currentWorkspaceId !== $workspaceId) {
                continue;
            }

            $workspaceSlug = WorkspaceValue::string($workspace['slug'] ?? '');
            $nodes = array_values(array_filter(
                $this->workspaces->nodesForWorkspace($currentWorkspaceId),
                static fn(array $node): bool => WorkspaceValue::string($node['node_type'] ?? '') === 'document'
                && WorkspaceValue::string($node['document_key'] ?? '') !== '',
            ));
            $workflows = $this->workspaces->nodeWorkflowsForNodesAllLanguages(array_map(
                static fn(array $node): int => WorkspaceValue::int($node['id'] ?? 0),
                $nodes,
            ));

            $nodesById = [];
            $nodesByDocument = [];
            $versionsByLanguage = [];
            $workflowsByLanguage = [];
            foreach ($nodes as $node) {
                $nodesById[WorkspaceValue::int($node['id'] ?? 0)] = $node;
                $documentKey = WorkspaceValue::string($node['document_key'] ?? '');
                if ($documentKey !== '') {
                    $nodesByDocument[$documentKey] = $node;
                }
            }

            foreach ($workflows as $nodeId => $nodeWorkflows) {
                $node = $nodesById[$nodeId] ?? null;
                if (!is_array($node)) {
                    continue;
                }

                foreach ($nodeWorkflows as $row) {
                    if (!$this->workflow->isReadableWorkflow($row)) {
                        continue;
                    }

                    $rowLanguage = strtolower(WorkspaceValue::string($row['language_code'] ?? ''));
                    $version = WorkspaceValue::int($row['published_version_number'] ?? 0);
                    $documentKey = WorkspaceValue::string($node['document_key'] ?? '');
                    if (
                        $rowLanguage === ''
                        || ($language !== null && $rowLanguage !== $language)
                        || $version <= 0
                        || $documentKey === ''
                    ) {
                        continue;
                    }

                    $versionsByLanguage[$rowLanguage][$documentKey] = $version;
                    $workflowsByLanguage[$rowLanguage][$documentKey] = $row;
                }
            }

            foreach ($versionsByLanguage as $language => $versions) {
                $localizedWorkspace = $this->workspaces->localizeWorkspace(
                    $workspace,
                    $language,
                    $primaryLanguage,
                );
                foreach ($this->editor->publishedVersions($versions, $language) as $documentKey => $version) {
                    $node = $nodesByDocument[(string)$documentKey] ?? null;
                    $row = $workflowsByLanguage[$language][$documentKey] ?? null;
                    if (!is_array($node) || !is_array($row)) {
                        continue;
                    }

                    $localizedNode = $this->workspaces->localizeNode($node, $language, $primaryLanguage);

                    $nodeId = WorkspaceValue::int($node['id'] ?? 0);
                    $authorId = WorkspaceValue::int($row['published_by_user_id'] ?? 0);
                    if ($authorId > 0 && !array_key_exists($authorId, $authorNames)) {
                        $author = $this->users->findByIdIncludingInactive($authorId);
                        $authorNames[$authorId] = is_array($author)
                        ? WorkspaceValue::string($author['display_name'] ?? $author['login_identifier'] ?? '')
                        : '';
                    }

                    $body = $this->plainText($version->html);
                    $title = trim($version->title) !== ''
                    ? trim($version->title)
                    : WorkspaceValue::string($localizedNode['title'] ?? '');
                    $hash = hash('sha256', implode("\n", [
                    $title,
                    $body,
                    (string)$version->versionNumber,
                    WorkspaceValue::string($row['published_at'] ?? ''),
                    ]));
                    $liveKey = $nodeId . ':' . $language;
                    $liveKeys[] = $liveKey;
                    if (WorkspaceValue::string($existingByKey[$liveKey]['content_hash'] ?? '') === $hash) {
                        ++$indexed;
                        continue;
                    }

                    $now = date('Y-m-d H:i:s');
                    $this->database->table(ModuleWorkspaceSearch::TABLE_INDEX)->upsert([
                    'workspace_id' => $currentWorkspaceId,
                    'node_id' => $nodeId,
                    'workspace_slug' => $workspaceSlug,
                    'workspace_name' => WorkspaceValue::string($localizedWorkspace['name'] ?? ''),
                    'node_slug' => WorkspaceValue::string($node['slug'] ?? ''),
                    'document_key' => (string)$documentKey,
                    'language_code' => $language,
                    'title' => $title,
                    'body_text' => $body,
                    'normalized_text' => $this->normalize($title . ' ' . $body),
                    'author_user_id' => $authorId > 0 ? $authorId : null,
                    'author_name' => $authorId > 0 ? $authorNames[$authorId] : null,
                    'published_at' => WorkspaceValue::string($row['published_at'] ?? '') ?: null,
                    'version_number' => $version->versionNumber,
                    'content_hash' => $hash,
                    'indexed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                    ], ['node_id', 'language_code'], [
                    'workspace_id', 'workspace_slug', 'workspace_name', 'node_slug', 'document_key', 'title',
                    'body_text', 'normalized_text', 'author_user_id', 'author_name', 'published_at',
                    'version_number', 'content_hash', 'indexed_at', 'updated_at',
                    ]);
                    ++$indexed;
                }
            }
        }

        if (!$full) {
            foreach ($existingByKey as $row) {
                $key = WorkspaceValue::int($row['node_id'] ?? 0) . ':'
                . strtolower(WorkspaceValue::string($row['language_code'] ?? ''));
                if (!in_array($key, $liveKeys, true)) {
                    $removed += $this->database->table(ModuleWorkspaceSearch::TABLE_INDEX)
                    ->where('id', '=', WorkspaceValue::int($row['id'] ?? 0))
                    ->delete();
                }
            }
        }

        return ['indexed' => $indexed, 'removed' => $removed];
    }

    /**
     * HR: Sinkronizira samo jedno područje, odnosno po potrebi samo jednu
     *     jezičnu inačicu, bez skeniranja ostalih područja.
     * EN: Synchronizes one Workspace, optionally just one language variant,
     *     without scanning unrelated Workspaces.
     * @return array{indexed:int,removed:int}
     */
    public function synchronizeWorkspace(int $workspaceId, ?string $language = null): array
    {
        if ($workspaceId <= 0) {
            return ['indexed' => 0, 'removed' => 0];
        }

        return $this->rebuild(false, $workspaceId, $language);
    }

    /**
     * HR: Sinkronizira točno jedan objavljeni jezični red stranice. Ova je
     *     putanja namijenjena događaju objave kako obična objava ne bi
     *     skenirala ostale stranice područja.
     * EN: Synchronizes exactly one published language row of a page. This path
     *     is intended for publication events so an ordinary publish does not
     *     scan unrelated Workspace pages.
     * @return array{indexed:int,removed:int}
     */
    public function synchronizeNode(int $workspaceId, int $nodeId, string $language): array
    {
        if (
            $workspaceId <= 0
            || $nodeId <= 0
            || !$this->database->schema()->hasTable(ModuleWorkspaceSearch::TABLE_INDEX)
        ) {
            return ['indexed' => 0, 'removed' => 0];
        }

        $language = strtolower(trim($language));
        if ($language === '') {
            return ['indexed' => 0, 'removed' => 0];
        }

        $context = $this->workspaces->publishedNodeContext($nodeId, $language);
        if (
            !is_array($context)
            || WorkspaceValue::int($context['workspace_id'] ?? 0) !== $workspaceId
            || WorkspaceValue::string($context['node_type'] ?? '') !== 'document'
            || !$this->workflow->isReadableWorkflow($context)
        ) {
            return [
            'indexed' => 0,
            'removed' => $this->removeNodeLanguage($nodeId, $language),
            ];
        }

        $documentKey = WorkspaceValue::string($context['document_key'] ?? '');
        $versionNumber = WorkspaceValue::int($context['published_version_number'] ?? 0);
        if ($documentKey === '' || $versionNumber <= 0) {
            return [
            'indexed' => 0,
            'removed' => $this->removeNodeLanguage($nodeId, $language),
            ];
        }

        $version = $this->editor->publishedVersions([$documentKey => $versionNumber], $language)[$documentKey]
        ?? null;
        if (!$version instanceof \AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorDocumentVersion) {
            return [
            'indexed' => 0,
            'removed' => $this->removeNodeLanguage($nodeId, $language),
            ];
        }

        $authorId = WorkspaceValue::int($context['published_by_user_id'] ?? 0);
        $authorName = $authorId > 0
        ? WorkspaceValue::string($context['author_login_identifier'] ?? '')
        : null;

        $body = $this->plainText($version->html);
        $primaryLanguage = $this->workspaceConfig->siteDefaultLanguage();
        $localizedNodeTitle = $this->workspaces->localizedValue(
            $context['node_title_translations'] ?? [],
            $language,
            $primaryLanguage,
        );
        if ($localizedNodeTitle === '') {
            $localizedNodeTitle = WorkspaceValue::string($context['node_title'] ?? '');
        }

        $localizedWorkspaceName = $this->workspaces->localizedValue(
            $context['workspace_name_translations'] ?? [],
            $language,
            $primaryLanguage,
        );
        if ($localizedWorkspaceName === '') {
            $localizedWorkspaceName = WorkspaceValue::string($context['workspace_name'] ?? '');
        }

        $title = trim($version->title) !== ''
        ? trim($version->title)
        : $localizedNodeTitle;
        $publishedAt = WorkspaceValue::string($context['published_at'] ?? '');
        $hash = hash('sha256', implode("\n", [$title, $body, (string)$versionNumber, $publishedAt]));
        $now = date('Y-m-d H:i:s');
        $this->database->table(ModuleWorkspaceSearch::TABLE_INDEX)->upsert([
        'workspace_id' => $workspaceId,
        'node_id' => $nodeId,
        'workspace_slug' => WorkspaceValue::string($context['workspace_slug'] ?? ''),
        'workspace_name' => $localizedWorkspaceName,
        'node_slug' => WorkspaceValue::string($context['node_slug'] ?? ''),
        'document_key' => $documentKey,
        'language_code' => $language,
        'title' => $title,
        'body_text' => $body,
        'normalized_text' => $this->normalize($title . ' ' . $body),
        'author_user_id' => $authorId > 0 ? $authorId : null,
        'author_name' => $authorName,
        'published_at' => $publishedAt !== '' ? $publishedAt : null,
        'version_number' => $versionNumber,
        'content_hash' => $hash,
        'indexed_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
        ], ['node_id', 'language_code'], [
        'workspace_id', 'workspace_slug', 'workspace_name', 'node_slug', 'document_key', 'title',
        'body_text', 'normalized_text', 'author_user_id', 'author_name', 'published_at',
        'version_number', 'content_hash', 'indexed_at', 'updated_at',
        ]);

        return ['indexed' => 1, 'removed' => 0];
    }

    /**
     * HR: Potpuno briše i ponovno gradi izvedene retke jednog područja.
     * EN: Fully removes and rebuilds the derived rows of one Workspace.
     * @return array{indexed:int,removed:int}
     */
    public function rebuildWorkspace(int $workspaceId): array
    {
        if ($workspaceId <= 0) {
            return ['indexed' => 0, 'removed' => 0];
        }

        return $this->rebuild(true, $workspaceId);
    }

    /**
     * HR: Uklanja jedan zastarjeli red stranice i jezika.
     * EN: Removes one stale page-and-language row.
     */
    private function removeNodeLanguage(int $nodeId, string $language): int
    {
        return $this->database->table(ModuleWorkspaceSearch::TABLE_INDEX)
        ->where('node_id', '=', $nodeId)
        ->where('language_code', '=', $language)
        ->delete();
    }

    /**
     * HR: Pretvara objavljeni HTML u sažeti čisti tekst bez izvođenja oznaka ili skripti.
     * EN: Converts published HTML to compact plain text without executing tags or scripts.
     */
    private function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * HR: Normalizira tekst za prijenosno pretraživanje bez HTML razmaka.
     * EN: Normalizes text for portable searching without HTML whitespace.
     */
    private function normalize(string $text): string
    {
        return mb_strtolower(trim((string)preg_replace('/\s+/u', ' ', $text)), 'UTF-8');
    }
}
