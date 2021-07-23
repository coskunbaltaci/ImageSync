<?php
namespace Cb\ImageSync\Model\ResourceModel;

class ImportList extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context
    )
    {
        parent::__construct($context);
    }

    protected function _construct()
    {
        $this->_init('cb_imagesync_import_list', 'import_id');
    }
}
