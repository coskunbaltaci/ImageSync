<?php
namespace Cb\ImageSync\Helper;

use Cb\ImageSync\Provider\Ftp;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File as DriverFile;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Logger\Monolog;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Data
{
    protected $scopeConfig;
    protected $logger;
    protected $transportBuilder;
    protected $storeManager;
    protected $filesystem;
    protected $file;
    protected $driverFile;
    protected $logMessage;

    const DOWNLOAD_PATH = 'image-sync';
    const LOG_FILE_FOLDER = 'logs';

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        Filesystem $filesystem,
        File $file,
        DriverFile $driverFile,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->filesystem = $filesystem;
        $this->file = $file;
        $this->driverFile = $driverFile;
        $this->logger = $logger;
    }

    public function getDestinationPath()
    {
        //this is the path where we download files
        $destinationPath = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath(self::DOWNLOAD_PATH);
        $this->file->checkAndCreateFolder($destinationPath);

        return $destinationPath;
    }

    public function getProvider()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $providerClass = 'Cb\\ImageSync\\Provider\\' . $this->scopeConfig->getValue('cb_imagesync/general/type');

        return $objectManager->get($providerClass);
    }

    public function log($level, $message, $context = [])
    {
        switch ($level) {
            case Monolog::INFO:
                $this->logMessage .= '<p>' . $message . '</p>';
                break;
            case Monolog::ERROR || Monolog::CRITICAL:
                $this->logMessage .= '<p style="color:red">' . $message . '</p>';
                break;
        }

        $this->logger->addRecord($level, $message, $context);
    }

    public function deleteLocalFile($fileName)
    {
        //with destinationPath
        if ($this->driverFile->isExists($this->getDestinationPath() . DIRECTORY_SEPARATOR . $fileName)) {
            $this->driverFile->deleteFile($this->getDestinationPath() . DIRECTORY_SEPARATOR . $fileName);
        }
    }

    public function sendLogMail($attachment = null)
    {
        try {
            if (!$this->scopeConfig->getValue('cb_imagesync/general/log_emails')) {
                $this->logger->addRecord(Monolog::NOTICE, 'We could not send e-mail, Email configuration is empty.');
                return;
            }
            $defaultStoreId = $this->storeManager->getDefaultStoreView()->getId();
            $emails = explode(',', $this->scopeConfig->getValue('cb_imagesync/general/log_emails'));
            $emailTemplate = $this->scopeConfig->getValue('cb_imagesync/general/log_email_template');

            $sender = [
                'email' => $this->scopeConfig->getValue('trans_email/ident_support/email'),
                'name' => $this->scopeConfig->getValue('trans_email/ident_sales/name')
            ];

            $transport = $this->transportBuilder->setTemplateIdentifier($emailTemplate)
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $defaultStoreId])
                ->setTemplateVars(['log_message' => $this->logMessage])
                ->setFrom($sender);

            foreach ($emails as $email) {
                $transport->addTo($email);
            }
            $transport->getTransport()->sendMessage();

            $this->logger->addRecord(Monolog::INFO, 'Log e-mail sent');
        } catch (\Throwable $exception) {
            $this->logger->addRecord(Monolog::ERROR, 'There has been an error while sending email', ['exception' => $exception]);
        }
    }

    public function moveFile($files = [])
    {
        if (!empty($files)) {
            foreach ($files as $file) {
                if (!$this->getProvider()->moveFile(
                    $this->scopeConfig->getValue(Ftp::FTP_SYNC_PATH) . $file,
                    $this->scopeConfig->getValue(Ftp::FTP_SUCCESS_PATH) . $file
                )) {
                    $this->log(Monolog::ERROR, sprintf('%s could not moved to success folder', $file));
                } else {
                    $this->log(Monolog::INFO, sprintf('image %s moved to success folder', $file));
                }
            }
            return true;
        }
        return false;
    }

    public function createLogFileOnFtp()
    {
        $success_path = $this->scopeConfig->getValue(ftp::FTP_LOG_PATH);
        $file_name = date("Y.m.d_h.i.s") . ".txt";
        $source = $this->getDestinationPath() . DIRECTORY_SEPARATOR . $file_name;

        try {
            $this->driverFile->filePutContents($source, $this->logMessage, null);
            $ftp = $this->getProvider();
            if ($ftp->uploadFile($source, $success_path)) {
                $this->deleteLocalFile(basename($source));
                return $source;
            }
        } catch (FileSystemException $e) {
            $this->log(Monolog::ERROR, sprintf('%s could not create log file in success folder', $e->getMessage()));
        }
    }

    public function remove_utf8_bom($text)
    {
        $bom = pack('H*', 'EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);
        return $text;
    }

    public function changeStatusColumn($fileName, $content)
    {
        try {
            $source = $this->getDestinationPath() . DIRECTORY_SEPARATOR . $fileName;
            $resource = $this->driverFile->fileOpen($source, 'w');
            array_unshift($content, array_keys($content[0]));
            foreach ($content as $key => $row) {
                $this->driverFile->filePutCsv($resource, $row);
            }
            $this->driverFile->fileClose($resource);
            return true;
        } catch (\Exception $e) {
            $this->log(Monolog::ERROR, sprintf('Could not change status parameter. %s ', $e->getMessage()));
        }
    }

    /**
     * @param $fileName
     * @return bool
     */
    public function hasImageFile($fileName)
    {
        try {
            $this->getProvider()->getFile($fileName);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
