<?php

declare(strict_types=1);

return [
    'module' => 'workspace-search',
    'extension' => \AaiEduHr\SimbiozaModuleWorkspaceSearch\Api\WorkspaceSearchApiExtension::class,
    'resources' => [
        'workspace-search' => [
            'label' => ['hr' => 'Pretraga područja', 'en' => 'Workspace search'],
            'scopes' => [
                'workspace-search:read' => [
                    'label' => ['hr' => 'Pretraživanje', 'en' => 'Search'],
                    'description' => [
                        'hr' => 'Pretražuje isključivo objavljene stranice koje vlasnik API ključa smije vidjeti.',
                        'en' => 'Searches only published pages visible to the API key owner.',
                    ],
                ],
            ],
        ],
    ],
];
