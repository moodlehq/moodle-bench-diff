<?php

namespace App\Service;

use App\Model\Dataset;

class DatasetFilter
{
    public function filterDatasets(
        array $datasets,
        ?string $group = null,
        ?string $version = null,
        ?string $size = null,
        ?string $baseversion = null,
        ?string $sitepath = null,
        ?string $rundesc = null,
        ?string $users = null,
        ?string $loopcount = null,
        ?string $rampup = null,
        ?string $throughput = null,
        ?string $siteversion = null,
        ?string $sitebranch = null,
    ): array {
        $filters = [
            'group' => $group,
            'baseVersion' => $version,
            'size' => $size,
            'siteVersion' => $siteversion,
            'sitePath' => $sitepath,
            'runDescription' => $rundesc,
            'users' => $users,
            'loopCount' => $loopcount,
            'rampup' => $rampup,
            'throughput' => $throughput,
            'branch' => $sitebranch,
        ];

        foreach ($filters as $filter => $value) {
            if ($value === null) {
                continue;
            }

            $datasets = array_filter($datasets, function (Dataset $dataset) use ($filter, $value): bool {
                $getter = 'get' . ucfirst($filter);
                return $dataset->$getter() === $value;
            });
        }

        return $datasets;
    }
}
