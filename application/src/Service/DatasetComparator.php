<?php

namespace App\Service;

use App\Model\ComparisonResult;
use App\Model\Dataset;
use App\Model\Result;
use App\Model\Scenario;
use Psr\Log\LoggerInterface;

class DatasetComparator implements DatasetComparatorInterface
{

    public const int LOWER_IS_BETTER = -1;
    public const int HIGHER_IS_BETTER = 1;

    public const array SCENARIO_THRESHOLDS = [
        'dbreads' => 2,
        'dbwrites' => 2,
        'dbquerytime' => 2,
        'memoryused' => 2,
        'filesincluded' => 1,
        'serverload' => 10,
        'sessionsize' => 2,
        'timeused' => 5,
    ];

    public const array TOTAL_THRESHOLDS = [
        'dbreads' => 2,
        'dbwrites' => 2,
        'dbquerytime' => 2,
        'memoryused' => 2,
        'filesincluded' => 1,
        'serverload' => 10,
        'sessionsize' => 2,
        'timeused' => 5,
    ];

    /**
     * Compare the two datasets.
     *
     * @param  \App\Model\Dataset $dataset1
     * @param  \App\Model\Dataset $dataset2
     * @return \App\Model\ComparisonResult
     */
    public function compare(
        Dataset $dataset1,
        Dataset $dataset2,
    ): ComparisonResult {
        $comparisonKeys = self::getAllKeys();

        $result = new ComparisonResult();
        foreach ($dataset1->getScenarios() as $scenario) {
            foreach ($comparisonKeys as $key => $direction) {
                $this->_compareTotals(
                    $scenario,
                    $dataset2->getScenario($scenario->name),
                    $key,
                    $direction,
                    $result,
                );

                $this->_compareAverages(
                    $scenario,
                    $dataset2->getScenario($scenario->name),
                    $key,
                    $direction,
                    $result,
                );
            }
        }

        return $result;
    }

    /**
     * Compare the totals of the two scenarios.
     *
     * This method takes the two scenarios and compares the value obtained
     * by adding all results for that scenario and key combination.
     *
     * @param \App\Model\Scenario $scenario1 The Before scenario
     * @param \App\Model\Scenario $scenario2 The After scenario
     * @param string $key The key to compare
     * @param int $direction The expected direction of the comparison (which is better)
     * @param \App\Model\ComparisonResult $resultSet The result set to add the comparison to
     *
     * @return void
     */
    private function _compareTotals(
        Scenario $scenario1,
        Scenario $scenario2,
        string $key,
        int $direction,
        ComparisonResult $resultSet,
    ): void {
        $total1 = $scenario1->getTotal($key);
        $total2 = $scenario2->getTotal($key);
        if ($total1 === $total2) {
            $resultSet->addResult(
                Result::createSuccess(
                    $scenario1,
                    $key,
                    'total',
                    "{$total1} === {$total2} (no change)",
                    $total1,
                    $total2,
                ),
            );
        } else {
            $comparison = $total1 <=> $total2;

            if ($comparison === $direction) {
                $resultSet->addResult(
                    Result::createSuccess(
                        $scenario1,
                        $key,
                        'total',
                        "{$total1} better than {$total2} (improved)",
                        $total1,
                        $total2,
                    ),
                );
            } else {
                if (self::isKeyIgnored($key)) {
                    $resultSet->addResult(
                        Result::createSuccess(
                            $scenario1,
                            $key,
                        'total',
                            "{$total1} worse than {$total2} (ignored)",
                            $total1,
                            $total2,
                        ),
                    );
                } elseif (self::exceedsTotalThreshold($key, $total1, $total2)) {
                    $resultSet->addResult(
                        Result::createFailure(
                            $scenario1,
                            $key,
                            'total',
                            "{$total1} worse than {$total2} (exceeded threshold)",
                            $total1,
                            $total2,
                        ),
                    );
                } else {
                    $resultSet->addResult(
                        Result::createSuccess(
                            $scenario1,
                            $key,
                            'total',
                            "{$total1} marginally worse than {$total2} (regressed)",
                            $total1,
                            $total2,
                        ),
                    );
                }
            }
        }
    }

    /**
     * Compare the average of the two scenarios.
     *
     * This method takes the two scenarios and compares the value obtained
     * by adding all results and dividing by the number of results
     * for that scenario and key combination.
     *
     * @param \App\Model\Scenario $scenario1 The Before scenario
     * @param \App\Model\Scenario $scenario2 The After scenario
     * @param string $key The key to compare
     * @param int $direction The expected direction of the comparison (which is better)
     * @param \App\Model\ComparisonResult $resultSet The result set to add the comparison to
     *
     * @return void
     */
    private function _compareAverages(
        Scenario $scenario1,
        Scenario $scenario2,
        string $key,
        int $direction,
        ComparisonResult $resultSet,
    ): void {
        $average1 = $scenario1->getAverage($key);
        $average2 = $scenario2->getAverage($key);
        if ($average1 === $average2) {
            $resultSet->addResult(
                Result::createSuccess(
                    $scenario1,
                    $key,
                    'average',
                    "{$average1} === {$average2} (no change)",
                    $average1,
                    $average2,
                ),
            );
        } else {
            $comparison = $average1 <=> $average2;

            if ($comparison === $direction) {
                $resultSet->addResult(
                    Result::createSuccess(
                        $scenario1,
                        $key,
                        'average',
                        "{$average1} better than {$average2} (improved)",
                        $average1,
                        $average2,
                    ),
                );
            } else {
                if (self::isKeyIgnored($key)) {
                    $resultSet->addResult(
                        Result::createSuccess(
                            $scenario1,
                            $key,
                        'average',
                            "{$average1} worse than {$average2} (ignored)",
                            $average1,
                            $average2,
                        ),
                    );
                } elseif (self::exceedsScenarioThreshold($key, $average1, $average2)) {
                    $resultSet->addResult(
                        Result::createFailure(
                            $scenario1,
                            $key,
                            'average',
                            "{$average1} worse than {$average2} (exceeded threshold)",
                            $average1,
                            $average2,
                        ),
                    );
                } else {
                    $resultSet->addResult(
                        Result::createSuccess(
                            $scenario1,
                            $key,
                            'average',
                            "{$average1} marginally worse than {$average2} (regressed)",
                            $average1,
                            $average2,
                        ),
                    );
                }
            }
        }
    }

    /**
     * @return array<string, int>
     */
    public static function getAllKeys(): array
    {
        return [
            'dbreads' => self::LOWER_IS_BETTER,
            'dbwrites' => self::LOWER_IS_BETTER,
            'dbquerytime' => self::LOWER_IS_BETTER,
            'memoryused' => self::LOWER_IS_BETTER,
            'filesincluded' => self::LOWER_IS_BETTER,
            'serverload' => self::LOWER_IS_BETTER,
            'sessionsize' => self::LOWER_IS_BETTER,
            'timeused' => self::LOWER_IS_BETTER,
            'bytes' => self::LOWER_IS_BETTER,
            'time' => self::LOWER_IS_BETTER,
            'latency' => self::LOWER_IS_BETTER,
        ];
    }

    /**
     * @return array<string>
     */
    public static function getIgnoredKeys(): array
    {
        return [
            'dbquerytime',
            'timeused',
            'time',
            'latency',
            'bytes',
        ];
    }

    public static function isKeyIgnored(string $key): bool
    {
        return isset(self::getIgnoredKeys()[$key]);
    }

    /**
     * @return array<string, int>
     */
    public static function getComparedKeys(): array
    {
        return array_filter(
            self::getAllKeys(),
            fn($key): bool => !isset(self::getIgnoredKeys()[$key]),
            ARRAY_FILTER_USE_KEY,
        );
    }

    public static function exceedsScenarioThreshold(
        string $key,
        float $before,
        float $after,
    ): bool {
        if (!array_key_exists($key, self::SCENARIO_THRESHOLDS)) {
            return false;
        }

        return abs($before - $after) > self::SCENARIO_THRESHOLDS[$key];
    }

    public static function exceedsTotalThreshold(
        string $key,
        float $before,
        float $after,
    ): bool {
        if (!array_key_exists($key, self::TOTAL_THRESHOLDS)) {
            return false;
        }

        return abs($before - $after) > self::TOTAL_THRESHOLDS[$key];
    }

}
