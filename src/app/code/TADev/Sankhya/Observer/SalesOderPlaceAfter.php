<?php

/**
* TA Dev
*
* NOTICE OF LICENSE
* @author TA Dev Core Team <suporte@tatecnologia.com>
*/

namespace TADev\Sankhya\Observer;

use Psr\Log\LoggerInterface;

class SalesOderPlaceAfter implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \TADev\Sankhya\Helper\Integration\Authentication
     */
    protected $authentication;

    /**
     * @var \TADev\Sankhya\Helper\Request\Send
     */
    protected $request;

    /**
     * @var \TADev\Sankhya\Model\Integration\CidadesIBGE
     */
    protected $cidadesIBGE;

    /**
     * @var \TADev\Sankhya\Model\Orders
     */
    protected $orders;

    /**
     * @var \Magento\Catalog\Model\Product
     */
    protected $_product;

    public function __construct(
        LoggerInterface $logger,
        \TADev\Sankhya\Helper\Integration\Authentication $authentication,
        \TADev\Sankhya\Helper\Request\Send $request,
        \TADev\Sankhya\Model\Integration\CidadesIBGE $cidadesIBGE,
        \TADev\Sankhya\Model\OrdersFactory $orders
    )
    {
        $this->logger = $logger;
        $this->authentication = $authentication;
        $this->request = $request;
        $this->cidadesIBGE = $cidadesIBGE;
        $this->orders = $orders;
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
     * Send the post order
     *
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            $order = $observer->getData('order');
            $postOrder = $this->generateBodyOrder($order);
            if ($postOrder) {
                $token = $this->getTokenKey();

                $response = $this->request->sendRequest(
                    'pedido-de-venda/',
                    \Zend\Http\Request::METHOD_POST,
                    'Bearer',
                    $token,
                    $postOrder
                );

                if ($response) {
                    // Save the Sankhya Order ID next to the Store Order increment_id
                    $model = $this->orders->create();
                    $model->addData([
                        "increment_id" => $order->getIncrementId(),
                        "sankhya_order_id" => $response['id']
                    ]);
                    $model->save();
                }
            } else {
                $this->logger->info(__('#There was an unexpected error when sending the Order to Sankhya.'));
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->info($e->getMessage());
        }

        return $this;
    }

    /**
     * Generate post order
     *
     * @return string|json encode
     */
    private function generateBodyOrder($order)
    {
        $orderItems = array();
        foreach ($order->getItems() as $item) {
            if (!$item->isDeleted()) {

                $unity = $item->getProduct()->getData('unity_sankhya');
                $qty = $item->getQtyOrdered();

                if($unity == 'KG'){
                    $qty = $qty / 10;
                }

                $orderItems[] = [
                    "produtoId" => $item->getSku(),
                    "quantidade" => $qty
                ];
            }
        }

        if (count($orderItems) <= 0) {
            $this->logger->info(__('The Order must have at least one item to be sent to Sankhya.'));
            return false;
        }

        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        $billingStreet = $billingAddress->getStreet();
        $shippingStreet = $shippingAddress->getStreet();

        $postData = [
            "cliente" => [
                "documento" => $this->removeCharacters($order->getCustomerTaxvat()),
                "email" => $order->getCustomerEmail(),
                "inscricaoEstadual" => "",
                "nomeDoCliente" => $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname(),
                "nomeFantasia" => ""
            ],
            "enderecoDeCobranca" => [
                "bairro" => (!empty($billingStreet[3])) ? $billingStreet[3] : '',
                "cep" => $this->removeCharacters($billingAddress->getPostcode()),
                "codigoIbge" => (int)$this->cidadesIBGE->getCityCode($billingAddress->getCity()),
                "complemento" => (!empty($billingStreet[2])) ? $billingStreet[2] : '',
                "logradouro" => $billingStreet[0],
                "numero" => (!empty($billingStreet[1])) ? $billingStreet[1] : ''
            ],
            "enderecoDeEntrega" => [
                "bairro" => (!empty($shippingStreet[3])) ? $shippingStreet[3] : '',
                "cep" => $this->removeCharacters($shippingAddress->getPostcode()),
                "codigoIbge" => (int)$this->cidadesIBGE->getCityCode($shippingAddress->getCity()),
                "complemento" => (!empty($shippingStreet[2])) ? $shippingStreet[2] : '',
                "logradouro" => $shippingStreet[0],
                "numero" => (!empty($shippingStreet[1])) ? $shippingStreet[1] : ''
            ],
            "itens" => $orderItems,
            "meiosDePagamento" => [
                [
                    "idMeioDePagamento" => 1, // Boleto: 1 / Cartao de credito: 4 / Cartao de debito: 5
                    "parcelas" => 1,
                    "valor" => $order->getGrandTotal()
                ]
            ],
            "valorDoFrete" => $order->getShippingAmount()
        ];
        $this->logger->log('DEBUG', 'postDataOrder: ' . $order->getIncrementId(), $postData);

        return $postData;
    }

    private function removeCharacters($value)
    {
        return preg_replace('/\D/', '', $value);
    }
}
