<?php

/**
* TA Dev
*
* NOTICE OF LICENSE
* @author TA Dev Core Team <suporte@tatecnologia.com>
*/

namespace TADev\Sankhya\Helper\Request;

use Magento\Framework\App\Helper\AbstractHelper;
use Psr\Log\LoggerInterface;

class Send extends AbstractHelper
{
    /**
     * API request URL
     */
    //const API_REQUEST_URI = 'http://gestao.emporiodigrano.com.br:8380/';
    const API_REQUEST_URI = 'sankhya/general/sankhya_end_point';

    /**
     * API request endpoint
     */
    const API_MGE = 'mge/service.sbr';
    const API_MGECOM = 'mgecom/service.sbr';

    /**
     * @var Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

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
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        \Magento\Framework\HTTP\Client\Curl $curl
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->curl = $curl;
    }

    /**
     * Function responsible for init the integration
     * @return string
     */
    public function getEndPoint()
    {
        return $this->scopeConfig->getValue(static::API_REQUEST_URI, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * Function responsible send requests and get these responses
     * @return array
     */
    public function sendRequest($modulo = '', $url, $method = \Zend\Http\Request::METHOD_GET, $authToken = '', $postOrder = null)
    {
        $modulo = $modulo == 'MGE' ? static::API_MGE : static::API_MGECOM;
        try {            
            $curlUrl = $this->getEndPoint() . $modulo . $url; //$curlUrl = static::API_REQUEST_URI . static::API_REQUEST_ENDPOINT . $url;

            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->setOption(CURLOPT_MAXREDIRS, 0);
            $this->curl->setOption(CURLOPT_TIMEOUT, 50);
            $this->curl->setOption(CURLOPT_ENCODING, "");
            $this->curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Content-Length' =>  json_encode(strlen(json_encode($postOrder)))
            ];

            if(!empty($authToken) && !is_array($authToken)){
              $headers['Cookie'] = 'JSESSIONID=' . $authToken . '.master';
            }

            //$this->logger->info('PostOrder ' . json_encode($postOrder));
            //$this->logger->log(100,print_r($postOrder,true));

            $this->curl->setHeaders($headers);

            if ($method == \Zend\Http\Request::METHOD_POST && isset($postOrder)) {
                //$this->logger->log('DEBUG', 'Enviando POST: ', $postOrder);
               $this->curl->post($curlUrl, json_encode($postOrder,JSON_FORCE_OBJECT));
            } else {
                //$this->logger->log('DEBUG', 'Enviando GET: ', $postOrder);
                $this->curl->get($curlUrl);
            }

            /*$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/debuger.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);*/

            $response = $this->curl->getBody();
            $result =  json_decode(utf8_encode($response), true);


            if ($this->curl->getStatus() == 200) {
                // $this->logger->log('DEBUG', 'resultReturnSankhya', $result);
                if ($result) {
                    return $result;
                }
            } else {
                if (is_array($result)) {
                    $this->logger->log('DEBUG', 'errorsReturnSankhya', $result);
                }
                throw new \Exception(__('@There was an unexpected error when sending the Order to Sankhya.'));
            }
        } catch (\Exception $e) {
            $this->logger->info($e->getMessage());
        }

        return false;
    }
}
