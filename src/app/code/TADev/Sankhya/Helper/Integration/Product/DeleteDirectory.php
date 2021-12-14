<?php
namespace TADev\Sankhya\Helper\Integration\Product;

use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Psr\Log\LoggerInterface;

class DeleteDirectory
{
    /**
     * @var Psr\Log\LoggerInterface
     */
    protected $logger;
    /**
     * @var Filesystem
     */
    protected $fileSystem;

    /**
     * @var WriteInterface
     */
    protected $deleteDirectory;


    public function __construct(
        LoggerInterface $logger,
        Filesystem $fileSystem

    ) {
        $this->logger = $logger;
        $this->directory = $fileSystem->getDirectoryWrite(DirectoryList::ROOT);
    }

    /**
     * Delete folder
     *
     * @return bool
     * @throws LocalizedException
     */
    public function deleteDirectory($path)
    {
        $imgPath = substr($path,0,strlen($path)-1);
        $deleteDirectory = $this->directory->delete($imgPath);
        return $deleteDirectory;
    }
}
