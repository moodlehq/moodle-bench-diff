<?php
namespace App\Service;

use App\Model\Dataset;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class Comparisons
{
    const int LOWER_IS_BETTER = -1;
    const int HIGHER_IS_BETTER = 1;

    const array SCENARIO_THRESHOLDS = [
        'dbreads' => 2,
        'dbwrites' => 2,
        'dbquerytime' => 2,
        'memoryused' => 2,
        'filesincluded' => 1,
        'serverload' => 10,
        'sessionsize' => 2,
        'timeused' => 5,
    ];

    const array TOTAL_THRESHOLDS = [
        'dbreads' => 2,
        'dbwrites' => 2,
        'dbquerytime' => 2,
        'memoryused' => 2,
        'filesincluded' => 1,
        'serverload' => 10,
        'sessionsize' => 2,
        'timeused' => 5,
    ];

    public function getComparisonKeys(): array
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

    public function getIgnoredKeys(): array
    {
        return [
            'dbquerytime' => true,
            'timeused' => true,
            'time' => true,
            'latency' => true,
            'bytes' => true,
        ];
    }

    public function exceedsScenarioThreshold(
        string $key,
        float $before,
        float $after,
    ): bool {
        if (!array_key_exists($key, self::SCENARIO_THRESHOLDS)) {
            return false;
        }

        return abs($before - $after) > self::SCENARIO_THRESHOLDS[$key];
    }

    public function exceedsTotalThreshold(
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

//# thresholds='{"bystep":{"dbreads":2,"dbwrites":2,"dbquerytime":2,"memoryused":2,"filesincluded":1,"serverload":10,"sessionsize":2,"timeused":5},"total":{"dbreads":2,"dbwrites":2,"dbquerytime":2,"memoryused":2,"filesincluded":1,"serverload":10,"sessionsize":2,"timeused":5}}'
