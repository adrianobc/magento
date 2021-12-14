<?php

/**
* AV Dev
*
* NOTICE OF LICENSE
* @author AV Dev Core Team <suporte@avdev.com.br>
*/

namespace AVDev\Hiper\Helper\Integration\Product;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Catalog\Model\Product;
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
     * @var \AVDev\Hiper\Helper\Integration\Authentication
     */
    protected $authentication;

    /**
     * @var \AVDev\Hiper\Helper\Request\Send
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
     * HiperApiService constructor
     */
    public function __construct(
        LoggerInterface $logger,
        \AVDev\Hiper\Helper\Integration\Authentication $authentication,
        \AVDev\Hiper\Helper\Request\Send $request,
        DirectoryList $directoryList,
        File $file
    )
    {
        $this->logger = $logger;
        $this->authentication = $authentication;
        $this->request = $request;
        $this->directoryList = $directoryList;
        $this->file = $file;
    }

    public function setProductImages($product, $images)
    {
        foreach (explode(',', $images) as $key => $image) {
            try {
                $imageType = [];
                if ($key == 0) {
                    $imageType = ['image', 'small_image', 'thumbnail'];
                }

                // Check if file already exists
                if (!empty($image)) {
                    if (!$this->checkProductImage($image, $product->getSku())) {
                        $result = $this->importImage($product, $image, $imageType);
                        if ($result) {
                            //$this->logger->info(__('Image imported successfully: SKU %1', $product->getSku()));
                        }
                    } else {
                        //$this->logger->info(__('Existing %1 image for Product SKU: %2', baseName($image), $product->getSku()));
                    }
                }
            } catch (Exception $e) {
                //$this->logger->info(__('Error importing image to product: %1', $e->getMessage()));
            }
        }
    }

    private function checkProductImage($image, $sku)
    {
        $fileName = baseName($image);
        $fileDir = $this->getProductImageDir($sku) . $fileName;
        return $this->file->fileExists($fileDir);
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
     */
    private function importImage($product, $imageUrl, $imageType = [])
    {
        /** @var string $tmpDir */
        $tmpDir = $this->getProductImageDir($product->getSku());
        /** create folder if it is not exists */
        $this->file->checkAndCreateFolder($tmpDir);
        /** @var string $newFileName */
        $newFileName = $tmpDir . baseName($imageUrl);
        /** copy file from URL and copy it to the new destination */
        $result = $this->file->cp($imageUrl, $newFileName);
        if ($result) {
            /** add saved file to the $product gallery */
            $product->addImageToMediaGallery($newFileName, $imageType, false, false);
            $product->save();
        }

        return $result;
    }

    /**
     * Media directory name for the temporary file storage
     * pub/media/catalog/product/hiper/
     *
     * @return string
     */
    protected function getProductImageDir($sku = '')
    {
        return $this->directoryList->getPath(DirectoryList::MEDIA) . DIRECTORY_SEPARATOR . 
            'catalog' . DIRECTORY_SEPARATOR . 
            'product' . DIRECTORY_SEPARATOR . 
            'hiper' . DIRECTORY_SEPARATOR . 
            $sku . DIRECTORY_SEPARATOR;
    }
}
