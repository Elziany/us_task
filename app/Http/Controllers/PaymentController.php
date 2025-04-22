<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use App\Services\OrderService;
use App\Services\FedexService;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    protected $paymentService;
    protected $orderService;
    protected $fedexService;
    protected $invoiceService;

    public function __construct(
        PaymentService $paymentService,
        OrderService $orderService,
        FedexService $fedexService,
        InvoiceService $invoiceService
    ) {
        $this->paymentService = $paymentService;
        $this->orderService = $orderService;
        $this->fedexService = $fedexService;
        $this->invoiceService = $invoiceService;
    }

    public function checkDoublePayment(Request $request)
    {
        $requestData = [
            'Cardno' => str_replace(" ", "", $request->cardNumber),
            'DollarAmount' => "",
            'ExpireDate' => $request->month.$request->year,
            'HolderName' => $request->owner, 
            'PayInvoice' => '',
            'RequestNo' => '',
            'Month' => $request->month,
            'Year' => $request->year,
            'ZipCode' => $request->zipcode,
            'Email' => $request->email,
            'WebsiteId' => 1,
            'Description' => 'USLegalization',
        ];
    
        $response = $this->paymentService->checkDoublePayment($requestData);
        return response()->json($response['response'], 200);
    }

    public function placeOrder($shippingMethod, $formData, $request)
    {
        // Generate request number
        $generatedRequestNumber = $this->orderService->generateRequestNumber();
        if (!$generatedRequestNumber) {
            return ['pay' => false, 'status' => 'Failed to generate request number'];
        }
    
        session()->put('requestNumber', $generatedRequestNumber);
    
        // Process shipping
        $shippingResult = $this->processShipping($shippingMethod, $formData, $request);
        if (isset($shippingResult['error'])) {
            return $shippingResult;
        }
    
        // Prepare order data
        $orderData = $this->prepareOrderData(
            $shippingMethod,
            $formData,
            $request,
            $shippingResult['fedexSettings'] ?? [],
            $shippingResult['fedexRate'] ?? 0
        );
    
        // Process payment if credit card
        if ($request->paymentMethod == 1) { // Credit
            $paymentResult = $this->processPayment($request, $orderData);
            if (!$paymentResult['success']) {
                return $paymentResult;
            }
            $orderData['Paid'] = true;
            $orderData['Due'] = 0;
            $orderData['AuthCode'] = $paymentResult['authCode'];
        } else {
            $orderData['Paid'] = false;
            $orderData['Due'] = $orderData['TotalAmount'];
        }
    
        // Save order
        $savedOrder = $this->orderService->saveOrder(
            $orderData,
            $this->prepareContactData($formData),
            isset($formData['before']) ? $formData['before']->IsExist : false
        );
    
        if (!$savedOrder) {
            return ['pay' => false, 'status' => 'Failed to save order'];
        }
        
    
        // Handle post-order actions
        $this->handlePostOrderActions($formData, $savedOrder->RequestNumber);
    
        return $this->buildSuccessResponse(
            $orderData,
            $shippingMethod,
            $shippingResult,
            $savedOrder,
            $request->paymentMethod == 1 ? 'Credit' : ($request->paymentMethod == 2 ? 'Billing' : 'Check')
        );
    }
    
    public function payInvoicePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'owner' => 'required',
            'cardNumber' => 'required',
            'cvv' => 'required',
            'month' => 'required',
            'year' => 'required'
        ]);

        if ($validator->fails()) {
            return redirect()->route('payinvoicedetails')->withErrors($validator)->withInput();
        }

        $invoices = [["number" => $request->invoiceNumber, "amount" => $request->invoicePrice]];
        
        if ($request->relatedInvoice != null && is_array($request->relatedInvoice)) {
            foreach ($request->relatedInvoice as $key => $value) {
                $invoices[] = ["number" => $key, "amount" => $value];
            }
        }

        $invoiceData = [
            'Cardno' => str_replace(" ", "", $request->cardNumber),
            'Cvv' => $request->cvv,
            'DollarAmount' => array_sum(array_column($invoices, 'amount')) * 1.05,
            'ExpireDate' => $request->month.$request->year,
            'HolderName' => $request->owner,
            'PayInvoice' => 1,
            'InvoiceNo' => $request->invoiceNumber,
            'Description' => 'USLegalization',
            'HFRequestNo' => $request->invoiceNumber,
            'Month' => $request->month,
            'Year' => $request->year,
            'ZipCode' => "",
            'Email' => "",
            'Phone' => "",
            'WebsiteId' => 1,
            'invoices' => $invoices
        ];

        try {
            $result = $this->invoiceService->payInvoice($invoiceData);
            return redirect()->route('afterPayInvoice');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['status' => $e->getMessage()]);
        }
    }
}