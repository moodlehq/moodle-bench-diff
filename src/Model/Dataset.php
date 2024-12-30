<?php

namespace App\Model;

use stdClass;

class Dataset
{
    public private(set) array $scenarios = [];

    public function __construct(
        public private(set) string $name,
        public private(set) string $host,
        public private(set) string $sitepath,
        public private(set) string $group,
        public private(set) string $rundesc,
        public private(set) string $users,
        public private(set) string $loopcount,
        public private(set) string $rampup,
        public private(set) string $throughput,
        public private(set) string $size,
        public private(set) string $baseversion,
        public private(set) string $siteversion,
        public private(set) string $sitebranch,
        public private(set) string $sitecommit,
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
        );

        $dataset->addResults($data->results);

        return $dataset;
    }

    public function addResults(
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

    public function getScenario(string $scenarioName): Scenario
    {
        $scenarioName = Scenario::normalizeName($scenarioName);

        if (!array_key_exists($scenarioName, $this->scenarios)) {
            $this->scenarios[$scenarioName] = new Scenario($scenarioName);
        }

        return $this->scenarios[$scenarioName];
    }
}
