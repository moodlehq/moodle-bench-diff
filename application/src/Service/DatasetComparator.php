<?php

namespace App\Service;

use App\Model\ComparisonResult;
use App\Model\Dataset;
use Psr\Log\LoggerInterface;

class DatasetComparator implements DatasetComparatorInterface
{
    public function __construct(
        private Comparisons $comparisons,
        private LoggerInterface $logger,
    ) {
    }

    public function compare(
        Dataset $dataset1,
        Dataset $dataset2,
    ): ComparisonResult {
        $comparisonKeys = array_filter(
            $this->comparisons->getComparisonKeys(),
            fn($key): bool => !isset($this->comparisons->getIgnoredKeys()[$key]),
            ARRAY_FILTER_USE_KEY,
        );

        $result = new ComparisonResult();
        foreach ($dataset1->scenarios as $scenario) {
            foreach ($comparisonKeys as $key => $direction) {
                $average1 = $scenario->getAverage($key);
                $average2 = $dataset2->getScenario($scenario->name)->getAverage($key);

                if ($average1 === $average2) {
                    $result->addResult($scenario, $key, self::COMPARISON_SUCCESS);
                    $this->logger->debug("{$scenario->name} - $key: {$average1} === {$average2} (no change)");
                    continue;
                }

                $comparison = $average1 <=> $average2;

                if ($comparison === $direction) {
                    $this->logger->debug("{$scenario->name} - $key: {$average1} better than {$average2} (improved)");
                    $result->addResult($scenario, $key, self::COMPARISON_SUCCESS);
                } else {
                    if ($this->comparisons->exceedsScenarioThreshold($key, $average1, $average2)) {
                        $this->logger->error("{$scenario->name} - $key: {$average1} worse than {$average2} (exceeded threshold)");
                        $result->addResult($scenario, $key, self::COMPARISON_FAILURE);
                    } else {
                        $this->logger->debug("{$scenario->name} - $key: {$average1} marginally worse than {$average2} (regressed)");
                        $result->addResult($scenario, $key, self::COMPARISON_SUCCESS);
                    }
                }
            }
        }

        return $result;
    }
}
