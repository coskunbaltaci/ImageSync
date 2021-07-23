<?php

namespace Cb\Imagesync\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Cb\ImageSync\Cron\DeleteDoneRows;


class CbDeleteDoneRows  extends Command
{
    protected $deleteDoneRows;

    public function __construct(DeleteDoneRows $deleteDoneRows)
    {
        $this->deleteDoneRows = $deleteDoneRows;

        parent::__construct();
    }

    public function configure()
    {
        $this->setName('cb:image-sync:delete-done-rows')
            ->setDescription('Delete Done Rows');

        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Starting Delete Done Rows");
        $this->deleteDoneRows->execute();
        $output->writeln("Deleted Done Rows");

    }
}

