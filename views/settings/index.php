<?php

declare(strict_types=1);

/**
 * @var \HeartPhrame\View\View $this
 * @var string $title
 * @var string $reindexPath
 * @var string $workspaceLookupPath
 * @var string $workspaceAssetsCssPath
 * @var string $workspaceAssetsJsPath
 * @var string $csrfInput
 * @var string $settingsMenuActiveSection
 * @var object|null $menuRenderer
 */

$settingsMenuHtml = null;
if (isset($menuRenderer) && is_object($menuRenderer)) {
    $callback = [$menuRenderer, 'renderSettingsMenu'];
    if (is_callable($callback)) {
        $rendered = $callback($settingsMenuActiveSection);
        $settingsMenuHtml = is_string($rendered) ? $rendered : null;
    }
}
?>
<link rel="stylesheet" href="<?= $this->escape($workspaceAssetsCssPath) ?>">
<script src="<?= $this->escape($workspaceAssetsJsPath) ?>" defer></script>
<div class="row g-4">
    <?php if (is_string($settingsMenuHtml) && $settingsMenuHtml !== '') : ?>
        <aside class="col-lg-3"><?= $settingsMenuHtml ?></aside>
    <?php endif; ?>
    <div class="<?= is_string($settingsMenuHtml) && $settingsMenuHtml !== '' ? 'col-lg-9' : 'col-12' ?>">
        <section class="card">
            <div class="card-body">
                <header class="mb-4">
                    <h1 class="h3 mb-1"><?= $this->escape($title) ?></h1>
                    <p class="text-body-secondary mb-0">
                        <?= $this->escape(__(
                            'Obnovite izvedeni indeks nakon uvoza, povrata backupa ili sumnje u njegovu ispravnost.',
                        )) ?>
                    </p>
                </header>
                <form method="post" action="<?= $this->escape($reindexPath) ?>">
                    <?= is_string($csrfInput) ? $csrfInput : '' ?>
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-lg-8">
                            <label class="form-label">
                                <?= $this->escape(__('Opseg reindeksa')) ?>
                            </label>
                            <div
                                class="dropdown workspace-lookup-picker"
                                data-workspace-lookup-picker="workspace"
                                data-workspace-lookup-endpoint="<?= $this->escape($workspaceLookupPath) ?>"
                                data-workspace-lookup-audience="current"
                                data-workspace-lookup-workspace-selector=""
                                data-workspace-lookup-all-label="<?= $this->escape(__('Sva područja')) ?>"
                                data-workspace-lookup-all-value="0"
                                data-workspace-lookup-value-mode="id"
                                data-workspace-lookup-published="0"
                                data-workspace-lookup-include-shorts="0"
                            >
                                <input type="hidden" name="workspace_id" value="0" data-workspace-lookup-value>
                                <button
                                    class="form-select text-start"
                                    type="button"
                                    data-bs-toggle="dropdown"
                                    data-bs-auto-close="outside"
                                    data-bs-boundary="viewport"
                                    aria-expanded="false"
                                    data-workspace-lookup-toggle
                                    data-workspace-lookup-placeholder="<?= $this->escape(__('Odaberite područje')) ?>"
                                ><?= $this->escape(__('Sva područja')) ?></button>
                                <div class="dropdown-menu p-3 shadow workspace-lookup-menu">
                                    <input
                                        class="form-control form-control-sm mb-2"
                                        type="search"
                                        autocomplete="off"
                                        placeholder="<?= $this->escape(__('Pretraži područja')) ?>"
                                        data-workspace-lookup-search
                                    >
                                    <div class="small text-body-secondary mb-2" data-workspace-lookup-loading hidden>
                                        <?= $this->escape(__('Učitavanje...')) ?>
                                    </div>
                                    <div class="alert alert-danger py-2" data-workspace-lookup-error hidden></div>
                                    <div
                                        class="list-group list-group-flush workspace-lookup-list"
                                        data-workspace-lookup-list
                                    ></div>
                                    <div class="small text-body-secondary mt-2" data-workspace-lookup-empty hidden>
                                        <?= $this->escape(__('Nema rezultata.')) ?>
                                    </div>
                                    <button
                                        class="btn btn-sm btn-outline-secondary mt-2"
                                        type="button"
                                        data-workspace-lookup-more
                                        hidden
                                    ><?= $this->escape(__('Učitaj još')) ?></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-4 d-grid">
                            <button type="submit" class="btn btn-primary">
                                <?= $this->escape(__('Ponovno izgradi indeks')) ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>
