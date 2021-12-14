<?php

/**
* AV Dev
*
* NOTICE OF LICENSE
* @author AV Dev Core Team <suporte@avdev.com.br>
*/

namespace AVDev\Hiper\Observer;

use Psr\Log\LoggerInterface;

class SalesOderPlaceAfter implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \AVDev\Hiper\Helper\Integration\Authentication
     */
    protected $authentication;

    /**
     * @var \AVDev\Hiper\Helper\Request\Send
     */
    protected $request;

    /**
     * @var \AVDev\Hiper\Model\Integration\CidadesIBGE
     */
    protected $cidadesIBGE;

    /**
     * @var \AVDev\Hiper\Model\Orders
     */
    protected $orders;

    /**
     * @var \Magento\Catalog\Model\Product
     */
    protected $_product;

    public function __construct(
        LoggerInterface $logger,
        \AVDev\Hiper\Helper\Integration\Authentication $authentication,
        \AVDev\Hiper\Helper\Request\Send $request,
        \AVDev\Hiper\Model\Integration\CidadesIBGE $cidadesIBGE,
        \AVDev\Hiper\Model\OrdersFactory $orders
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

                if ($response['message'] == 'Pedido recebido e em processamento.'){
                    // Save the Hiper Order ID next to the Store Order increment_id
                    $model = $this->orders->create();
                    $model->addData([
                        "increment_id" => $order->getIncrementId(),
                        "hiper_order_id" => $response['id']
                    ]);
                    $model->save();


                    /** salvamos uma flag no pedido para depois não precisar fazer 
                        uma busca em todos e so puxar os que não esta flageados **/

                    $order->setIsExported(1);
                    $order->save();

                }
            } else {
                //$this->logger->info(__('There was an unexpected error when sending the Order to Hiper.'));
                return false;
            }
        } catch (\Exception $e) {
            //$this->logger->info($e->getMessage());
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

                $unity = $item->getProduct()->getData('unity_hiper');
                $qty = $item->getQtyOrdered();

                if($unity == 'KG'){
                   $qty = $qty / 10;
                }

                $orderItems[] = [
                    "produtoId" => $item->getSku(),
                    "quantidade" => (float) number_format($qty,3)
                ];
            }
        }

        if (count($orderItems) <= 0) {
            //$this->logger->info(__('The Order must have at least one item to be sent to Hiper.'));
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
                "bairro" => (!empty($billingStreet[3])) ? substr($billingStreet[3],60,0) : 'bairro não informado',
                "cep" => $this->removeCharacters($billingAddress->getPostcode()),
                "codigoIbge" => (int)$this->cidadesIBGE->getCityCode($billingAddress->getCity()),
                "complemento" => (!empty($billingStreet[2])) ? $billingStreet[2] : 'complemento não informado',
                "logradouro" => $billingStreet[0],
                "numero" => (!empty($billingStreet[1])) ? $billingStreet[1] : 'numero não informado'
            ],
            "enderecoDeEntrega" => [
                "bairro" => (!empty($shippingStreet[3])) ? substr($shippingStreet[3],60,0): 'bairro não informado',
                "cep" => $this->removeCharacters($shippingAddress->getPostcode()),
                "codigoIbge" => (int)$this->cidadesIBGE->getCityCode($shippingAddress->getCity()),
                "complemento" => (!empty($shippingStreet[2])) ? $shippingStreet[2] : 'complemento não informado',
                "logradouro" => $shippingStreet[0],
                "numero" => (!empty($shippingStreet[1])) ? $shippingStreet[1] : 'numero não informado'
            ],
            "itens" => $orderItems,
            "meiosDePagamento" => [
                [
                    "idMeioDePagamento" => 1, // Boleto: 1 / Cartao de credito: 4 / Cartao de debito: 5
                    "parcelas" => 1,
                    "valor" => (float) number_format($order->getGrandTotal(),1,".","")
                ]
            ],
            "valorDoFrete" =>  (float)  number_format($order->getShippingAmount(),1,".","")
        ];
        //$this->logger->log('DEBUG', 'postDataOrder: ' . $order->getIncrementId(), $postData);

        return $postData;
    }

    private function removeCharacters($value)
    {
        return preg_replace('/\D/', '', $value);
    }
}
