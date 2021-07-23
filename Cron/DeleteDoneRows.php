<?php

namespace Cb\ImageSync\Cron;

use Cb\ImageSync\Helper\Data;
use Cb\ImageSync\Model\ImportListFactory;
use Cb\ImageSync\Provider\Ftp;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Logger\Monolog;
use Psr\Log\LoggerInterface;

class DeleteDoneRows
{
    /**
     * @var Data
     */
    protected $helper;
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var
     */
    protected $logger;
    /**
     * @var ImportListFactory
     */
    protected $importListFactory;
    /**
     * @var State
     */
    private $state;

    public function __construct(
        Data $helper,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        State $state,
        ImportListFactory $importListFactory
    ) {
        $this->helper = $helper;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger->withName(__CLASS__);
        $this->state = $state;
        $this->importListFactory = $importListFactory;
    }

    public function execute()
    {
        if (!$this->scopeConfig->getValue('imagesync/general/active')) {
            return;
        }
        if (!$this->helper->getProvider()->isEnabled()) {
            $this->helper->log(Monolog::ERROR, 'Provider is not enabled');
        }
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);
        $this->helper->getProvider()->checkAndCreateFolder($this->scopeConfig->getValue(ftp::FTP_SYNC_PATH));
        $this->helper->getProvider()->checkAndCreateFolder($this->scopeConfig->getValue(ftp::FTP_SUCCESS_PATH));
        $this->deleteDoneRows();
        $this->helper->log(Monolog::INFO, 'Delete transaction is done.');
    }

    public function deleteDoneRows()
    {
        $importList = $this->importListFactory->create();
        $getDoneItems =  $importList->getCollection()
            ->addFieldToFilter('status', 1);
        if (count($getDoneItems) > 0) {
            foreach ($getDoneItems as $item) {
                try {
                    $item->delete();
                    $this->helper->log(Monolog::INFO, sprintf('Product SKU:"%s" and image:"%s" deleted the db.', $item->getSku(), $item->getImageFileName()));
                    if (!$this->hasProcessingImage($item->getImageFileName()) && $this->helper->hasImageFile($item->getImageFileName())) {
                        try {
                            $this->helper->getProvider()->moveFile($this->scopeConfig->getValue(Ftp::FTP_SYNC_PATH) . $item->getImageFileName(), $this->scopeConfig->getValue(Ftp::FTP_SUCCESS_PATH) . $item->getImageFileName());
                            $this->helper->log(Monolog::INFO, sprintf('"%s" file moved to success folder.', $item->getImageFileName()));
                        } catch (\Exception $e) {
                            $this->helper->log(Monolog::ERROR, sprintf('"%s" file could not moved to success folder. %s', $item->getImageFileName(), $e->getMessage()));
                            $this->helper->log(Monolog::ERROR, sprintf('"%s" file could not moved to success folder. %s', $item->getImageFileName(), $e->getMessage()));
                        }
                    }
                } catch (\Exception $e) {
                    $this->helper->log(Monolog::ERROR, sprintf('%s could not delete the db. %s', $item->getSku(), $e->getMessage()));
                }
            }
        }
    }

    /**
     * @param $imageFileName
     * @return bool
     */
    public function hasProcessingImage($imageFileName)
    {
        $importList = $this->importListFactory->create();
        $checkImage =  $importList->getCollection()
            ->addFieldToFilter('status', 0)
            ->addFieldToFilter('image_file_name', $imageFileName);
        if (count($checkImage) > 0) {
            return true;
        }
        return false;
    }
}
