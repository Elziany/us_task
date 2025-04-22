<?php

namespace App\Services;

use App\Models\TransactionLog;
use Illuminate\Http\Request;

class TransactionLogger
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function log(
        string $type,
        string $status,
        ?string $reference = null,
        ?float $amount = null,
        ?array $requestData = null,
        ?array $responseData = null,
        ?string $notes = null
    ): TransactionLog {
        return TransactionLog::create([
            'transaction_type' => $type,
            'reference_number' => $reference,
            'amount' => $amount,
            'status' => $status,
            'request_data' => $requestData ? json_encode($requestData) : null,
            'response_data' => $responseData ? json_encode($responseData) : null,
            'ip_address' => $this->request->ip(),
            'notes' => $notes,
        ]);
    }
}