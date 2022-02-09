<?php

/**
* TA Dev
*
* NOTICE OF LICENSE
* @author TA Dev Core Team <suporte@tatecnologia.com>
*/

namespace TADev\Sankhya\Cron;

use Psr\Log\LoggerInterface;
use Magento\Framework\App\AreaList;
use TADev\Sankhya\Helper\Integration;

class ExportOrders
{
  /**
     * @var Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\AreaList
     */
    private $areaList;

    /**
     * @var \TADev\Sankhya\Helper\Integration
     */
    private $helperIntegration;

    public function __construct(
        LoggerInterface $logger,
        AreaList $areaList,
        Integration $helperIntegration
    )
    {
        $this->logger = $logger;
        $this->areaList = $areaList;
        $this->helperIntegration = $helperIntegration;
    }

    /**
     * Write to system.log
     * Calls the function responsible for init the integration
     * @return void
     */
    public function execute()
    {
        $areaObject = $this->areaList->getArea(\Magento\Framework\App\Area::AREA_ADMINHTML);
        $areaObject->load(\Magento\Framework\App\Area::PART_TRANSLATE);
        $this->logger->info(__('Initialization of Send Orders to ERP - Sankhya Integration'));

        $this->helperIntegration->init(\TADev\Sankhya\Model\Integration\Method::IMPORT_UPDATE_PRODUCTS);

        $this->logger->info(__('Finalization of Send Orders to ERP - Sankhya Integration'));
    }
}
