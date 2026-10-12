<?php

declare(strict_types=1);

namespace AaiEduHr\SimbiozaModuleWorkspaceSearch\Command;

use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchIndexer;
use HeartPhrame\Config\ConfigInterface;
use RuntimeException;

/**
 * EN: CLI commands for installing and rebuilding the derived search index.
 *
 * HR: CLI naredbe za instalaciju i obnovu izvedenog indeksa pretrage.
 */
final readonly class HpWorkspaceSearchCommand
{
    /**
     * EN: Initializes the object and its dependencies.
     *
     * HR: Inicijalizira objekt i njegove ovisnosti.
     */
    public function __construct(
        private ConfigInterface $config,
        private WorkspaceSearchIndexer $indexer,
    ) {
    }

    /**
     * EN: Routes the compound command to installation, rebuild, or help.
     *
     * HR: Usmjerava složenu naredbu prema instalaciji, obnovi ili pomoći.
     *
     * @param array<int, string> $arguments
     * @param array<string, mixed> $options
     */
    public function run(array $arguments = [], array $options = []): int
    {
        $command = strtolower(trim($arguments[0] ?? 'help'));

        return match ($command) {
            'rebuild' => $this->rebuild([], $options),
            'install', 'install-migration' => $this->installMigration(),
            default => $this->help(),
        };
    }

    /**
     * EN: Copies the installation migration into the application.
     *
     * HR: Kopira instalacijsku migraciju u aplikaciju.
     */
    public function installMigration(): int
    {
        $target = rtrim($this->config->getAppRootDir(), '/') . '/database/migrations';
        $source = dirname(__DIR__, 2) . '/resources/migrations/initial_workspace_search_schema.php';
        $destination = $target . '/' . date('YmdHis') . '_install_workspace_search_schema.php';
        if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
            throw new RuntimeException(__('Nije moguće stvoriti direktorij migracija.'));
        }

        if (!copy($source, $destination)) {
            throw new RuntimeException(__('Nije moguće kopirati Workspace Search migraciju.'));
        }

        fwrite(STDOUT, __('Migracija je stvorena: ') . $destination . PHP_EOL);

        return 0;
    }

    /**
     * EN: Fully rebuilds the site or one Workspace supplied as `--workspace=ID`.
     *
     * HR: Potpuno obnavlja site ili jedno područje zadano kao `--workspace=ID`.
     *
     * @param array<int,string> $arguments
     * @param array<string,mixed> $options
     */
    public function rebuild(array $arguments = [], array $options = []): int
    {
        $workspaceId = is_numeric($options['workspace'] ?? null) ? (int)$options['workspace'] : 0;
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--workspace=')) {
                $workspaceId = (int)substr($argument, strlen('--workspace='));
            }
        }

        $result = $workspaceId > 0
        ? $this->indexer->rebuildWorkspace($workspaceId)
        : $this->indexer->rebuild(true);
        fwrite(STDOUT, sprintf(
            __('Indeksirano stranica: %d; uklonjeno starih redaka: %d.'),
            $result['indexed'],
            $result['removed'],
        ) . PHP_EOL);

        return 0;
    }

    /**
     * EN: Prints concise CLI help documented for both languages.
     *
     * HR: Ispisuje kratku dvojezično dokumentiranu CLI pomoć.
     */
    private function help(): int
    {
        fwrite(STDOUT, "hph workspace-search <install|rebuild|help>\n");
        fwrite(STDOUT, "  vendor/bin/hph workspace-search:install-migration\n");
        fwrite(STDOUT, "  vendor/bin/hph workspace-search:rebuild\n");
        fwrite(STDOUT, "  vendor/bin/hph workspace-search rebuild --workspace=42\n");

        return 0;
    }
}
