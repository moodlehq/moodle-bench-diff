<?php

namespace App\Command;

use App\Service\DatasetLoaderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsCommand(
    name: 'moodle:warm-cache',
    description: 'Warm the cache',
)]
#[AsPeriodicTask(frequency: '1 hour')]
class MoodleWarmCacheCommand extends Command
{
    public function __construct(
        private DatasetLoaderInterface $datasetLoader,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Warming the Cache from S3');

        $this->datasetLoader->listDatasets(
            io: $io,
        );

        $io->success('Cache warmed with all current values.');

        return Command::SUCCESS;
    }
}
