<?php

namespace Cb\ImageSync\Cron;

use Cb\ImageSync\Helper\Data;
use Cb\ImageSync\Model\ImageSyncException;
use Cb\ImageSync\Model\ImportListFactory;
use Cb\ImageSync\Provider\Ftp;
use Magento\Catalog\Model\Product\Media\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\File\Csv;
use Magento\Framework\File\Mime;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Logger\Monolog;
use Magento\MediaStorage\Helper\File\Storage\Database;
use Psr\Log\LoggerInterface;

class SaveImportRows
{
    protected $helper;
    protected $file;
    protected $productFactory;
    protected $scopeConfig;
    protected $logger;
    protected $mediaDirectory;
    protected $fileStorageDb;
    protected $mediaConfig;
    protected $processor;
    protected $csv;
    protected $successCount = 0;
    protected $errorCount = 0;
    private $mime;
    protected $importListFactory;
    /**
     * @var State
     */
    private $state;

    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png'];
    const SEPARATOR = '-';
    const BASE_IMAGE_POSITION = 1;
    const MODULE_IS_ACTIVE = 'cb_imagesync/general/active';
    const SET_ALT_TAG = 'cb_imagesync/general/set_alt_tag';

    public function __construct(
        Data $helper,
        File $file,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        State $state,
        Csv $csv,
        Filesystem $filesystem,
        Config $mediaConfig,
        Database $fileStorageDb,
        Mime $mime = null,
        ImportListFactory $importListFactory
    ) {
        $this->helper = $helper;
        $this->file = $file;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger->withName(__CLASS__);
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->mediaConfig = $mediaConfig;
        $this->csv = $csv;
        $this->fileStorageDb = $fileStorageDb;
        $this->mime = $mime ?: ObjectManager::getInstance()->get(\Magento\Framework\File\Mime::class);
        $this->state = $state;
        $this->importListFactory = $importListFactory;
    }

    public function execute()
    {
        if (!$this->scopeConfig->getValue(self::MODULE_IS_ACTIVE)) {
            return;
        }
        if (!$this->helper->getProvider()->isEnabled()) {
            $this->helper->log(Monolog::ERROR, 'Provider is not enabled');
        }
        if (!$this->state->validateAreaCode()) {
            try {
                $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);
            } catch (\Exception $e) {
                $this->helper->log(Monolog::CRITICAL, $e->getMessage());
            }
        }
        $this->helper->getProvider()->checkAndCreateFolder($this->scopeConfig->getValue(ftp::FTP_SYNC_PATH));
        $this->helper->getProvider()->checkAndCreateFolder($this->scopeConfig->getValue(ftp::FTP_SUCCESS_PATH));

        if ($this->scopeConfig->getValue('cb_imagesync/general/use_custom_format')) {
            $this->helper->log(Monolog::INFO, sprintf('Custom import format working'));
            $this->executeCustomFormat();
        } else {
            $this->helper->log(Monolog::INFO, sprintf('Standart import format working'));
            $this->executeStandartFormat();
        }
        $this->helper->log(Monolog::INFO, 'Import list checked');
    }

    /**
     * @return false
     */
    public function executeCustomFormat()
    {
        $customFormatFiles = $this->helper->getProvider()->getFileList();
        foreach ($customFormatFiles as $customFormatFile) {
            $pathInfo = $this->file->getPathInfo($customFormatFile['text']);
            if ($pathInfo['extension'] !== 'csv') {
                continue;
            }
            $this->helper->log(Monolog::INFO, sprintf('"%s" csv file importing', $customFormatFile['text']));
            $this->saveCsvContentToDb($customFormatFile['text']);
            try {
                $this->helper->getProvider()->moveFile($this->scopeConfig->getValue(Ftp::FTP_SYNC_PATH) . $customFormatFile['text'], $this->scopeConfig->getValue(Ftp::FTP_SUCCESS_PATH) . $customFormatFile['text']);
                $this->helper->log(Monolog::INFO, sprintf('"%s" csv file importing is done and the file moved to "success" folder', $customFormatFile['text']));
            } catch (\Exception $e) {
                $this->helper->log(Monolog::ERROR, sprintf('"%s" did not moved to "success" folder', $customFormatFile['text']));
            }
        }
    }

    public function executeStandartFormat()
    {
        $images = $this->helper->getProvider()->getFileList();
        $this->helper->log(Monolog::INFO, sprintf('%s image found to save database', count($images)));
        foreach ($images as $file) {
            try {
                $this->helper->log(Monolog::DEBUG, sprintf('Checking image %s', $file['text']));
                if (!($result = $this->checkImage($file['text']))) {
                    continue;
                }
                $this->assignImageByProductId(
                    $result['product_id'],
                    $result['file_name'],
                    $result['position'],
                    $result['label'],
                    $result['image_types']
                );
            } catch (ImageSyncException $exception) {
                $this->errorCount++;
                $this->helper->log(Monolog::ERROR, $exception->getMessage());
            } catch (\Throwable $exception) {
                $this->errorCount++;
                $this->helper->log(Monolog::CRITICAL, 'There has been an error', ['exception' => $exception]);
            }
        }
    }

    /**
     * @param $csvFile
     * @return array|void
     * @throws \Exception
     */
    public function saveCsvContentToDb($csvFile)
    {
        if (!$this->helper->getProvider()->getFile($csvFile)) {
            $this->helper->log(Monolog::ERROR, sprintf('%s could not download from remote', $csvFile));
            return;
        }
        $csvData = $this->csv->getData($this->helper->getDestinationPath() . DIRECTORY_SEPARATOR . $csvFile);
        $customFormatArray = [];
        foreach ($csvData as $row => $data) {
            if ($row === 0) {
                // checking and removing utf8 bom from header data
                foreach ($data as $key => $value) {
                    $data[$key] = $this->helper->remove_utf8_bom($value);
                }
                $fields = $data;
                continue;
            }
            if (count($data) > 1) {
                foreach ($fields as $key => $field) {
                    $customFormatRow[$field] = isset($data[$key]) ? $data[$key] : 0;
                }
                $this->saveDb($customFormatRow);
            }
        }
        return $customFormatArray;
    }

    /**
     * @param $data
     * @return bool
     * @throws \Exception
     */
    public function saveDb($data)
    {
        $importList = $this->importListFactory->create();
        $getCollection =  $importList->getCollection()
            ->addFieldToFilter('sku', [$data['sku']])
            ->addFieldToFilter('image_order', [$data['image_order']]);
        if (count($getCollection) == 0) {
            $importList = $this->importListFactory->create();
            $importList->addData($data);
            if ($importList->save()) {
                return true;
            } else {
                $this->helper->log(Monolog::ERROR, sprintf('%s could save the db', $data['sku']));
            }
        }
        return false;
    }

    /**
     * @param $fileName
     * @throws ImageSyncException
     */
    public function checkImage($fileName)
    {
        $pathInfo = $this->file->getPathInfo($fileName);
        if (!in_array($pathInfo['extension'], self::ALLOWED_EXTENSIONS)) {
            throw new ImageSyncException(sprintf('image %s extension is not allowed.', $fileName));
        }
        $sku = $pathInfo['filename'];
        $imageOrder = self::BASE_IMAGE_POSITION;
        if (strpos($pathInfo['filename'], self::SEPARATOR)) {
            if (preg_match('/^(.*)(-\d+)$/', $pathInfo['filename'], $matches)) {
                $sku = $matches[1];
                $matches[2] = str_replace(self::SEPARATOR, '', $matches[2]);
                $imageOrder = (is_numeric($matches[2]) ? $matches[2] : self::BASE_IMAGE_POSITION);
            }
        }
        $data = [
            'sku' => $sku,
            'image_file_name' => $fileName,
            'image_order' => $imageOrder,
            'alt_text' => ($this->scopeConfig->getValue(self::SET_ALT_TAG) ? $sku : ''),
            'status' => 0
        ];
        $this->saveDb($data);
    }
}
