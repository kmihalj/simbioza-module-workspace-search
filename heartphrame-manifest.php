<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleAuth\Middleware\RequireAuthenticatedUserMiddleware;
use AaiEduHr\HeartPhrameModuleAuth\ModuleAuth;
use AaiEduHr\HeartPhrameModuleEditorHtml\ModuleEditorHtml;
use AaiEduHr\HeartPhrameModuleMenu\Extension\TopMenuControlRegistry;
use AaiEduHr\HeartPhrameModuleMenu\ModuleMenu;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\SimbiozaModuleWorkspace\Event\WorkspaceContentChanged;
use AaiEduHr\SimbiozaModuleWorkspace\ModuleWorkspace;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Command\HpWorkspaceSearchCommand;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Controller\WorkspaceSearchController;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Controller\WorkspaceSearchSettingsController;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Listener\PurgeWorkspaceSearchIndex;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Listener\SynchronizeWorkspaceSearchIndex;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\ModuleWorkspaceSearch;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchMenuIntegration;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchTopMenuControl;
use HeartPhrame\Bridge\ComposerBridge;
use HeartPhrame\Command\CommandDefinition;
use HeartPhrame\Config\ConfigInterface;
use HeartPhrame\Event\EventListener;
use Psr\Container\ContainerInterface;

return new class extends \HeartPhrame\Module\AbstractModuleManifest {
    private const REQUIRED_PACKAGES = [
        'aaieduhr/heartphrame-module-orm',
        'aaieduhr/heartphrame-module-menu',
        'aaieduhr/heartphrame-module-auth',
        'aaieduhr/heartphrame-module-editor-html',
        'aaieduhr/simbioza-module-workspace',
    ];

    /**
     * EN: Search loads only after every data owner and the navigation module.
     *
     * HR: Pretraga se učitava tek nakon svih vlasnika podataka i navigacije.
     */
    public function canLoad(ContainerInterface $container): bool
    {
        $composer = $container->get(ComposerBridge::class);
        $config = $container->get(ConfigInterface::class);
        if (!($composer instanceof ComposerBridge) || !($config instanceof ConfigInterface)) {
            throw new RuntimeException('Workspace Search requires ComposerBridge and ConfigInterface.');
        }

        $enabled = $config->getAsArrayWithValuesAsNonEmptyStrings('app.modules.enabled') ?? [];
        $searchPosition = array_search(ModuleWorkspaceSearch::PACKAGE_NAME, $enabled, true);
        foreach (self::REQUIRED_PACKAGES as $package) {
            $requiredPosition = array_search($package, $enabled, true);
            if (
                !$composer->isInstalled($package)
                || $requiredPosition === false
                || ($searchPosition !== false && $requiredPosition > $searchPosition)
            ) {
                throw new RuntimeException(
                    'Workspace Search requires enabled module "' . $package . '" before itself.',
                );
            }
        }

        if (
            !class_exists(ModuleAuth::class) || !class_exists(ModuleEditorHtml::class)
            || !class_exists(ModuleMenu::class) || !class_exists(ModuleWorkspace::class)
            || !class_exists(Database::class)
        ) {
            throw new RuntimeException('Workspace Search required module classes are unavailable.');
        }

        return true;
    }

    /**
     * EN: Defers integration until dependencies load.
     *
     * HR: Odgađa integraciju do učitavanja svih ovisnosti.
     */
    public function requiresDeferredLoading(): bool
    {
        return true;
    }

    /**
     * EN: Returns module DI factories.
     *
     * HR: Vraća DI tvornice modula.
     */
    public function getServices(): array
    {
        $services = require __DIR__ . '/config/services.php';

        return is_array($services) ? $services : [];
    }

    /**
     * EN: Registers page, suggestions, and CSS routes.
     *
     * HR: Registrira stranicu, prijedloge i CSS.
     */
    public function getBaseRoutes(): array
    {
        return [
            ['GET', '/search', WorkspaceSearchController::class . '@index', 'workspace-search.index', []],
            [
                'GET',
                '/search/suggest',
                WorkspaceSearchController::class . '@suggest',
                'workspace-search.suggest',
                [],
            ],
            [
                'GET',
                '/search/lookups/authors',
                WorkspaceSearchController::class . '@authors',
                'workspace-search.lookup.authors',
                [RequireAuthenticatedUserMiddleware::class],
            ],
            [
                'GET',
                '/settings/workspace-search',
                WorkspaceSearchSettingsController::class . '@index',
                'workspace-search.settings',
                [RequireAuthenticatedUserMiddleware::class],
            ],
            [
                'POST',
                '/settings/workspace-search/reindex',
                WorkspaceSearchSettingsController::class . '@reindex',
                'workspace-search.settings.reindex',
                [RequireAuthenticatedUserMiddleware::class],
            ],
            [
                'GET',
                '/search/assets.css',
                WorkspaceSearchController::class . '@styles',
                'workspace-search.assets.css',
                [],
            ],
        ];
    }

    /**
     * EN: Returns the view template directory.
     *
     * HR: Vraća direktorij view predložaka.
     */
    public function getViewsPath(): string
    {
        return __DIR__ . '/views';
    }

    /**
     * EN: Exposes migration and indexing CLI commands.
     *
     * HR: Izlaže migracijsku i indeksne CLI naredbe.
     */
    public function getCommands(): array
    {
        return [
            new CommandDefinition(
                'workspace-search',
                'Workspace Search helper command.',
                [HpWorkspaceSearchCommand::class, 'run'],
            ),
            new CommandDefinition(
                'workspace-search:install-migration',
                'Copy Workspace Search migration.',
                [HpWorkspaceSearchCommand::class, 'installMigration'],
            ),
            new CommandDefinition(
                'workspace-search:rebuild',
                'Rebuild the Workspace Search index.',
                [HpWorkspaceSearchCommand::class, 'rebuild'],
            ),
        ];
    }

    /**
     * EN: Binds neutral Workspace changes to targeted search synchronization.
     *
     * HR: Veže neutralne Workspace promjene na ciljanu sinkronizaciju pretrage.
     *
     * @return EventListener[]
     */
    public function getEventListeners(): array
    {
        return [
            new EventListener(WorkspaceContentChanged::class, SynchronizeWorkspaceSearchIndex::class),
            new EventListener(
                \AaiEduHr\SimbiozaModuleWorkspace\Event\WorkspacePermanentlyDeleting::class,
                PurgeWorkspaceSearchIndex::class,
            ),
        ];
    }

    /**
     * EN: Adds search to the composable top menu.
     *
     * HR: Dodaje tražilicu u sastavljivi gornji meni.
     */
    public function getBootstrapCallables(): array
    {
        return [
            static function (ContainerInterface $container): void {
                $registry = $container->get(TopMenuControlRegistry::class);
                $control = $container->get(WorkspaceSearchTopMenuControl::class);
                if ($registry instanceof TopMenuControlRegistry && $control instanceof WorkspaceSearchTopMenuControl) {
                    $registry->register(ModuleWorkspaceSearch::PACKAGE_NAME, $control);
                }
            },
            static function (ContainerInterface $container): void {
                $integration = $container->get(WorkspaceSearchMenuIntegration::class);
                if ($integration instanceof WorkspaceSearchMenuIntegration) {
                    $integration->registerSettingsMenuItem();
                }
            },
        ];
    }
};
