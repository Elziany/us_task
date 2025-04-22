<?php

namespace App\Services;

use GuzzleHttp\Client;

class InvoiceService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function payInvoice(array $invoiceData)
    {
        $url = 'http://apipayment.uslegalization.com/api/AuthorizeNet/Transaction';
        $url2 = 'http://apipayment2.uslegalization.com/api/AuthorizeNet/Transaction';
        
        $paymentResponse = $this->callApiTwice($url, $url2, $invoiceData);
        
        if ($paymentResponse['status'] == 200 && $paymentResponse['response']->Approved) {
            return $this->recordPayment($invoiceData, $paymentResponse['response']->AuthCode);
        }
        
        return $paymentResponse;
    }

    protected function recordPayment(array $invoiceData, string $authCode)
    {
        $payUrl = 'http://apidesktop.uslegalization.com/api/Payment/single';
        $payUrl2 = 'http://apidesktop2.uslegalization.com/api/Payment/single';
        
        $responses = [];
        
        foreach ($invoiceData['invoices'] as $invoice) {
            $requestData = [
                "InvoiceNo" => $invoice['number'],
                "MethodTypeId" => 2,
                "MethodTypeName" => "Credit Card",
                "PaymentNumber" => $authCode,
                "PaymentAmount" => $invoice['amount'] + ($invoice['amount'] * .05),
                "PaymentFees" => ($invoice['amount'] * .05),
                'WebSiteId' => 16
            ];
            
            $responses[] = $this->callApiTwice($payUrl, $payUrl2, $requestData);
        }
        
        return $responses;
    }

    protected function callApiTwice($primaryUrl, $secondaryUrl, $data)
    {
        try {
            $response = $this->client->post($primaryUrl, [
                'json' => $data,
                'timeout' => 30
            ]);
            
            return [
                'status' => $response->getStatusCode(),
                'response' => json_decode($response->getBody()->getContents())
            ];
        } catch (\Exception $e) {
            try {
                $response = $this->client->post($secondaryUrl, [
                    'json' => $data,
                    'timeout' => 30
                ]);
                
                return [
                    'status' => $response->getStatusCode(),
                    'response' => json_decode($response->getBody()->getContents())
                ];
            } catch (\Exception $e) {
                throw $e;
            }
        }
    }
}