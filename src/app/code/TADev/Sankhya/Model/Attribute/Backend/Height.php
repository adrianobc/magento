<?php

/**
* TA Dev
*
* NOTICE OF LICENSE
* @author TA Dev Core Team <suporte@tatecnologia.com>
*/

namespace TADev\Sankhya\Model\Attribute\Backend;

class Height extends \Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend
{
    /**
     * Validate
     * @param \Magento\Catalog\Model\Product $object
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return bool
     */
    public function validate($object)
    {
        $value = $object->getData($this->getAttribute()->getAttributeCode());
        if ($value < 0) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Height can not be less than 0.')
            );
        } else if ($value > 99999.999) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Height can not be greater than 99999.999.')
            );
        }
        return true;
    }
}
