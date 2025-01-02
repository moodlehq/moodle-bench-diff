<?php

namespace App\Service;

use App\Model\Dataset;

interface DatasetComparatorInterface
{
    public function compare(
        Dataset $dataset1,
        Dataset $dataset2,
    );
}
