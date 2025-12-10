<?php
/**
 * MPESA CHECKOUT SESSION WITH FULL LOGGING
 */

$checkoutHost = 'https://api.leogcltd.com';
$sessionUrl   = $checkoutHost . '/api/v1/session';

// YOUR CREDENTIALS (sandbox or prod)
$merchantKey   = 'a9375384-26f2-11f0-877d-022c42254708';
$merchantPass  = '554999c284e9f29cf95f090d9a8f3171';

$orderNumber   = 'mpesa-checkout-' . time();
$orderAmount   = '10.00';
$orderCurrency = 'KES';
$orderDescr    = 'Test Mpesa checkout payment';

$successUrl = 'https://zal25.pp.ua/success.php';
$cancelUrl  = 'https://zal25.pp.ua/cancel.php';

$logFile = __DIR__ . '/logs/mpesa_checkout_session.log';

// ===================================
// HASH (sha1(md5(strtoupper(...))))
// ===================================

$toMd5 = $orderNumber . $orderAmount . $orderCurrency . $orderDescr . $merchantPass;
$hash  = sha1(md5(strtoupper($toMd5)));

$payload = [
    'merchant_key' => $merchantKey,
    'operation'    => 'purchase',
    'methods'      => ['mpesa'],
    'order'        => [
        'number'      => $orderNumber,
        'amount'      => $orderAmount,
        'currency'    => $orderCurrency,
        'description' => $orderDescr,
    ],
    'success_url' => $successUrl,
    'cancel_url'  => $cancelUrl,
    'hash'        => $hash,
];

// LOG REQUEST
file_put_contents($logFile,
    "\n====================\n" .
    "DATE: " . date('c') . "\n" .
    "REQUEST:\n" . json_encode($payload, JSON_PRETTY_PRINT) . "\n",
    FILE_APPEND
);

// ===================================
// CURL REQUEST
// ===================================

$ch = curl_init($sessionUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);

curl_close($ch);

// LOG RESPONSE
file_put_contents($logFile,
    "HTTP CODE: {$httpCode}\n" .
    "RESPONSE:\n" . $response . "\n" .
    "CURL ERROR:\n" . $error . "\n",
    FILE_APPEND
);

// ===================================
// HANDLE RESULT
// ===================================

$data = json_decode($response, true);

if (!empty($data['redirect_url'])) {
    header('Location: ' . $data['redirect_url']);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
echo "NO redirect_url FOUND\n\n";
echo $response;
