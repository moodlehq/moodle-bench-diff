<?php
namespace App\Service;

use App\Model\Dataset;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class Comparisons
{
    const int LOWER_IS_BETTER = -1;
    const int HIGHER_IS_BETTER = 1;

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
        ];
    }
}
