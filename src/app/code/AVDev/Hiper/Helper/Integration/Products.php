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

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;

class Products extends AbstractHelper
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
     * @var \AVDev\Hiper\Helper\Integration\Product\Images
     */
    protected $images;

    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    protected $product;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    protected $category;

    /**
     * @var \Magento\Framework\App\Config\Storage\WriterInterface
     */
    protected $configWriter;

    /**
     * @var Magento\Framework\App\Cache\TypeListInterface
     */
    protected $cacheTypeList;

    /**
     * @var Magento\Framework\App\Cache\Frontend\Pool
     */
    protected $cacheFrontendPool;

    /**
     * HiperApiService constructor
     */
    public function __construct(
        LoggerInterface $logger,
        \AVDev\Hiper\Helper\Integration\Authentication $authentication,
        \AVDev\Hiper\Helper\Request\Send $request,
        \AVDev\Hiper\Helper\Integration\Product\Images $images,
        \Magento\Catalog\Model\ProductRepository $product,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $category,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        TypeListInterface $cacheTypeList, 
        Pool $cacheFrontendPool
    )
    {
        $this->logger = $logger;
        $this->authentication = $authentication;
        $this->request = $request;
        $this->images = $images;
        $this->product = $product;
        $this->category = $category;
        $this->configWriter = $configWriter;
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
    }

    /**
     * Get Hiper Products
     *
     * @return array
     */
    public function getProducts($token = null)
    {
        if (isset($token)) {
            $syncPointValue = $this->authentication->getSyncPoint();
            $syncPoint = '';
            if (!empty($syncPointValue)) {
                $syncPoint = '?pontoDeSincronizacao=' . $syncPointValue;
            }

            $response = $this->request->sendRequest(
                'produtos/pontoDeSincronizacao/' . $syncPoint,
                \Zend\Http\Request::METHOD_GET,
                'Bearer',
                $token
            );


            #$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/debuger.log');
            #$logger = new \Zend\Log\Logger();
            #$logger->addWriter($writer);
            #$logger->info( 'getProducts RESPONSE '.json_encode($response));

            if ($response) {
                //$this->logger->log('DEBUG', 'retornoHiperProducts', $response);
                if (isset($response['produtos'])) {
                    $this->updateIntegrationProducts($response['produtos']);

                    //$this->logger->info(__('pontoDeSincronizacao: ') . $response['pontoDeSincronizacao']);

                    // Set the current sync point
                    if ($syncPointValue != $response['pontoDeSincronizacao']) {
                        $this->configWriter->save('hiper/general/sync_point', $response['pontoDeSincronizacao']);
                        $this->flushConfigCache();
                    }
                } else {
                    //$this->logger->info(__('No products found after the last sync point.'));
                }
            }
        } else {
            //$this->logger->info(__('Token can not be null.'));
            return false;
        }

        return $this;
    }

    private function getProductObject($sku,$codigo) //private function getProductObject($sku) //
    {
        try {
            $product = $this->product->get($sku);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $product = false;
        }
         if (!$product) {
            try {
                $product = $this->product->get($codigo);
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                $product = false;
            }
        } 

        if (!$product) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $product = $objectManager->create('\Magento\Catalog\Model\Product');
        }

        return $product;
    }

    private function updateIntegrationProducts($products)
    {
        foreach ($products as $hiperProduct) {
            $product = $this->getProductObject($hiperProduct['id'],$hiperProduct['codigo']); //$product = $this->getProductObject($hiperProduct['id']); //

            $product->setSku($hiperProduct['codigo']); //$product->setSku($hiperProduct['id']); //
            $product->setCodigoErp($hiperProduct['codigo']);
            $product->setAttributeSetId(4); // Default attribute set id
            $product->setWebsiteIds([1]);

            $status = ($hiperProduct['ativo']) ? 1 : 0;
            $product->setStatus($status); // Status 1 enabled / 0 disabled
            $product->setVisibility(4); // visibilty of product (not visible individually / catalog / search / catalog, search)
            $product->setTaxClassId(0);
            $product->setTypeId('simple'); // type of product (simple / virtual / downloadable / configurable)

            if($hiperProduct["unidade"] == "KG"){
                $hiperProduct['nome'] = $hiperProduct['nome']. " 100g";
                $product->setData("short_description", "Pacotes de 100 gramas");
                $hiperProduct["preco"] = $hiperProduct["preco"] / 10;
                $hiperProduct['quantidadeEmEstoque'] = floor(($hiperProduct['quantidadeEmEstoque'] / 100 ) * 1000);
                $hiperProduct['peso'] = 0.001;
            }

            $product->setName($hiperProduct['nome']);
            $product->setPrice($hiperProduct["preco"]);
			$product->setData("description",$hiperProduct['descricao']);
            $product->setCountryOfManufacture('BR');

            // Dimensions data
            $product->setWeight($hiperProduct['peso']);
            $product->setHeightHiper($hiperProduct['altura']);
            $product->setWidthHiper($hiperProduct['largura']);
            $product->setLengthHiper($hiperProduct['comprimento']);

            $product->setBrandHiper($hiperProduct['marca']);
            $product->setUnityHiper($hiperProduct['unidade']);
            $product->setSize($hiperProduct['tamanho']);
            $product->setColor($hiperProduct['cor']);

            $product->setIdHiper($hiperProduct['id']);

            // Search by Product Category
            $category = $this->category
                ->create()
                ->addAttributeToFilter('name', $hiperProduct['categoria'])
                ->setPageSize(1);

            $categoryId = null;
            if ($category->getSize()) {
                if ($categoryId = $category->getFirstItem()->getId()) {
                    $product->setCategoryIds($categoryId);
                }
            }

            // Stock Data
            $isInStock = ($hiperProduct['quantidadeEmEstoque'] > 0) ? 1 : 0;
			if($hiperProduct["unidade"] == "KG"){
				$hiperProduct['quantidadeEmEstoque'] = ($hiperProduct['quantidadeEmEstoque'] > 20) ? $hiperProduct['quantidadeEmEstoque'] - 20 : 0;
			}
			if($hiperProduct["unidade"] != "KG"){
				$hiperProduct['quantidadeEmEstoque'] = ($hiperProduct['quantidadeEmEstoque'] > 5) ? $hiperProduct['quantidadeEmEstoque'] - 5 : 0;
			}
            $product->setStockData(
                array(
                    'use_config_manage_stock' => 0,
                    'manage_stock' => 1,
                    'is_in_stock' => $isInStock,
                    'qty' => $hiperProduct['quantidadeEmEstoque']
                )
            );

            $product->save();

            // Get product images
            $this->images->setProductImages($product, $hiperProduct['imagem']);

            unset($product);
        }
    }

    private function flushConfigCache()
    {
        $_types = [
            'config'
        ];

        foreach ($_types as $type) {
            $this->cacheTypeList->cleanType($type);
        }

        foreach ($this->cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
    }
}
