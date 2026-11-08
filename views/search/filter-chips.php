<?php

declare(strict_types=1);

use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceValue;

/**
 * EN: Removal links preserve the query, locale and every unrelated filter and reset result pagination.
 *
 * HR: Poveznice za uklanjanje čuvaju upit, jezik i ostale filtre te poništavaju stranicu rezultata.
 *
 * @var \HeartPhrame\View\View $this
 * @var array<string,mixed> $filters
 * @var array<string,scalar|list<scalar>> $paginationQuery
 * @var string $searchPath
 * @var string $query
 * @var string $language
 * @var list<string> $selectedWorkspaceScopes
 * @var array<string,string> $workspaceNames
 * @var string $personalWorkspaceFilter
 * @var string $allWorkspaceFilter
 * @var string $selectedAuthorLabel
 */
$chips = [];
foreach ($selectedWorkspaceScopes as $scope) {
    if ($scope === $allWorkspaceFilter) {
        continue;
    }

    $parameters = $paginationQuery;
    unset($parameters['workspace'], $parameters['page']);
    $parameters['workspaces'] = array_values(array_diff($selectedWorkspaceScopes, [$scope]));
    if ($parameters['workspaces'] === []) {
        unset($parameters['workspaces']);
    }

    $name = $scope === $personalWorkspaceFilter ? __('Osobna područja') : ($workspaceNames[$scope] ?? $scope);
    $chips[] = ['label' => __('Područje') . ': ' . $name, 'url' => $searchPath . '?' . http_build_query($parameters)];
}
$chipLabels = [
    'author' => __('Autor'), 'from' => __('Objavljeno od'), 'to' => __('Objavljeno do'),
    'label' => __('Oznaka'), 'property_key' => __('Svojstvo'), 'property_value' => __('Vrijednost svojstva'),
    'workflow' => __('Status objave'),
];
$workflowLabels = [
    'published' => __('Objavljeno'),
    'draft' => __('Nacrt izmjena'),
    'in_review' => __('Izmjene na pregledu'),
];
foreach ($chipLabels as $key => $label) {
    $value = WorkspaceValue::string($filters[$key] ?? '');
    if ($value === '') {
        continue;
    }

    $parameters = $paginationQuery;
    unset($parameters[$key], $parameters['page']);
    if ($key === 'property_key') {
        unset($parameters['property_value']);
    }

    $displayValue = $key === 'workflow' ? ($workflowLabels[$value] ?? $value) : $value;
    if ($key === 'author' && trim($selectedAuthorLabel) !== '') {
        $displayValue = $selectedAuthorLabel;
    }
    $chips[] = ['label' => $label . ': ' . $displayValue, 'url' => $searchPath . '?' . http_build_query($parameters)];
}
$clearUrl = $searchPath . '?' . http_build_query(['q' => $query, 'lang' => $language]);
if ($chips !== []) : ?>
    <nav class="d-flex flex-wrap align-items-center gap-2 mt-3" aria-label="<?= $this->escape(__('Aktivni filtri')) ?>">
        <?php foreach ($chips as $chip) : ?>
            <a class="btn btn-sm btn-outline-secondary hph-workspace-search__filter-chip"
                href="<?= $this->escape($chip['url']) ?>"
                aria-label="<?= $this->escape(sprintf(__('Ukloni filtar: %s'), $chip['label'])) ?>"
            ><?= $this->escape($chip['label']) ?> <span aria-hidden="true">×</span></a>
        <?php endforeach; ?>
        <a class="btn btn-sm btn-link" href="<?= $this->escape($clearUrl) ?>">
            <?= $this->escape(__('Očisti filtre')) ?>
        </a>
    </nav>
<?php endif; ?>
