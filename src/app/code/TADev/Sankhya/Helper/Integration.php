<?php

/**
* TA Dev
*
* NOTICE OF LICENSE
* @author TA Dev Core Team <suporte@tatecnologia.com>
*/

namespace TADev\Sankhya\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Psr\Log\LoggerInterface;

class Integration extends AbstractHelper
{
    protected $_methods;

    /**
     * @var Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \TADev\Sankhya\Helper\Integration\Authentication
     */
    protected $authentication;

    /**
     * @var \TADev\Sankhya\Helper\Integration\Orders
     */
    protected $orders;

    /**
     * @var \TADev\Sankhya\Helper\Integration\Products
     */
    protected $products;

    /**
     * SankhyaApiService constructor
     */
    public function __construct(
        LoggerInterface $logger,
        \TADev\Sankhya\Helper\Integration\Authentication $authentication,
        \TADev\Sankhya\Helper\Integration\Orders $orders,
        \TADev\Sankhya\Helper\Integration\Products $products
    )
    {
        $this->logger = $logger;
        $this->authentication = $authentication;
        $this->orders = $orders;
        $this->products = $products;
    }

    /**
     * Get API token
     *
     * @return array
     */
    private function getLoginReturn()
    {
        return $this->authentication->getLogin();
    }

    /**
     * Function responsible for init the integration
     * @return bool
     */
    public function init($integrationMethod)
    {
        $this->_methods = array(
            \TADev\Sankhya\Model\Integration\Method::IMPORT_UPDATE_PRODUCTS,
            \TADev\Sankhya\Model\Integration\Method::PUT_ORDERS,
            \TADev\Sankhya\Model\Integration\Method::GET_ORDERS
        );

        if (in_array($integrationMethod, $this->_methods)) {
            if ($sessionid = $this->getLoginReturn()) {
                //$this->logger->info('Status Retorno: ' . $login);
                if ($integrationMethod == \TADev\Sankhya\Model\Integration\Method::IMPORT_UPDATE_PRODUCTS) {
                    //$this->logger->info('Sankhya SessionId: ' . $sessionid);
                    $total = $this->products->getProducts($sessionid);
                    $this->logger->info('Total de produtos atualizados: ' . $total);
                    $this->authentication->getLogout($sessionid);
                } else if ($integrationMethod == \TADev\Sankhya\Model\Integration\Method::PUT_ORDERS) {
                   // $this->orders->putOrders($sessionid);
                   // $this->authentication->getLogout();
                } else if ($integrationMethod == \TADev\Sankhya\Model\Integration\Method::GET_ORDERS) {                    
                    // $this->orders->getCustomer($sessionid);
                      $this->orders->putOrders($sessionid);
                      $this->authentication->getLogout($sessionid);                     
                }
            }
        }
    }
}
