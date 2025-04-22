<?php

namespace App\Services;

use App\Models\ShippingContact;
use GuzzleHttp\Client;

class FedexService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function createFedexTicket(array $requestData, bool $priorityOvernight, int $numberOfLabels, string $type)
    {
        // Implementation of FedEx ticket creation
        // This would contain the logic from FedexController::createFedexTicket()
    }

    public function validateFedexForm(string $shippingMethod, array $formData, $request)
    {
        // Implementation of FedEx form validation
        // This would contain the logic from FedexController::validateFedexForm()
    }

    public function saveShippingContact(array $shippingData, string $invoiceNumber)
    {
        return ShippingContact::create([
            'name' => $shippingData['firstname'] . ' ' . ($shippingData['lastname'] ?? ""),
            'phone' => $shippingData['phone'],
            'zipcode' => $shippingData['zipcode'],
            'city' => $shippingData['city'] ?? "",
            'country' => $shippingData['country'] ?? "",
            'address' => $shippingData['address'],
            'address_second' => $shippingData['internationalAddress4'] ?? "",
            'province' => $shippingData['internationalAddress51'] ?? "",
            'company' => $shippingData['companyName'] ?? "",
            'invoice' => $invoiceNumber
        ]);
    }
}