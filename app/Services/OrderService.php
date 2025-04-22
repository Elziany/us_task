<?php

namespace App\Services;

use GuzzleHttp\Client;

class OrderService
{
    protected $client;
    protected $logger;

    public function __construct(Client $client, TransactionLogger $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    public function generateRequestNumber()
    {
        $url = 'http://apirequestnumber.uslegalization.com/api/Request/GenerateNumber';
        $url2 = 'http://apirequestnumber2.uslegalization.com/api/Request/GenerateNumber';
        
        $log = $this->logger->log(
            'request_number_generation',
            'initiated'
        );
        
        try {
            $response = $this->callApi($url, "");
            
            $this->logger->log(
                'request_number_generation',
                'completed',
                $response['response']->RequestNumber ?? null,
                null,
                null,
                (array)$response['response'],
                "Primary API success"
            );
            
            return $response['response']->RequestNumber;
        } catch (\Exception $e) {
            try {
                $response = $this->callApi($url2, "");
                
                $this->logger->log(
                    'request_number_generation',
                    'completed',
                    $response['response']->RequestNumber ?? null,
                    null,
                    null,
                    (array)$response['response'],
                    "Fallback API success after primary failed"
                );
                
                return $response['response']->RequestNumber;
            } catch (\Exception $e) {
                $this->logger->log(
                    'request_number_generation',
                    'failed',
                    null,
                    null,
                    null,
                    ['error' => $e->getMessage()],
                    "Both primary and fallback APIs failed"
                );
                
                return false;
            }
        }
    }

    public function saveOrder(array $orderData, array $contactData, bool $isExistingUser)
    {
        $url = 'http://apiwebrequest.uslegalization.com/api/Request/Save';
        $url2 = 'http://apiwebrequest2.uslegalization.com/api/Request/Save';
        
        $data = [
            'ContactInformation' => $contactData,
            'OnlineRequest' => $orderData,
            'BarCode' => "",
            'IsExitsUser' => $isExistingUser,
        ];

        $log = $this->logger->log(
            'order_save',
            'initiated',
            $orderData['RequestNumber'] ?? null,
            $orderData['TotalAmount'] ?? null,
            $data
        );
        
        try {
            $response = $this->callApi($url, $data);
            
            $this->logger->log(
                'order_save',
                'completed',
                $orderData['RequestNumber'] ?? null,
                $orderData['TotalAmount'] ?? null,
                $data,
                (array)$response['response'],
                "Primary API success"
            );
            
            return $response['response'];
        } catch (\Exception $e) {
            try {
                $response = $this->callApi($url2, $data);
                
                $this->logger->log(
                    'order_save',
                    'completed',
                    $orderData['RequestNumber'] ?? null,
                    $orderData['TotalAmount'] ?? null,
                    $data,
                    (array)$response['response'],
                    "Fallback API success after primary failed"
                );
                
                return $response['response'];
            } catch (\Exception $e) {
                $this->logger->log(
                    'order_save',
                    'failed',
                    $orderData['RequestNumber'] ?? null,
                    $orderData['TotalAmount'] ?? null,
                    $data,
                    ['error' => $e->getMessage()],
                    "Both primary and fallback APIs failed"
                );
                
                return false;
            }
        }
    }

    protected function callApi($url, $data)
    {
        $response = $this->client->post($url, [
            'json' => $data,
            'timeout' => 30
        ]);
        
        return [
            'status' => $response->getStatusCode(),
            'response' => json_decode($response->getBody()->getContents())
        ];
    }
}