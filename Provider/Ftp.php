<?php
namespace Cb\ImageSync\Provider;

use Cb\ImageSync\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\AbstractIo;
use Magento\Framework\Filesystem\Io\Ftp as FtpConnection;
use Magento\Framework\Filesystem\Io\Sftp as SFtpConnection;
use Psr\Log\LoggerInterface;

class Ftp implements FtpInterface
{
    const FTP_HOST_PATH ='cb_imagesync/ftp/host';
    const FTP_USERNAME_PATH ='cb_imagesync/ftp/username';
    const FTP_PASSWORD_PATH ='cb_imagesync/ftp/password';
    const FTP_SYNC_PATH ='cb_imagesync/ftp/path';
    const FTP_SUCCESS_PATH ='cb_imagesync/ftp/success_path';
    const FTP_LOG_PATH = 'cb_imagesync/ftp/logs_path';
    /**
     * @var SFtpConnection
     */
    protected $sftp;
    /**
     * @var FtpConnection
     */
    protected $ftp;
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var DirectoryList
     */
    protected $directoryList;
    /**
     * @var
     */
    protected $file;
    /**
     * @var Data
     */
    protected $helper;
    /**
     * @var
     */
    protected $logger;

    /**
     * @var AbstractIo
     */
    protected $connection;
    /**
     * Ftp constructor.
     * @param FtpConnection $ftp
     * @param SFtpConnection $sftp
     * @param DirectoryList $directoryList
     * @param ScopeConfigInterface $scopeConfig
     * @param Data $helper
     * @param LoggerInterface $logger
     */
    public function __construct(
        FtpConnection $ftp,
        SFtpConnection $sftp,
        DirectoryList $directoryList,
        ScopeConfigInterface $scopeConfig,
        Data $helper,
        LoggerInterface $logger
    ) {
        $this->directoryList = $directoryList;
        $this->scopeConfig = $scopeConfig;
        $this->sftp = $sftp;
        $this->ftp = $ftp;
        $this->helper = $helper;
        $this->logger = $logger->withName(__CLASS__);
    }

    public function __destruct()
    {
        if ($this->isEnabled()) {
            $this->getConnection()->close();
            $this->logger->info('Ftp connection closed');
        }
    }

    public function isEnabled()
    {
        return $this->scopeConfig->getValue(self::FTP_HOST_PATH)
            && $this->scopeConfig->getValue(self::FTP_USERNAME_PATH)
            && $this->scopeConfig->getValue(self::FTP_PASSWORD_PATH);
    }

    private function getConnection()
    {
        try {
            $this->connection->pwd();
        } catch (\Throwable $exception) {
            $this->logger->debug('Trying to connect Ftp!');
            $host = $this->scopeConfig->getValue(self::FTP_HOST_PATH);
            list($host, $port) = array_pad(explode(':', $host, 2), 2, null);
            if ($port!=null && $port==22) {
                $this->sftp->open(
                    [
                        'host' => $host,
                        'username' => $this->scopeConfig->getValue(self::FTP_USERNAME_PATH),
                        'password' => $this->scopeConfig->getValue(self::FTP_PASSWORD_PATH)
                    ]
                );
                $this->logger->debug('Ftp connection successfull!');
                $this->connection = $this->sftp;
            } else {
                $this->ftp->open(
                    [
                        'host' => $host,
                        'user' => $this->scopeConfig->getValue(self::FTP_USERNAME_PATH),
                        'password' => $this->scopeConfig->getValue(self::FTP_PASSWORD_PATH),
                        'passive' => false,
                        'ssl' => false
                    ]
                );
            }
            $this->logger->debug('Ftp connection successfull!');
            $this->connection = $this->ftp;
        }
        return $this->connection;
    }

    public function getFileList()
    {
        $this->getConnection()->cd($this->scopeConfig->getValue(self::FTP_SYNC_PATH));

        $files = $this->getConnection()->ls();
        $files = array_filter($files, function ($file) {
            if (!in_array($file['text'], ['.','..'])) {
                return true;
            }
        });

        return $files;
    }

    public function getFile(String $fileName)
    {
        $this->getConnection()->cd($this->scopeConfig->getValue(self::FTP_SYNC_PATH));

        return $this->getConnection()->read($fileName, $this->helper->getDestinationPath() . DIRECTORY_SEPARATOR . $fileName);
    }

    public function moveFile(String $source, String $destination)
    {
        return $this->getConnection()->mv($source, $destination);
    }

    public function uploadFile($source, $destination)
    {
        $destination = $destination . basename($source);
        try {
            $content = file_get_contents($source);
            $this->getConnection()->write($destination, $content);
            return true;
        } catch (\Throwable $ex) {
            $this->logger->debug('Ftp connection failed!' . $ex->getMessage());
        }
    }

    public function checkAndCreateFolder($dir)
    {
        if (!$this->getConnection()->cd($dir)) {
            $parts = explode("/", $dir);
            $fullpath = '';
            foreach ($parts as $part) {
                if (empty($part)) {
                    $fullpath .= "/";
                    continue;
                }
                $fullpath .= $part . "/";
                if (!$this->getConnection()->cd($fullpath)) {
                    if ($this->getConnection()->mkdir($fullpath)) {
                        $this->logger->info($fullpath . ' folder created');
                    } else {
                        $this->logger->error($fullpath . ' folder could not create');
                    }
                }
            }
            $fullpath = $parts ='';
        }
        return true;
    }
    public function hasLockFile()
    {
        try {
            if ($this->getFile('.lock')) {
                return true;
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
        return false;
    }

    public function createLockFile()
    {
        try {
            if ($this->getConnection()->write($this->scopeConfig->getValue(self::FTP_SYNC_PATH) . '.lock', 'Y-m-d H:i:s')) {
                return true;
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    public function deleteLockFile()
    {
        try {
            if ($this->getConnection()->rm($this->scopeConfig->getValue(self::FTP_SYNC_PATH) . '.lock')) {
                return true;
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }
}
