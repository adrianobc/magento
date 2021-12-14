<?php

/**
* TA Dev
*
* NOTICE OF LICENSE
* @author TA Dev Core Team <suporte@tatecnologia.com>
*/

namespace TADev\Sankhya\Model\ResourceModel;

class Orders extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    public function _construct()
    {
        $this->_init("tadev_sankhya_orders", "id");
    }
}
