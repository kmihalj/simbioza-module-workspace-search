<?php

declare(strict_types=1);

use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceValue;

/**
 * EN: Every option comes from a bounded ACL-aware lookup; no complete metadata catalogue is embedded.
 *
 * HR: Svaka opcija dolazi iz ograničenog ACL-svjesnog dohvata; potpuni katalog metapodataka ne ugrađuje se u HTML.
 *
 * @var \HeartPhrame\View\View $this
 * @var array<string,mixed> $filters
 * @var array<string,string> $metadataLookupPaths
 */
$metadataPickers = [
    'label' => [__('Oznaka'), __('Sve oznake'), __('Pretraži oznake')],
    'property_key' => [__('Svojstvo'), __('Sva svojstva'), __('Pretraži svojstva')],
    'property_value' => [__('Vrijednost svojstva'), __('Sve vrijednosti'), __('Pretraži vrijednosti')],
];
$workflowOptions = [
    '' => __('Svi dostupni statusi'),
    'published' => __('Objavljeno'),
    'draft' => __('Nacrt izmjena'),
    'in_review' => __('Izmjene na pregledu'),
];
foreach ($metadataPickers as $filterName => [$filterTitle, $allLabel, $searchLabel]) :
    $filterValue = WorkspaceValue::string($filters[$filterName] ?? '');
    $disabled = $filterName === 'property_value' && WorkspaceValue::string($filters['property_key'] ?? '') === '';
    ?>
    <div class="col-12 col-md-6 col-lg-3">
        <label class="form-label" for="workspace-search-<?= $this->escape($filterName) ?>-toggle">
            <?= $this->escape($filterTitle) ?>
        </label>
        <div
            class="dropdown hph-workspace-search__metadata-picker"
            data-workspace-search-metadata-picker
            data-filter-name="<?= $this->escape($filterName) ?>"
            data-endpoint="<?= $this->escape($metadataLookupPaths[$filterName] ?? '') ?>"
            data-all-label="<?= $this->escape($allLabel) ?>"
            data-error-label="<?= $this->escape(__('Popis filtara trenutačno nije moguće dohvatiti.')) ?>"
        >
            <input type="hidden" name="<?= $this->escape($filterName) ?>"
                value="<?= $this->escape($filterValue) ?>" data-metadata-value>
            <button
                class="form-select text-start text-truncate"
                id="workspace-search-<?= $this->escape($filterName) ?>-toggle"
                type="button"
                data-bs-toggle="dropdown"
                data-bs-auto-close="outside"
                aria-expanded="false"
                data-metadata-toggle
                <?= $disabled ? 'disabled' : '' ?>
            ><?= $this->escape($filterValue !== '' ? $filterValue : $allLabel) ?></button>
            <div class="dropdown-menu w-100 p-3 shadow">
                <input class="form-control mb-2" type="search" autocomplete="off"
                    aria-label="<?= $this->escape($searchLabel) ?>"
                    placeholder="<?= $this->escape($searchLabel) ?>" data-metadata-search>
                <div class="small text-body-secondary mb-2" role="status" aria-live="polite"
                    data-metadata-loading hidden>
                    <?= $this->escape(__('Učitavanje...')) ?>
                </div>
                <div class="alert alert-danger py-2" role="alert" data-metadata-error hidden></div>
                <div class="list-group list-group-flush hph-workspace-search__metadata-list" data-metadata-list></div>
                <div class="small text-body-secondary mt-2" data-metadata-empty hidden>
                    <?= $this->escape(__('Nema dostupnih vrijednosti.')) ?>
                </div>
                <button class="btn btn-sm btn-outline-secondary mt-2" type="button" data-metadata-more hidden>
                    <?= $this->escape(__('Učitaj još')) ?>
                </button>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<div class="col-12 col-md-6 col-lg-3">
    <label class="form-label" for="workspace-search-workflow"><?= $this->escape(__('Status objave')) ?></label>
    <select class="form-select" id="workspace-search-workflow" name="workflow">
        <?php foreach ($workflowOptions as $workflowValue => $workflowLabel) : ?>
            <option value="<?= $this->escape($workflowValue) ?>"
                <?= WorkspaceValue::string($filters['workflow'] ?? '') === $workflowValue ? 'selected' : '' ?>
            ><?= $this->escape($workflowLabel) ?></option>
        <?php endforeach; ?>
    </select>
</div>
