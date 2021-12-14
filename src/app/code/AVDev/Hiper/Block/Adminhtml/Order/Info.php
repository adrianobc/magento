<?php

/**
* AV Dev
*
* NOTICE OF LICENSE
* @author AV Dev Core Team <suporte@avdev.com.br>
*/

namespace AVDev\Hiper\Block\Adminhtml\Order;

class Info extends \Magento\Sales\Block\Adminhtml\Order\AbstractOrder
{
    protected $_hOrder = null;

    /**
     * @var \AVDev\Hiper\Model\ResourceModel\Orders\CollectionFactory
     */
    protected $hOrdersCollectionFactory;

    /**
     * @var \AVDev\Hiper\Model\Orders
     */
    protected $hOrderModel;

    /**
     * Constructor
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Sales\Helper\Admin $adminHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Sales\Helper\Admin $adminHelper,
        \AVDev\Hiper\Model\ResourceModel\Orders\CollectionFactory $hOrdersCollectionFactory,
        \AVDev\Hiper\Model\Orders $hOrderModel,
        array $data = []
    ) {
        $this->hOrdersCollectionFactory = $hOrdersCollectionFactory;
        $this->hOrderModel = $hOrderModel;
        parent::__construct($context, $registry, $adminHelper, $data);
    }

    public function hasHiperOrder()
    {
        $hOrder = $this->hOrdersCollectionFactory->create()
            ->addFieldToSelect('id')
            ->addFieldToFilter('increment_id', $this->getOrder()->getIncrementId())
            ->getFirstItem();

        if ($hOrder->getId()) {
            $hOrder->load($hOrder->getId());

            $this->_hOrder = $hOrder;

            return true;
        }

        return false;
    }

    public function getHiperOrderDetails()
    {
        return $this->_hOrder;
    }

    public function getProcessingStatusLabel()
    {
        return $this->hOrderModel->getProcessingStatusLabel($this->_hOrder->getProcessingStatusCode());
    }

    public function hasEventsOrder()
    {
        return (!empty($this->_hOrder->getEvents())) ? (count(json_decode($this->_hOrder->getEvents())) > 0) : false;
    }

    public function getEventsOrder()
    {
        return json_decode($this->_hOrder->getEvents());
    }

    public function getEventsOrderColumns()
    {
        return [
            'event_code_type' => __('Event Type Code'),
            'event_label' => __('Event'),
            'date_event' => __('Date'),
            'observation' => __('Observation'),
        ];
    }

    public function getEventLabel($eventCodeType)
    {
        return $this->hOrderModel->getEventLabel($eventCodeType);
    }
}
