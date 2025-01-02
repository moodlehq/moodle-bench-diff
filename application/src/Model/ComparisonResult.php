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
        foreach ($this->results as $result) {
        }
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
        // Sort by name.
        // usort(
        //     $this->results,
        //     fn($a, $b): int => $a->scenario->name <=> $b->scenario->name,
        // );

        // Then sort by comparison type.
        usort(
            $this->results,
            fn($a, $b): int => $a->key <=> $b->key,
        );

        return $this->results;
    }
}
