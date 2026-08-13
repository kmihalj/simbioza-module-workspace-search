<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspaceSearch\Command;

use AaiEduHr\HeartPhrameModuleWorkspaceSearch\Service\WorkspaceSearchIndexer;
use HeartPhrame\Config\ConfigInterface;
use RuntimeException;

/**
 * HR: CLI naredbe za instalaciju i obnovu izvedenog indeksa pretrage.
 * EN: CLI commands for installing and rebuilding the derived search index.
 */
final readonly class HpWorkspaceSearchCommand
{
    /**
     * HR: Inicijalizira objekt i njegove ovisnosti.
     * EN: Initializes the object and its dependencies.
     */
    public function __construct(
        private ConfigInterface $config,
        private WorkspaceSearchIndexer $indexer,
    ) {
    }

    /**
 * HR: Usmjerava složenu naredbu prema instalaciji, obnovi ili pomoći.
 * EN: Routes the compound command to installation, rebuild, or help.
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
     * HR: Kopira instalacijsku migraciju u aplikaciju.
     * EN: Copies the installation migration into the application.
     */
    public function installMigration(): int
    {
        $target = rtrim($this->config->getAppRootDir(), '/') . '/database/migrations';
        $source = dirname(__DIR__, 2) . '/resources/migrations/initial_workspace_search_schema.php';
        $destination = $target . '/' . date('YmdHis') . '_install_workspace_search_schema.php';
        if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
            throw new RuntimeException(__('Unable to create the migrations directory.'));
        }

        if (!copy($source, $destination)) {
            throw new RuntimeException(__('Unable to copy the Workspace Search migration.'));
        }

        fwrite(STDOUT, __('Migration created: ') . $destination . PHP_EOL);

        return 0;
    }

    /**
     * HR: Potpuno obnavlja site ili jedno područje zadano kao `--workspace=ID`.
     * EN: Fully rebuilds the site or one Workspace supplied as `--workspace=ID`.
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
            __('Pages indexed: %d; stale rows removed: %d.'),
            $result['indexed'],
            $result['removed'],
        ) . PHP_EOL);

        return 0;
    }

    /**
     * HR: Ispisuje kratku dvojezično dokumentiranu CLI pomoć.
     * EN: Prints concise CLI help documented for both languages.
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
