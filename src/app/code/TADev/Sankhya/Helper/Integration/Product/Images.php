<?php

/**
* TA Dev
*
	* NOTICE OF LICENSE
	* @author TA Dev Core Team <suporte@tatecnologia.com>
*/

namespace TADev\Sankhya\Helper\Integration\Product;

use Magento\Framework\App\Helper\AbstractHelper;
//use Magento\Catalog\Model\Product;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File;
use Psr\Log\LoggerInterface;

class Images extends AbstractHelper
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
     * @var Magento\Framework\App\Filesystem\DirectoryList
     */
    protected $directoryList;

    /**
     * @var Magento\Framework\Filesystem\Io\File
     */
    protected $file;

    /**
     * SankhyaApiService constructor
     */
    public function __construct(
        LoggerInterface $logger,
        \TADev\Sankhya\Helper\Integration\Authentication $authentication,
        \TADev\Sankhya\Helper\Request\Send $request,
        \TADev\Sankhya\Helper\Integration\Product\DeleteDirectory $deleteDirectory,
        DirectoryList $directoryList,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        File $file
    )
    {
        $this->logger = $logger;
        $this->authentication = $authentication;
        $this->deleteDirectory = $deleteDirectory;
        $this->request = $request;
        $this->_productRepository = $productRepository;
        $this->directoryList = $directoryList;
        $this->file = $file;
    }

    public function setProductImages($product,$sessionid)
    {
        try {
            $imageType = ['image', 'small_image', 'thumbnail'];
            $image =  $product->getSku() . '.png';
                $result = $this->importImage($product, $image, $imageType, $sessionid);
                if ($result) {
                    $result = $this->deleteDirectory->deleteDirectory($this->getProductImageDir($product->getSku()));
                    If($result){
                        $this->logger->info(__('Temporary media folder was deleted for: SKU %1', $product->getSku()));
                    }
                    $this->logger->info(__('Image imported successfully: SKU %1', $product->getSku()));
                }
        } catch (Exception $e) {
            $this->logger->info(__('Error importing image to product: %1', $e->getMessage()));
        }
    }


    /**
     * Main service executor
     *
     * @param Product $product
     * @param string $imageUrl
     * @param array $imageType
     * @param bool $visible
     *
     * @return bool
     * @throws \Exception
     */
    private function importImage($product, $imageUrl, $imageType = [], $sessionid)
    {
        //$this->deleteExistingMediaEntries($product);
        $imageData = base64_decode($this->getImage($sessionid,$product->getSku()));
        /** @var string $tmpDir */
        $tmpDir = $this->getProductImageDir($product->getSku());
        /** create folder if it is not exists */
        $this->file->checkAndCreateFolder($tmpDir);
        /** @var string $newFileName */
        $newFileName = $tmpDir . $imageUrl; //baseName($imageUrl);
        /** copy file from URL and copy it to the new destination */

        if (file_put_contents($newFileName, $imageData)) {
            /** add saved file to the $product gallery */
            $product->addImageToMediaGallery($newFileName, $imageType, false, false);
            $product->save();
            return 'success';
        } else {
            return 'failed';
        }
    }

    /**
     * Media directory name for the temporary file storage
     * pub/media/catalog/product/sankhya/
     *
     * @return string
     */
    protected function getProductImageDir($sku = '')
    {
        return $this->directoryList->getPath(DirectoryList::MEDIA) . DIRECTORY_SEPARATOR .
            'catalog' . DIRECTORY_SEPARATOR .
            'product' . DIRECTORY_SEPARATOR .
            'sankhya' . DIRECTORY_SEPARATOR .
            $sku . DIRECTORY_SEPARATOR;
    }

    /**
     * Get Sankhya Image
     *
     * @param null $sessionid
     * @param null $codprod
     * @return string
     */
    public function getImage($sessionid = null,$codprod = null): string
    {
        #$this->logger->info('Entrou no getImage CodProd=' . $codprod);
        $img = '';
        If(isset($sessionid)){
            $postBody = [
                "serviceName" => "DbExplorerSP.executeQuery",
                "requestBody" => [
                    "sql" =>  "select base64_img1 = CAST('' AS XML).value('xs:base64Binary(sql:column(\"IMAGEM\"))','VARCHAR(MAX)') from (select IMAGEM = cast(IMAGEM as varbinary(max)) from TGFPRO where CODPROD = " . $codprod . " ) T"
                ]
            ];
            $response = $this->request->sendRequest(
                'MGE','?serviceName=DbExplorerSP.executeQuery&outputType=json',
                \Zend\Http\Request::METHOD_POST,
                $sessionid,
                $postBody
            );
            $img = $response['responseBody']['rows']['0']['0'];
        }
        if(isset($img)){
            return $img;
        }else{
            return '';
        }
            

    }

    public function deleteExistingMediaEntries($product){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $imageProcessor = $objectManager->create('Magento\Catalog\Model\Product\Gallery\Processor');
        $productGallery = $objectManager->create('Magento\Catalog\Model\ResourceModel\Product\Gallery');
        $images = $product->getMediaGalleryImages();

        foreach($images as $child) {
            $productGallery->deleteGallery($child->getValueId());
            $imageProcessor->removeImage($product, $child->getFile());
        }
    }
}
