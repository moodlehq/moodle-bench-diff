<?php

namespace App\Service;

use App\Model\Dataset;

/**
 * Loads a dataset from an explicit filesystem path.
 *
 * FilebasedDatasetLoader resolves a dataset *name* under app.datasets_path and
 * rejects anything path-like, which is right for the web UI. This loader takes
 * the path as given, so the CLI can compare two arbitrary run files without
 * needing S3 credentials or a fixed datasets directory.
 */
class LocalPathDatasetLoader implements DatasetLoaderInterface
{
    public function datasetExists(
        string $name,
    ): bool {
        return is_file($name) && is_readable($name);
    }

    public function loadFullDataset(
        string $name,
    ): Dataset {
        if (!is_file($name)) {
            throw new \InvalidArgumentException("Dataset file not found: {$name}");
        }

        if (!is_readable($name)) {
            throw new \RuntimeException("Dataset file is not readable: {$name}");
        }

        $contents = file_get_contents($name);
        if ($contents === false) {
            throw new \RuntimeException("Could not read dataset file: {$name}");
        }

        $data = json_decode($contents);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(sprintf(
                'Dataset file %s is not valid JSON: %s',
                $name,
                json_last_error_msg(),
            ));
        }

        // runTime is not part of the result JSON. S3DatasetLoader takes it from
        // the object's LastModified; the local equivalent is the file mtime.
        if (!isset($data->runTime)) {
            $data->runTime = (new \DateTimeImmutable())->setTimestamp(filemtime($name));
        }

        return Dataset::loadFullDataset($name, $data);
    }
}
