<?php

namespace App\Model;

use App\Service\DatasetLoaderInterface;
use stdClass;

class Dataset
{
    /** @var array<Scenario> The list of Scenarios */
    protected array $scenarios = [];

    /** @var bool Whether the full result set has been loaded */
    private bool $_resultsLoaded = false;

    /** @var DatasetLoaderInterface The loader used to load the result set if loaded from cache */
    private ?DatasetLoaderInterface $_loader = null;

    /** @var stdClass|null The cache data */
    private ?stdClass $_cacheData = null;

    public function __construct(
        public readonly string $name,
        public readonly string $host,
        public readonly string $sitepath,
        public readonly string $group,
        public readonly string $rundesc,
        public readonly string $users,
        public readonly string $loopcount,
        public readonly string $rampup,
        public readonly string $throughput,
        public readonly string $size,
        public readonly string $baseversion,
        public readonly string $siteversion,
        public readonly string $sitebranch,
        public readonly string $sitecommit,
        public readonly \DateTimeImmutable $runTime,
    ) {
    }

    public static function loadFullDataset(
        string $name,
        stdClass $data,
    ): self {
        $dataset = new self(
            $name,
            $data->host,
            $data->sitepath,
            $data->group,
            $data->rundesc,
            $data->users,
            $data->loopcount,
            $data->rampup,
            $data->throughput,
            $data->size,
            $data->baseversion,
            $data->siteversion,
            $data->sitebranch,
            $data->sitecommit,
            $data->runTime,
        );

        $dataset->_resultsLoaded = true;
        $dataset->_addResults($data->results);

        return $dataset;
    }

    public static function fromCache(
        DatasetLoaderInterface $loader,
        stdClass $cacheData,
    ): self {
        $dataset = new self(
            $cacheData->name,
            $cacheData->data->host,
            $cacheData->data->sitepath,
            $cacheData->data->group,
            $cacheData->data->rundesc,
            $cacheData->data->users,
            $cacheData->data->loopcount,
            $cacheData->data->rampup,
            $cacheData->data->throughput,
            $cacheData->data->size,
            $cacheData->data->baseversion,
            $cacheData->data->siteversion,
            $cacheData->data->sitebranch,
            $cacheData->data->sitecommit,
            $cacheData->data->runTime,
        );

        $dataset->_setDatasetLoader($loader);

        return $dataset;
    }

    public function getScenarios(): array
    {
        $this->_loadFullDataset();
        return $this->scenarios;
    }

    public function getScenario(string $scenarioName): Scenario
    {
        $scenarioName = Scenario::normalizeName($scenarioName);

        if (!array_key_exists($scenarioName, $this->getScenarios())) {
            $this->scenarios[$scenarioName] = new Scenario($scenarioName);
        }

        return $this->scenarios[$scenarioName];
    }

    public function getCacheValue(): stdClass {
        if ($this->_cacheData === null) {
            $this->_cacheData = (object) [
                'name' => $this->name,
                'data' => (object) [
                    'host' => $this->host,
                    'sitepath' => $this->sitepath,
                    'group' => $this->group,
                    'rundesc' => $this->rundesc,
                    'users' => $this->users,
                    'loopcount' => $this->loopcount,
                    'rampup' => $this->rampup,
                    'throughput' => $this->throughput,
                    'size' => $this->size,
                    'baseversion' => $this->baseversion,
                    'siteversion' => $this->siteversion,
                    'sitebranch' => $this->sitebranch,
                    'sitecommit' => $this->sitecommit,
                    'runTime' => $this->runTime,
                ],
            ];
        }
        return $this->_cacheData;
    }

    public function getTitle(): string {
        return sprintf(
            '%s %s: Moodle %s (%s) (%s users * %s loops, rampup=%s throughput=%s)',
            $this->runTime->format('Y-m-d H:i:s'), // @phpstan-ignore-line
            $this->size,
            $this->sitebranch,
            $this->sitecommit,
            $this->users,
            $this->loopcount,
            $this->rampup,
            $this->throughput,
        );
    }

    public function getShortTitle(): string {
        return sprintf(
            '%s (%s)',
            $this->sitebranch,
            substr($this->sitecommit, 0, 12),
        );
    }

    public function getSitepath(): string {
        return $this->sitepath;
    }

    public function getGroup(): string {
        return $this->group;
    }

    public function getRunDescription(): string {
        return $this->rundesc;
    }

    public function getUsers(): string {
        return $this->users;
    }

    public function getLoopCount(): string {
        return $this->loopcount;
    }

    public function getRampup(): string {
        return $this->rampup;
    }

    public function getThroughput(): string {
        return $this->throughput;
    }

    public function getSize(): string {
        return $this->size;
    }

    public function getBaseVersion(): string {
        return $this->baseversion;
    }

    public function getSiteVersion(): string {
        return $this->siteversion;
    }

    public function getBranch(): string {
        return $this->sitebranch;
    }

    public function getSiteCommit(): string {
        return $this->sitecommit;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getRunTime(): \DateTimeImmutable {
        return $this->runTime;
    }

    private function _addResults(
        array $results,
    ): void {
        // Data is organised as a set of threads with each thread having a set of results.
        foreach ($results as $thread) {
            foreach ($thread as $result) {
                $scenario = $this->getScenario($result->name);
                $scenario->addData((array) $result);
            }
        }
    }

    private function _setDatasetLoader(
        DatasetLoaderInterface $loader,
    ): void {
        $this->_loader = $loader;
    }

    private function _loadFullDataset(): void {
        if ($this->_resultsLoaded) {
            return;
        }
        if ($this->_loader === null) {
            throw new \RuntimeException('Cannot load results without a loader');
        }

        $fullDataset = $this->_loader->loadFullDataset($this->name, true);
        $this->scenarios = $fullDataset->scenarios;
        
        $this->_resultsLoaded = true;
    }
}
