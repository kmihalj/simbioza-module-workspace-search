<?php

declare(strict_types=1);

namespace AaiEduHr\SimbiozaModuleWorkspaceSearch\Service;

use Psr\Container\ContainerInterface;
use Throwable;

/**
 * EN: Adds index administration to the existing Workspace settings group.
 *
 * HR: Dodaje administraciju indeksa u postojeću grupu postavki područja.
 */
final readonly class WorkspaceSearchMenuIntegration
{
    private const MENU_REPOSITORY = \AaiEduHr\HeartPhrameModuleMenu\Service\MenuConfigRepository::class;

    /**
     * EN: Initializes the object and its dependencies.
     *
     * HR: Inicijalizira objekt i njegove ovisnosti.
     */

    public function __construct(private ContainerInterface $container)
    {
    }

    /**
     * EN: Refreshes the owned item without changing the manually arranged menu order.
     *
     * HR: Osvježava vlastitu stavku bez mijenjanja ručno složenog redoslijeda menija.
     */
    public function registerSettingsMenuItem(): void
    {
        if (!class_exists(self::MENU_REPOSITORY)) {
            return;
        }

        try {
            $repository = $this->container->get(self::MENU_REPOSITORY);
            if (!is_object($repository) || !method_exists($repository, 'upsertItemsForSection')) {
                return;
            }

            $repository->upsertItemsForSection('settings', [$this->definition()]);
        } catch (Throwable) {
            // EN: Optional settings-menu integration must not stop search.
            //
            // HR: Opcionalni settings meni ne smije zaustaviti pretragu.
        }
    }

    /**
     * EN: Returns the settings-item definition owned by Search.
     *
     * HR: Vraća definiciju stavke koju modul pretrage posjeduje.
     *
     * @return array<string,mixed>
     */
    private function definition(): array
    {
        return [
            'id' => 'workspace-search.settings',
            'parent_id' => 'workspace.settings.group',
            'label' => ['hr' => 'Indeks pretrage', 'en' => 'Search index'],
            'route' => 'workspace-search.settings',
            'url' => '',
            'query' => '',
            'order' => 50,
            'enabled' => true,
            'level' => 1,
        ];
    }
}
