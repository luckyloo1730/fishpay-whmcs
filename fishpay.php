<?php

use WHMCS\Database\Capsule;

function fishpay_config() {
    return [
        "FriendlyName" => ["Type" => "System", "Value" => "FishPay"],
        "api_key" => ["FriendlyName" => "API Key", "Type" => "text"],
        "merchant_id" => ["FriendlyName" => "Merchant ID", "Type" => "text"],
        "gateway_url" => ["FriendlyName" => "Gateway Base URL", "Type" => "text", "Default" => "https://pay.zoomov.xyz"]
    ];
}

function fishpay_link($params) {
    $apiKey = $params['api_key'];
    $merchantId = $params['merchant_id'];
    $baseUrl = rtrim($params['gateway_url'], '/');

    $rubAmount = floatval($params['amount']);
    $amount = round($rubAmount * 0.5, 2); // RUB → UAH

    $invoiceId = $params['invoiceid'];
    $userId = $params['clientdetails']['userid'];

    // Step 1: Generate the payment
    $postData = json_encode([
        "amount" => $amount,
        "user_id" => $userId
    ]);

    $ch = curl_init("{$baseUrl}/generate-payment");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: $apiKey",
        "Merchant-ID: $merchantId",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (!isset($data['paymentPageUrl']) || !isset($data['transactionId'])) {
        logTransaction("FishPay", "❌ Invalid generate-payment response:\n" . $response, "Error");
        return "<div style='padding: 20px; color: red;'>❌ FishPay error: could not generate payment</div>";
    }

    $transactionId = $data['transactionId'];
    $paymentUrl = $data['paymentPageUrl'];

    // Log TX for background checker
    logTransaction("FishPay", "Invoice ID: $invoiceId\nTransaction ID: $transactionId\nStatus: Created", "Pending");
    
return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Redirecting...</title>
    <meta http-equiv="refresh" content="0; url={$paymentUrl}">
    <style>
        body {
            background-color: #f9f9f9;
            color: #000;
            font-family: sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .button {
            background-color: #007bff;
            color: #fff;
            padding: 14px 26px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <a class="button" href="{$paymentUrl}">Redirecting to FishPay...</a>
</body>
</html>
HTML;

