<?php
namespace App\Service;

use App\Model\Dataset;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class S3DatasetLoader implements
    CachingDatasetLoaderInterface,
    DatasetLoaderInterface
{
    public function __construct(
        private \Aws\S3\S3Client $s3Client,
        private ContainerBagInterface $params,
    ) {
    }

    private function validateDatasetName(
        string $name,
    ): void {
        if (str_contains($name, '..')) {
            throw new \InvalidArgumentException('Invalid dataset name');
        }
    }

    private function getDatasetPath(
        string $name,
    ): string {
        $this->validateDatasetName($name);

        if (str_ends_with($name, '.json')) {
            $filename = $name;
        } else {
            $filename = sprintf('%s.json', $name);
        }

        return $this->params->get('app.s3_dataset_path') . '/' . $filename;
    }

    public function datasetExists(
        string $name,
    ): bool {
        $path = $this->getDatasetPath($name);

        try {
            $this->s3Client->headObject([
                'Key' => $path,
                'Bucket' => $this->params->get('app.s3_bucket'),
            ]);
        } catch (\Aws\S3\Exception\S3Exception $e) {
            return false;
        }

        return true;
    }

    public function loadFullDataset(
        string $name,
        bool $pathIsResolved = false,
    ): Dataset {
        if ($pathIsResolved) {
            $path = $name;
        } else {
            $path = $this->getDatasetPath($name);
        }

        try {
            $datasetObject = $this->s3Client->getObject([
                'Key' => $path,
                'Bucket' => $this->params->get('app.s3_bucket'),
            ]);
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new \InvalidArgumentException('Dataset not accessible');
        }

        $data = json_decode($datasetObject->get('Body'));
        $data->runTime = \DateTimeImmutable::createFromMutable($datasetObject->get('LastModified'));

        return Dataset::loadFullDataset($name, $data);
    }

    public function listDatasets(
        string $matching,
    ): array {
        $continuationToken = null;
        $allResults = [];

        do {
            $results = $this->s3Client->listObjectsV2([
                'Prefix' => $this->params->get('app.s3_dataset_path') . '/' . $matching,
                'Bucket' => $this->params->get('app.s3_bucket'),
                'ContinuationToken' => $continuationToken,
            ]);

            if ($results->get('KeyCount') === 0) {
                return [];
            }

            // array_push($allResults, ...array_map(
            //     fn ($object) => $object,
            //     $results->get('Contents'),
            // ));

            array_push($allResults, ...$results->get('Contents'));

            if ($results->get('isTruncated')) {
                $continuationToken = $results->get('NextContinuationToken');
            } else {
                $continuationToken = null;
            }
        } while ($continuationToken !== null);

        $filteredResults = array_filter(
            $allResults,
            fn (array $result): bool => str_ends_with($result['Key'], '.json'),
        );

        return $this->_loadDatasetSummaries($filteredResults);
    }

    /**
     * Summary of _loadDatasetSummaries
     * @param array<Key: string, LastModified: string> $datasetNames
     * @return array
     */
    private function _loadDatasetSummaries(
        array $datasetNames,
    ): array {
        $cache = new FilesystemAdapter('datasets');

        $loadedDatasets = array_map(
            function ($datasetReference) use ($cache): Dataset {
                $datasetName = $datasetReference['Key'];
                $cacheValue = $cache->getItem($this->_getCacheKeyforDataset($datasetName));
                if ($cacheValue->isHit()) {
                    $dataset = Dataset::fromCache(
                        $this,
                        $cacheValue->get(),
                    );
                } else {
                    $dataset = $this->loadFullDataset($datasetName, true);
                    $cacheValue->set($dataset->getCacheValue());
                    $cache->save($cacheValue);
                }

                return $dataset;
            },
            $datasetNames,
        );

        return $loadedDatasets;
    }

    private function _getCacheKeyForDataset(
        string $name,
    ): string {
        return sha1($name);
    }
}
