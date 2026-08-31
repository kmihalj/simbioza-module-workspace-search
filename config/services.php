<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleAuth\Service\AuthUserService;
use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorPublishedVersionProviderInterface;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceAccessService;
use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceConfig;
use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceRepository;
use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceWorkflowService;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Command\HpWorkspaceSearchCommand;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Api\WorkspaceSearchApiExtension;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Api\WorkspaceSearchResourceController;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Controller\WorkspaceSearchController;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Controller\WorkspaceSearchSettingsController;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Listener\SynchronizeWorkspaceSearchIndex;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Listener\PurgeWorkspaceSearchIndex;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchConfig;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchEditorBridge;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchIndexer;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchMenuIntegration;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchModuleViewRenderer;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchService;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchTopMenuControl;
use HeartPhrame\Alert\AlertHandler;
use HeartPhrame\Config\ConfigInterface;
use HeartPhrame\Http\ResponseFactory;
use HeartPhrame\Localization\TranslatorInterface;
use HeartPhrame\Routing\UrlGenerator;
use HeartPhrame\View\CsrfHandler;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

$services = [
    PurgeWorkspaceSearchIndex::class => static fn(ContainerInterface $container): PurgeWorkspaceSearchIndex =>
        new PurgeWorkspaceSearchIndex($container->get(Database::class)),
    WorkspaceSearchConfig::class => static fn(ContainerInterface $container): WorkspaceSearchConfig =>
        new WorkspaceSearchConfig($container->get(ConfigInterface::class), dirname(__DIR__)),
    WorkspaceSearchEditorBridge::class => static fn(ContainerInterface $container): WorkspaceSearchEditorBridge =>
        new WorkspaceSearchEditorBridge($container->get(EditorPublishedVersionProviderInterface::class)),
    WorkspaceSearchIndexer::class => static fn(ContainerInterface $container): WorkspaceSearchIndexer =>
        new WorkspaceSearchIndexer(
            $container->get(Database::class),
            $container->get(WorkspaceRepository::class),
            $container->get(WorkspaceWorkflowService::class),
            $container->get(WorkspaceSearchEditorBridge::class),
            $container->get(AuthUserService::class),
            $container->get(WorkspaceConfig::class),
            $container->get(WorkspaceSearchConfig::class),
        ),
    WorkspaceSearchService::class => static fn(ContainerInterface $container): WorkspaceSearchService =>
        new WorkspaceSearchService(
            $container->get(Database::class),
            $container->get(WorkspaceAccessService::class),
            $container->get(WorkspaceRepository::class),
            $container->get(WorkspaceConfig::class),
            $container->get(WorkspaceSearchConfig::class),
            $container->get(WorkspaceSearchIndexer::class),
            $container->get(UrlGenerator::class),
        ),
    WorkspaceSearchModuleViewRenderer::class =>
        static fn(ContainerInterface $container): WorkspaceSearchModuleViewRenderer =>
            new WorkspaceSearchModuleViewRenderer($container->get(ResponseFactory::class)),
    WorkspaceSearchTopMenuControl::class =>
        static fn(ContainerInterface $container): WorkspaceSearchTopMenuControl =>
            new WorkspaceSearchTopMenuControl(
                $container->get(UrlGenerator::class),
                $container->get(TranslatorInterface::class),
            ),
    WorkspaceSearchController::class => static fn(ContainerInterface $container): WorkspaceSearchController =>
        new WorkspaceSearchController(
            $container->get(WorkspaceSearchModuleViewRenderer::class),
            $container->get(ResponseFactory::class),
            $container->get(WorkspaceSearchService::class),
            $container->get(WorkspaceSearchConfig::class),
            $container->get(WorkspaceAccessService::class),
            $container->get(WorkspaceConfig::class),
            $container->get(TranslatorInterface::class),
            $container->get(UrlGenerator::class),
            $container->get(\Psr\EventDispatcher\EventDispatcherInterface::class),
            $container->get(LoggerInterface::class),
        ),
    WorkspaceSearchSettingsController::class =>
        static fn(ContainerInterface $container): WorkspaceSearchSettingsController =>
            new WorkspaceSearchSettingsController(
                $container->get(WorkspaceSearchModuleViewRenderer::class),
                $container->get(ResponseFactory::class),
                $container->get(WorkspaceSearchIndexer::class),
                $container->get(WorkspaceRepository::class),
                $container->get(\AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspacePresentationRegistry::class),
                $container->get(WorkspaceAccessService::class),
                $container->get(UrlGenerator::class),
                $container->get(AlertHandler::class),
                $container->get(CsrfHandler::class),
            ),
    WorkspaceSearchMenuIntegration::class =>
        static fn(ContainerInterface $container): WorkspaceSearchMenuIntegration =>
            new WorkspaceSearchMenuIntegration($container),
    SynchronizeWorkspaceSearchIndex::class =>
        static fn(ContainerInterface $container): SynchronizeWorkspaceSearchIndex =>
            new SynchronizeWorkspaceSearchIndex(
                $container->get(WorkspaceSearchIndexer::class),
                $container->get(LoggerInterface::class),
            ),
    HpWorkspaceSearchCommand::class => static fn(ContainerInterface $container): HpWorkspaceSearchCommand =>
        new HpWorkspaceSearchCommand(
            $container->get(ConfigInterface::class),
            $container->get(WorkspaceSearchIndexer::class),
        ),
];

// HR: Search endpoint pripada Search modulu i aktivira se samo uz API jezgru.
// EN: The search endpoint belongs to Search and activates only with the API core.
if (interface_exists(\AaiEduHr\HeartPhrameModuleApi\Contract\ApiExtensionInterface::class)) {
    $services[WorkspaceSearchApiExtension::class] =
        static fn(): WorkspaceSearchApiExtension => new WorkspaceSearchApiExtension();
    $services[WorkspaceSearchResourceController::class] =
        static fn(ContainerInterface $container): WorkspaceSearchResourceController =>
            new WorkspaceSearchResourceController(
                $container->get(\AaiEduHr\HeartPhrameModuleApi\Http\ApiResponseFactory::class),
                $container->get(WorkspaceSearchService::class),
                $container->get(ConfigInterface::class),
            );
}

if (class_exists(\AaiEduHr\HeartPhrameModuleBackup\Service\CallbackFinalizerBackupProvider::class)) {
    $services['heartphrame.backup.provider.workspace-search-site'] =
        static fn(ContainerInterface $container): \AaiEduHr\HeartPhrameModuleBackup\Service\CallbackFinalizerBackupProvider =>
            new \AaiEduHr\HeartPhrameModuleBackup\Service\CallbackFinalizerBackupProvider(
                new \AaiEduHr\HeartPhrameModuleBackup\Value\BackupProviderMetadata(
                    'workspace-search-site',
                    \AaiEduHr\SimbiozaModuleWorkspaceSearch\ModuleWorkspaceSearch::PACKAGE_NAME,
                    1,
                    ['hr' => 'Obnova indeksa pretrage sitea', 'en' => 'Site search-index rebuild'],
                    ['workspace', 'editor-html'],
                    [
                        \AaiEduHr\HeartPhrameModuleBackup\Value\BackupScope::SITE,
                        \AaiEduHr\HeartPhrameModuleBackup\Value\BackupScope::COMPONENT,
                    ],
                    true,
                    false,
                    [\AaiEduHr\SimbiozaModuleWorkspace\ModuleWorkspace::PACKAGE_NAME],
                    true,
                ),
                static function (\AaiEduHr\HeartPhrameModuleBackup\Value\BackupImportContext $context) use ($container): void {
                    // HR: Indeks je izveden podatak i uvijek se gradi iz vraćenog sadržaja.
                    // EN: The index is derived data and is always rebuilt from restored content.
                    $container->get(WorkspaceSearchIndexer::class)->rebuild(true);
                },
            );

    $services['heartphrame.backup.provider.workspace-search-workspace'] =
        static fn(ContainerInterface $container): \AaiEduHr\HeartPhrameModuleBackup\Service\CallbackFinalizerBackupProvider =>
            new \AaiEduHr\HeartPhrameModuleBackup\Service\CallbackFinalizerBackupProvider(
                new \AaiEduHr\HeartPhrameModuleBackup\Value\BackupProviderMetadata(
                    'workspace-search-workspace',
                    \AaiEduHr\SimbiozaModuleWorkspaceSearch\ModuleWorkspaceSearch::PACKAGE_NAME,
                    1,
                    ['hr' => 'Obnova indeksa odabranog područja', 'en' => 'Selected-workspace index rebuild'],
                    ['workspace-scope', 'editor-html-workspace'],
                    [\AaiEduHr\HeartPhrameModuleBackup\Value\BackupScope::WORKSPACE],
                    true,
                    false,
                    [\AaiEduHr\SimbiozaModuleWorkspace\ModuleWorkspace::PACKAGE_NAME],
                    true,
                ),
                static function (\AaiEduHr\HeartPhrameModuleBackup\Value\BackupImportContext $context) use ($container): void {
                    // HR: Scoped restore može promijeniti slug područja. Workspace
                    // provider zato objavljuje ciljnu mapu po stvarnom target slugu.
                    // EN: A scoped restore may change the workspace slug. The Workspace
                    // provider therefore publishes a mapping under the actual target slug.
                    $targetSlug = trim((string)(
                        $context->optionsFor('workspace-scope')['target_slug']
                        ?? $context->scope->identifier
                    ));
                    $workspaceId = $context->state->require('workspace.id-by-slug', $targetSlug);
                    $container->get(WorkspaceSearchIndexer::class)->rebuild(
                        true,
                        is_numeric($workspaceId) ? (int)$workspaceId : null,
                    );
                },
            );
}

return $services;
