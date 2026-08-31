<?php

declare(strict_types=1);

use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceValue;

/**
 * HR: Rezultati pretrage već su ACL-filtrirani u servisu; view samo prikazuje siguran model.
 * EN: Search results are already ACL-filtered in the service; the view only renders the safe model.
 *
 * @var \HeartPhrame\View\View $this
 * @var array<string, mixed> $result
 * @var int $minimumQueryLength
 * @var string $assetsCssPath
 * @var string $searchPath
 * @var array<string,scalar> $paginationQuery
 */

$items = WorkspaceValue::rows($result['items'] ?? null);
$filters = WorkspaceValue::stringKeyArray($result['filters'] ?? null);
$workspaces = WorkspaceValue::rows($result['workspaces'] ?? null);
$query = is_scalar($result['query'] ?? null) ? (string)$result['query'] : '';
$total = is_numeric($result['total'] ?? null) ? (int)$result['total'] : 0;
$page = is_numeric($result['page'] ?? null) ? (int)$result['page'] : 1;
$pages = is_numeric($result['pages'] ?? null) ? (int)$result['pages'] : 0;
?>

<link rel="stylesheet" href="<?= $this->escape($assetsCssPath) ?>">

<section class="container-xl hph-workspace-search py-4">
    <form
        class="card shadow-sm hph-workspace-search__form"
        method="get"
        action="<?= $this->escape($searchPath) ?>"
        role="search"
    >
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
                    <label class="form-label" for="workspace-search-workspace">
                        <?= $this->escape(__('Workspace')) ?>
                    </label>
                    <select class="form-select" id="workspace-search-workspace" name="workspace">
                        <option value=""><?= $this->escape(__('All visible workspaces')) ?></option>
                        <?php foreach ($workspaces as $workspace) : ?>
                            <?php
                            $slug = WorkspaceValue::string($workspace['slug'] ?? '');
                            $name = WorkspaceValue::string($workspace['name'] ?? '') ?: $slug;
                            ?>
                            <option
                                value="<?= $this->escape($slug) ?>"
                                <?= ($filters['workspace'] ?? '') === $slug ? 'selected' : '' ?>
                            ><?= $this->escape($name) ?></option>
                        <?php endforeach; ?>
                    </select>
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
                            <?= $this->escape(WorkspaceValue::string($item['workspace_name'] ?? '')) ?>
                            <?php if (WorkspaceValue::string($item['author_name'] ?? '') !== '') : ?>
                                · <?= $this->escape(WorkspaceValue::string($item['author_name'])) ?>
                            <?php endif; ?>
                            <?php if (WorkspaceValue::string($item['published_at'] ?? '') !== '') : ?>
                                · <?= $this->escape(WorkspaceValue::string($item['published_at'])) ?>
                            <?php endif; ?>
                        </p>
                        <p class="mb-0"><?= WorkspaceValue::string($item['snippet_html'] ?? '') ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1) : ?>
            <nav class="mt-4" aria-label="<?= $this->escape(__('Result pages')) ?>">
                <ul class="pagination">
                    <?php for ($number = 1; $number <= $pages; ++$number) : ?>
                        <?php $params = [...$paginationQuery, 'page' => $number]; ?>
                        <li class="page-item <?= $number === $page ? 'active' : '' ?>">
                            <a
                                class="page-link"
                                href="<?= $this->escape($searchPath . '?' . http_build_query($params)) ?>"
                            ><?= $this->escape((string)$number) ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php else : ?>
        <p class="text-muted mt-4">
            <?= $this->escape(sprintf(__('Enter at least %d characters.'), $minimumQueryLength)) ?>
        </p>
    <?php endif; ?>
</section>
