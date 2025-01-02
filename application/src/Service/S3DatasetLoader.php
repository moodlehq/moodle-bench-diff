<?php
namespace App\Service;

use App\Model\Dataset;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class S3DatasetLoader implements DatasetLoaderInterface
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
        $filename = sprintf('%s.json', $name);

        return $this->params->get('app.s3_dataset_path') . '/' . $filename;
    }

    public function datasetExists(
        string $name,
    ): bool {
        $path = $this->getDatasetPath($name);

        try {
            $datasetObject = $this->s3Client->headObject([
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
    ): Dataset {
        $path = $this->getDatasetPath($name);

        try {
            $datasetObject = $this->s3Client->getObject([
                'Key' => $path,
                'Bucket' => $this->params->get('app.s3_bucket'),
            ]);
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new \InvalidArgumentException('Dataset not accessible');
        }

        $data = json_decode($datasetObject->get('Body'));

        return Dataset::loadFullDataset($name, $data);
    }
}
