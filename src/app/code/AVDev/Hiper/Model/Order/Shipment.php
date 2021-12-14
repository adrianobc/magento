<?php

/**
* AV Dev
*
* NOTICE OF LICENSE
* @author AV Dev Core Team <suporte@avdev.com.br>
*/

namespace AVDev\Hiper\Model\Order;

use Psr\Log\LoggerInterface;

class Shipment extends \Magento\Framework\Model\AbstractModel
{
    /**
     * @var Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Sales\Model\Convert\Order
     */
    protected $convertOrder;

    /**
     * @var \Magento\Shipping\Model\ShipmentNotifier
     */
    protected $shipmentNotifier;

    /**
     * Shipment constructor
     */
    public function __construct(
        LoggerInterface $logger,
        \Magento\Sales\Model\Convert\Order $convertOrder,
        \Magento\Shipping\Model\ShipmentNotifier $shipmentNotifier
    )
    {
        $this->logger = $logger;
        $this->convertOrder = $convertOrder;
        $this->shipmentNotifier = $shipmentNotifier;
    }

    /**
     * Creates the Order Shipment
     *
     * @return array
     */
    public function createShipment($order)
    {
        try {
            if (!$order->getId()) {
                throw new \Magento\Framework\Exception\LocalizedException(__('The order no longer exists.'));
            }

            if (!$order->canShip()) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('You can\'t create a shipment.')
                );
            }

            // Initialize the order shipment object
            $shipment = $this->convertOrder->toShipment($order);

            // Loop through order items
            foreach ($order->getAllItems() as $orderItem) {
                // Check if order item has qty to ship or is virtual
                if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                    continue;
                }

                $qtyShipped = $orderItem->getQtyToShip();

                // Create shipment item with qty
                $shipmentItem = $this->convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);

                // Add shipment item to shipment
                $shipment->addItem($shipmentItem);
            }

            // Register shipment
            $shipment->register();
            $shipment->getOrder()->setIsInProcess(true);
            try {
                // Save created shipment and order
                $shipment->save();
                $shipment->getOrder()->save();

                try {
                    // Send shipment emails, If you want to stop mail disable below try/catch code
                    $this->shipmentNotifier->notify($shipment);
                } catch (\Exception $e) {
                    //$this->logger->info(__('We can\'t send the invoice email right now.'));
                }

                $shipment->save();
            } catch (\Exception $e) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __($e->getMessage())
                );
            }
        } catch (\Exception $e) {
            //$this->logger->info($e->getMessage());
        }

        return $shipment;
    }
}
