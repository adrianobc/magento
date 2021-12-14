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

class Orders extends AbstractHelper
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
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var \AVDev\Hiper\Model\ResourceModel\Orders\CollectionFactory
     */
    protected $hOrdersCollectionFactory;

    /**
     * @var \AVDev\Hiper\Model\Orders
     */
    protected $hOrdersModel;

    /**
     * @var \AVDev\Hiper\Model\Order\Invoice
     */
    protected $invoice;

    /**
     * @var \AVDev\Hiper\Model\Order\Shipment
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
     * HiperApiService constructor
     */
    public function __construct(
        LoggerInterface $logger,
        \AVDev\Hiper\Helper\Integration\Authentication $authentication,
        \AVDev\Hiper\Helper\Request\Send $request,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \AVDev\Hiper\Model\ResourceModel\Orders\CollectionFactory $hOrdersCollectionFactory,
        \AVDev\Hiper\Model\Orders $hOrdersModel,
        \AVDev\Hiper\Model\Order\Invoice $invoice,
        \AVDev\Hiper\Model\Order\Shipment $shipment,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Api\OrderStatusHistoryRepositoryInterface $orderStatusRepository
    )
    {
        $this->logger = $logger;
        $this->authentication = $authentication;
        $this->request = $request;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->hOrdersCollectionFactory = $hOrdersCollectionFactory;
        $this->hOrdersModel = $hOrdersModel;
        $this->invoice = $invoice;
        $this->shipment = $shipment;
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
                    if (!empty($hOrder->getHiperOrderId())) {
                        $response = $this->request->sendRequest(
                            'pedido-de-venda/eventos/' . $hOrder->getHiperOrderId(),
                            \Zend\Http\Request::METHOD_GET,
                            'Bearer',
                            $token
                        );



                    $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/debuger.log');
                    $logger = new \Zend\Log\Logger();
                    $logger->addWriter($writer);
                    #$logger->info( 'getOrders RESPONSE '.json_encode($response));



                        if ($response) {
                            try {
                                $order = $this->orderRepository->get($order->getId());
                                //$this->logger->log('DEBUG', 'returnHiperOrder: ' . $order->getIncrementId(), $response);
                                $hOrderEvents = json_decode($hOrder->getEvents());

                                // Set on Order the return of Hiper
                                $hOrder->setProcessingStatusCode($response['codigoDaSituacaoDeProcessamento']);
                                $hOrder->setSalesOrderCode($response['codigoDoPedidoDeVenda']);
                                $hOrder->setEvents(json_encode($response['eventos']));
                                $hOrder->save();

                                if ($response['cancelado'] === true && $this->authentication->getCancelOrders()) {
                                    // Cancel Order
                                    $this->_cancelOrder($order);
                                } else {
                                    // Checks the status of the Order in Hiper and whether it will be necessary to send the Order update email to the Customer
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
                                                    //$this->logger->critical($e->getMessage());
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
                                //$this->logger->error($e->getMessage());
                            }
                        }
                    } else {
                        //$this->logger->info(__('Hiper Order not found: %1', $order->getIncrementId()));
                    }
                }

                unset($hOrder);
            }
        } else {
            //$this->logger->info(__('Token can not be null.'));
            return false;
        }

        return $this;
    }

    private function _cancelOrder($order = null)
    {
        try {
            if ($order->getId()) {
                $this->orderManagement->cancel($order->getId());
                //$this->logger->info(__('Order canceled: %1', $order->getIncrementId()));
            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            //$this->logger->info($e->getMessage());
        }
    }
}
