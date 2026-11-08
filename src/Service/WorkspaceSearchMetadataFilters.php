<?php

declare(strict_types=1);

namespace AaiEduHr\SimbiozaModuleWorkspaceSearch\Service;

use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\QueryBuilder;
use AaiEduHr\SimbiozaModuleWorkspace\ModuleWorkspace;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\ModuleWorkspaceSearch;
use RuntimeException;

/**
 * EN: Reads canonical page metadata through bounded SQL; callers supply freshly authorized node IDs.
 *
 * HR: Čita izvorne metapodatke stranica ograničenim SQL-om; pozivatelj predaje aktualne dopuštene ID-eve.
 */
final readonly class WorkspaceSearchMetadataFilters
{
    /**
     * EN: Shares the search connection without copying metadata into a second index.
     *
     * HR: Koristi konekciju pretrage bez kopiranja metapodataka u dodatni indeks.
     */
    public function __construct(private Database $database)
    {
    }

    /**
     * EN: Preserves exact property values and fails closed for malformed filter parameters.
     *
     * HR: Čuva točne vrijednosti svojstava i ne proširuje rezultate za nevaljane parametre filtara.
     *
     * @param array<string,mixed> $filters
     * @return array{label:string,property_key:string,property_value:string,workflow:string}
     */
    public function normalize(array $filters): array
    {
        $values = [];
        $invalid = false;
        foreach (['label', 'property_key', 'property_value', 'workflow'] as $key) {
            $value = $filters[$key] ?? '';
            $invalid = $invalid || !is_scalar($value);
            $values[$key] = is_scalar($value) ? trim((string)$value) : '__invalid_filter__';
        }

        return [
            'label' => $values['label'],
            'property_key' => $values['property_key'],
            'property_value' => $values['property_value'],
            'workflow' => $invalid ? '__invalid_filter__' : $values['workflow'],
        ];
    }

    /**
     * EN: Adds correlated predicates without multiplying search rows or changing result counts.
     *     Only page editors, publishers and managers may filter actual draft/review state.
     *
     * HR: Dodaje povezane uvjete bez umnažanja redaka pretrage ili promjene načina brojanja.
     *     Samo urednici, objavljivači i upravitelji smiju filtrirati stvarno stanje nacrta/pregleda.
     *
     * @param array{label:string,property_key:string,property_value:string,workflow:string} $filters
     * @param list<int> $workflowNodeIds
     */
    public function apply(QueryBuilder $builder, array $filters, array $workflowNodeIds): void
    {
        $index = ModuleWorkspaceSearch::TABLE_INDEX;
        if (array_filter($filters, static fn(string $value): bool => $value !== '') !== []) {
            $builder->whereExists($this->readableWorkflow());
        }

        if ($filters['label'] !== '') {
            $table = ModuleWorkspace::TABLE_WORKSPACE_NODE_LABELS;
            $builder->whereExists($this->database->table($table)->select('node_id')
                ->whereColumn($table . '.node_id', '=', $index . '.node_id')
                ->where('label', '=', $filters['label']));
        }

        if ($filters['property_key'] !== '') {
            $table = ModuleWorkspace::TABLE_WORKSPACE_NODE_PROPERTIES;
            $property = $this->database->table($table)->select('node_id')
                ->whereColumn($table . '.node_id', '=', $index . '.node_id')
                ->where('property_key', '=', $filters['property_key']);
            if ($filters['property_value'] !== '') {
                if ($this->database->getDriverName() === 'mysql') {
                    $property->whereRaw('BINARY property_value = BINARY ?', [$filters['property_value']]);
                } else {
                    $property->where('property_value', '=', $filters['property_value']);
                }
            }

            $builder->whereExists($property);
        } elseif ($filters['property_value'] !== '') {
            $builder->whereRaw('0 = 1');
        }

        if ($filters['workflow'] === '') {
            return;
        }

        if ($filters['workflow'] === 'published') {
            if ($workflowNodeIds !== []) {
                $builder->whereNested(function (QueryBuilder $nested) use ($workflowNodeIds): void {
                    $nested->whereNotIn('node_id', $workflowNodeIds)
                        ->orWhereExists($this->readableWorkflow()->where('status', '=', 'published'));
                });
            }

            return;
        }

        if (!in_array($filters['workflow'], ['draft', 'in_review'], true) || $workflowNodeIds === []) {
            $builder->whereRaw('0 = 1');

            return;
        }

        $builder->whereIn('node_id', $workflowNodeIds)
            ->whereExists($this->readableWorkflow()->where('status', '=', $filters['workflow']));
    }

    /**
     * EN: Fetches at most 26 distinct authorized options in SQL, returning 25 plus a has-more flag.
     *     Options require a readable publication in the same locale priority as the search.
     *
     * HR: SQL-om dohvaća najviše 26 različitih dopuštenih opcija, vraća 25 i oznaku nastavka.
     *     Opcije zahtijevaju čitljivu objavu u istom jezičnom prioritetu kao pretraga.
     *
     * @param list<int> $nodeIds
     * @param list<string> $languages
     * @return array{items:list<array{id:string,label:string}>,page:int,hasMore:bool}
     */
    public function lookupPage(
        string $kind,
        array $nodeIds,
        array $languages,
        string $query,
        int $page = 1,
        string $propertyKey = '',
    ): array {
        $page = max(1, min(100000, $page));
        $empty = ['items' => [], 'page' => $page, 'hasMore' => false];
        if ($nodeIds === [] || $languages === [] || !in_array($kind, ['labels', 'properties', 'values'], true)) {
            return $empty;
        }

        if ($kind === 'values' && $propertyKey === '') {
            return $empty;
        }

        $table = $kind === 'labels'
            ? ModuleWorkspace::TABLE_WORKSPACE_NODE_LABELS
            : ModuleWorkspace::TABLE_WORKSPACE_NODE_PROPERTIES;
        $column = match ($kind) {
            'labels' => 'label',
            'properties' => 'property_key',
            default => 'property_value',
        };
        $groupColumn = 'm.' . $column;
        $idColumn = $groupColumn;
        if ($kind === 'values' && $this->database->getDriverName() === 'mysql') {
            // EN: The digest also separates long TEXT values beyond MySQL's max_sort_length prefix.
            //
            // HR: Sažetak razlikuje i duge TEXT vrijednosti izvan prefiksa MySQL max_sort_length.
            $groupColumn = 'SHA2(' . $groupColumn . ', 256), BINARY ' . $groupColumn;
            $idColumn = 'MIN(m.' . $column . ')';
        }

        $label = $kind === 'properties' ? 'MIN(m.property_label)' : $idColumn;
        $nodePlaceholders = implode(',', array_fill(0, count($nodeIds), '?'));
        $languagePlaceholders = implode(',', array_fill(0, count($languages), '?'));
        $workflow = ModuleWorkspace::TABLE_WORKSPACE_NODE_WORKFLOWS;
        $sql = 'SELECT ' . $idColumn . ' AS id, ' . $label . ' AS label FROM ' . $table . ' m '
            . 'WHERE m.node_id IN (' . $nodePlaceholders . ') '
            . 'AND EXISTS (SELECT 1 FROM ' . $workflow . ' f WHERE f.node_id = m.node_id '
            . 'AND f.language_code IN (' . $languagePlaceholders . ') '
            . 'AND f.published_version_number > 0 AND f.status <> ?) '
            . 'AND m.' . $column . " <> '' ";
        $bindings = [...$nodeIds, ...$languages, 'archived'];
        if ($kind === 'values') {
            $sql .= 'AND m.property_key = ? ';
            $bindings[] = $propertyKey;
        }

        $query = mb_substr(trim($query), 0, 128);
        if ($query !== '') {
            $fold = $this->foldFunction();
            $needle = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($query)) . '%';
            $sql .= 'AND (' . $fold . '(m.' . $column . ") LIKE ? ESCAPE '!'";
            $bindings[] = $needle;
            if ($kind === 'properties') {
                $sql .= ' OR ' . $fold . "(m.property_label) LIKE ? ESCAPE '!'";
                $bindings[] = $needle;
            }

            $sql .= ') ';
        }

        $order = $this->database->localizedOrderExpression($label, $languages[0]);
        $tieOrder = $kind === 'values' && $this->database->getDriverName() === 'mysql'
            ? 'BINARY ' . $idColumn . ', SHA2(' . $idColumn . ', 256)' : $idColumn;
        $sql .= 'GROUP BY ' . $groupColumn . ' ORDER BY ' . $order . ' ASC, ' . $tieOrder . ' ASC '
            . 'LIMIT 26 OFFSET ' . (($page - 1) * 25);
        $rows = $this->database->fetchAll($sql, $bindings);
        $items = [];
        foreach (array_slice($rows, 0, 25) as $row) {
            $id = is_scalar($row['id'] ?? null) ? (string)$row['id'] : '';
            if ($id !== '') {
                $items[] = ['id' => $id, 'label' => is_scalar($row['label'] ?? null) ? (string)$row['label'] : $id];
            }
        }

        return ['items' => $items, 'page' => $page, 'hasMore' => count($rows) > 25];
    }

    /**
     * EN: SQLite's built-in LOWER handles ASCII only; this SQL function folds Unicode before paging.
     *
     * HR: Ugrađeni SQLite LOWER radi samo s ASCII-jem; ova SQL funkcija obrađuje Unicode prije straničenja.
     */
    private function foldFunction(): string
    {
        if ($this->database->getDriverName() !== 'sqlite') {
            return 'LOWER';
        }

        $pdo = $this->database->getConnection();
        $callback = static fn(?string $value): string => mb_strtolower($value ?? '', 'UTF-8');
        $registered = method_exists($pdo, 'createFunction')
            ? $pdo->createFunction('workspace_search_casefold', $callback, 1)
            : $pdo->sqliteCreateFunction('workspace_search_casefold', $callback, 1);
        if (!$registered) {
            throw new RuntimeException('Unable to register Unicode metadata search.');
        }

        return 'workspace_search_casefold';
    }

    /**
     * EN: Correlates publication state with the exact indexed language, including locale fallback.
     *
     * HR: Povezuje stanje objave s točnim indeksiranim jezikom, uključujući jezični fallback.
     */
    private function readableWorkflow(): QueryBuilder
    {
        $table = ModuleWorkspace::TABLE_WORKSPACE_NODE_WORKFLOWS;
        $index = ModuleWorkspaceSearch::TABLE_INDEX;

        return $this->database->table($table)->select('node_id')
            ->whereColumn($table . '.node_id', '=', $index . '.node_id')
            ->whereColumn($table . '.language_code', '=', $index . '.language_code')
            ->where('published_version_number', '>', 0)
            ->where('status', '<>', 'archived');
    }
}
