<?php

namespace App\Model;

class Result
{

    const COMPARISON_SUCCESS = 'success';
    const COMPARISON_FAILURE = 'failure';

    public function __construct(
        public readonly Scenario $scenario,
        public readonly string $key,
        public readonly string $comparisonType,
        public readonly string $description,
        public readonly string $status,
        public readonly float $valueA,
        public readonly float $valueB,
    ) {
    }

    public static function createSuccess(
        Scenario $scenario,
        string $key,
        string $description,
        string $comparison,
        float $valueA,
        float $valueB,
    ): self {
        return new self(
            $scenario,
            $key,
            $description,
            $comparison,
            self::COMPARISON_SUCCESS,
            $valueA,
            $valueB,
        );
    }

    public static function createFailure(
        Scenario $scenario,
        string $key,
        string $description,
        string $comparison,
        float $valueA,
        float $valueB,
    ): self {
        return new self(
            $scenario,
            $key,
            $description,
            $comparison,
            self::COMPARISON_FAILURE,
            $valueA,
            $valueB,
        );
    }

    public function isFailed(): bool
    {
        return $this->status === self::COMPARISON_FAILURE;
    }

    public function getSummary(): string
    {
        return "{$this->scenario->name} - {$this->key}: {$this->description}";
    }
}
