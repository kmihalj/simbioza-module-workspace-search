<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspaceSearch\Service;

use Psr\Container\ContainerInterface;
use Throwable;

/**
 * HR: Dodaje administraciju indeksa u postojeću grupu postavki područja.
 * EN: Adds index administration to the existing Workspace settings group.
 */
final readonly class WorkspaceSearchMenuIntegration
{
    private const MENU_REPOSITORY = \AaiEduHr\HeartPhrameModuleMenu\Service\MenuConfigRepository::class;

    /**

     * HR: Inicijalizira objekt i njegove ovisnosti.

     * EN: Initializes the object and its dependencies.

     */

    public function __construct(private ContainerInterface $container)
    {
    }

    /**
 * HR: Osvježava vlastitu stavku bez mijenjanja ručno složenog redoslijeda menija.
 * EN: Refreshes the owned item without changing the manually arranged menu order.
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
            // HR: Opcionalni settings meni ne smije zaustaviti pretragu.
            // EN: Optional settings-menu integration must not stop search.
        }
    }

    /**
     * HR: Vraća definiciju stavke koju modul pretrage posjeduje.
     * EN: Returns the settings-item definition owned by Search.
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
