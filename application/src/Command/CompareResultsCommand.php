<?php
namespace App\Command;

use App\Service\DatasetComparatorInterface;
use App\Service\DatasetLoaderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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

        $output->writeln("Comparing results between {$before} and {$after}");

        // Load the datasets.
        $beforeDataset = $this->datasetLoader->loadFullDataset($before);
        $afterDataset = $this->datasetLoader->loadFullDataset($after);

        // Compare the datasets.
        $result = $this->datasetComparator->compare($beforeDataset, $afterDataset);

        if ($result->isSuccessful()) {
            $output->writeln('Comparison successful');
            return Command::SUCCESS;
        } else {
            $output->writeln('Comparison failed');
            return Command::FAILURE;
        }
    }
}
