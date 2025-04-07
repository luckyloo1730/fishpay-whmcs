<?php

require __DIR__ . '/init.php';
require_once __DIR__ . '/includes/gatewayfunctions.php';
require_once __DIR__ . '/includes/invoicefunctions.php';

use WHMCS\Database\Capsule;

// Config
$gatewayParams = getGatewayVariables("fishpay");
$apiKey = $gatewayParams["api_key"];
$merchantId = $gatewayParams["merchant_id"];
$baseUrl = rtrim($gatewayParams["gateway_url"], '/');

// Get logs for pending transactions
$logs = Capsule::table('tblgatewaylog')
    ->where('gateway', 'FishPay')
    ->where('data', 'like', '%Status: Created%')
    ->orderBy('id', 'desc')
    ->get();

foreach ($logs as $log) {
    if (!preg_match('/Invoice ID: (\d+)\nTransaction ID: ([-a-z0-9]+)/i', $log->data, $m)) continue;

    $invoiceId = $m[1];
    $transactionId = $m[2];

    $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
    if (!$invoice || $invoice->status === 'Paid') continue;

    // Check FishPay API
    $ch = curl_init("{$baseUrl}/check-payment/{$transactionId}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: $apiKey",
        "Merchant-ID: $merchantId"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (isset($data['status']) && $data['status'] === "completed") {
        // Mark invoice as paid
        addInvoicePayment(
            $invoiceId,
            $transactionId . '-' . time(),
            $invoice->total,
            0,
            $gatewayParams['paymentmethod']
        );

        logTransaction("FishPay", "✅ Auto-paid invoice $invoiceId via CRON\nTX: $transactionId", "Success");
    }
}
