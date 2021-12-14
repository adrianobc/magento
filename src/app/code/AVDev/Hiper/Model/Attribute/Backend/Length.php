<?php

/**
* AV Dev
*
* NOTICE OF LICENSE
* @author AV Dev Core Team <suporte@avdev.com.br>
*/

namespace AVDev\Hiper\Model\Attribute\Backend;

class Length extends \Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend
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
                __('Length can not be less than 0.')
            );
        } else if ($value > 99999.999) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Length can not be greater than 99999.999.')
            );
        }
        return true;
    }
}
