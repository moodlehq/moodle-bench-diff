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
        $datasets = $this->datasetLoader->listDatasets('');
        $datasetFilterForm = $this->createForm(
            DatasetFilterType::class,
            options: [
                'datasets' => $datasets,
            ],
        );

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

        usort($datasets, function ($a, $b) {
            return $b->getRunTime() <=> $a->getRunTime();
        });

        $datasetComparisonForm = $this->createForm(
            DatasetComparisonType::class,
            options: [
                'datasets' => $datasets,
            ],
        );

        $datasetComparisonForm->handleRequest($request);
        if ($datasetComparisonForm->isSubmitted() && $datasetComparisonForm->isValid()) {
            $data = $datasetComparisonForm->getData();

            $datasetsToCompare = array_filter(
                $datasets,
                function ($dataset) use ($data): bool {
                    return in_array($dataset->getName(), $data['datasets']);
                },
            );

            $datasetsToCompare = array_filter(
                $datasets,
                function ($dataset) use ($data): bool {
                    return in_array($dataset->getName(), $data['datasets']);
                },
            );
            $charts = $this->getCharts($datasetsToCompare);

        } else {
            $datasetsToCompare = [];
        }

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
            'datasets' => $data,
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
