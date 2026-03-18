<?php

namespace App\Model;

class ComparisonResult
{
    /** @var array<Result> */
    public array $results = [];

    public function addResult(
        Result $result,
    ): void {
        $this->results[] = $result;
    }

    public function getResultSummary(): array
    {
        $summaries = [];
        foreach ($this->results as $result) {
            $summaries[] = $result->getSummary();
        }
        return $summaries;
    }

    public function isSuccessful(): bool
    {
        foreach ($this->results as $result) {
            if ($result->isFailed()) {
                return false;
            }
        }

        return true;
    }

    public function isFailed(): bool
    {
        foreach ($this->results as $result) {
            if ($result->isFailed()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<\App\Model\Result>
     */
    public function getFailures(): array
    {
        return array_filter($this->results, fn($result): bool => $result->isFailed());
    }

    /**
     * @return array<\App\Model\Result>
     */
    public function getResults(): array
    {
        $sorted = $this->results;

        // Sort by key first, then by comparison type (total vs average) for stable output.
        usort(
            $sorted,
            fn($a, $b): int => ($a->key <=> $b->key) ?: ($a->comparisonType <=> $b->comparisonType),
        );

        return $sorted;
    }
}
