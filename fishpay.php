<?php

use WHMCS\Database\Capsule;

function fishpay_config() {
    return [
        "FriendlyName" => [
            "Type" => "System",
            "Value" => "FishPay"
        ],
        "api_key" => [
            "FriendlyName" => "API Key",
            "Type" => "text",
            "Size" => "100"
        ],
        "merchant_id" => [
            "FriendlyName" => "Merchant ID",
            "Type" => "text",
            "Size" => "100"
        ],
        "gateway_url" => [
            "FriendlyName" => "Gateway Base URL",
            "Type" => "text",
            "Size" => "100",
            "Default" => "https://pay.zoomov.xyz"
        ]
    ];
}

function fishpay_link($params) {
    $apiKey = $params['api_key'];
    $merchantId = $params['merchant_id'];
    $baseUrl = rtrim($params['gateway_url'], '/');

    $rubAmount = floatval($params['amount']);
    $amount = round($rubAmount * 0.5, 2); // Convert RUB → UAH
    $userId = $params['clientdetails']['userid'];
    $invoiceId = $params['invoiceid'];
    $paymentMethod = $params['paymentmethod'];

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

    // Check if valid
    if (!isset($data['paymentPageUrl']) || !isset($data['transactionId'])) {
        return '
            <div style="padding: 20px; background: #1e1e1e; border: 1px solid #444; color: #ff4d4f; border-radius: 6px; font-family: monospace;">
                <strong>❌ FishPay error:</strong><br>
                Invalid response from API<br><br>
                <strong>📦 Raw response:</strong><br>
                <div style="background: #2a2a2a; color: #ccc; padding: 10px; border-radius: 4px;">
                    ' . htmlspecialchars($response) . '
                </div>
            </div>';
    }

    $transactionId = $data['transactionId'];
    $paymentUrl = $data['paymentPageUrl'];

    // Log the transaction as pending
    logTransaction("FishPay", "Invoice ID: $invoiceId\nTransaction ID: $transactionId\nStatus: Waiting for payment", "Pending");

    // Return HTML that redirects to payment page
    return '
        <meta http-equiv="refresh" content="0; url=' . htmlspecialchars($paymentUrl) . '">
        <div style="padding: 20px; background: #1e1e1e; border: 1px solid #444; color: #00ffcc; border-radius: 6px; font-family: monospace;">
            <strong>💳 Redirecting you to FishPay...</strong><br>
            If you are not redirected, <a href="' . htmlspecialchars($paymentUrl) . '">click here</a>.
        </div>
    ';
}
