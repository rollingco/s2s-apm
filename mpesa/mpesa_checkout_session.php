<?php
/**
 * MPESA CHECKOUT SESSION WITH FULL LOGGING
 */

$checkoutHost = 'https://api.leogcltd.com';
$sessionUrl   = $checkoutHost . '/api/v1/session';

$merchantKey  = 'PUT_YOUR_MERCHANT_KEY';
$merchantPass = 'PUT_YOUR_MERCHANT_PASSWORD';

$orderNumber   = 'mpesa-checkout-' . time();
$orderAmount   = '10.00';
$orderCurrency = 'KES';
$orderDescr    = 'Test Mpesa payment';

$successUrl = 'https://yourdomain.com/success.php';
$cancelUrl  = 'https://yourdomain.com/cancel.php';

$logFile = __DIR__ . '/logs/mpesa_checkout_session.log';

// ================================
// HASH
// ================================

$toMd5 = $orderNumber . $orderAmount . $orderCurrency . $orderDescr . $merchantPass;
$hash  = sha1(md5(strtoupper($toMd5)));

// ================================
// PAYLOAD
// ================================

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

// ================================
// LOG REQUEST
// ================================

file_put_contents($logFile,
    "====================\n" .
    "DATE: " . date('c') . "\n" .
    "REQUEST:\n" .
    json_encode($payload, JSON_PRETTY_PRINT) . "\n",
    FILE_APPEND
);

// ================================
// CURL
// ================================

$ch = curl_init($sessionUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);

curl_close($ch);

// ================================
// LOG RESPONSE
// ================================

file_put_contents($logFile,
    "HTTP CODE: {$httpCode}\n" .
    "RESPONSE:\n" .
    $response . "\n" .
    "ERROR:\n" .
    $error . "\n\n",
    FILE_APPEND
);

// ================================
// REDIRECT OR DEBUG
// ================================

$data = json_decode($response, true);

if (!empty($data['redirect_url'])) {
    header('Location: ' . $data['redirect_url']);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
echo "NO REDIRECT URL\n\n";
echo $response;
