<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspaceSearch\Service;

use HeartPhrame\Config\ConfigInterface;

/**
 * HR: Čita postavke pretrage iz modula i opcionalnog aplikacijskog overridea.
 * EN: Reads search settings from the module and an optional application override.
 */
final readonly class WorkspaceSearchConfig
{
    /** @var array<string, mixed> */
    private array $settings;

    /**
 * HR: Spaja zadane postavke modula s opcionalnim aplikacijskim overrideom.
 * EN: Merges module defaults with an optional application override.
 */
    public function __construct(ConfigInterface $config, string $moduleRoot)
    {
        $defaults = require rtrim($moduleRoot, '/') . '/config/workspace-search.php';
        $appPath = rtrim($config->getAppRootDir(), '/') . '/config/workspace-search.php';
        $override = is_file($appPath) ? require $appPath : [];
        $this->settings = $this->stringKeyArray([
        ...$this->stringKeyArray($defaults),
        ...$this->stringKeyArray($override),
        ]);
    }

    /**
     * HR: Izvršava internu operaciju resultsPerPage uz provjeru ulaza.
     * EN: Performs the internal resultsPerPage operation with validated input.
     *
     * HR: Vraća zadanu veličinu stranice. EN: Returns the default page size.
     */
    public function resultsPerPage(): int
    {
        return $this->boundedInt('results_per_page', 20, 1, $this->maximumResultsPerPage());
    }

    /**
     * HR: Izvršava internu operaciju maximumResultsPerPage uz provjeru ulaza.
     * EN: Performs the internal maximumResultsPerPage operation with validated input.
     *
     * HR: Vraća najveću dopuštenu veličinu stranice. EN: Returns the maximum page size.
     */
    public function maximumResultsPerPage(): int
    {
        return $this->boundedInt('maximum_results_per_page', 100, 10, 250);
    }

    /**
     * HR: Izvršava internu operaciju minimumQueryLength uz provjeru ulaza.
     * EN: Performs the internal minimumQueryLength operation with validated input.
     *
     * HR: Vraća najmanju duljinu upita. EN: Returns the minimum query length.
     */
    public function minimumQueryLength(): int
    {
        return $this->boundedInt('minimum_query_length', 2, 1, 10);
    }

    /**
     * HR: Izvršava internu operaciju snippetLength uz provjeru ulaza.
     * EN: Performs the internal snippetLength operation with validated input.
     *
     * HR: Vraća duljinu sigurnog isječka. EN: Returns the safe snippet length.
     */
    public function snippetLength(): int
    {
        return $this->boundedInt('snippet_length', 320, 120, 1200);
    }

    /**
     * HR: Izvršava internu operaciju refreshSeconds uz provjeru ulaza.
     * EN: Performs the internal refreshSeconds operation with validated input.
     *
     * HR: Vraća interval automatskog osvježavanja indeksa. EN: Returns the index refresh interval.
     */
    public function refreshSeconds(): int
    {
        return $this->boundedInt('automatic_index_refresh_seconds', 60, 0, 3600);
    }

    /**
     * HR: Čita ograničenu cjelobrojnu postavku.
     * EN: Reads a bounded integer setting.
     */
    private function boundedInt(string $key, int $fallback, int $minimum, int $maximum): int
    {
        $value = is_numeric($this->settings[$key] ?? null) ? (int)$this->settings[$key] : $fallback;

        return max($minimum, min($maximum, $value));
    }

    /**
     * HR: Zadržava samo tekstualne konfiguracijske ključeve.
     * EN: Keeps only string configuration keys.
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
