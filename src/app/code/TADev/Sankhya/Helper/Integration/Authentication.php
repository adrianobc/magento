<?php

/**
* TA Dev
*
* NOTICE OF LICENSE
* @author TA Dev Core Team <suporte@tatecnologia.com>
*/

namespace TADev\Sankhya\Helper\Integration;

use Magento\Framework\App\Helper\AbstractHelper;
use Psr\Log\LoggerInterface;

class Authentication extends AbstractHelper
{
    const SYSTEM_GENERAL_SANKHYA_USER = 'sankhya/general/sankhya_user';
    const SYSTEM_GENERAL_SANKHYA_PASS = 'sankhya/general/sankhya_password';
    const SYSTEM_GENERAL_SYNC_POINT = 'sankhya/general/sync_point';
    const SYSTEM_GENERAL_CANCEL_ORDERS = 'sankhya/general/cancel_orders';

    const SYSTEM_ORDERS_EVENT_ORDER_PROCESSING = 'sankhya/orders/event_order_processing';
    const SYSTEM_ORDERS_EVENT_STOCK_SEPARATION = 'sankhya/orders/event_stock_separation';
    const SYSTEM_ORDERS_EVENT_ISSUANCE_INVOICE = 'sankhya/orders/event_invoice_issue';
    const SYSTEM_ORDERS_EVENT_CARRIER_DELIVERY = 'sankhya/orders/event_delivery_carrier';

    /**
     * @var Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var TADev\Sankhya\Helper\Request\Send
     */
    protected $request;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        \TADev\Sankhya\Helper\Request\Send $request
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
    public function getSankhyaUser()
    {
        return $this->scopeConfig->getValue(static::SYSTEM_GENERAL_SANKHYA_USER, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getSankhyaPass()
    {
        return $this->scopeConfig->getValue(static::SYSTEM_GENERAL_SANKHYA_PASS, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get the sync point
     * The synchronization point is used to control the package of the latest product updates in ERP Sankhya
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
                case \TADev\Sankhya\Model\Orders::EVENT_CODE_ORDER_PROCESSING :
                    $statusCode = static::SYSTEM_ORDERS_EVENT_ORDER_PROCESSING;
                    break;
                case \TADev\Sankhya\Model\Orders::EVENT_CODE_STOCK_SEPARATION :
                    $statusCode = static::SYSTEM_ORDERS_EVENT_STOCK_SEPARATION;
                    break;
                case \TADev\Sankhya\Model\Orders::EVENT_CODE_ISSUANCE_INVOICE :
                    $statusCode = static::SYSTEM_ORDERS_EVENT_ISSUANCE_INVOICE;
                    break;
                case \TADev\Sankhya\Model\Orders::EVENT_CODE_CARRIER_DELIVERY :
                    $statusCode = static::SYSTEM_ORDERS_EVENT_CARRIER_DELIVERY;
                    break;
                case \TADev\Sankhya\Model\Orders::EVENT_CODE_CANCELLATION :
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

    public function getLogin()
    {
        $UserLogin = $this->getSankhyaUser();
        $UserPass = $this->getSankhyaPass();
        //$this->logger->info(__('User Pass: ') . $UserPass);

        $postBody = [
            "serviceName" => "MobileLoginSP.login",
            "requestBody" => [
                "NOMUSU" => [
                    "$" => $UserLogin
                ],
                "INTERNO" =>[
                    "$"=> $UserPass
                ],
                "KEEPCONNECTED"=>[
                    "$" => "S"
                ]
            ]
        ];

        $response = $this->request->sendRequest('MGE','?serviceName=MobileLoginSP.login&outputType=json',
            \Zend\Http\Request::METHOD_POST, '',$postBody
            );
        //$this->logger->info('TADev_LoginStatus TESTE LOG: ' );
        //$this->logger->log(100,print_r($response,true));
        if ($response){
            if ($response['status'] == 0) {
                $this->logger->info('TADev_LoginStatus: ' .  $response['statusMessage'] . ' ## - Login mal sucedido!!!');
                return false;
            } else {
                foreach($response['responseBody'] as $key => $value){
                    if ($key == 'jsessionid'){
                        $this->logger->info('Sankhya SessionId: ' . $value['$'] . ' ## - Login realizado com sucesso!!!');
                        return $value['$'];
                    }
                }
            }
        } else {
            $this->logger->info('### Servidor Sankhya:  Sem conexão - Verifique se o serviço está em execução !!!');
        }

    }

    public function getLogout($sessionid = null)
    {

        $postBody = [
            "serviceName" => "MobileLoginSP.logout",
            "status" => "1",
            "pendingPrinting" => "false",
            ];

        $response = $this->request->sendRequest('MGE','?serviceName=MobileLoginSP.logout&outputType=json',
            \Zend\Http\Request::METHOD_POST, $sessionid, $postBody
        );
        $this->logger->info('Sankhya SessionId: ' . $sessionid .' ## - LogoutStatus: Logout realizado com sucesso!' );
    }

}
