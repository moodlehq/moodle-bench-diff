<?php
namespace App\Command;

use App\Service\DatasetComparatorInterface;
use App\Service\DatasetLoaderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// the name of the command is what users type after "php bin/console"
#[AsCommand(name: 'moodle:compare-results')]
class CompareResultsCommand extends Command
{
    public function __construct(
        private DatasetLoaderInterface $datasetLoader,
        private DatasetComparatorInterface $datasetComparator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Compare results from Moodle Performance runs.')
            ->setHelp('This command allows you to compare results from Moodle Performance runs.')
            ->addArgument('before', InputArgument::REQUIRED, 'The before file')
            ->addArgument('after', InputArgument::REQUIRED, 'The after file')
        ;
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $before = $input->getArgument('before');
        $after = $input->getArgument('after');

        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        // Load the datasets.
        $beforeDataset = $this->datasetLoader->loadFullDataset($before);
        $afterDataset = $this->datasetLoader->loadFullDataset($after);

        $io->title(sprintf(
            "Comparing results between %s and %s",
            $beforeDataset->name,
            $afterDataset->name,
        ));

        // Describe the datasets.

        $io->table(
            ['', 'Before', 'After'],
            [
                ['Commit', $beforeDataset->sitecommit, $afterDataset->sitecommit],
                ['Size', $beforeDataset->size, $afterDataset->size],
                ['Run description', $beforeDataset->rundesc, $afterDataset->rundesc],
                ['Group', $beforeDataset->group, $afterDataset->group],
                ['Users', $beforeDataset->users, $afterDataset->users],
                ['Loops', $beforeDataset->loopcount, $afterDataset->loopcount],
                ['Ramp up', $beforeDataset->rampup, $afterDataset->rampup],
                ['Throughput', $beforeDataset->throughput, $afterDataset->throughput],
                ['Base Version', $beforeDataset->baseversion, $afterDataset->baseversion],
                ['Site branch', $beforeDataset->sitebranch, $afterDataset->sitebranch],
            ],
        );

        // Compare the datasets.
        $comparisonResult = $this->datasetComparator->compare($beforeDataset, $afterDataset);

        $failedStyling = [
            'style' => new TableCellStyle([
                'bg' => 'red',
                'fg' => 'white',
            ]),
        ];

        if ($input->getOption('verbose')) {
            $io->section('Results');

            $table = new Table($output);
            $table->setHeaders(['Scenario', 'Name', 'Description']);
            foreach ($comparisonResult->getResults() as $result) {
                if ($result->isFailed()) {
                    $table->addRow([
                        new TableCell($result->scenario->name, $failedStyling),
                        new TableCell(
                            "{$result->key} ({$result->comparisonType})",
                            $failedStyling,
                        ),
                        new TableCell($result->description, $failedStyling),
                    ]);
                }
                $table->addRow([
                    $result->scenario->name,
                    "{$result->key} ({$result->comparisonType})",
                    $result->description,
                ]);
            }
            $table->render();
        }

        $io->writeln('');

        if ($comparisonResult->isSuccessful()) {
            $io->info('Comparison successful');
            return Command::SUCCESS;
        } else {
            $io->section('Summary of all Failures');

            $table = new Table($output);
            $table->setHeaders(['Scenario', 'Name', 'Description', 'Before', 'After']);
            $table->setRows(
                array_map(
                    fn($result): array => [
                        $result->scenario->name,
                        "{$result->key} ({$result->comparisonType})",
                        $result->description,
                        $result->valueA,
                        $result->valueB,
                    ],
                    $comparisonResult->getFailures()
                )
            );

            $table->render();

            $io->writeln('');

            $io->error('Comparison failed');
            return Command::FAILURE;
        }
    }
}
