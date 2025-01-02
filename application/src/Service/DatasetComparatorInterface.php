<?php

namespace App\Service;

use App\Model\Dataset;

interface DatasetComparatorInterface
{
    const int COMPARISON_SUCCESS = 0;

    const int COMPARISON_FAILURE = 1;

    public function compare(
        Dataset $dataset1,
        Dataset $dataset2,
    );
}
