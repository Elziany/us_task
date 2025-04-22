<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    protected $client;
    protected $logger;

    public function __construct(Client $client, TransactionLogger $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    public function checkDoublePayment(array $requestData)
    {
        $url = 'http://apipayment.uslegalization.com/api/Payment/CheckDoublePayment';
        $url2 = 'http://apipayment2.uslegalization.com/api/Payment/CheckDoublePayment';
        
        $log = $this->logger->log(
            'double_payment_check',
            'initiated',
            null,
            $requestData['DollarAmount'] ?? null,
            $requestData
        );
        
        try {
            $response = $this->callApi($url, $requestData);
            
            $this->logger->log(
                'double_payment_check',
                'completed',
                null,
                $requestData['DollarAmount'] ?? null,
                $requestData,
                (array)$response['response'],
                "Primary API success"
            );
            
            return $response;
        } catch (\Exception $e) {
            try {
                $response = $this->callApi($url2, $requestData);
                
                $this->logger->log(
                    'double_payment_check',
                    'completed',
                    null,
                    $requestData['DollarAmount'] ?? null,
                    $requestData,
                    (array)$response['response'],
                    "Fallback API success after primary failed"
                );
                
                return $response;
            } catch (\Exception $e) {
                $this->logger->log(
                    'double_payment_check',
                    'failed',
                    null,
                    $requestData['DollarAmount'] ?? null,
                    $requestData,
                    ['error' => $e->getMessage()],
                    "Both primary and fallback APIs failed"
                );
                
                throw $e;
            }
        }
    }

    public function processPayment(array $paymentData)
    {
        $url = 'http://apipayment.uslegalization.com/api/AuthorizeNet/Transaction';
        $url2 = 'http://apipayment2.uslegalization.com/api/AuthorizeNet/Transaction';
        
        $log = $this->logger->log(
            'payment',
            'initiated',
            $paymentData['InvoiceNo'] ?? $paymentData['HFRequestNo'] ?? null,
            $paymentData['DollarAmount'] ?? null,
            $paymentData
        );
        
        try {
            $response = $this->callApi($url, $paymentData);
            
            if ($response['response']->Approved) {
                $this->logger->log(
                    'payment',
                    'success',
                    $paymentData['InvoiceNo'] ?? $paymentData['HFRequestNo'] ?? null,
                    $paymentData['DollarAmount'] ?? null,
                    $paymentData,
                    (array)$response['response'],
                    "Payment approved"
                );
            } else {
                $this->logger->log(
                    'payment',
                    'declined',
                    $paymentData['InvoiceNo'] ?? $paymentData['HFRequestNo'] ?? null,
                    $paymentData['DollarAmount'] ?? null,
                    $paymentData,
                    (array)$response['response'],
                    $response['response']->ErrorMessage ?? $response['response']->Bank_Message ?? "Payment declined"
                );
            }
            
            return $response;
        } catch (\Exception $e) {
            try {
                $response = $this->callApi($url2, $paymentData);
                
                if ($response['response']->Approved) {
                    $this->logger->log(
                        'payment',
                        'success',
                        $paymentData['InvoiceNo'] ?? $paymentData['HFRequestNo'] ?? null,
                        $paymentData['DollarAmount'] ?? null,
                        $paymentData,
                        (array)$response['response'],
                        "Fallback API success after primary failed"
                    );
                } else {
                    $this->logger->log(
                        'payment',
                        'declined',
                        $paymentData['InvoiceNo'] ?? $paymentData['HFRequestNo'] ?? null,
                        $paymentData['DollarAmount'] ?? null,
                        $paymentData,
                        (array)$response['response'],
                        $response['response']->ErrorMessage ?? $response['response']->Bank_Message ?? "Payment declined"
                    );
                }
                
                return $response;
            } catch (\Exception $e) {
                $this->logger->log(
                    'payment',
                    'failed',
                    $paymentData['InvoiceNo'] ?? $paymentData['HFRequestNo'] ?? null,
                    $paymentData['DollarAmount'] ?? null,
                    $paymentData,
                    ['error' => $e->getMessage()],
                    "Both primary and fallback APIs failed"
                );
                
                throw $e;
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