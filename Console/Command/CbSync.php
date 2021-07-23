<?php

namespace Cb\Imagesync\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Cb\ImageSync\Cron\Sync;

class CbSync extends Command
{
    protected $sync;

    public function __construct(Sync $sync)
    {
        $this->sync = $sync;

        parent::__construct();
    }

    public function configure()
    {
        $this->setName('cb:image-sync:start')
            ->setDescription('Executes Image sync');

        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Starting imagesync");
        $this->sync->execute();
        $output->writeln("imagesync Finished");

    }
}
