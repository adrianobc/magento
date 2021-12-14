<?php

/**
* AV Dev
*
* NOTICE OF LICENSE
* @author AV Dev Core Team <suporte@avdev.com.br>
*/

namespace AVDev\Hiper\Model;

class Orders extends \Magento\Framework\Model\AbstractModel
{
	const SITUATION_CODE_PROCESSING = 1;
	const SITUATION_CODE_SUCCESS = 2;
	const SITUATION_CODE_ERROR = 3;

    const EVENT_CODE_ORDER_PROCESSING = 1;
    const EVENT_CODE_STOCK_SEPARATION = 2;
    const EVENT_CODE_ISSUANCE_INVOICE = 3;
    const EVENT_CODE_CARRIER_DELIVERY = 4;
    const EVENT_CODE_CANCELLATION = 99;

    public function _construct()
    {
        $this->_init("AVDev\Hiper\Model\ResourceModel\Orders");
    }

    public function getProcessingStatusLabel($code)
    {
        switch ($code) {
            case static::SITUATION_CODE_PROCESSING :
                $label = __('Processing');
                break;
            case static::SITUATION_CODE_SUCCESS :
                $label = __('Successfully processed');
                break;
            case static::SITUATION_CODE_ERROR :
                $label = __('Processed with error');
                break;
            default:
                $label = __('Processing');
                break;
        }

        return $label;
    }

    public function getEventLabel($code)
    {
        switch ($code) {
            case static::EVENT_CODE_ORDER_PROCESSING :
                $label = __('Order processing');
                break;
            case static::EVENT_CODE_STOCK_SEPARATION :
                $label = __('Stock separation');
                break;
            case static::EVENT_CODE_ISSUANCE_INVOICE :
                $label = __('Issuance of invoice');
                break;
            case static::EVENT_CODE_CARRIER_DELIVERY :
                $label = __('Carrier delivery');
                break;
            case static::EVENT_CODE_CANCELLATION :
                $label = __('Cancellation');
                break;
            default:
                $label = __('Order processing');
                break;
        }

        return $label;
    }
}
