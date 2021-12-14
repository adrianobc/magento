<?php

/**
* AV Dev
*
* NOTICE OF LICENSE
* @author AV Dev Core Team <suporte@avdev.com.br>
*/

namespace AVDev\Hiper\Helper\Request;

use Magento\Framework\App\Helper\AbstractHelper;
use Psr\Log\LoggerInterface;

class Send extends AbstractHelper
{
    /**
     * API request URL
     */
    const API_REQUEST_URI = 'http://ms-ecommerce.hiper.com.br/';

    /**
     * API request endpoint
     */
    const API_REQUEST_ENDPOINT = 'api/v1/';

    /**
     * @var Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    protected $curl;

    protected $_postBody = [];

    public function __construct(
        LoggerInterface $logger,
        \Magento\Framework\HTTP\Client\Curl $curl
    )
    {
        $this->logger = $logger;
        $this->curl = $curl;
    }

    /**
     * Function responsible send requests and get these responses
     * @return array
     */
    public function sendRequest($url, $method = \Zend\Http\Request::METHOD_GET, $authType = '', $authToken = '', $postOrder = null)
    {
        try {
            $curlUrl = static::API_REQUEST_URI . static::API_REQUEST_ENDPOINT . $url;

            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->setOption(CURLOPT_MAXREDIRS, 0);
            $this->curl->setOption(CURLOPT_TIMEOUT, 50);
            $this->curl->setOption(CURLOPT_ENCODING, "");
            $this->curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];
            if (!empty($authType) && !empty($authToken)) {
                $headers['Authorization'] = $authType . ' ' . $authToken;
            }
            $this->curl->setHeaders($headers);

            if ($method == \Zend\Http\Request::METHOD_POST && isset($postOrder)) {
                //$this->logger->log('DEBUG', 'postBody', $postOrder);
                $this->curl->post($curlUrl, json_encode($postOrder));
            } else {
                $this->curl->get($curlUrl);
            }


            /*$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/debug.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);*/


            $response = $this->curl->getBody();
            $result = json_decode($response, true);
            if(isset($result['message'])){
                //$logger->info('message: '.$result['message']);
            }

             #$this->logger->log('DEBUG', 'resultReturnHiper', $result->errors);
             #return;

            if ($this->curl->getStatus() === 200) {
                 $this->logger->log('DEBUG', 'resultReturnHiper', $result);
                if ($result) {
                    return $result;
                }
            } else {
                if (is_array($result)) {
                    $this->logger->log('DEBUG', 'errorsReturnHiper', $result);
                }
                return $result;
                #throw new \Exception(__('There was an unexpected error when sending the Order to Hiper.'));
            }
        } catch (\Exception $e) {
            $this->logger->info($e->getMessage());
        }

        return false;
    }
}
