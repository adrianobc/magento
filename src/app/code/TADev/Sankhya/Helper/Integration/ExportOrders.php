<?php

/**
* AV Dev
*
* NOTICE OF LICENSE
* @author AV Dev Core Team <suporte@avdev.com.br>
*/

namespace TADev\Sankhya\Helper\Integration;

use Psr\Log\LoggerInterface;

class ExportOrders
{


    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $orderCollectionFactory;


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
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \AVDev\Hiper\Helper\Integration\Authentication $authentication,
        \AVDev\Hiper\Helper\Request\Send $request,
        \AVDev\Hiper\Model\Integration\CidadesIBGE $cidadesIBGE,
        \AVDev\Hiper\Model\OrdersFactory $hOrders
    )
    {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->logger = $logger;
        $this->authentication = $authentication;
        $this->request = $request;
        $this->cidadesIBGE = $cidadesIBGE;
        $this->orders = $hOrders;
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
    public function execute()
    {        
        $token = $this->getTokenKey();
        $this->getOrders($token);
    }


    /**
     * Get API token
     *
     * @return array
     */
    public function getOrders($token = null)
    {

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/debuger.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);

        if (isset($token)) {
            $orders = $this->orderCollectionFactory->create()
                ->addFieldToSelect('*')
                ->addFieldToFilter('is_exported', 0);

            $orderItems = array();
            foreach ($orders as $order) {
                
                $logger->info('order #'.$order->getIncrementId());

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

                $billingAddress = $order->getBillingAddress();
                $shippingAddress = $order->getShippingAddress();

                $billingStreet = $billingAddress->getStreet();
                $shippingStreet = $shippingAddress->getStreet();

                $postData = [
                    "cliente" => [
                        "documento" => $this->removeCharacters($order->getCustomerTaxvat()),
                        "email" => substr($order->getCustomerEmail(),0,80),
                        "inscricaoEstadual" => "",
                        "nomeDoCliente" => substr($order->getCustomerFirstname() . ' ' . $order->getCustomerLastname(),0,80),
                        "nomeFantasia" => ""
                    ],
                    "enderecoDeCobranca" => [
                        "bairro" => $billingStreet[3] ? substr($billingStreet[3],0,60) : 'bairro não informado',
                        "cep" => $this->removeCharacters($billingAddress->getPostcode()),
                        "codigoIbge" => (int)$this->cidadesIBGE->getCityCode($billingAddress->getCity()),
                        "complemento" => $billingStreet[2] ? substr($billingStreet[2],0,60) : 'complemento não informado',
                        "logradouro" => substr($billingStreet[0],0,60),
                        "numero" => $billingStreet[1] ? substr($billingStreet[1],0,10) : 'em branco'
                    ],
                    "enderecoDeEntrega" => [
                        "bairro" => $shippingStreet[3] ? substr($shippingStreet[3],0,60): 'bairro não informado',
                        "cep" => $this->removeCharacters($shippingAddress->getPostcode()),
                        "codigoIbge" => (int)$this->cidadesIBGE->getCityCode($shippingAddress->getCity()),
                        "complemento" => $shippingStreet[2] ? substr($shippingStreet[2],0,60) : 'complemento não informado',
                        "logradouro" => substr($shippingStreet[0],0,60),
                        "numero" => $shippingStreet[1] ? substr($shippingStreet[1],0,10) : 'em branco'
                    ],
                    "itens" => $orderItems,
                    "meiosDePagamento" => [
                        [
                            "idMeioDePagamento" => 1, // Boleto: 1 / Cartao de credito: 4 / Cartao de debito: 5
                            "parcelas" => 1,
                            "valor" => (float) number_format($order->getGrandTotal(),1,".","")
                        ]
                    ],
                    "valorDoFrete" =>  (float)  number_format($order->getShippingAmount(),1,".",""),
					"numeroPedidoDeVenda" =>  $order->getIncrementId()
                ];

                $logger->info( 'exportOrders postData '.json_encode($postData));

               try {
                       
                    $response = $this->request->sendRequest(
                        'MGE','pedido-de-venda/',
                        \Zend\Http\Request::METHOD_POST,
                        'Bearer',
                        $token,
                        $postData
                    );

                    if(isset($response)){
                        $logger->info( 'exportOrders RESPONSE '.json_encode($response));
                    }
                
                    if ($response['message'] == 'Pedido recebido e em processamento.') {
                        // Save the Hiper Order ID next to the Store Order increment_id
                        $model = $this->orders->create();
                        $model->addData([
                            "increment_id" => $order->getIncrementId(),
                            "hiper_order_id" => $response['id']
                        ]);
                        $model->save();


                        /** salvamos uma flag no pedido para depois não precisar fazer 
                            uma busca em todos, e com isso so puxar os que não esta flageados **/

                        $order->setIsExported(1);
                        $order->save();

                        $logger->info( 'Order #'.$order->getIncrementId().' was exported');

                    }

                } catch (\Exception $e) {
                    //$this->logger->info($e->getMessage());
                }

            }
        } else {
            //$this->logger->info(__('Token can not be null.'));
            return false;
        }

        return $this;
    }

    private function removeCharacters($value)
    {
        return preg_replace('/\D/', '', $value);
    }
}
