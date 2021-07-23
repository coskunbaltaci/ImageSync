<?php

namespace Cb\ImageSync\Cron;

use Cb\ImageSync\Helper\Data;
use Cb\ImageSync\Model\ImageSyncException;
use Cb\ImageSync\Model\ImportListFactory;
use Cb\ImageSync\Provider\Ftp;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Gallery\Processor;
use Magento\Catalog\Model\Product\Media\Config;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\Mime;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Logger\Monolog;
use Magento\MediaStorage\Helper\File\Storage\Database;
use Psr\Log\LoggerInterface;

class Sync
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
    const IMPORT_ROW_COUNT = 'cb_imagesync/general/import_row_count';
    const MODULE_IS_ACTIVE = 'cb_imagesync/general/active';

    public function __construct(
        Data $helper,
        File $file,
        ProductFactory $productFactory,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        State $state,
        Filesystem $filesystem,
        Config $mediaConfig,
        Database $fileStorageDb,
        Mime $mime = null,
        Processor $processor,
        ImportListFactory $importListFactory
    ) {
        $this->helper = $helper;
        $this->file = $file;
        $this->scopeConfig = $scopeConfig;
        $this->productFactory = $productFactory;
        $this->logger = $logger->withName(__CLASS__);
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->mediaConfig = $mediaConfig;
        $this->fileStorageDb = $fileStorageDb;
        $this->processor = $processor;
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
            if (!$this->state->validateAreaCode()) {
                try {
                    $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);
                } catch (\Exception $e) {
                    $this->helper->log(Monolog::CRITICAL, $e->getMessage());
                }
            }
        }
        $this->helper->getProvider()->checkAndCreateFolder($this->scopeConfig->getValue(ftp::FTP_SYNC_PATH));
        $this->helper->getProvider()->checkAndCreateFolder($this->scopeConfig->getValue(ftp::FTP_SUCCESS_PATH));
        $this->helper->getProvider()->checkAndCreateFolder($this->scopeConfig->getValue(ftp::FTP_LOG_PATH));
        $this->startImportTransaction();
        $this->helper->log(Monolog::INFO, vsprintf('Image sync completed with %s success assignments, %s errors', [$this->successCount, $this->errorCount]));
        $log_file_path = $this->helper->createLogFileOnFtp();
        $this->helper->sendLogMail($log_file_path);
    }
    public function startImportTransaction()
    {
        $importList = $this->importListFactory->create();
        $importListCollection = $importList->getCollection()
            ->addFieldToFilter('status', ['eq' => 0])
            ->setPageSize($this->scopeConfig->getValue(self::IMPORT_ROW_COUNT))
            ->setCurPage(1);
        if (count($importListCollection) > 0) {
            foreach ($importListCollection as $item) {
                $itemDetail = [
                    'sku' => $item->getSku(),
                    'image_file_name' => $item->getImageFileName(),
                    'image_order' => $item->getImageOrder(),
                    'alt_text' => $item->getImageAltText(),
                    'status' => $item->getStatus()
                ];
                try {
                    if ($result = $this->checkCustomFormatImage($itemDetail)) {
                        if ($this->helper->hasImageFile($result['file_name'])) {
                            if ($this->assignImageByProductId(
                                $result['product_id'],
                                $result['file_name'],
                                $result['position'],
                                $result['label'],
                                $result['image_types']
                            )) {
                                $this->errorCount++;
                                $item->setData('status', 2);
                                $item->setData('message', 'System Error');
                                $item->setData('updated_at', new \Zend_Db_Expr('NOW()'));
                            }
                            $item->setData('status', 1);
                            $item->setData('updated_at', new \Zend_Db_Expr('NOW()'));
                        } else {
                            $this->errorCount++;
                            $this->helper->log(Monolog::INFO, sprintf('%s Image Not Found', $result['file_name']));
                            $item->setData('status', 2);
                            $item->setData('message', 'Image Not Found');
                            $item->setData('updated_at', new \Zend_Db_Expr('NOW()'));
                        }
                    } else {
                        $this->errorCount++;
                        $item->setData('status', 2);
                        $item->setData('message', 'Product Not Found');
                        $item->setData('updated_at', new \Zend_Db_Expr('NOW()'));
                    }
                    try {
                        $item->save();
                    } catch (\Exception $e) {
                        $this->helper->log(Monolog::CRITICAL, 'order did not updated', ['exception' => $exception]);
                    }
                } catch (ImageSyncException $exception) {
                    $this->errorCount++;
                    $this->helper->log(Monolog::ERROR, $exception->getMessage());
                } catch (\Throwable $exception) {
                    $this->errorCount++;
                    $this->helper->log(Monolog::CRITICAL, 'There has been an error', ['exception' => $exception]);
                }
            }
        }
    }

    /**
     * @param $productId
     * @param $fileName
     * @param $position
     * @param $label
     * @param $imageTypes
     * @return false
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function assignImageByProductId($productId, $fileName, $position, $label, $imageTypes)
    {
        $product = $this->productFactory->create();
        $product->load($productId);
        $this->helper->log(Monolog::INFO, vsprintf('image %s is assigning with Position %s , label  %s', [$fileName, $position, $label]));
        if (!$this->assignImage($product, $fileName, $position, $label, $imageTypes, false, false)) {
            return false;
        }
        $this->helper->log(Monolog::INFO, vsprintf('image %s assigned to product "%s"', [$fileName, $product->getSku()]));
        $this->helper->deleteLocalFile($fileName);
        $this->successCount++;
        $product = null;
    }

    /**
     * check \Magento\Catalog\Model\Product\Gallery\Processor::addImage for original method
     * Why this function?
     * Because original is getting all images, gets the last positioned image, and set +1 position for new image.
     * We want to set custom image position
     * @param Product $product
     * @param $file
     * @param $position
     * @param null $label
     * @param null $mediaAttribute
     * @param false $move
     * @param bool $exclude
     * @return false|string|string[]
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function assignImage(
        Product $product,
        $file,
        $position,
        $label = null,
        $mediaAttribute = null,
        $move = false,
        $exclude = true
    ) {
        $file = $this->mediaDirectory->getRelativePath(Data::DOWNLOAD_PATH . DIRECTORY_SEPARATOR . $file);
        if (!$this->mediaDirectory->isFile($file)) {
            $this->helper->log(Monolog::ERROR, 'The image doesn\'t exist.');
            return false;
        }
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $pathinfo = pathinfo($file);
        $imgExtensions = ['jpg', 'jpeg', 'gif', 'png'];
        if (!isset($pathinfo['extension']) || !in_array(strtolower($pathinfo['extension']), $imgExtensions)) {
            throw new LocalizedException(
                __('The image type for the file is invalid. Enter the correct image type and try again.')
            );
        }
        $fileName = \Magento\MediaStorage\Model\File\Uploader::getCorrectFileName($pathinfo['basename']);
        $dispersionPath = \Magento\MediaStorage\Model\File\Uploader::getDispersionPath($fileName);
        $fileName = $dispersionPath . '/' . $fileName;
        //$fileName = $this->processor->getNotDuplicatedFilename($fileName, $dispersionPath);
        $destinationFile = $this->mediaConfig->getTmpMediaPath($fileName);
        try {
            /** @var $storageHelper \Magento\MediaStorage\Helper\File\Storage\Database */
            $storageHelper = $this->fileStorageDb;
            if ($move) {
                $this->mediaDirectory->renameFile($file, $destinationFile);
                //If this is used, filesystem should be configured properly
                $storageHelper->saveFile($this->mediaConfig->getTmpMediaShortUrl($fileName));
            } else {
                $this->mediaDirectory->copyFile($file, $destinationFile);
                $storageHelper->saveFile($this->mediaConfig->getTmpMediaShortUrl($fileName));
            }
        } catch (\Exception $e) {
            $this->helper->log(Monolog::ERROR, sprintf('The "%1" file couldn\'t be moved.', $e->getMessage()));
            return false;
        }
        $fileName = str_replace('\\', '/', $fileName);
        $attrCode = $this->processor->getAttribute()->getAttributeCode();
        $mediaGalleryData = $product->getData($attrCode);
        $absoluteFilePath = $this->mediaDirectory->getAbsolutePath($destinationFile);
        $imageMimeType = $this->mime->getMimeType($absoluteFilePath);
        $imageContent = $this->mediaDirectory->readFile($absoluteFilePath);
        $imageBase64 = base64_encode($imageContent);
        $imageName = $pathinfo['filename'];
        if (!is_array($mediaGalleryData)) {
            $mediaGalleryData = ['images' => []];
        }
        $mediaGalleryData['images'][] = [
            'file' => $fileName,
            'position' => $position,
            'label' => $label,
            'disabled' => (int)$exclude,
            'media_type' => 'image',
            'types' => $mediaAttribute,
            'content' => [
                'data' => [
                    ImageContentInterface::NAME => $imageName,
                    ImageContentInterface::BASE64_ENCODED_DATA => $imageBase64,
                    ImageContentInterface::TYPE => $imageMimeType,
                ]
            ]
        ];
        $product->setData($attrCode, $mediaGalleryData);
        if ($mediaAttribute !== null) {
            $this->processor->setMediaAttribute($product, $mediaAttribute, $fileName);
        }
        $product->save();
        return $fileName;
    }

    /**
     * @param $csvRow
     * @return array|false
     */
    public function checkCustomFormatImage($csvRow)
    {
        $pathInfo = $this->file->getPathInfo($csvRow['image_file_name']);
        if (!in_array($pathInfo['extension'], self::ALLOWED_EXTENSIONS)) {
            $this->helper->log(Monolog::DEBUG, sprintf('image "%s" extension is not allowed.', $csvRow['image_file_name']));
            return false;
        }
        $product = $this->productFactory->create();
        if ($productId = $product->getIdBySku($csvRow['sku'])) {
            $this->helper->log(Monolog::DEBUG, vsprintf('image "%s" matched with product "%s"', [$csvRow['image_file_name'], $csvRow['sku']]));
            return [
                'sku' => $csvRow['sku'],
                'product_id' => $productId,
                'position' => $csvRow['image_order'],
                'file_name' => $pathInfo['basename'],
                'image_types' => $csvRow['image_order'] == self::BASE_IMAGE_POSITION ? ['image', 'small_image', 'thumbnail'] : [],
                'label' => $csvRow['alt_text']
            ];
        }
        return false;
    }
}
