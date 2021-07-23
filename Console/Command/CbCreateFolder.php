<?php

namespace Cb\Imagesync\Console\Command;

use Cb\ImageSync\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Cb\ImageSync\Provider\Ftp;

class CbCreateFolder extends Command
{
    protected $helper;
    protected $scopeConfig;

    public function __construct(
        Data $helper,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->helper = $helper;
        $this->scopeConfig = $scopeConfig;
        parent::__construct();
    }

    public function configure()
    {
        $this->setName('cb:image-sync:create-folder')
            ->setDescription('Executes Image Create Folder');

        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->helper->getProvider()->checkAndCreateFolder($this->scopeConfig->getValue(ftp::FTP_SYNC_PATH));
        $this->helper->getProvider()->checkAndCreateFolder($this->scopeConfig->getValue(ftp::FTP_SUCCESS_PATH));
        $this->helper->getProvider()->checkAndCreateFolder($this->scopeConfig->getValue(ftp::FTP_LOG_PATH));
    }
}
