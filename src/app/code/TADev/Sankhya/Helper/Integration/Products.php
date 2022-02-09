<?php

//$this->logger->info('IF False ' . json_encode($entities));
//$this->logger->log(100,print_r($arrayProdutos,true));
//$this->logger->log('DEBUG', 'retornoSankhyaProducts', $entities);

/**
* TA Dev
*
* NOTICE OF LICENSE
* @author TA Dev Core Team <suporte@tatecnologia.com>
*/

namespace TADev\Sankhya\Helper\Integration;

use Magento\Framework\App\Helper\AbstractHelper;
use PhpParser\Node\Stmt\Foreach_;
use PHPUnit\Util\FileLoader;
use Psr\Log\LoggerInterface;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;

class Products extends AbstractHelper
{
    const SYSTEM_GENERAL_STOCK_DIFF_KG = 'sankhya/general/stock_diff_kg';
    const SYSTEM_GENERAL_STOCK_DIFF_UN = 'sankhya/general/stock_diff_un';
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
     * @var \TADev\Sankhya\Helper\Integration\Product\Images
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
     * @var Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * SankhyaApiService constructor
     */
    public function __construct(
        LoggerInterface $logger,
        \TADev\Sankhya\Helper\Integration\Authentication $authentication,
        \TADev\Sankhya\Helper\Request\Send $request,
        \TADev\Sankhya\Helper\Integration\Product\Images $images,
        \Magento\Catalog\Model\ProductRepository $product,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $category,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool
    )
    {
        //$objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // instance of object manager
        //$this->product = $objectManager->create('\Magento\Catalog\Model\Product');
        $this->logger = $logger;
        $this->authentication = $authentication;
        $this->request = $request;
        $this->images = $images;
        $this->product = $product;
        $this->category = $category;
        $this->configWriter = $configWriter;
        $this->cacheTypeList = $cacheTypeList;
        $this->scopeConfig = $scopeConfig;
        $this->cacheFrontendPool = $cacheFrontendPool;
    }

    public function getStockDiffKg()
    {
        return $this->scopeConfig->getValue(static::SYSTEM_GENERAL_STOCK_DIFF_KG, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getStockDiffUn()
    {
        return $this->scopeConfig->getValue(static::SYSTEM_GENERAL_STOCK_DIFF_UN, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

        /**
     * Get Sankhya Products
     *
     * @return int
     */
    public function getProducts($sessionid = null):int
    {
        If(isset($sessionid)){
            $count = 0;
            $offset = 0;
            $arrayProdutos = array();
            $syncPointValue = $this->authentication->getSyncPoint();            
            do{
                $postBody = [
                    "serviceName" => "CRUDServiceProvider.loadRecords",
                    "requestBody" => [
                        "dataSet" => [
                            "rootEntity" => "Produto",
                            "includePresentationFields" => "N",
                            "offsetPage" => json_encode($offset),
                            "criteria" => [
                                "expression" => [
                                    "$" => "this.AD_LJVIRTUAL='S' AND this.AD_SINCRONIA > " . $syncPointValue //. " AND this.CODPROD in (4)"
                                ]
                            ],
                            "entity" => [
                                "fieldset" => [
                                    "list" => "CODPROD,DESCRPROD,MARCA,CODVOL,ATIVO,CODGRUPOPROD,CARACTERISTICAS"
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
                
                if (array_key_exists('entity',$response['responseBody']['entities'])){   // Se houver produtos para atualizar!   
                    if (!array_key_exists('f0',$response['responseBody']['entities']['entity'])){  //Se houver mais de 1 produto para atualizar!
                        foreach ($response['responseBody']['entities']['entity'] as $entity){
                            $tmpProdutos['codigo'] = $entity['f0']['$'];
                            $tmpProdutos['preco'] = $this->getPrices($sessionid,$entity['f0']['$']);
                            $tmpProdutos['quantidadeEmEstoque'] = $this->getStock($sessionid,$entity['f0']['$']);
                            $tmpProdutos['nome'] = $entity['f1']['$'];
                            $tmpProdutos['marca'] = (array_key_exists('$',$entity['f2'])) ? $entity['f2']['$'] : '';
                            $tmpProdutos['unidade'] = $entity['f3']['$'];
                            $tmpProdutos['id'] = $entity['f0']['$']; // $entity['f4']['$']; = id Hiper || $entity['f0']['$']; = codigo Hiper
                            $tmpProdutos['ativo'] = ($entity['f4']['$']=='S') ? 1 : 0;
                            $tmpProdutos['categoria'] = $this->getGrupo($sessionid,$entity['f5']['$']);
                            $tmpProdutos['descricao'] = (array_key_exists('$',$entity['f6'])) ? $entity['f6']['$'] : '';
                            $tmpProdutos['altura'] = 0.000;
                            $tmpProdutos['comprimento'] = 0.000;
                            $tmpProdutos['largura'] = 0.000;
                            $tmpProdutos['peso'] = 0.000;
                            $tmpProdutos['tamanho'] = '';
                            $tmpProdutos['cor'] = '';
                            array_push($arrayProdutos, $tmpProdutos) ;
                            $this->logger->info('Prod ' . $entity['f0']['$']);
                        }
                    }else{  //Se houver apenas 1 produto para atualizar!
                        $tmpProdutos['codigo'] = $response['responseBody']['entities']['entity']['f0']['$'];
                        $tmpProdutos['preco'] = $this->getPrices($sessionid,$response['responseBody']['entities']['entity']['f0']['$']);
                        $tmpProdutos['quantidadeEmEstoque'] = $this->getStock($sessionid,$response['responseBody']['entities']['entity']['f0']['$']);
                        $tmpProdutos['nome'] = $response['responseBody']['entities']['entity']['f1']['$'];
                        $tmpProdutos['marca'] = (array_key_exists('$',$response['responseBody']['entities']['entity']['f2'])) ? $response['responseBody']['entities']['entity']['f2']['$'] : '';
                        $tmpProdutos['unidade'] = $response['responseBody']['entities']['entity']['f3']['$'];
                        $tmpProdutos['id'] = $response['responseBody']['entities']['entity']['f0']['$']; // $entity['f4']['$']; = id Hiper || $entity['f0']['$']; = codigo Hiper
                        $tmpProdutos['ativo'] = ($response['responseBody']['entities']['entity']['f4']['$']=='S') ? 1 : 0;
                        $tmpProdutos['categoria'] = $this->getGrupo($sessionid,$response['responseBody']['entities']['entity']['f5']['$']);
                        $tmpProdutos['descricao'] = (array_key_exists('$',$response['responseBody']['entities']['entity']['f6'])) ? $response['responseBody']['entities']['entity']['f6']['$'] : '';
                        $tmpProdutos['altura'] = 0.000;
                        $tmpProdutos['comprimento'] = 0.000;
                        $tmpProdutos['largura'] = 0.000;
                        $tmpProdutos['peso'] = 0.000;
                        $tmpProdutos['tamanho'] = '';
                        $tmpProdutos['cor'] = '';
                        array_push($arrayProdutos, $tmpProdutos) ;
                    }
                    //$this->logger->log(100,print_r($arrayProdutos,true));
                }else{
                    $this->logger->info('Nenhum produto para atualizar!');
                }
                $offset++;
            }while($response['responseBody']['entities']['hasMoreResult'] == 'true');

            if (isset($arrayProdutos)) {
                $count = count($arrayProdutos);
                $this->updateIntegrationProducts($arrayProdutos,$sessionid);
                // Set the current sync point
                $sync = $this->getSyncPoint($sessionid);
                if ($syncPointValue !=  $sync) {
                    $this->configWriter->save('sankhya/general/sync_point', $sync);
                    $this->flushConfigCache();
                }
            } 
        } else {
            $this->logger->info(__('Sankhya SessionId can not be null.'));
            return false;
        }
        return $count;
    }

    private function getProductObject($sku)
    {
        try {
            $product = $this->product->get($sku);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $product = false;
        }

        if (!$product) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $product = $objectManager->create('\Magento\Catalog\Model\Product');
        }

        return $product;
    }

    private function updateIntegrationProducts($products,$sessionid)
    {
        $stockDiffKg = $this->getStockDiffKg();
        $stockDiffUn = $this->getStockDiffUn();

        foreach ($products as $sankhyaProduct) {

            //$this->logger->log(100, print_r($sankhyaProduct, true));
            //$this->logger->log(100,print_r($sankhyaProduct,true));
            //$this->logger->info('Teste = ' . $sankhyaProduct['id']);

            $product = $this->getProductObject($sankhyaProduct['id']);

            $product->setSku($sankhyaProduct['id']);
            $product->setCodigoErp($sankhyaProduct['id']);
            $product->setAttributeSetId(4); // Default attribute set id
            $product->setWebsiteIds([1]);

            $status = ($sankhyaProduct['ativo']) ? 1 : 0;
            $product->setStatus($status); // Status 1 enabled / 0 disabled
            $product->setVisibility(4); // visibilty of product (not visible individually / catalog / search / catalog, search)
            $product->setTaxClassId(0);
            $product->setTypeId('simple'); // type of product (simple / virtual / downloadable / configurable)

            if($sankhyaProduct["unidade"] == "KG"){
                $sankhyaProduct['nome'] = $sankhyaProduct['nome']. " 100g";
                $product->setData("short_description", "Pacotes de 100 gramas");
                $sankhyaProduct["preco"] = $sankhyaProduct["preco"] / 10;
                $sankhyaProduct['quantidadeEmEstoque'] = floor(($sankhyaProduct['quantidadeEmEstoque'] / 100 ) * 1000);
                $sankhyaProduct['peso'] = 0.001;
            }

            $product->setName($sankhyaProduct['nome']);
            $product->setPrice($sankhyaProduct["preco"]);
            $product->setData("description",$sankhyaProduct['descricao']);
            $product->setCountryOfManufacture('BR');

            // Dimensions data
            $product->setWeight($sankhyaProduct['peso']);
            $product->setHeightSankhya($sankhyaProduct['altura']);
            $product->setWidthSankhya($sankhyaProduct['largura']);
            $product->setLengthSankhya($sankhyaProduct['comprimento']);

            $product->setBrandSankhya($sankhyaProduct['marca']);
            $product->setUnitySankhya($sankhyaProduct['unidade']);
            $product->setSize($sankhyaProduct['tamanho']);
            $product->setColor($sankhyaProduct['cor']);

            $product->setIdSankhya($sankhyaProduct['id']);

            // Search by Product Category
            $category = $this->category
                ->create()
                ->addAttributeToFilter('name', $sankhyaProduct['categoria'])
                ->setPageSize(1);

            $categoryId = null;
            if ($category->getSize()) {
                if ($categoryId = $category->getFirstItem()->getId()) {
                    $product->setCategoryIds($categoryId);
                }
            }

            // Stock Data
            $isInStock = ($sankhyaProduct['quantidadeEmEstoque'] > 0) ? 1 : 0;
            if($sankhyaProduct["unidade"] == "KG"){
                $sankhyaProduct['quantidadeEmEstoque'] = ($sankhyaProduct['quantidadeEmEstoque'] > $stockDiffKg) ? $sankhyaProduct['quantidadeEmEstoque'] - $stockDiffKg : 0;
            }
            if($sankhyaProduct["unidade"] != "KG"){
                $sankhyaProduct['quantidadeEmEstoque'] = ($sankhyaProduct['quantidadeEmEstoque'] > $stockDiffUn) ? $sankhyaProduct['quantidadeEmEstoque'] - $stockDiffUn : 0;
            }
            $product->setStockData(
                array(
                    'use_config_manage_stock' => 0,
                    'manage_stock' => 1,
                    'is_in_stock' => $isInStock,
                    'qty' => $sankhyaProduct['quantidadeEmEstoque']
                )
            );

            $product->save();

            // Get product images
            $this->images->setProductImages($product,$sessionid);

            unset($product);

        }
    }

    /**
     * Get Sankhya Stock
     *
     * @param null $sessionid
     * @param null $codprod
     * @return float
     */
    public function getStock($sessionid = null,$codprod = null): float
    {
        $tmpStock = 0.0;
        If(isset($sessionid)){
            $offset = 0;
            //$entities = array();
            $postBody = [
                "serviceName" => "CRUDServiceProvider.loadRecords",
                "requestBody" => [
                    "dataSet" => [
                        "rootEntity" => "Estoque",
                        "includePresentationFields" => "N",
                        "offsetPage" => json_encode($offset),
                        "criteria" => [
                            "expression" => [
                                "$" => "this.CODEMP = 1 and this.CODPROD =" . $codprod
                            ]
                        ],
                        "entity" => [
                            "fieldset" => [
                                "list" => "CODPROD,ESTOQUE,RESERVADO"
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
            //$this->logger->log(100,print_r($response['responseBody']['entities']['entity']['f1']['$'],true));
            if (array_key_exists('entity',$response['responseBody']['entities'])){ 
                $tmpStock = (float) $response['responseBody']['entities']['entity']['f1']['$'] - (float) $response['responseBody']['entities']['entity']['f2']['$'];
            }           
        }
//        $this->logger->info('Produto ' . $codprod . ' - Stock ' . $tmpStock);
        return $tmpStock;
    }

    /**
     * Get Sankhya Grupo
     *
     * @param null $sessionid
     * @param null $codprod
     * @return string
     */
    public function getGrupo($sessionid = null,$codgrupoprod = null): string
    {
        $tmpGrupo = '';
        If(isset($sessionid)){
            $offset = 0;
            //$entities = array();
            $postBody = [
                "serviceName" => "CRUDServiceProvider.loadRecords",
                "requestBody" => [
                    "dataSet" => [
                        "rootEntity" => "GrupoProduto",
                        "includePresentationFields" => "N",
                        "offsetPage" => json_encode($offset),
                        "criteria" => [
                            "expression" => [
                                "$" => "this.CODGRUPOPROD =" . $codgrupoprod
                            ]
                        ],
                        "entity" => [
                            "fieldset" => [
                                "list" => "CODGRUPOPROD,DESCRGRUPOPROD"
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
            $tmpGrupo = $response['responseBody']['entities']['entity']['f1']['$'];
        }
        return $tmpGrupo;
    }

    /**
     * Get Sankhya Prices
     *
     * @param null $sessionid
     * @param null $codprod
     * @return float
     */
    public function getPrices($sessionid = null,$codprod = null): float
    {
        $tmpPrice = 0;
        If(isset($sessionid)){
            $offset = 0;
            //$entities = array();
            $postBody = [
                "serviceName" => "DbExplorerSP.executeQuery",
                "requestBody" => [
                    "sql" =>  "SELECT VLRVENDA FROM TGFEXC WHERE CODPROD=" . $codprod . " AND NUTAB = (SELECT MAX(NUTAB) FROM TGFEXC WHERE CODPROD=" . $codprod . ")"
                ]
            ];
            $response = $this->request->sendRequest(
                'MGE','?serviceName=DbExplorerSP.executeQuery&outputType=json',
                \Zend\Http\Request::METHOD_POST,
                $sessionid,
                $postBody
            );

            //$this->logger->log(100,print_r($response,true));
            $tmpPrice = $response['responseBody']['rows']['0']['0'];
        }
        return $tmpPrice;
    }

    /**
     * Get Sankhya Sync Point
     *
     * @param null $sessionid
     * @param null $codprod
     * @return int
     */
    public function getSyncPoint($sessionid = null): int
    {
        #$this->logger->info('Entrou no getImage CodProd=' . $codprod);
        $sync = 0;
        If(isset($sessionid)){
            $postBody = [
                "serviceName" => "DbExplorerSP.executeQuery",
                "requestBody" => [
                    "sql" =>  "SELECT MAX(AD_SINCRONIA) FROM TGFPRO"
                ]
            ];
            $response = $this->request->sendRequest(
                'MGE','?serviceName=DbExplorerSP.executeQuery&outputType=json',
                \Zend\Http\Request::METHOD_POST,
                $sessionid,
                $postBody
            );
            $sync = $response['responseBody']['rows']['0']['0'];
        }
        return $sync;
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
