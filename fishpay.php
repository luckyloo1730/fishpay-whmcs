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

    // Step 2: Redirect with pretty UI
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Redirecting to FishPay...</title>
    <meta http-equiv="refresh" content="0; url={$paymentUrl}">
    <style>
        body {
            background-color: #111;
            color: #ccc;
            font-family: "Segoe UI", sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }
        .box {
            background-color: #1a1a1a;
            padding: 30px 40px;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(0,0,0,0.5);
            max-width: 400px;
        }
        .title {
            font-size: 22px;
            margin-bottom: 10px;
            color: #00ffcc;
        }
        .desc {
            font-size: 15px;
            margin-top: 10px;
            color: #999;
        }
        a {
            color: #00bfff;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="title">🔁 Redirecting to FishPay...</div>
        <div class="desc">
            If you are not redirected automatically,<br>
            <a href="{$paymentUrl}">click here to continue</a>.
        </div>
    </div>
</body>
</html>
HTML;
}
