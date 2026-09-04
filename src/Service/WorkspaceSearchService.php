<?php

declare(strict_types=1);

namespace AaiEduHr\SimbiozaModuleWorkspaceSearch\Service;

use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\QueryBuilder;
use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceAccessService;
use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceConfig;
use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspacePresentationRegistry;
use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceRepository;
use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceValue;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\ModuleWorkspaceSearch;
use HeartPhrame\Routing\UrlGenerator;

/**
 * HR: Izvršava pretragu tek nakon stvarnog Workspace i page ACL filtriranja.
 *     Ukupan broj, isječci i prijedlozi nastaju isključivo iz dopuštenog skupa.
 * EN: Executes search only after actual Workspace and page ACL filtering.
 *     Totals, snippets, and suggestions are built exclusively from the allowed set.
 */
final readonly class WorkspaceSearchService
{
    public const ALL_WORKSPACES_FILTER = '__all__';

    public const PERSONAL_WORKSPACES_FILTER = '__personal__';

    /**
 * HR: Prima izvedeni indeks, jedinstveni Workspace ACL servis i konfiguraciju
 *     potrebnu za prijenosnu pretragu i sigurne poveznice rezultata.
 * EN: Receives the derived index, canonical Workspace ACL service, and
 *     configuration required for portable search and safe result links.
 */
    public function __construct(
        private Database $database,
        private WorkspaceAccessService $access,
        private WorkspaceRepository $workspaces,
        private WorkspaceConfig $workspaceConfig,
        private WorkspaceSearchConfig $config,
        private WorkspaceSearchIndexer $indexer,
        private UrlGenerator $urls,
        private ?WorkspacePresentationRegistry $presentations = null,
    ) {
    }

    /**
     * HR: Izvršava filtriranu i straničenu pretragu samo nad ACL-dopuštenim čvorovima.
     * EN: Executes filtered, paginated search only across ACL-authorized nodes.
     * @param array<string, mixed> $filters
     * @param array<string, mixed>|null $user
     * @return array<string, mixed>
     */
    public function search(string $query, string $language, array $filters = [], ?array $user = null): array
    {
        $query = mb_substr(trim($query), 0, 250);
        $language = $this->language($language);
        $defaultLanguage = $this->workspaceConfig->siteDefaultLanguage();
        $page = $this->boundedInt($filters['page'] ?? 1, 1, 1, 100000);
        $perPage = $this->boundedInt(
            $filters['per_page'] ?? $this->config->resultsPerPage(),
            $this->config->resultsPerPage(),
            1,
            $this->config->maximumResultsPerPage(),
        );
        $workspaceSlug = $this->string($filters['workspace'] ?? '');
        $requestedWorkspaceScopes = $this->workspaceScopes($filters, $workspaceSlug);
        $embedded = $this->string($filters['embedded'] ?? '') === '1';
        $author = $this->string($filters['author'] ?? '');
        $from = $this->date($filters['from'] ?? '');
        $to = $this->date($filters['to'] ?? '');

        $base = [
        'query' => $query,
        'language' => $language,
        'default_language' => $defaultLanguage,
        'page' => $page,
        'per_page' => $perPage,
        'total' => 0,
        'pages' => 0,
        'items' => [],
        'workspace_scopes' => [],
        'filters' => [
        'workspace' => $workspaceSlug,
        'embedded' => $embedded ? '1' : '',
        'author' => $author,
        'from' => $from,
        'to' => $to,
        ],
        'workspaces' => [],
        ];
        $hasSearchQuery = mb_strlen($query) >= $this->config->minimumQueryLength();
        [$visibleNodeIds, $visibleWorkspaces] = $this->visibleScope(
            $user,
            $language,
            $defaultLanguage,
            $hasSearchQuery,
        );
        $base['workspaces'] = $visibleWorkspaces;
        $workspaceScope = $this->resolvedWorkspaceScope(
            $requestedWorkspaceScopes,
            $visibleWorkspaces,
            $embedded,
        );
        $base['workspace_scopes'] = $workspaceScope['tokens'];
        if (!$hasSearchQuery) {
            return $base;
        }

        $this->indexer->refreshIfDue();
        $workspaceResults = $author === '' && $from === '' && $to === ''
            ? $this->workspaceResults(
                $visibleWorkspaces,
                $query,
                $workspaceScope['all'] ? null : $workspaceScope['slugs'],
            )
            : [];

        $rows = [];
        if ($visibleNodeIds !== []) {
            $builder = $this->database->table(ModuleWorkspaceSearch::TABLE_INDEX)
                ->whereIn('node_id', $visibleNodeIds)
                ->whereIn('language_code', array_values(array_unique([$language, $defaultLanguage])));
            if (!$workspaceScope['all']) {
                if ($workspaceScope['slugs'] === []) {
                    $builder->where('workspace_slug', '=', '__no_selected_visible_workspace__');
                } else {
                    $builder->whereIn('workspace_slug', $workspaceScope['slugs']);
                }
            }

            if ($author !== '') {
                if (ctype_digit($author)) {
                    $builder->where('author_user_id', '=', (int)$author);
                } else {
                    $builder->whereRaw('LOWER(author_name) LIKE ?', ['%' . mb_strtolower($author) . '%']);
                }
            }

            if ($from !== '') {
                $builder->where('published_at', '>=', $from . ' 00:00:00');
            }

            if ($to !== '') {
                $builder->where('published_at', '<=', $to . ' 23:59:59');
            }

            foreach ($this->terms($query) as $term) {
                $needle = '%' . $term . '%';
                $builder->whereNested(static function (QueryBuilder $nested) use ($needle): void {
                    $nested->where('normalized_text', 'LIKE', $needle)
                        ->orWhereRaw('LOWER(author_name) LIKE ?', [$needle]);
                });
            }

            $preferred = [];
            foreach (WorkspaceValue::rows($builder->get()) as $row) {
                $nodeId = WorkspaceValue::int($row['node_id'] ?? 0);
                $rowLanguage = strtolower(WorkspaceValue::string($row['language_code'] ?? ''));
                if (!isset($preferred[$nodeId]) || $rowLanguage === $language) {
                    $preferred[$nodeId] = $row;
                }
            }

            $rows = array_values($preferred);
            usort($rows, fn(array $left, array $right): int => $this->compareRows($left, $right, $query));
        }

        $pageResults = array_map(
            fn(array $row): array => $this->result($row, $query),
            $rows,
        );
        $results = [...$workspaceResults, ...$pageResults];
        $total = count($results);
        $offset = ($page - 1) * $perPage;
        $items = array_values(array_slice($results, $offset, $perPage));

        return [
        ...$base,
        'total' => $total,
        'pages' => $total > 0 ? (int)ceil($total / $perPage) : 0,
        'items' => $items,
        ];
    }

    /**
     * HR: Vraća ograničen popis prijedloga iz istog ACL-filtriranog skupa.
     * EN: Returns a bounded suggestion list from the same ACL-filtered set.
     * @param array<string, mixed>|null $user
     * @return list<array{title:string,url:string,workspace:string}>
     */
    public function suggest(
        string $query,
        string $language,
        ?array $user = null,
        int $limit = 8,
        string $workspaceSlug = '',
    ): array {
        $result = $this->search($query, $language, [
            'page' => 1,
            'per_page' => min(20, $limit),
            'workspace' => $workspaceSlug,
        ], $user);

        return array_map(
            static fn(array $item): array => [
            'title' => WorkspaceValue::string($item['title'] ?? ''),
            'url' => WorkspaceValue::string($item['url'] ?? ''),
            'workspace' => WorkspaceValue::string($item['workspace_name'] ?? ''),
            ],
            array_slice(WorkspaceValue::rows($result['items'] ?? null), 0, max(1, min(20, $limit))),
        );
    }

    /**
     * HR: Gradi skup dopuštenih čvorova i radnih prostora prije SQL pretrage.
     * EN: Builds the allowed node and Workspace set before the SQL search.
     * @param array<string, mixed>|null $user
     * @return array{list<int>,list<array<string,mixed>>}
     */
    private function visibleScope(
        ?array $user,
        string $language,
        string $defaultLanguage,
        bool $collectNodeIds = true,
    ): array {
        $nodeIds = [];
        $workspaces = [];
        $visibleWorkspaces = $this->access->visibleWorkspaces($user);
        $visibleWorkspaces = $this->presentations instanceof WorkspacePresentationRegistry
            ? $this->presentations->many($visibleWorkspaces, $language)
            : $this->workspaces->localizeWorkspaces($visibleWorkspaces, $language, $defaultLanguage);
        foreach ($visibleWorkspaces as $workspace) {
            if ($collectNodeIds) {
                $tree = $this->access->visibleTreeForLanguages(
                    $workspace,
                    $user,
                    array_values(array_unique([$language, $defaultLanguage])),
                );
                $this->collectNodeIds($tree, $nodeIds);
            }

            $workspaces[] = [
                'id' => WorkspaceValue::int($workspace['id'] ?? 0),
                'slug' => WorkspaceValue::string($workspace['slug'] ?? ''),
                'name' => WorkspaceValue::string($workspace['name'] ?? ''),
                'description' => WorkspaceValue::string($workspace['description'] ?? ''),
                'is_personal_workspace' => (bool)($workspace['is_personal_workspace'] ?? false),
            ];
        }

        return [array_values(array_unique($nodeIds)), $workspaces];
    }

    /**
     * HR: Pretvara podudaranje naziva, opisa ili sluga već ACL-dopuštenog
     *     područja u pravi rezultat, neovisno o tome ima li objavljenu stranicu.
     * EN: Converts a name, description, or slug match from an already ACL-
     *     authorized Workspace into a real result even when it has no page.
     *
     * @param list<array<string,mixed>> $workspaces
     * @param list<string>|null $workspaceSlugs
     * @return list<array<string,mixed>>
     */
    private function workspaceResults(array $workspaces, string $query, ?array $workspaceSlugs): array
    {
        $terms = $this->terms($query);
        $results = [];
        foreach ($workspaces as $workspace) {
            $slug = WorkspaceValue::string($workspace['slug'] ?? '');
            if (is_array($workspaceSlugs) && !in_array($slug, $workspaceSlugs, true)) {
                continue;
            }

            $name = WorkspaceValue::string($workspace['name'] ?? $slug);
            $description = WorkspaceValue::string($workspace['description'] ?? '');
            $haystack = mb_strtolower($name . ' ' . $description . ' ' . $slug, 'UTF-8');
            $matches = true;
            foreach ($terms as $term) {
                if (!str_contains($haystack, $term)) {
                    $matches = false;
                    break;
                }
            }

            if (!$matches) {
                continue;
            }

            $results[] = [
                'result_type' => 'workspace',
                'workspace_id' => WorkspaceValue::int($workspace['id'] ?? 0),
                'workspace_slug' => $slug,
                'workspace_name' => $name,
                'node_id' => 0,
                'node_slug' => '',
                'language' => '',
                'title' => $name,
                'snippet' => $description,
                'snippet_html' => $this->highlight($description, $terms),
                'author_user_id' => 0,
                'author_name' => '',
                'published_at' => '',
                'url' => $this->workspacePath($slug),
            ];
        }

        return $results;
    }

    /**
     * HR: Prihvaća novi višestruki filtar i zadržava kompatibilnost sa starim
     *     jednokratnim parametrom `workspace`.
     * EN: Accepts the new multi-Workspace filter while retaining compatibility
     *     with the legacy single `workspace` parameter.
     *
     * @param array<string,mixed> $filters
     * @return list<string>
     */
    private function workspaceScopes(array $filters, string $legacyWorkspaceSlug): array
    {
        $value = $filters['workspaces'] ?? null;
        $values = is_array($value) ? $value : explode(',', $this->string($value));
        if ($values === [] || (count($values) === 1 && $this->string($values[0] ?? '') === '')) {
            $values = $legacyWorkspaceSlug !== '' ? [$legacyWorkspaceSlug] : [];
        }

        $result = [];
        foreach (array_slice($values, 0, 50) as $candidate) {
            $scope = $this->string($candidate);
            if ($scope !== '' && !in_array($scope, $result, true)) {
                $result[] = $scope;
            }
        }

        return $result;
    }

    /**
     * HR: Sjecište korisničkog odabira i ACL-vidljivih područja. Posebna stavka
     *     osobnih područja razvija se samo u osobna područja koja korisnik vidi.
     * EN: Intersects the requested selection with ACL-visible Workspaces. The
     *     personal aggregate expands only to personal Workspaces the actor sees.
     *
     * @param list<string> $requested
     * @param list<array<string,mixed>> $visibleWorkspaces
     * @return array{tokens:list<string>,slugs:list<string>,all:bool}
     */
    private function resolvedWorkspaceScope(
        array $requested,
        array $visibleWorkspaces,
        bool $embedded,
    ): array {
        $visible = [];
        $personal = [];
        foreach ($visibleWorkspaces as $workspace) {
            $slug = WorkspaceValue::string($workspace['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $visible[$slug] = true;
            if ((bool)($workspace['is_personal_workspace'] ?? false)) {
                $personal[] = $slug;
            }
        }

        if (!$embedded && ($requested === [] || in_array(self::ALL_WORKSPACES_FILTER, $requested, true))) {
            return [
                'tokens' => [self::ALL_WORKSPACES_FILTER],
                'slugs' => array_keys($visible),
                'all' => true,
            ];
        }

        $tokens = [];
        $slugs = [];
        foreach ($requested as $scope) {
            if ($scope === self::ALL_WORKSPACES_FILTER) {
                continue;
            }

            if ($scope === self::PERSONAL_WORKSPACES_FILTER) {
                if ($personal !== []) {
                    $tokens[] = $scope;
                    $slugs = [...$slugs, ...$personal];
                }

                continue;
            }

            if (isset($visible[$scope])) {
                $tokens[] = $scope;
                $slugs[] = $scope;
            }
        }

        return [
            'tokens' => array_values(array_unique($tokens)),
            'slugs' => array_values(array_unique($slugs)),
            'all' => false,
        ];
    }

    /**
     * HR: Rekurzivno skuplja identifikatore isključivo ACL-filtriranih dokumenata.
     * EN: Recursively collects identifiers from ACL-filtered document nodes only.
     * @param list<array<string, mixed>> $tree
     * @param list<int> $nodeIds
     */
    private function collectNodeIds(array $tree, array &$nodeIds): void
    {
        foreach ($tree as $node) {
            if (WorkspaceValue::string($node['node_type'] ?? '') === 'document') {
                $nodeIds[] = WorkspaceValue::int($node['id'] ?? 0);
            }

            $this->collectNodeIds(WorkspaceValue::rows($node['children'] ?? null), $nodeIds);
        }
    }

    /**
     * HR: Bez operatora cijeli upit tretira kao frazu. Navodnici i prefiks `+`
     *     uključuju napredni način u kojem se svi pojmovi i fraze traže AND
     *     semantikom, redom kojim su uneseni.
     * EN: Without operators, treats the whole query as one phrase. Quotes and
     *     the `+` prefix enable advanced mode where every term and phrase is
     *     required with AND semantics, in input order.
     * @return list<string>
     */
    private function terms(string $query): array
    {
        $normalized = trim(mb_strtolower($query, 'UTF-8'));
        if ($normalized === '') {
            return [];
        }

        if (!str_contains($normalized, '+') && !str_contains($normalized, '"')) {
            return [$normalized];
        }

        preg_match_all('/\+?"[^"]*"|\+?[^\s"]+/u', $normalized, $matches);
        $terms = [];
        foreach ($matches[0] as $match) {
            $term = ltrim($match, '+');
            if (str_starts_with($term, '"') && str_ends_with($term, '"')) {
                $term = substr($term, 1, -1);
            }

            $term = trim($term);
            if ($term !== '' && !in_array($term, $terms, true)) {
                $terms[] = $term;
            }
        }

        return $terms !== [] ? $terms : [$normalized];
    }

    /**
     * HR: Rangira točno i djelomično podudaranje naslova prije novijih objava.
     * EN: Ranks exact and partial title matches before newer publications.
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function compareRows(array $left, array $right, string $query): int
    {
        $needle = mb_strtolower($query, 'UTF-8');
        $leftTitle = mb_strtolower(WorkspaceValue::string($left['title'] ?? ''), 'UTF-8');
        $rightTitle = mb_strtolower(WorkspaceValue::string($right['title'] ?? ''), 'UTF-8');
        $leftScore = ($leftTitle === $needle ? 3 : (str_contains($leftTitle, $needle) ? 2 : 0));
        $rightScore = ($rightTitle === $needle ? 3 : (str_contains($rightTitle, $needle) ? 2 : 0));
        if ($leftScore !== $rightScore) {
            return $rightScore <=> $leftScore;
        }

        return strcmp(
            WorkspaceValue::string($right['published_at'] ?? ''),
            WorkspaceValue::string($left['published_at'] ?? ''),
        );
    }

    /**
     * HR: Pretvara interni indeksni redak u stabilni i sigurno istaknuti rezultat.
     * EN: Converts an internal index row into a stable, safely highlighted result.
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function result(array $row, string $query): array
    {
        $body = WorkspaceValue::string($row['body_text'] ?? '');
        $snippet = $this->snippet($body, $query);
        $workspaceSlug = WorkspaceValue::string($row['workspace_slug'] ?? '');
        $nodeSlug = WorkspaceValue::string($row['node_slug'] ?? '');

        return [
        'result_type' => 'page',
        'workspace_id' => WorkspaceValue::int($row['workspace_id'] ?? 0),
        'workspace_slug' => $workspaceSlug,
        'workspace_name' => WorkspaceValue::string($row['workspace_name'] ?? $workspaceSlug),
        'node_id' => WorkspaceValue::int($row['node_id'] ?? 0),
        'node_slug' => $nodeSlug,
        'language' => WorkspaceValue::string($row['language_code'] ?? ''),
        'title' => WorkspaceValue::string($row['title'] ?? ''),
        'snippet' => $snippet,
        'snippet_html' => $this->highlight($snippet, $this->terms($query)),
        'author_user_id' => WorkspaceValue::int($row['author_user_id'] ?? 0),
        'author_name' => WorkspaceValue::string($row['author_name'] ?? ''),
        'published_at' => WorkspaceValue::string($row['published_at'] ?? ''),
        'url' => $this->pagePath($workspaceSlug, $nodeSlug),
        ];
    }

    /** HR: Gradi kanonsku putanju područja. EN: Builds the canonical Workspace path. */
    private function workspacePath(string $workspaceSlug): string
    {
        try {
            return $this->urls->getPathFor('workspace.show', ['workspaceSlug' => $workspaceSlug]);
        } catch (\Throwable) {
            return '/' . $this->workspaceConfig->rootPath() . '/' . rawurlencode($workspaceSlug);
        }
    }

    /**
     * HR: Gradi kanonsku putanju rezultata stranice.
     * EN: Builds the canonical path for a page result.
     */
    private function pagePath(string $workspaceSlug, string $nodeSlug): string
    {
        try {
            return $this->urls->getPathFor('workspace.node.show', [
            'workspaceSlug' => $workspaceSlug,
            'nodeSlug' => $nodeSlug,
            ]);
        } catch (\Throwable) {
            return '/' . $this->workspaceConfig->rootPath() . '/'
            . rawurlencode($workspaceSlug) . '/' . rawurlencode($nodeSlug);
        }
    }

    /**
     * HR: Izrezuje kontekst oko prvog pronađenog pojma bez vraćanja HTML-a stranice.
     * EN: Extracts context around the first match without returning page HTML.
     */
    private function snippet(string $body, string $query): string
    {
        $length = $this->config->snippetLength();
        $position = mb_stripos($body, $query, 0, 'UTF-8');
        if ($position === false) {
            foreach ($this->terms($query) as $term) {
                $position = mb_stripos($body, $term, 0, 'UTF-8');
                if ($position !== false) {
                    break;
                }
            }
        }

        $start = is_int($position) ? max(0, $position - (int)floor($length / 3)) : 0;
        $excerpt = trim(mb_substr($body, $start, $length, 'UTF-8'));

        return ($start > 0 ? '… ' : '') . $excerpt
        . (mb_strlen($body, 'UTF-8') > $start + $length ? ' …' : '');
    }

    /**
     * HR: Escapea cijeli isječak pa tek zatim dodaje sigurne `mark` elemente.
     * EN: Escapes the complete snippet before adding safe `mark` elements.
     * @param list<string> $terms
     */
    private function highlight(string $snippet, array $terms): string
    {
        $escaped = htmlspecialchars($snippet, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $quotedTerms = array_values(array_unique(array_map(
            static fn(string $term): string => preg_quote(
                htmlspecialchars($term, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                '/',
            ),
            array_filter($terms, static fn(string $term): bool => $term !== ''),
        )));
        usort($quotedTerms, static fn(string $left, string $right): int => strlen($right) <=> strlen($left));
        if ($quotedTerms !== []) {
            return (string)preg_replace('/(' . implode('|', $quotedTerms) . ')/iu', '<mark>$1</mark>', $escaped);
        }

        return $escaped;
    }

    /**
     * HR: Odabire podržani jezik ili zadani jezik sitea.
     * EN: Selects a supported language or the site default.
     */
    private function language(string $language): string
    {
        $language = strtolower(trim($language));

        return preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $language) === 1
        ? $language
        : $this->workspaceConfig->siteDefaultLanguage();
    }

    /**
     * HR: Prihvaća samo potpuni ISO datum kako filtar ne bi mijenjao SQL semantiku.
     * EN: Accepts only a complete ISO date so the filter cannot alter SQL semantics.
     */
    private function date(mixed $value): string
    {
        $value = $this->string($value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1 ? $value : '';
    }

    /**
     * HR: Normalizira isključivo skalarne ulazne vrijednosti.
     * EN: Normalizes scalar input values only.
     */
    private function string(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    /**
     * HR: Ograničava stranične brojeve na konfigurirani sigurni raspon.
     * EN: Clamps pagination numbers to the configured safe range.
     */
    private function boundedInt(mixed $value, int $fallback, int $minimum, int $maximum): int
    {
        $value = is_numeric($value) ? (int)$value : $fallback;

        return max($minimum, min($maximum, $value));
    }
}
