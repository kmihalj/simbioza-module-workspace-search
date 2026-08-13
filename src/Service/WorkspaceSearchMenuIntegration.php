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
            if (!is_object($repository) || !method_exists($repository, 'jsonPathForSection')) {
                return;
            }

            $path = $repository->jsonPathForSection('settings');
            if (!is_string($path) || $path === '') {
                return;
            }

            $items = $this->read($path);
            $original = $items;
            if (!$this->update($items)) {
                $this->appendToParent($items, 'workspace.settings.group', $this->definition());
            }

            if ($items !== $original) {
                $this->write($path, $items);
            }
        } catch (Throwable) {
            // HR: Opcionalni settings meni ne smije zaustaviti pretragu.
            // EN: Optional settings-menu integration must not stop search.
        }
    }

    /**
     * HR: Čita postojeće stablo postavki bez normalizacije njegova redoslijeda.
     * EN: Reads the existing settings tree without normalizing its ordering.
     * @return list<array<string,mixed>>
     */
    private function read(string $path): array
    {
        $decoded = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;

        return $this->rows($decoded);
    }

    /**
     * HR: Osvježava vlastitu stavku na postojećem mjestu i čuva ručni redoslijed.
     * EN: Refreshes the owned item in place and preserves manual ordering.
     * @param list<array<string,mixed>> $items
     */
    private function update(array &$items): bool
    {
        foreach ($items as &$item) {
            if (($item['id'] ?? null) === 'workspace-search.settings') {
                $order = $item['order'] ?? null;
                $item = array_replace($item, $this->definition());
                if (is_numeric($order)) {
                    $item['order'] = (int)$order;
                }

                unset($item);

                return true;
            }

            $children = $this->rows($item['children'] ?? null);
            if ($children !== [] && $this->update($children)) {
                $item['children'] = $children;
                unset($item);

                return true;
            }
        }

        unset($item);

        return false;
    }

    /**
     * HR: Dodaje novu stavku samo u postojeću roditeljsku granu.
     * EN: Appends a new item only to the existing parent branch.
     * @param list<array<string,mixed>> $items
     * @param array<string,mixed> $definition
     */
    private function appendToParent(array &$items, string $parentId, array $definition): bool
    {
        foreach ($items as &$item) {
            if (($item['id'] ?? null) === $parentId) {
                $children = $this->rows($item['children'] ?? null);
                $children[] = $definition;
                $item['children'] = $children;
                unset($item);

                return true;
            }

            $children = $this->rows($item['children'] ?? null);
            if ($children !== [] && $this->appendToParent($children, $parentId, $definition)) {
                $item['children'] = $children;
                unset($item);

                return true;
            }
        }

        unset($item);

        return false;
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

    /**
     * HR: Atomarno zapisuje settings JSON samo kada se vlastita stavka promijenila.
     * EN: Atomically writes settings JSON only when the owned item changed.
     * @param list<array<string,mixed>> $items
     */
    private function write(string $path, array $items): void
    {
        $json = json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return;
        }

        $temporary = $path . '.tmp.' . bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $json . PHP_EOL) !== false) {
            rename($temporary, $path);
        }
    }

    /**
     * HR: Pretvara miješanu vrijednost u listu menu stavki.
     * EN: Converts a mixed value to a list of menu items.
     * @return list<array<string,mixed>>
     */
    private function rows(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $row = [];
            foreach ($item as $key => $entry) {
                if (is_string($key)) {
                    $row[$key] = $entry;
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
