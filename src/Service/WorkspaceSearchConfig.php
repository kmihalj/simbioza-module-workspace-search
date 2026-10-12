<?php

declare(strict_types=1);

namespace AaiEduHr\SimbiozaModuleWorkspaceSearch\Service;

use HeartPhrame\Config\ConfigArrayMerger;
use HeartPhrame\Config\ConfigInterface;
use RuntimeException;

/**
 * EN: Reads package defaults and the framework's layered workspace-search configuration.
 *
 * HR: Čita paketne postavke i frameworkovu slojevitu konfiguraciju pretrage područja.
 */
final readonly class WorkspaceSearchConfig
{
    /** @var array<string, mixed> */
    private array $settings;

    /**
     * EN: The host loads HPH_CONFIG_PATH/workspace-search/workspace-search.php.
     * Invalid configuration fails; no old flat application file is read.
     *
     * HR: Host učitava HPH_CONFIG_PATH/workspace-search/workspace-search.php.
     * Nevaljana konfiguracija prekida rad; stara ravna datoteka aplikacije ne čita se.
     */
    public function __construct(ConfigInterface $config, string $moduleRoot)
    {
        $defaults = (static fn(string $file): mixed => require $file)(
            rtrim($moduleRoot, '/') . '/config/workspace-search.php',
        );
        $override = $config->get('workspace-search.workspace-search', []);
        if (!is_array($defaults) || !is_array($override)) {
            throw new RuntimeException('Workspace Search configuration must be an array.');
        }

        $this->settings = $this->stringKeyArray(ConfigArrayMerger::merge(
            $this->stringKeyArray($defaults),
            $this->stringKeyArray($override),
        ));
    }

    /**
     * EN: Returns the default page size.
     *
     * HR: Vraća zadanu veličinu stranice.
     */
    public function resultsPerPage(): int
    {
        return $this->boundedInt('results_per_page', 20, 1, $this->maximumResultsPerPage());
    }

    /**
     * EN: Returns the maximum page size.
     *
     * HR: Vraća najveću dopuštenu veličinu stranice.
     */
    public function maximumResultsPerPage(): int
    {
        return $this->boundedInt('maximum_results_per_page', 100, 10, 250);
    }

    /**
     * EN: Returns the minimum query length.
     *
     * HR: Vraća najmanju duljinu upita.
     */
    public function minimumQueryLength(): int
    {
        return $this->boundedInt('minimum_query_length', 2, 1, 10);
    }

    /**
     * EN: Returns the safe snippet length.
     *
     * HR: Vraća duljinu sigurnog isječka.
     */
    public function snippetLength(): int
    {
        return $this->boundedInt('snippet_length', 320, 120, 1200);
    }

    /**
     * EN: Returns the index refresh interval.
     *
     * HR: Vraća interval automatskog osvježavanja indeksa.
     */
    public function refreshSeconds(): int
    {
        return $this->boundedInt('automatic_index_refresh_seconds', 60, 0, 3600);
    }

    /**
     * EN: Reads a bounded integer setting.
     *
     * HR: Čita ograničenu cjelobrojnu postavku.
     */
    private function boundedInt(string $key, int $fallback, int $minimum, int $maximum): int
    {
        $value = is_numeric($this->settings[$key] ?? null) ? (int)$this->settings[$key] : $fallback;

        return max($minimum, min($maximum, $value));
    }

    /**
     * EN: Keeps only string configuration keys.
     *
     * HR: Zadržava samo tekstualne konfiguracijske ključeve.
     * @return array<string, mixed>
     */
    private function stringKeyArray(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $result = [];
        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
