<?php

namespace App\Model;

use stdClass;

class Dataset
{
    public array $scenarios = [];

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
