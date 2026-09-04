<?php

declare(strict_types=1);

use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceValue;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchService;

/**
 * HR: Rezultati pretrage već su ACL-filtrirani u servisu; view samo prikazuje siguran model.
 * EN: Search results are already ACL-filtered in the service; the view only renders the safe model.
 *
 * @var \HeartPhrame\View\View $this
 * @var array<string, mixed> $result
 * @var int $minimumQueryLength
 * @var string $assetsCssPath
 * @var string $searchPath
 * @var array<string,scalar|list<scalar>> $paginationQuery
 */

$items = WorkspaceValue::rows($result['items'] ?? null);
$filters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$workspaces = WorkspaceValue::rows($result['workspaces'] ?? null);
$query = is_scalar($result['query'] ?? null) ? (string)$result['query'] : '';
$language = WorkspaceValue::string($result['language'] ?? '');
$total = is_numeric($result['total'] ?? null) ? (int)$result['total'] : 0;
$page = is_numeric($result['page'] ?? null) ? (int)$result['page'] : 1;
$pages = is_numeric($result['pages'] ?? null) ? (int)$result['pages'] : 0;
$pageNumbers = $pages > 0
    ? array_values(array_unique([
        1,
        ...range(max(1, $page - 2), min($pages, $page + 2)),
        $pages,
    ]))
    : [];
sort($pageNumbers);
$allWorkspaceFilter = WorkspaceSearchService::ALL_WORKSPACES_FILTER;
$personalWorkspaceFilter = WorkspaceSearchService::PERSONAL_WORKSPACES_FILTER;
$hasPersonalWorkspaces = array_filter(
    $workspaces,
    static fn(array $workspace): bool => (bool)($workspace['is_personal_workspace'] ?? false),
) !== [];
$selectedWorkspaceScopes = array_values(array_filter(array_map(
    static fn(mixed $scope): string => is_scalar($scope) ? trim((string)$scope) : '',
    is_array($result['workspace_scopes'] ?? null) ? $result['workspace_scopes'] : [],
)));
$embeddedWorkspaceSearch = WorkspaceValue::string($filters['embedded'] ?? '') === '1';
$workspaceNames = [];
foreach ($workspaces as $workspace) {
    $slug = WorkspaceValue::string($workspace['slug'] ?? '');
    if ($slug !== '') {
        $workspaceNames[$slug] = WorkspaceValue::string($workspace['name'] ?? '') ?: $slug;
    }
}
$selectedWorkspaceLabels = [];
foreach ($selectedWorkspaceScopes as $scope) {
    if ($scope === $allWorkspaceFilter) {
        $selectedWorkspaceLabels[] = __('All visible workspaces');
    } elseif ($scope === $personalWorkspaceFilter) {
        $selectedWorkspaceLabels[] = __('Personal Workspaces');
    } elseif (isset($workspaceNames[$scope])) {
        $selectedWorkspaceLabels[] = $workspaceNames[$scope];
    }
}
$allWorkspacesSelected = in_array($allWorkspaceFilter, $selectedWorkspaceScopes, true);
$workspaceSelectionLabel = $allWorkspacesSelected
    ? __('All visible workspaces')
    : (count($selectedWorkspaceLabels) === 1
        ? $selectedWorkspaceLabels[0]
        : sprintf(__('Selected Workspaces: %d'), count($selectedWorkspaceLabels)));
$embeddedWorkspaceLabel = $selectedWorkspaceLabels !== []
    ? implode(', ', $selectedWorkspaceLabels)
    : __('No selected Workspace is available.');
?>

<link rel="stylesheet" href="<?= $this->escape($assetsCssPath) ?>">

<section class="container-xl hph-workspace-search py-4">
    <form
        class="card shadow-sm hph-workspace-search__form"
        method="get"
        action="<?= $this->escape($searchPath) ?>"
        role="search"
    >
        <input type="hidden" name="lang" value="<?= $this->escape($language) ?>">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-lg-6">
                    <label class="form-label" for="workspace-search-q"><?= $this->escape(__('Search term')) ?></label>
                    <input
                        class="form-control form-control-lg"
                        id="workspace-search-q"
                        name="q"
                        type="search"
                        value="<?= $this->escape($query) ?>"
                        minlength="<?= $this->escape((string)$minimumQueryLength) ?>"
                        required
                        autofocus
                    >
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label" for="workspace-search-workspace-button">
                        <?= $this->escape(__('Workspace')) ?>
                    </label>
                    <?php if ($embeddedWorkspaceSearch) : ?>
                        <div
                            class="form-control"
                            id="workspace-search-workspace-button"
                            aria-readonly="true"
                        ><?= $this->escape($embeddedWorkspaceLabel) ?></div>
                        <?php foreach ($selectedWorkspaceScopes as $scope) : ?>
                            <input type="hidden" name="workspaces[]" value="<?= $this->escape($scope) ?>">
                        <?php endforeach; ?>
                        <input type="hidden" name="embedded" value="1">
                        <div class="form-text">
                            <?= $this->escape(__('Search is limited to the selected Workspaces.')) ?>
                        </div>
                    <?php else : ?>
                        <div class="dropdown" data-workspace-search-scope-picker>
                            <button
                                class="form-select text-start"
                                id="workspace-search-workspace-button"
                                type="button"
                                data-bs-toggle="dropdown"
                                data-bs-auto-close="outside"
                                aria-expanded="false"
                                data-workspace-search-scope-label
                            ><?= $this->escape($workspaceSelectionLabel) ?></button>
                            <div
                                class="dropdown-menu w-100 p-2 shadow-sm hph-workspace-search__scope-menu"
                                aria-labelledby="workspace-search-workspace-button"
                            >
                                <div class="form-check mb-1">
                                    <input
                                        class="form-check-input"
                                        id="workspace-search-scope-all"
                                        name="workspaces[]"
                                        type="checkbox"
                                        value="<?= $this->escape($allWorkspaceFilter) ?>"
                                        data-workspace-search-scope-all
                                        <?= $allWorkspacesSelected ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="workspace-search-scope-all">
                                        <?= $this->escape(__('All visible workspaces')) ?>
                                    </label>
                                </div>
                                <?php $workspaceIndex = 0; ?>
                                <?php foreach ($workspaces as $workspace) : ?>
                                    <?php if ((bool)($workspace['is_personal_workspace'] ?? false)) {
                                        continue;
                                    } ?>
                                    <?php
                                    $slug = WorkspaceValue::string($workspace['slug'] ?? '');
                                    $name = WorkspaceValue::string($workspace['name'] ?? '') ?: $slug;
                                    $workspaceIndex++;
                                    ?>
                                    <div class="form-check mb-1">
                                        <input
                                            class="form-check-input"
                                            id="workspace-search-scope-<?= $this->escape((string)$workspaceIndex) ?>"
                                            name="workspaces[]"
                                            type="checkbox"
                                            value="<?= $this->escape($slug) ?>"
                                            data-workspace-search-scope
                                            data-workspace-search-scope-title="<?= $this->escape($name) ?>"
                                            <?= in_array($slug, $selectedWorkspaceScopes, true) ? 'checked' : '' ?>
                                        >
                                        <label
                                            class="form-check-label"
                                            for="workspace-search-scope-<?= $this->escape((string)$workspaceIndex) ?>"
                                        ><?= $this->escape($name) ?></label>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($hasPersonalWorkspaces) : ?>
                                    <div class="form-check mb-1">
                                        <input
                                            class="form-check-input"
                                            id="workspace-search-scope-personal"
                                            name="workspaces[]"
                                            type="checkbox"
                                            value="<?= $this->escape($personalWorkspaceFilter) ?>"
                                            data-workspace-search-scope
                                            data-workspace-search-scope-title="<?=
                                                $this->escape(__('Personal Workspaces'))
                                            ?>"
                                            <?= in_array($personalWorkspaceFilter, $selectedWorkspaceScopes, true)
                                                ? 'checked' : '' ?>
                                        >
                                        <label class="form-check-label" for="workspace-search-scope-personal">
                                            <?= $this->escape(__('Personal Workspaces')) ?>
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-12 col-md-6 col-lg-3 d-grid">
                    <button class="btn btn-primary btn-lg" type="submit"><?= $this->escape(__('Search')) ?></button>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="workspace-search-author"><?= $this->escape(__('Author')) ?></label>
                    <input
                        class="form-control"
                        id="workspace-search-author"
                        name="author"
                        value="<?= $this->escape(WorkspaceValue::string($filters['author'] ?? '')) ?>"
                    >
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="workspace-search-from">
                        <?= $this->escape(__('Published from')) ?>
                    </label>
                    <input
                        class="form-control"
                        id="workspace-search-from"
                        name="from"
                        type="date"
                        value="<?= $this->escape(WorkspaceValue::string($filters['from'] ?? '')) ?>"
                    >
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="workspace-search-to">
                        <?= $this->escape(__('Published to')) ?>
                    </label>
                    <input
                        class="form-control"
                        id="workspace-search-to"
                        name="to"
                        type="date"
                        value="<?= $this->escape(WorkspaceValue::string($filters['to'] ?? '')) ?>"
                    >
                </div>
                <div class="col-12">
                    <p class="form-text mb-0">
                        <?= $this->escape(__(
                            'If you simply enter one or more words, '
                            . 'the entire input is searched as one phrase. '
                            . 'If the result must contain several separate words or phrases, put + before each one. '
                            . 'Example: +part +second +"Part 2" finds content containing the word “part”, '
                            . 'the word “second”, and the phrase “Part 2”.',
                        )) ?>
                    </p>
                </div>
            </div>
        </div>
    </form>

    <?php if (mb_strlen($query) >= $minimumQueryLength) : ?>
        <p class="text-muted mt-4 mb-3">
            <?= $this->escape(sprintf(__('Results found: %d'), $total)) ?>
        </p>
        <?php if ($items === []) : ?>
            <div class="alert alert-info" role="status">
                <?= $this->escape(__('No results match the selected filters.')) ?>
            </div>
        <?php endif; ?>

        <div class="vstack gap-3">
            <?php foreach ($items as $item) : ?>
                <article class="card shadow-sm hph-workspace-search__result">
                    <div class="card-body">
                        <h2 class="h4 mb-1">
                            <a href="<?= $this->escape(WorkspaceValue::string($item['url'] ?? '#')) ?>">
                                <?= $this->escape(WorkspaceValue::string($item['title'] ?? '')) ?>
                            </a>
                        </h2>
                        <p class="small text-muted mb-2">
                            <?php if (WorkspaceValue::string($item['result_type'] ?? 'page') === 'workspace') : ?>
                                <span class="badge text-bg-secondary"><?= $this->escape(__('Workspace')) ?></span>
                            <?php else : ?>
                                <?= $this->escape(WorkspaceValue::string($item['workspace_name'] ?? '')) ?>
                                <?php if (WorkspaceValue::string($item['author_name'] ?? '') !== '') : ?>
                                    · <?= $this->escape(WorkspaceValue::string($item['author_name'])) ?>
                                <?php endif; ?>
                                <?php if (WorkspaceValue::string($item['published_at'] ?? '') !== '') : ?>
                                    · <?= $this->escape(WorkspaceValue::string($item['published_at'])) ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </p>
                        <?php if (WorkspaceValue::string($item['snippet_html'] ?? '') !== '') : ?>
                            <p class="mb-0"><?= WorkspaceValue::string($item['snippet_html'] ?? '') ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1) : ?>
            <nav class="mt-4" aria-label="<?= $this->escape(__('Result pages')) ?>">
                <ul class="pagination flex-wrap">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <?php $params = [...$paginationQuery, 'page' => max(1, $page - 1)]; ?>
                        <a
                            class="page-link"
                            href="<?= $this->escape($searchPath . '?' . http_build_query($params)) ?>"
                            aria-label="<?= $this->escape(__('Previous page')) ?>"
                        >&lsaquo;</a>
                    </li>
                    <?php $previousNumber = 0; ?>
                    <?php foreach ($pageNumbers as $number) : ?>
                        <?php if ($previousNumber > 0 && $number > $previousNumber + 1) : ?>
                            <li class="page-item disabled" aria-hidden="true">
                                <span class="page-link">…</span>
                            </li>
                        <?php endif; ?>
                        <?php $params = [...$paginationQuery, 'page' => $number]; ?>
                        <li class="page-item <?= $number === $page ? 'active' : '' ?>">
                            <a
                                class="page-link"
                                href="<?= $this->escape($searchPath . '?' . http_build_query($params)) ?>"
                                <?= $number === $page ? 'aria-current="page"' : '' ?>
                            ><?= $this->escape((string)$number) ?></a>
                        </li>
                        <?php $previousNumber = $number; ?>
                    <?php endforeach; ?>
                    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                        <?php $params = [...$paginationQuery, 'page' => min($pages, $page + 1)]; ?>
                        <a
                            class="page-link"
                            href="<?= $this->escape($searchPath . '?' . http_build_query($params)) ?>"
                            aria-label="<?= $this->escape(__('Next page')) ?>"
                        >&rsaquo;</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php else : ?>
        <p class="text-muted mt-4">
            <?= $this->escape(sprintf(__('Enter at least %d characters.'), $minimumQueryLength)) ?>
        </p>
    <?php endif; ?>
</section>

<?php if (!$embeddedWorkspaceSearch) : ?>
    <script>
        document.querySelectorAll('[data-workspace-search-scope-picker]').forEach(function (picker) {
            var all = picker.querySelector('[data-workspace-search-scope-all]');
            var scopes = Array.from(picker.querySelectorAll('[data-workspace-search-scope]'));
            var label = picker.querySelector('[data-workspace-search-scope-label]');
            var allLabel = <?= json_encode(__('All visible workspaces'), JSON_UNESCAPED_UNICODE) ?>;
            var selectedLabel = <?= json_encode(__('Selected Workspaces: %d'), JSON_UNESCAPED_UNICODE) ?>;

            function synchronize(changed) {
                if (changed === all && all?.checked) {
                    scopes.forEach(function (scope) { scope.checked = false; });
                } else if (changed && changed !== all && changed.checked && all) {
                    all.checked = false;
                }

                var selected = scopes.filter(function (scope) { return scope.checked; });
                if ((!all || !all.checked) && selected.length === 0 && all) {
                    all.checked = true;
                }
                if (!label) {
                    return;
                }
                if (all?.checked) {
                    label.textContent = allLabel;
                } else if (selected.length === 1) {
                    label.textContent = String(selected[0].dataset.workspaceSearchScopeTitle || selected[0].value);
                } else {
                    label.textContent = selectedLabel.replace('%d', String(selected.length));
                }
            }

            all?.addEventListener('change', function () { synchronize(all); });
            scopes.forEach(function (scope) {
                scope.addEventListener('change', function () { synchronize(scope); });
            });
            synchronize(null);
        });
    </script>
<?php endif; ?>
