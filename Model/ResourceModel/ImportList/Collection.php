<?php
namespace Cb\ImageSync\Model\ResourceModel\ImportList;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'import_id';
	protected $_eventPrefix = 'cb_imagesync_import_list_collection';
	protected $_eventObject = 'import_list_collection';

    /**
     * Define the resource model & the model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Cb\ImageSync\Model\ImportList', 'Cb\ImageSync\Model\ResourceModel\ImportList');
    }
}
