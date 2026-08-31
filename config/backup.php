<?php

declare(strict_types=1);

// HR: Indeks nije izvorni podatak. Odvojeni završni provideri omogućuju
// ispravan graf ovisnosti za potpuni site i za jedno područje.
// EN: The index is not source data. Separate finalizer providers keep the
// dependency graph correct for a full site and for a single workspace.
return ['providers' => [
    [
        'service' => 'heartphrame.backup.provider.workspace-search-site',
        'requires' => [
            'aaieduhr/simbioza-module-workspace',
            'aaieduhr/heartphrame-module-editor-html',
        ],
    ],
    [
        'service' => 'heartphrame.backup.provider.workspace-search-workspace',
        'requires' => [
            'aaieduhr/simbioza-module-workspace',
            'aaieduhr/heartphrame-module-editor-html',
        ],
    ],
]];
