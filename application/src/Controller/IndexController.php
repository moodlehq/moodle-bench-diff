<?php

namespace App\Controller;

use App\Form\DatasetComparisonType;
use App\Form\DatasetFilterType;
use App\Service\Comparisons;
use App\Service\DatasetFilter;
use App\Service\DatasetLoaderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class IndexController extends AbstractController
{
    public function __construct(
        private DatasetLoaderInterface $datasetLoader,
        private DatasetFilter $datasetFilter,
        private ChartBuilderInterface $chartBuilder,
        private Comparisons $comparisons,
    ) {}

    #[Route('/', name: 'datasetList')]
    public function index(
        Request $request,
    ): Response {
        // Load all datasets.
        $datasets = $this->datasetLoader->listDatasets('');

        // Create the filter form.
        $datasetFilterForm = $this->createForm(
            DatasetFilterType::class,
            options: [
                'datasets' => $datasets,
            ],
        );

        // Apply the filter to the datasets.
        $datasetFilterForm->handleRequest($request);
        if ($datasetFilterForm->isSubmitted() && $datasetFilterForm->isValid()) {
            $data = $datasetFilterForm->getData();

            $filteredDatasets = $this->datasetFilter->filterDatasets(
                $datasets,
                group: $data['group'],
                version: $data['version'],
                size: $data['size'],
                baseversion: $request->query->get('baseversion'),
                sitepath: $request->query->get('sitepath'),
                rundesc: $request->query->get('rundesc'),
                users: $request->query->get('users'),
                loopcount: $request->query->get('loopcount'),
                rampup: $request->query->get('rampup'),
                throughput: $request->query->get('throughput'),
                siteversion: $request->query->get('siteversion'),
                sitebranch: $request->query->get('sitebranch'),
            );
        } else {
            $filteredDatasets = $datasets;
        }

        // Sort the available datasets by runtime.
        usort($filteredDatasets, function ($a, $b) {
            return $b->getRunTime() <=> $a->getRunTime();
        });

        $datasetsToCompare = [];
        if ($request->query->has('compare')) {
            // Check the query string for comparison data.
            $getParams = $request->query->all();
            $comparisons = $getParams['compare'];

            // Suffix with the .json extension if it isn't already there.
            $comparisons = array_map(
                fn ($comparison): string => str_ends_with($comparison, ".json") ? $comparison : "{$comparison}.json",
                $comparisons,
            );

            $datasetsToCompare = array_filter(
                $datasets,
                function ($dataset) use ($comparisons): bool {
                    if (in_array($dataset->getName(), $comparisons)) {
                        return true;
                    }

                    foreach ($comparisons as $comparison) {
                        if (str_ends_with($dataset->getName(), $comparison)) {
                            return true;
                        }
                    }

                    return false;
                },
            );
        }

        // Create the comparison selection form.
        $datasetComparisonForm = $this->createForm(
            DatasetComparisonType::class,
            data: [
                'datasets' => array_map(
                    fn ($dataset): string => $dataset->getName(),
                    $datasetsToCompare,
                ),
            ],
            options: [
                'datasets' => $filteredDatasets,
            ],
        );

        $datasetComparisonForm->handleRequest($request);
        if ($datasetComparisonForm->isSubmitted() && $datasetComparisonForm->isValid()) {
            // Check for submitted comparison form.
            $data = $datasetComparisonForm->getData();

            $datasetsToCompare = array_filter(
                $datasets,
                function ($dataset) use ($data): bool {
                    return in_array($dataset->getName(), $data['datasets']);
                },
            );
        }
        $charts = $this->getCharts($datasetsToCompare);

        return $this->render('index/index.html.twig', [
            'datasets' => $datasets,
            'datasetFilterForm' => $datasetFilterForm,
            'datasetComparisonForm' => $datasetComparisonForm,
            'datasetsToCompare' => $datasetsToCompare,
            'controller_name' => 'IndexController',
            'charts' => $charts,
        ]);
    }

    private function getCharts(
        array $datasetsToCompare,
    ): array {
        $datasetScenarios = array_map(fn ($dataset) => $dataset->getScenarios(), $datasetsToCompare);
        $scenarios = [];
        array_walk_recursive($datasetScenarios, function ($scenario) use (&$scenarios) {
            $scenarios[] = $scenario->name;
        });
        $scenarios = array_unique($scenarios);

        $keys = array_keys($this->comparisons->getComparisonKeys());

        foreach ($keys as $key) {
            $charts[$key] = $this->getChart($datasetsToCompare, $scenarios, $key);
        }

        return $charts;
    }

    private function getChart(
        array $datasetsToCompare,
        array $scenarios,
        string $key,
    ): Chart {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);

        $data = array_map(function ($dataset) use ($scenarios, $key): array {
            return [
                'label' => $dataset->getShortTitle(),
                // 'backgroundColor' => 'rgb(255, 99, 132)',
                // 'borderColor' => 'rgb(255, 99, 132)',
                'data' => array_map(function ($scenario) use ($dataset, $key): float {
                    return $dataset->getScenario($scenario)->getAverage($key);
                }, $scenarios),
            ];
        }, $datasetsToCompare);

        $chart->setData([
            'labels' => array_values($scenarios),
            'datasets' => array_values($data),
        ]);

        $chart->setOptions([
            'indexAxis' => 'y',
            'scales' => [
                'y' => [
                    'suggestedMin' => 0,
                    'suggestedMax' => 100,
                ],
            ],
        ]);

        return $chart;
    }
}
