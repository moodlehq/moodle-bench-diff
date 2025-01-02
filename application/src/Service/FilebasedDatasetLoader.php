<?php
namespace App\Service;

use App\Model\Dataset;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class FilebasedDatasetLoader implements DatasetLoaderInterface
{
    public function __construct(
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

        return $this->params->get('app.datasets_path') . '/' . $filename;
    }

    public function datasetExists(
        string $name,
    ): bool {
        $path = $this->getDatasetPath($name);

        return file_exists($path) && is_readable($path);
    }

    public function loadFullDataset(
        string $name,
    ): Dataset {
        $path = $this->getDatasetPath($name);

        if (!file_exists($path)) {
            throw new \InvalidArgumentException('Dataset not found');
        }

        if (!is_readable($path)) {
            throw new \RuntimeException('Dataset is not readable');
        }

        $data = json_decode(file_get_contents($path));

        return Dataset::loadFullDataset($name, $data);
    }
}
