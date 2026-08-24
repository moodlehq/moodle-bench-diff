<?php

namespace App\Tests\Service;

use App\Model\Dataset;
use App\Model\Result;
use App\Service\DatasetComparator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatasetComparator::class)]
class DatasetComparatorTest extends TestCase
{
    /**
     * Build a dataset containing a single scenario with one sample per value.
     *
     * @param string $key The metric key to populate.
     * @param array<float> $values One value per loop.
     */
    private function makeDataset(
        string $name,
        string $key,
        array $values,
        string $scenarioName = 'Frontpage logged',
    ): Dataset {
        // Real rundata carries every comparison key on every sample, and
        // Scenario::getAverage() divides by the number of samples that have the
        // key - so a fixture missing one would divide by zero. Fill them all
        // with a constant, then override the key under test.
        $defaults = array_fill_keys(array_keys(DatasetComparator::getAllKeys()), 1);

        $samples = array_map(
            fn($value): object => (object) array_merge(
                $defaults,
                ['name' => $scenarioName, $key => $value],
            ),
            $values,
        );

        return Dataset::loadFullDataset($name, (object) [
            'host' => 'localhost',
            'sitepath' => '',
            'group' => '503',
            'rundesc' => $name,
            'users' => '1',
            'loopcount' => (string) count($values),
            'rampup' => '1',
            'throughput' => '120',
            'size' => 'XS',
            'baseversion' => '2026080700.01',
            'siteversion' => '503',
            'sitebranch' => '503',
            'sitecommit' => 'abc123',
            'runTime' => new \DateTimeImmutable(),
            // A single thread containing every sample.
            'results' => [$samples],
        ]);
    }

    /**
     * Find the result for a given key and comparison type.
     */
    private function findResult(
        \App\Model\ComparisonResult $comparison,
        string $key,
        string $comparisonType,
    ): Result {
        foreach ($comparison->getResults() as $result) {
            if ($result->key === $key && $result->comparisonType === $comparisonType) {
                return $result;
            }
        }

        $this->fail("No {$comparisonType} result found for key {$key}");
    }

    /**
     * The regression this suite exists for.
     *
     * The comparison direction was inverted, so a run that got worse was
     * reported as "(improved)" and passed the build, while a run that got
     * better failed it. Both directions are asserted so that neither can
     * regress on its own.
     */
    public function testAfterGettingWorseIsAFailure(): void
    {
        $before = $this->makeDataset('before', 'dbreads', [45, 45, 45, 45, 45]);
        $after = $this->makeDataset('after', 'dbreads', [55, 55, 55, 55, 55]);

        $comparison = (new DatasetComparator())->compare($before, $after);

        $this->assertTrue($comparison->isFailed(), 'A worse "after" must fail the comparison');

        $average = $this->findResult($comparison, 'dbreads', 'average');
        $this->assertTrue($average->isFailed());
        $this->assertStringContainsString('55 worse than 45', $average->description);
    }

    public function testAfterGettingBetterIsAnImprovement(): void
    {
        $before = $this->makeDataset('before', 'dbreads', [55, 55, 55, 55, 55]);
        $after = $this->makeDataset('after', 'dbreads', [45, 45, 45, 45, 45]);

        $comparison = (new DatasetComparator())->compare($before, $after);

        $this->assertTrue($comparison->isSuccessful(), 'A better "after" must not fail the comparison');

        $average = $this->findResult($comparison, 'dbreads', 'average');
        $this->assertFalse($average->isFailed());
        $this->assertStringContainsString('45 better than 55', $average->description);
    }

    /**
     * The exact shape of Jenkins build #160: MDL-88287 made the Dashboard
     * cheaper and the job failed the build for it.
     */
    public function testRealWorldImprovementDoesNotFailTheBuild(): void
    {
        $before = $this->makeDataset('moodle.git', 'filesincluded', [1121, 1121, 1121, 1121, 1121]);
        $after = $this->makeDataset('integration.git', 'filesincluded', [1081, 1081, 1081, 1081, 1081]);

        $comparison = (new DatasetComparator())->compare($before, $after);

        $this->assertTrue($comparison->isSuccessful());
        $this->assertSame([], $comparison->getFailures());
    }

    /**
     * Before and after values must stay in argument order regardless of which
     * one is worse, because the report renders them as Before/After columns.
     */
    public function testValuesArePresentedInBeforeAfterOrder(): void
    {
        $before = $this->makeDataset('before', 'dbreads', [45]);
        $after = $this->makeDataset('after', 'dbreads', [55]);

        $comparison = (new DatasetComparator())->compare($before, $after);
        $average = $this->findResult($comparison, 'dbreads', 'average');

        $this->assertSame(45.0, $average->valueA, 'valueA must be the before value');
        $this->assertSame(55.0, $average->valueB, 'valueB must be the after value');
    }

    public function testIdenticalValuesAreNoChange(): void
    {
        $before = $this->makeDataset('before', 'dbreads', [45, 45]);
        $after = $this->makeDataset('after', 'dbreads', [45, 45]);

        $comparison = (new DatasetComparator())->compare($before, $after);

        $this->assertTrue($comparison->isSuccessful());
        $this->assertStringContainsString(
            'no change',
            $this->findResult($comparison, 'dbreads', 'average')->description,
        );
    }

    /**
     * A regression inside the threshold is still a regression, but must not
     * break the build - that is what absorbs run-to-run noise.
     */
    public function testRegressionWithinThresholdDoesNotFail(): void
    {
        // SCENARIO_THRESHOLDS['dbreads'] is 2, so +1 is under it.
        $before = $this->makeDataset('before', 'dbreads', [45]);
        $after = $this->makeDataset('after', 'dbreads', [46]);

        $comparison = (new DatasetComparator())->compare($before, $after);
        $average = $this->findResult($comparison, 'dbreads', 'average');

        $this->assertFalse($average->isFailed());
        $this->assertStringContainsString('marginally worse', $average->description);
    }

    /**
     * serverload is the CI worker's load average, not the code under test.
     * It must never fail a build however far it swings.
     */
    public function testIgnoredKeysNeverFail(): void
    {
        $before = $this->makeDataset('before', 'serverload', [4]);
        $after = $this->makeDataset('after', 'serverload', [62]);

        $comparison = (new DatasetComparator())->compare($before, $after);
        $average = $this->findResult($comparison, 'serverload', 'average');

        $this->assertFalse($average->isFailed());
        $this->assertStringContainsString('ignored', $average->description);
        $this->assertTrue($comparison->isSuccessful());
    }

    #[DataProvider('ignoredKeyProvider')]
    public function testEveryIgnoredKeyIsActuallyIgnored(string $key): void
    {
        $this->assertTrue(
            DatasetComparator::isKeyIgnored($key),
            "{$key} is listed as ignored but isKeyIgnored() disagrees",
        );

        $before = $this->makeDataset('before', $key, [10]);
        $after = $this->makeDataset('after', $key, [10000]);

        $this->assertTrue((new DatasetComparator())->compare($before, $after)->isSuccessful());
    }

    /**
     * @return array<array{string}>
     */
    public static function ignoredKeyProvider(): array
    {
        return array_map(
            fn($key): array => [$key],
            DatasetComparator::getIgnoredKeys(),
        );
    }

    /**
     * Totals and averages are compared by separate code paths, so the
     * direction has to be asserted on both.
     */
    public function testTotalsAreComparedInTheSameDirectionAsAverages(): void
    {
        $before = $this->makeDataset('before', 'filesincluded', [470, 470, 470, 470, 470]);
        $after = $this->makeDataset('after', 'filesincluded', [576, 576, 576, 576, 576]);

        $comparison = (new DatasetComparator())->compare($before, $after);

        $total = $this->findResult($comparison, 'filesincluded', 'total');
        $average = $this->findResult($comparison, 'filesincluded', 'average');

        $this->assertTrue($total->isFailed(), 'total must fail when the after is worse');
        $this->assertTrue($average->isFailed(), 'average must fail when the after is worse');
        $this->assertStringContainsString('2880 worse than 2350', $total->description);
        $this->assertStringContainsString('576 worse than 470', $average->description);
    }
}
