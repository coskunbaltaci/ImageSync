<?php

namespace Cb\ImageSync\Controller\Adminhtml\ImportList;

use Cb\ImageSync\Cron\SaveImportRows;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class GetImportList extends Action
{
    /**
     * @var SaveImportRows
     */
    protected $saveImportRows;

    /**
     * constructor
     *
     * @param SaveImportRows $saveImportRows
     * @param Context $context
     */
    public function __construct(
        SaveImportRows $saveImportRows,
        Context $context
    ) {
        $this->saveImportRows = $saveImportRows;
        parent::__construct($context);
    }

    /**
     * execute action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $this->saveImportRows->execute();
        $this->messageManager->addSuccess(__('Starting Import'));
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('*/*/');
    }
}
