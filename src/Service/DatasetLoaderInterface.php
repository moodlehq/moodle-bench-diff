<?php

namespace App\Service;

use App\Model\Dataset;

interface DatasetLoaderInterface
{
    /**
     * Confirm whether a dataset exists.
     *
     * @param string $name
     * @return bool
     */
    public function datasetExists(
        string $name,
    ): bool;

    /**
     * Load a dataset.
     *
     * @param string $name
     * @return \App\Model\Dataset
     */
    public function loadFullDataset(
        string $name,
    ): Dataset;
}
