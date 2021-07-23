<?php
namespace Cb\ImageSync\Provider;

interface FtpInterface
{
    /**
     * Get list of files from remote
     * @return array
     */
    public function getFileList();

    /**
     * Download file from remote system
     * @param string $fileName
     * @return File
     */
    public function getFile(String $fileName);

    /**
     * Upload file to remote system
     * @param String $source
     * @param String $destination
     * @return boolean
     */
    public function uploadFile(String $source, String $destination);

    /**
     * Move file from remote system
     * @return boolean
     */
    public function moveFile(String $source, String $destination);
}