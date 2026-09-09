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
 * @var string $authorLookupPath
 * @var string $selectedAuthorLabel
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
$searchExecuted = (bool)($result['search_executed'] ?? false);
$browseMode = (bool)($result['browse_mode'] ?? false);
$queryTooShort = (bool)($result['query_too_short'] ?? false);
$queryRequiredForMultipleWorkspaces = (bool)($result['query_required_for_multiple_workspaces'] ?? false);
$authorValue = WorkspaceValue::string($filters['author'] ?? '');
$authorLabel = trim($selectedAuthorLabel ?? '') ?: ($authorValue !== '' ? $authorValue : __('All authors'));
$sort = WorkspaceValue::string($filters['sort'] ?? 'title') ?: 'title';
$direction = WorkspaceValue::string($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
$sortBaseQuery = $paginationQuery;
unset($sortBaseQuery['sort'], $sortBaseQuery['direction']);
$sortPath = static function (string $column) use ($searchPath, $sortBaseQuery, $sort, $direction): string {
    $nextDirection = $sort === $column && $direction === 'asc' ? 'desc' : 'asc';

    return $searchPath . '?' . http_build_query([
        ...$sortBaseQuery,
        'sort' => $column,
        'direction' => $nextDirection,
    ]);
};
$sortIndicator = static fn(string $column): string => $sort === $column ? ($direction === 'asc' ? ' ▲' : ' ▼') : '';
$displayDate = static function (mixed $value): string {
    $raw = is_scalar($value) ? trim((string)$value) : '';
    $timestamp = $raw !== '' ? strtotime($raw) : false;

    return is_int($timestamp) ? date('d.m.Y.', $timestamp) : $raw;
};
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
                        autofocus
                    >
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label" for="workspace-search-workspace-button">
                        <?= $this->escape(__('Workspace')) ?>
                    </label>
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
                </div>
                <div class="col-12 col-md-6 col-lg-3 d-grid">
                    <button class="btn btn-primary btn-lg" type="submit"><?= $this->escape(__('Search')) ?></button>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="workspace-search-author"><?= $this->escape(__('Author')) ?></label>
                    <div
                        class="dropdown hph-workspace-search__author-picker"
                        data-workspace-search-author-picker
                        data-endpoint="<?= $this->escape($authorLookupPath) ?>"
                        data-all-label="<?= $this->escape(__('All authors')) ?>"
                    >
                        <input
                            type="hidden"
                            id="workspace-search-author"
                            name="author"
                            value="<?= $this->escape($authorValue) ?>"
                            data-author-value
                        >
                        <button
                            class="form-select text-start"
                            type="button"
                            data-bs-toggle="dropdown"
                            data-bs-auto-close="outside"
                            aria-expanded="false"
                            data-author-toggle
                            <?= $authorLookupPath === '' ? 'disabled' : '' ?>
                        ><?= $this->escape(
                            $authorLookupPath !== '' ? $authorLabel : __('Sign in to select an author'),
                        ) ?></button>
                        <?php if ($authorLookupPath !== '') : ?>
                            <div class="dropdown-menu w-100 p-3 shadow">
                                <input
                                    class="form-control mb-2"
                                    type="search"
                                    autocomplete="off"
                                    placeholder="<?= $this->escape(__('Search authors')) ?>"
                                    aria-label="<?= $this->escape(__('Search authors')) ?>"
                                    data-author-search
                                >
                                <div class="small text-body-secondary mb-2" data-author-loading hidden>
                                    <?= $this->escape(__('Loading authors...')) ?>
                                </div>
                                <div class="alert alert-danger py-2" data-author-error hidden></div>
                                <div
                                    class="list-group list-group-flush hph-workspace-search__author-list"
                                    data-author-list
                                ></div>
                                <div class="small text-body-secondary mt-2" data-author-empty hidden>
                                    <?= $this->escape(__('No authors match the search.')) ?>
                                </div>
                                <div class="d-flex align-items-center justify-content-between gap-2 mt-2">
                                    <span class="small text-body-secondary" data-author-count></span>
                                    <button
                                        class="btn btn-sm btn-outline-secondary"
                                        type="button"
                                        data-author-more
                                        hidden
                                    ><?= $this->escape(__('Load more')) ?></button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
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
            </div>
        </div>
    </form>

    <aside class="card shadow-sm mt-3 hph-workspace-search__help" aria-labelledby="workspace-search-help-title">
        <div class="card-body">
            <h2 class="h5" id="workspace-search-help-title"><?= $this->escape(__('How search works')) ?></h2>
            <ul class="mb-0">
                <li>
                    <?= $this->escape(__(
                        'Select one Workspace without a search term to list all its visible pages.',
                    )) ?>
                </li>
                <li>
                    <?= $this->escape(__(
                        'Select an author without a search term to list pages they created or last modified.',
                    )) ?>
                </li>
                <li>
                    <?= $this->escape(__(
                        'Publication dates limit the results by publication date. '
                        . 'If only the start date is entered, the end date is set to today; '
                        . 'if only the end date is entered, all earlier dates are included.',
                    )) ?>
                </li>
                <li>
                    <?= $this->escape(__('A search term is required when two or more Workspaces are selected.')) ?>
                </li>
                <li>
                    <?= $this->escape(__(
                        'You can combine the search term, Workspace, author, and publication dates; '
                        . 'every selected criterion narrows the results.',
                    )) ?>
                </li>
                <li><?= $this->escape(__(
                    'Without operators, the entered words are searched as one phrase. '
                    . 'Use + before each required word or quoted phrase, for example: +part +second +"Part 2".',
                )) ?></li>
            </ul>
        </div>
    </aside>

    <?php if ($searchExecuted) : ?>
        <p class="text-muted mt-4 mb-3">
            <?= $this->escape(sprintf(__('Results found: %d'), $total)) ?>
        </p>
        <?php if ($items === []) : ?>
            <div class="alert alert-info" role="status">
                <?= $this->escape(__('No results match the selected filters.')) ?>
            </div>
        <?php endif; ?>

        <?php if ($browseMode) : ?>
            <div class="table-responsive hph-workspace-search__table-wrap">
                <table class="table table-hover align-middle hph-workspace-search__table">
                    <thead>
                    <tr>
                        <?php foreach (
                        [
                            'title' => __('Page name'),
                            'author' => __('Author'),
                            'published_at' => __('Published'),
                            'modified_at' => __('Last modified'),
                            'modified_by' => __('Modified by'),
                        ] as $column => $label
) : ?>
                            <th scope="col">
                                <a href="<?= $this->escape($sortPath($column)) ?>">
                                    <?= $this->escape($label . $sortIndicator($column)) ?>
                                </a>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item) : ?>
                        <tr>
                            <td data-label="<?= $this->escape(__('Page name')) ?>">
                                <a href="<?= $this->escape(WorkspaceValue::string($item['url'] ?? '#')) ?>">
                                    <?= $this->escape(WorkspaceValue::string($item['title'] ?? '')) ?>
                                </a>
                                <small class="d-block text-body-secondary">
                                    <?= $this->escape(WorkspaceValue::string($item['workspace_name'] ?? '')) ?>
                                </small>
                            </td>
                            <td data-label="<?= $this->escape(__('Author')) ?>">
                                <?= $this->escape(WorkspaceValue::string($item['author_name'] ?? '')) ?>
                            </td>
                            <td data-label="<?= $this->escape(__('Published')) ?>">
                                <?= $this->escape($displayDate($item['published_at'] ?? '')) ?>
                            </td>
                            <td data-label="<?= $this->escape(__('Last modified')) ?>">
                                <?= $this->escape($displayDate($item['modified_at'] ?? '')) ?>
                            </td>
                            <td data-label="<?= $this->escape(__('Modified by')) ?>">
                                <?= $this->escape(WorkspaceValue::string($item['modified_by_name'] ?? '')) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
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
                                        · <?= $this->escape($displayDate($item['published_at'] ?? '')) ?>
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
        <?php endif; ?>

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
    <?php elseif ($queryTooShort) : ?>
        <p class="text-muted mt-4">
            <?= $this->escape(sprintf(__('Enter at least %d characters.'), $minimumQueryLength)) ?>
        </p>
    <?php elseif ($queryRequiredForMultipleWorkspaces) : ?>
        <p class="text-muted mt-4">
            <?= $this->escape(__('Enter a search term when two or more Workspaces are selected.')) ?>
        </p>
    <?php else : ?>
        <p class="text-muted mt-4">
            <?= $this->escape(__('Enter a search term or select a Workspace, author, or publication date.')) ?>
        </p>
    <?php endif; ?>
</section>

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

        document.querySelectorAll('[data-workspace-search-author-picker]').forEach(function (picker) {
            var endpoint = String(picker.dataset.endpoint || '');
            var allLabel = String(picker.dataset.allLabel || '');
            var value = picker.querySelector('[data-author-value]');
            var toggle = picker.querySelector('[data-author-toggle]');
            var search = picker.querySelector('[data-author-search]');
            var loading = picker.querySelector('[data-author-loading]');
            var errorBox = picker.querySelector('[data-author-error]');
            var list = picker.querySelector('[data-author-list]');
            var empty = picker.querySelector('[data-author-empty]');
            var count = picker.querySelector('[data-author-count]');
            var more = picker.querySelector('[data-author-more]');
            var state = { page: 0, hasMore: false, loading: false, loaded: false, sequence: 0, timer: 0 };
            if (endpoint === '' || !list) {
                return;
            }

            function optionButton(id, label) {
                var button = document.createElement('button');
                button.className = 'list-group-item list-group-item-action';
                button.type = 'button';
                button.dataset.authorChoice = String(id || '');
                button.dataset.authorLabel = label;
                button.textContent = label;
                button.classList.toggle('active', String(value?.value || '') === String(id || ''));
                return button;
            }

            function render(items, append) {
                if (!append) {
                    list.replaceChildren(optionButton('', allLabel));
                }
                Array.from(items || []).forEach(function (item) {
                    var id = Number(item?.id || 0);
                    var label = String(item?.label || '').trim();
                    if (id > 0 && label !== '') {
                        list.appendChild(optionButton(String(id), label));
                    }
                });
                var resultCount = Math.max(0, list.children.length - 1);
                empty?.toggleAttribute('hidden', resultCount > 0);
                if (count) {
                    count.textContent = <?= json_encode(__('Shown: %d'), JSON_UNESCAPED_UNICODE) ?>
                        .replace('%d', String(resultCount));
                }
            }

            async function load(page, append) {
                var sequence = ++state.sequence;
                state.loading = true;
                loading?.removeAttribute('hidden');
                errorBox?.setAttribute('hidden', '');
                var url = new URL(endpoint, window.location.href);
                url.searchParams.set('q', String(search?.value || '').trim());
                url.searchParams.set('page', String(page));
                try {
                    var response = await fetch(url.toString(), {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json' }
                    });
                    var payload = await response.json();
                    if (sequence !== state.sequence) {
                        return;
                    }
                    if (!response.ok || payload?.ok !== true) {
                        throw new Error(String(payload?.error || <?= json_encode(
                            __('The author list could not be loaded.'),
                        ) ?>));
                    }
                    render(payload.items, append);
                    state.page = Number(payload.page || page) || 1;
                    state.hasMore = payload.hasMore === true;
                    state.loaded = true;
                    more?.toggleAttribute('hidden', !state.hasMore);
                } catch (error) {
                    if (sequence !== state.sequence) {
                        return;
                    }
                    if (!append) {
                        render([], false);
                    }
                    if (errorBox) {
                        errorBox.textContent = error instanceof Error
                            ? error.message
                            : <?= json_encode(__('The author list could not be loaded.')) ?>;
                        errorBox.removeAttribute('hidden');
                    }
                } finally {
                    if (sequence === state.sequence) {
                        state.loading = false;
                        loading?.setAttribute('hidden', '');
                    }
                }
            }

            list.addEventListener('click', function (event) {
                var option = event.target instanceof Element ? event.target.closest('[data-author-choice]') : null;
                if (!option || !list.contains(option)) {
                    return;
                }
                var id = String(option.dataset.authorChoice || '');
                var label = String(option.dataset.authorLabel || allLabel);
                if (value) {
                    value.value = id;
                }
                if (toggle) {
                    toggle.textContent = label;
                }
                Array.from(list.children).forEach(function (candidate) {
                    candidate.classList.toggle('active', candidate === option);
                });
                if (toggle && window.bootstrap?.Dropdown) {
                    window.bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
                }
            });
            search?.addEventListener('input', function () {
                window.clearTimeout(state.timer);
                state.timer = window.setTimeout(function () { load(1, false); }, 250);
            });
            more?.addEventListener('click', function () {
                if (state.hasMore && !state.loading) {
                    load(state.page + 1, true);
                }
            });
            picker.addEventListener('shown.bs.dropdown', function () {
                if (!state.loaded && !state.loading) {
                    load(1, false);
                }
                search?.focus();
            });
            toggle?.addEventListener('click', function () {
                if (!state.loaded && !state.loading) {
                    load(1, false);
                }
            });
        });

        document.querySelectorAll('.hph-workspace-search__form').forEach(function (form) {
            var query = form.querySelector('[name="q"]');
            var allWorkspaces = form.querySelector('[data-workspace-search-scope-all]');
            var workspaceScopes = Array.from(form.querySelectorAll('[data-workspace-search-scope]'));
            var author = form.querySelector('[data-author-value]');
            var from = form.querySelector('[name="from"]');
            var to = form.querySelector('[name="to"]');
            var missingFilterMessage = <?= json_encode(
                __('Enter a search term or select a Workspace, author, or publication date.'),
                JSON_UNESCAPED_UNICODE,
            ) ?>;
            var multipleWorkspacesMessage = <?= json_encode(
                __('Enter a search term when two or more Workspaces are selected.'),
                JSON_UNESCAPED_UNICODE,
            ) ?>;
            form.addEventListener('submit', function (event) {
                var selectedWorkspaceCount = workspaceScopes.filter(function (scope) {
                    return scope.checked;
                }).length;
                var emptyQuery = String(query?.value || '').trim() === '';
                var needsFilter = allWorkspaces?.checked
                    && String(author?.value || '') === ''
                    && String(from?.value || '') === ''
                    && String(to?.value || '') === '';
                var message = emptyQuery && selectedWorkspaceCount > 1
                    ? multipleWorkspacesMessage
                    : (emptyQuery && needsFilter ? missingFilterMessage : '');
                query?.setCustomValidity(message);
                if (query && !query.checkValidity()) {
                    event.preventDefault();
                    query.reportValidity();
                }
            });
            query?.addEventListener('input', function () { query.setCustomValidity(''); });
        });
</script>
