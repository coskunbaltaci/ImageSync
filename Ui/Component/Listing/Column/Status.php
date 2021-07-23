<?php
namespace Cb\ImageSync\Ui\Component\Listing\Column;

class Status implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 0, 'label' => __('Processing')],
            ['value' => 1, 'label' => __('Done')],
            ['value' => 2, 'label' => __('Error')],
        ];
    }
}
