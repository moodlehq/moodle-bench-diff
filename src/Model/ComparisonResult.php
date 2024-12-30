<?php

namespace App\Model;

use App\Service\DatasetComparatorInterface;
use stdClass;

class ComparisonResult
{
    public private(set) array $results;

    public function addResult(
        Scenario $scenario,
        string $key,
        int $result,
    ): void {
        $this->results[] = [
            'scenario' => $scenario,
            'key' => $key,
            'result' => $result,
        ];
    }

    public function getResultSummary(): array
    {
        foreach ($this->results as $result) {
        }
    }

    public function isSuccessful(): bool
    {
        foreach ($this->results as $result) {
            if ($result['result'] !== DatasetComparatorInterface::COMPARISON_SUCCESS) {
                return false;
            }
        }

        return true;
    }

    public function isFailed(): bool
    {
        foreach ($this->results as $result) {
            if ($result['result'] === DatasetComparatorInterface::COMPARISON_FAILURE) {
                return true;
            }
        }

        return false;
    }
}
