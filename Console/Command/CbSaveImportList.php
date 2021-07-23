<?php

namespace Cb\Imagesync\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Cb\ImageSync\Cron\SaveImportRows;

class CbSaveImportList extends Command
{
    protected $saveImportRows;

    public function __construct(SaveImportRows $saveImportRows)
    {
        $this->saveImportRows = $saveImportRows;

        parent::__construct();
    }

    public function configure()
    {
        $this->setName('cb:image-sync:save-import-list')
            ->setDescription('Save Csv Files');

        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Starting Save Import Rows");
        $this->saveImportRows->execute();
        $output->writeln("Save Import Row Finished");

    }
}
