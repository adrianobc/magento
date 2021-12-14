<?php

/**
* TA Dev
*
* NOTICE OF LICENSE
* @author TA Dev Core Team <suporte@tatecnologia.com>
*/

namespace TADev\Sankhya\Model\ResourceModel\Orders;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    public function _construct()
    {
        $this->_init("TADev\Sankhya\Model\Orders", "TADev\Sankhya\Model\ResourceModel\Orders");
    }
}
