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

use function GuzzleHttp\json_decode;
use function Safe\json_decode as SafeJson_decode;

class Orders extends AbstractHelper
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
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var \TADev\Sankhya\Model\ResourceModel\Orders\CollectionFactory
     */
    protected $hOrdersCollectionFactory;

    /**
     * @var \TADev\Sankhya\Model\Orders
     */
    protected $hOrdersModel;

    /**
     * @var \TADev\Sankhya\Model\Order\Invoice
     */
    protected $invoice;

    /**
     * @var \TADev\Sankhya\Model\Order\Shipment
     */
    protected $shipment;

    /**
     * @var \Magento\Sales\Api\OrderManagementInterface
     */
    protected $orderManagement;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Sales\Api\OrderStatusHistoryRepositoryInterface
     */
    protected $orderStatusRepository;

    /**
     * SankhyaApiService constructor
     */
    public function __construct(
        LoggerInterface $logger,
        \TADev\Sankhya\Helper\Integration\Authentication $authentication,
        \TADev\Sankhya\Helper\Request\Send $request,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \TADev\Sankhya\Model\ResourceModel\Orders\CollectionFactory $hOrdersCollectionFactory,
        \TADev\Sankhya\Model\Orders $hOrdersModel,
        \TADev\Sankhya\Model\Order\Invoice $invoice,
        \TADev\Sankhya\Model\Integration\CidadesIBGE $cidadesIBGE,
        \TADev\Sankhya\Model\OrdersFactory $hOrders,
        \TADev\Sankhya\Model\Order\Shipment $shipment,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Api\OrderStatusHistoryRepositoryInterface $orderStatusRepository,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezoneInterface
    )
    {
        $this->logger = $logger;
        $this->date = $date;
        $this->timezoneInterface = $timezoneInterface;
        $this->authentication = $authentication;
        $this->request = $request;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->hOrdersCollectionFactory = $hOrdersCollectionFactory;
        $this->hOrdersModel = $hOrdersModel;
        $this->invoice = $invoice;
        $this->shipment = $shipment;
        $this->cidadesIBGE = $cidadesIBGE;
        $this->orders = $hOrders;
        $this->orderManagement = $orderManagement;
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
    }

    /**
     * Get API token
     *
     * @return array
     */
    public function getOrders($token = null)
    {
        if (isset($token)) {
            $orders = $this->orderCollectionFactory->create()
                ->addFieldToSelect('entity_id')
                ->addFieldToSelect('increment_id')
                ->addFieldToFilter('status', [
                    'nin' => [
                        'canceled',
                        'complete'
                    ]
                ]);

            foreach ($orders as $order) {
                $hOrder = $this->hOrdersCollectionFactory->create()
                    ->addFieldToSelect('id')
                    ->addFieldToFilter('increment_id', $order->getIncrementId())
                    ->getFirstItem();

                if ($hOrder->getId()) {
                    $hOrder->load($hOrder->getId());
                    if (!empty($hOrder->getSankhyaOrderId())) {
                        $response = $this->request->sendRequest(
                            'pedido-de-venda/eventos/' . $hOrder->getSankhyaOrderId(),
                            \Zend\Http\Request::METHOD_GET,
                            'Bearer',
                            $token
                        );

                        if ($response) {
                            try {
                                $order = $this->orderRepository->get($order->getId());
                                $this->logger->log('DEBUG', 'returnSankhyaOrder: ' . $order->getIncrementId(), $response);
                                $hOrderEvents = json_decode($hOrder->getEvents());

                                // Set on Order the return of Sankhya
                                $hOrder->setProcessingStatusCode($response['codigoDaSituacaoDeProcessamento']);
                                $hOrder->setSalesOrderCode($response['codigoDoPedidoDeVenda']);
                                $hOrder->setEvents(json_encode($response['eventos']));
                                $hOrder->save();

                                if ($response['cancelado'] === true && $this->authentication->getCancelOrders()) {
                                    // Cancel Order
                                    $this->_cancelOrder($order);
                                } else {
                                    // Checks the status of the Order in Sankhya and whether it will be necessary to send the Order update email to the Customer
                                    if (count($response['eventos']) > count($hOrderEvents)) {
                                        $keysIncReturnEvents = array_keys((array)$response['eventos']);
                                        $lastIncReturnEvents = end($keysIncReturnEvents);

                                        $newEventOrder = $response['eventos'][$lastIncReturnEvents]['codigoDoTipoDeEvento'];
                                        $newStatus = $this->authentication->getStatusOrderByEvent($newEventOrder);

                                        if ($newStatus) {
                                            if ($newStatus == 'canceled') {
                                                $this->_cancelOrder($order);
                                            } else if ($order->getStatus() !== $newStatus) {
                                                try {
                                                    // Updates Order status
                                                    $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
                                                    $order->setStatus($newStatus);
                                                    $this->orderRepository->save($order);

                                                    $comment = $order->addCommentToStatusHistory(
                                                        __('Updated status: %1', $this->hOrdersModel->getEventLabel($newEventOrder)), false, true
                                                    )->setIsCustomerNotified(true);
                                                    $this->orderStatusRepository->save($comment);
                                                } catch (\Exception $e) {
                                                    $this->logger->critical($e->getMessage());
                                                }

                                                if ($newEventOrder === 3) {
                                                    if (!$order->hasInvoices()) {
                                                        // Creates the order invoice, if you have not yet
                                                        $this->invoice->createInvoice($order);
                                                    }
                                                } else if ($newEventOrder === 4) {
                                                    if (!$order->hasInvoices()) {
                                                        // Creates the order invoice, if you have not yet
                                                        $this->invoice->createInvoice($order);
                                                    }

                                                    if (!$order->hasShipments()) {
                                                        // Creates the Order Shipment
                                                        $this->shipment->createShipment($order);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                                $this->logger->error($e->getMessage());
                            }
                        }
                    } else {
                        $this->logger->info(__('Sankhya Order not found: %1', $order->getIncrementId()));
                    }
                }

                unset($hOrder);
            }
        } else {
            $this->logger->info(__('Token can not be null.'));
            return false;
        }

        return $this;
    }

    /**
     * Get API token
     *
     * @return array
     */
    public function putOrders($sessionid = null)
    {

        //$this->logger->info('IF False ' . json_encode($entities));
        //$this->logger->log(100,print_r($arrayProdutos,true));

        if (isset($sessionid)) {
            $orders = $this->orderCollectionFactory->create()
                ->addFieldToSelect('*')
                ->addFieldToFilter('is_exported', 0);

            
            foreach ($orders as $order) {
                
                $this->logger->info(__('order # ' . $order->getIncrementId()));                
                
                $billingAddress = $order->getBillingAddress();
                $shippingAddress = $order->getShippingAddress();

                $billingStreet = $billingAddress->getStreet();
                $shippingStreet = $shippingAddress->getStreet();

                $customerData = [
                    "cliente" => [
                        "documento" => $this->removeCharacters($order->getCustomerTaxvat()),
                        "email" => substr(mb_convert_case($order->getCustomerEmail(),MB_CASE_LOWER),0,80),
                        "nomeDoCliente" => substr(mb_convert_case($order->getCustomerFirstname(), MB_CASE_UPPER) . ' ' . mb_convert_case($order->getCustomerLastname(), MB_CASE_UPPER),0,80),
                    ],
                    "enderecoDeCobranca" => [
                        "cep" => $this->removeCharacters($billingAddress->getPostcode()),
                        "complemento" => $billingStreet[2] ? substr(mb_convert_case($billingStreet[2],MB_CASE_UPPER),0,60) : 'COMPLEMENTO NÃO INFORMADO',
                        "numero" => $billingStreet[1] ? substr($billingStreet[1],0,10) : 'EM BRANCO'
                    ],
                    "enderecoDeEntrega" => [
                        "cep" => $this->removeCharacters($shippingAddress->getPostcode()),
                        "complemento" => $shippingStreet[2] ? substr(mb_convert_case($shippingStreet[2],MB_CASE_UPPER),0,60) : 'COMPLEMENTO NÃO INFORMADO',
                        "numero" => $shippingStreet[1] ? substr($shippingStreet[1],0,10) : 'EM BRANCO'
                    ],
                ];
                
                $date = date('d/m/Y',strtotime($order->getCreatedAt()));
                $custid = $this->getCustomer($sessionid,$customerData);               

                $postData = [
                    "serviceName" => "CACSP.incluirNota",
                    "requestBody" => [
                        "nota" => [
                            "cabecalho" => [
                                "NUNOTA" => [
                                ],
                                "CODPARC" => [
                                    "$" =>  json_encode($custid) 
                                ],
                                "DTNEG" => [
                                    "$" =>  $date
                                ],
                                "CODTIPOPER" => [
                                    "$" => "1005"
                                ],
                                "CODTIPVENDA"=>[
                                   "$"=>"11"
                                ],
                                "CODEMP" => [
                                    "$" => "1"
                                ],
                                "TIPMOV" => [
                                    "$" => "P"
                                ],
                                "NUMPEDIDO2" => [
                                    "$" =>  $order->getIncrementId() 
                                ],
                                "VLRFRETE" => [
                                    "$" =>  (float)  number_format($order->getShippingAmount(),2,".","") 
                                ]
                            ]
                        ]
                    ]
                ];               

                // foreach($orderItems as $key => $item){
                //     $postData['requestBody']['nota']['itens'][] =  $item;
                // } testet

                // $this->logger->log(100,print_r($postData,true));
                // $this->logger->info(__('exportOrders customerData '.json_encode($postData,JSON_FORCE_OBJECT)));
                try {                                                       
                    
                    $response = $this->request->sendRequest(
                        'MGECOM', '?serviceName=CACSP.incluirNota&mgeSession='. $sessionid .'&outputType=json',
                        \Zend\Http\Request::METHOD_POST,
                        $sessionid,
                        $postData
                    );

                    if (isset($response)) {
                        //$logger->info( 'exportOrders RESPONSE '.json_encode($response));
                        if ($response['status'] == '1') {
                            // Save the Sankhya Order ID next to the Store Order increment_id
                            $model = $this->orders->create();
                            $model->addData([
                                "increment_id" => $order->getIncrementId(),
                                "sankhya_order_id" => $response['responseBody']['pk']['NUNOTA']['$'],
                                "sales_order_code" => $response['responseBody']['pk']['NUNOTA']['$']
                            ]);
                            $model->save();

                            $nunota = $response['responseBody']['pk']['NUNOTA']['$'];

                            $orderItems = array();
                            foreach ($order->getItems() as $item) {
                                if (!$item->isDeleted()) {

                                    $unity = $item->getProduct()->getData('unity_sankhya');
                                    $qty = $item->getQtyOrdered();
                                    $price = (float) $item->getRowTotal();

                                    if($unity == 'KG'){
                                        $qty = $qty / 10;
                                        //$price = $price * 10;
                                    }

                                    $postData = [
                                        "serviceName" => "CACSP.incluirNota",
                                        "requestBody" => [
                                            "nota" => [
                                                "cabecalho" => [
                                                    "NUNOTA" => [
                                                        "$" => $nunota
                                                    ]
                                                ],
                                                "itens" => [
                                                    "INFORMAPRECO" => "True",
                                                    "item" => [
                                                        "NUNOTA" => [
                                                            "$" => $nunota
                                                        ],
                                                        "SEQUENCIA" => [
                                                            "$" => ""
                                                        ],
                                                        "CODPROD" => [
                                                            "$" => $item->getSku()
                                                        ],
                                                        "QTDNEG" => [
                                                            "$" => json_encode((float) number_format($qty, 3))
                                                        ],
                                                        "PERCDESC" => [
                                                            "$" => json_encode((float) $item->getDiscountPercent())
                                                        ],
                                                        "VLRTOT" => [
                                                            "$" => json_encode($price)
                                                        ],
                                                        "CODLOCALORIG" => [
                                                            "$" => "110"
                                                        ],
                                                        "CODVOL" => [
                                                            "$" => $unity
                                                        ]
                                                    ]
                                                ]                                        
                                            ]
                                        ]
                                    ];
                                    
                                    try {                                                       
                    
                                        $response = $this->request->sendRequest(
                                            'MGECOM', '?serviceName=CACSP.incluirNota&mgeSession='. $sessionid .'&outputType=json',
                                            \Zend\Http\Request::METHOD_POST,
                                            $sessionid,
                                            $postData
                                        );

                                    } catch (\Exception $e) {
                                        $this->logger->info($e->getMessage());
                                    }
                                    /* if(!isset($orderItems)){
                                        $orderItems = json_encode($tmpItems,JSON_FORCE_OBJECT);
                                    }else{
                                        $orderItems .= ',' . json_encode($tmpItems,JSON_FORCE_OBJECT);
                                    } */
                                    
                                }
                            }
                            // foreach($orderItems as $item){
                            //     $this->logger->log(100,print_r($item,true));
                            // }
                        
                            // $this->logger->info(__('Data '. $orderItems ));                            
    
                            /** salvamos uma flag no pedido para depois não precisar fazer 
                                uma busca em todos, e com isso so puxar os que não esta flageados **/
    
                            $order->setIsExported(1);
                            $order->save();
    
                            $this->logger->info(__( 'Order #'.$order->getIncrementId().' was exported'));
    
                        }else{
                            $this->logger->info(__( 'Error on export Order #'.$order->getIncrementId().' to Sankhya'));
                        }                        
                    }  

                } catch (\Exception $e) {
                    $this->logger->info($e->getMessage());
                }

            }
        } else {
            return false;
        }

        return $this;
    }

    /**
     * Get Sankhya Customer
     *
     * @param null $sessionid
     * @param null $customerData
     * @return int
     */
    public function getCustomer($sessionid,$customerData): int
    {
        $custid = 0;
        $cgccpf = $customerData['cliente']['documento'];
        If(isset($sessionid)){
            $offset = 0;
            $entities = array();
            $postBody = [
                "serviceName" => "CRUDServiceProvider.loadRecords",
                "requestBody" => [
                    "dataSet" => [
                        "rootEntity" => "Parceiro",
                        "includePresentationFields" => "N",
                        "offsetPage" => json_encode($offset),
                        "criteria" => [
                            "expression" => [
                                "$" => "this.CLIENTE = 'S' and this.CGC_CPF = '" . $cgccpf . "'"
                            ]
                        ],
                        "entity" => [
                            "fieldset" => [
                                "list" => "CODPARC"
                            ]
                        ]
                    ]
                ]
            ];

            $response = $this->request->sendRequest(
                'MGE','?serviceName=CRUDServiceProvider.loadRecords&outputType=json',
                \Zend\Http\Request::METHOD_POST,
                $sessionid,
                $postBody
            );

            if (array_key_exists('entity',$response['responseBody']['entities'])){                
                $custid = $response['responseBody']['entities']['entity']['f0']['$'];
            } else{
                $custid = $this->putCustomer($sessionid,$customerData);
            }
        }
        return $custid;
    }

    /**
     * Put Sankhya Customer
     *
     * @param null $sessionid
     * @param null $customerData
     * @return int
     */
    public function putCustomer($sessionid,$customerData): int
    {
        $custid = 0;
        If(isset($sessionid)){
            $postBody = [
                "serviceName" => "CRUDServiceProvider.saveRecord",
                "requestBody" => [
                    "dataSet" => [
                        "rootEntity" => "Parceiro",
                        "includePresentationFields" => "S",
                        "dataRow" => [
                            "localFields" => [
                                "TIPPESSOA" => [
                                    "$" => strlen($customerData['cliente']['documento'])>11 ? 'J' : 'F'
                                ],
                                "NOMEPARC" => [ 
                                    "$" => $customerData['cliente']['nomeDoCliente']
                                ],
                                "CGC_CPF" => [
                                    "$" => $customerData['cliente']['documento']
                                ],
                                "EMAIL" => [
                                    "$" => $customerData['cliente']['email']
                                ],
                                "CEP" => [
                                    "$" => $customerData['enderecoDeCobranca']['cep']
                                ],
                                "NUMEND" => [
                                    "$" => $customerData['enderecoDeCobranca']['numero']
                                ],
                                "COMPLEMENTO" => [
                                    "$" => $customerData['enderecoDeCobranca']['complemento']
                                ],
                                "CODCID" => [
                                    "$" => "6"
                                ],
                                "ATIVO" => [
                                    "$" => "S"
                                ],
                                "CLIENTE" => [
                                    "$" => "S"
                                ],
                                "CLASSIFICMS" => [
                                    "$" => "C"
                                ]
                            ]
                        ],
                        "entity" => [
                            "fieldset" => [
                                "list" => "CODPARC"
                            ]
                        ]
                    ]
                ]                    
            ];            

            $response = $this->request->sendRequest(
                'MGE','?serviceName=CRUDServiceProvider.saveRecord&outputType=json',
                \Zend\Http\Request::METHOD_POST,
                $sessionid,
                $postBody
            );
            $this->logger->log(100,print_r($response,true));
            $custid = $response['responseBody']['entities']['entity']['CODPARC']['$'];
        }
        return $custid;
    }

    private function removeCharacters($value)
    {
        return preg_replace('/\D/', '', $value);
    }

    private function _cancelOrder($order = null)
    {
        try {
            if ($order->getId()) {
                $this->orderManagement->cancel($order->getId());
                $this->logger->info(__('Order canceled: %1', $order->getIncrementId()));
            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $this->logger->info($e->getMessage());
        }
    }
}
