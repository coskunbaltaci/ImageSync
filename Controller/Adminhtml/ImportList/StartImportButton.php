<?php

namespace Cb\ImageSync\Controller\Adminhtml\ImportList;

use Cb\ImageSync\Cron\Sync;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class StartImportButton extends Action
{
    /**
     * @var Sync
     */
    protected $sync;

    /**
     * constructor
     *
     * @param Sync $sync
     * @param Context $context
     */
    public function __construct(
        Sync $sync,
        Context $context
    ) {
        $this->sync = $sync;
        parent::__construct($context);
    }

    /**
     * execute action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $this->sync->execute();
        $this->messageManager->addSuccess(__('Starting Import'));
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('*/*/');
    }
}
