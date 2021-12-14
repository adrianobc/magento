<?php

/**
* AV Dev
*
* NOTICE OF LICENSE
* @author AV Dev Core Team <suporte@avdev.com.br>
*/

namespace AVDev\Hiper\Cron;

use Psr\Log\LoggerInterface;
use Magento\Framework\App\AreaList;
use AVDev\Hiper\Helper\Integration;

class ImportUpdateProducts
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
     * @var \AVDev\Hiper\Helper\Integration
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
        $this->logger->info(__('Initialization of Import and Update of Products - Hiper Integration'));

        $this->helperIntegration->init(\AVDev\Hiper\Model\Integration\Method::IMPORT_UPDATE_PRODUCTS);

        $this->logger->info(__('Finalization of Import and Update of Products - Hiper Integration'));
    }
}
