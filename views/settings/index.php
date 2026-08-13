<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceValue;

/**
 * @var \HeartPhrame\View\View $this
 * @var string $title
 * @var list<array<string,mixed>> $workspaces
 * @var string $reindexPath
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
                            <label class="form-label" for="workspace-search-reindex-scope">
                                <?= $this->escape(__('Opseg reindeksa')) ?>
                            </label>
                            <select class="form-select" id="workspace-search-reindex-scope" name="workspace_id">
                                <option value="0"><?= $this->escape(__('Cijeli site')) ?></option>
                                <?php foreach ($workspaces as $workspace) : ?>
                                    <option value="<?= WorkspaceValue::int($workspace['id'] ?? 0) ?>">
                                        <?= $this->escape(WorkspaceValue::string($workspace['name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
