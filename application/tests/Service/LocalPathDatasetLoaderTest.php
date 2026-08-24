<?php

namespace App\Tests\Service;

use App\Service\LocalPathDatasetLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocalPathDatasetLoader::class)]
class LocalPathDatasetLoaderTest extends TestCase
{
    /** @var array<string> */
    private array $tempfiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempfiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->tempfiles = [];
    }

    private function writeTempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'benchdiff');
        file_put_contents($path, $contents);
        $this->tempfiles[] = $path;

        return $path;
    }

    private function validDatasetJson(): string
    {
        return json_encode([
            'host' => 'localhost',
            'sitepath' => '',
            'group' => '503',
            'rundesc' => 'test run',
            'users' => '1',
            'loopcount' => '5',
            'rampup' => '1',
            'throughput' => '120',
            'size' => 'XS',
            'baseversion' => '2026080700.01',
            'siteversion' => '503',
            'sitebranch' => '503',
            'sitecommit' => 'abc123',
            'results' => [
                [
                    ['name' => 'Frontpage logged', 'dbreads' => 45],
                ],
            ],
        ]);
    }

    public function testLoadsADatasetFromAnAbsolutePath(): void
    {
        $path = $this->writeTempFile($this->validDatasetJson());

        $dataset = (new LocalPathDatasetLoader())->loadFullDataset($path);

        $this->assertSame('503', $dataset->sitebranch);
        $this->assertSame(45.0, $dataset->getScenario('Frontpage logged')->getAverage('dbreads'));
    }

    /**
     * runTime is not part of the results JSON - the S3 loader takes it from the
     * object's LastModified, and this loader must supply the file mtime instead.
     * Without it, loading fatals on an undefined property.
     */
    public function testRunTimeIsDerivedFromTheFileWhenAbsent(): void
    {
        $path = $this->writeTempFile($this->validDatasetJson());
        touch($path, 1700000000);
        clearstatcache();

        $dataset = (new LocalPathDatasetLoader())->loadFullDataset($path);

        $this->assertSame(1700000000, $dataset->runTime->getTimestamp());
    }

    public function testDatasetExistsReflectsTheFilesystem(): void
    {
        $loader = new LocalPathDatasetLoader();
        $path = $this->writeTempFile($this->validDatasetJson());

        $this->assertTrue($loader->datasetExists($path));
        $this->assertFalse($loader->datasetExists($path . '.nope'));
    }

    public function testMissingFileThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Dataset file not found');

        (new LocalPathDatasetLoader())->loadFullDataset('/definitely/not/here.json');
    }

    public function testInvalidJsonThrowsRatherThanFatallingLater(): void
    {
        $path = $this->writeTempFile('{ this is not json');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not valid JSON');

        (new LocalPathDatasetLoader())->loadFullDataset($path);
    }
}
