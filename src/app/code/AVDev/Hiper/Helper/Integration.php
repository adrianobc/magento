<?php

/**
* AV Dev
*
* NOTICE OF LICENSE
* @author AV Dev Core Team <suporte@avdev.com.br>
*/

namespace AVDev\Hiper\Helper;

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
     * @var \AVDev\Hiper\Helper\Integration\Authentication
     */
    protected $authentication;

    /**
     * @var \AVDev\Hiper\Helper\Integration\Orders
     */
    protected $orders;

    /**
     * @var \AVDev\Hiper\Helper\Integration\Products
     */
    protected $products;

    /**
     * HiperApiService constructor
     */
    public function __construct(
        LoggerInterface $logger,
        \AVDev\Hiper\Helper\Integration\Authentication $authentication,
        \AVDev\Hiper\Helper\Integration\Orders $orders,
        \AVDev\Hiper\Helper\Integration\Products $products
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
    private function getTokenKey()
    {
        return $this->authentication->getToken();
    }

    /**
     * Function responsible for init the integration
     * @return bool
     */
    public function init($integrationMethod)
    {
        $this->_methods = array(
            \AVDev\Hiper\Model\Integration\Method::IMPORT_UPDATE_PRODUCTS,
            \AVDev\Hiper\Model\Integration\Method::GET_ORDERS
        );

        if (in_array($integrationMethod, $this->_methods)) {
            if ($token = $this->getTokenKey()) {
                if ($integrationMethod == \AVDev\Hiper\Model\Integration\Method::IMPORT_UPDATE_PRODUCTS) {
                    $this->products->getProducts($token);
                } else if ($integrationMethod == \AVDev\Hiper\Model\Integration\Method::GET_ORDERS) {
                    $this->orders->getOrders($token);
                }
            }
        }
    }
}
