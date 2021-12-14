<?php

/**
* AV Dev
*
* NOTICE OF LICENSE
* @author AV Dev Core Team <suporte@avdev.com.br>
*/

namespace AVDev\Hiper\Helper\Integration;

use Magento\Framework\App\Helper\AbstractHelper;
use Psr\Log\LoggerInterface;

class Authentication extends AbstractHelper
{
    const SYSTEM_GENERAL_HIPER_TOKEN = 'hiper/general/hiper_token';
    const SYSTEM_GENERAL_SYNC_POINT = 'hiper/general/sync_point';
    const SYSTEM_GENERAL_CANCEL_ORDERS = 'hiper/general/cancel_orders';

    const SYSTEM_ORDERS_EVENT_ORDER_PROCESSING = 'hiper/orders/event_order_processing';
    const SYSTEM_ORDERS_EVENT_STOCK_SEPARATION = 'hiper/orders/event_stock_separation';
    const SYSTEM_ORDERS_EVENT_ISSUANCE_INVOICE = 'hiper/orders/event_invoice_issue';
    const SYSTEM_ORDERS_EVENT_CARRIER_DELIVERY = 'hiper/orders/event_delivery_carrier';

    /**
     * @var Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var AVDev\Hiper\Helper\Request\Send
     */
    protected $request;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        \AVDev\Hiper\Helper\Request\Send $request
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->request = $request;
    }

    /**
     * Function responsible for init the integration
     * @return string
     */
    public function getSecurityKey()
    {
        return $this->scopeConfig->getValue(static::SYSTEM_GENERAL_HIPER_TOKEN, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }






    /**
     * Get the sync point
     * The synchronization point is used to control the package of the latest product updates in ERP Hiper
     * @return string
     */
    public function getSyncPoint()
    {
        return $this->scopeConfig->getValue(static::SYSTEM_GENERAL_SYNC_POINT, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * Checks whether the option to cancel Orders is enabled
     * @return string
     */
    public function getCancelOrders()
    {
        return $this->scopeConfig->getValue(static::SYSTEM_GENERAL_CANCEL_ORDERS, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * Checks whether the option to cancel Orders is enabled
     * @return string
     */
    public function getStatusOrderByEvent($eventCode = null)
    {
        if (isset($eventCode)) {
            switch ($eventCode) {
                case \AVDev\Hiper\Model\Orders::EVENT_CODE_ORDER_PROCESSING :
                    $statusCode = static::SYSTEM_ORDERS_EVENT_ORDER_PROCESSING;
                    break;
                case \AVDev\Hiper\Model\Orders::EVENT_CODE_STOCK_SEPARATION :
                    $statusCode = static::SYSTEM_ORDERS_EVENT_STOCK_SEPARATION;
                    break;
                case \AVDev\Hiper\Model\Orders::EVENT_CODE_ISSUANCE_INVOICE :
                    $statusCode = static::SYSTEM_ORDERS_EVENT_ISSUANCE_INVOICE;
                    break;
                case \AVDev\Hiper\Model\Orders::EVENT_CODE_CARRIER_DELIVERY :
                    $statusCode = static::SYSTEM_ORDERS_EVENT_CARRIER_DELIVERY;
                    break;
                case \AVDev\Hiper\Model\Orders::EVENT_CODE_CANCELLATION :
                    $statusCode = 'canceled';
                    break;
                default:
                    $statusCode = false;
                    break;
            }

            if ($statusCode) {
                return $this->scopeConfig->getValue($statusCode, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            }
        }

        return false;
    }

    public function getToken()
    {
        $securityKey = $this->getSecurityKey();
        //$this->logger->info(__('Security Key: ') . $securityKey);

        $response = $this->request->sendRequest('auth/gerar-token/' . $securityKey);
	$pkCount = (is_array($response['errors']) ? count($response['errors']) : 0);
        if ($pkCount > 0) {
            //$this->logger->log('DEBUG', 'errorGerarToken', $response['errors']);
            return false;
        } else {
            return $response['token'];
        }
    }
}
