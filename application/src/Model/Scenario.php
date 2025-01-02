<?php

namespace App\Model;

use stdClass;

class Scenario
{
    public array $data;

    public function __construct(
        public readonly string $name,
    ) {
    }

    public static function normalizeName(
        string $name,
    ): string {
        return trim($name);
    }

    public function addData(
        array $data,
    ): void {
        $this->data[] = $data;
    }

    public function getAverage(
        string $key,
    ): float {
        $total = 0;
        $count = 0;

        foreach ($this->data as $data) {
            if (!isset($data[$key])) {
                continue;
            }

            if (!is_numeric($data[$key])) {
                throw new \InvalidArgumentException('Invalid data');
            }

            $total += $data[$key];
            $count++;
        }

        return $total / $count;
    }
}
