<?php

namespace Cb\ImageSync\Model\Config;

use Magento\Config\Model\Config\CommentInterface;
use Magento\Framework\View\Asset\Repository;

class CommentSampleFile implements CommentInterface
{
    protected $assetRepository;

    public function __construct(Repository $assetRepository)
    {
        $this->assetRepository = $assetRepository;
    }

    public function getCommentText($elementValue)
    {
        $sampleUrl = $this->assetRepository->getUrl('Cb_ImageSync::sample.csv');

        return 'Click <a href="' . $sampleUrl . '" download>here</a> to download sample file';
    }
}
