<?php
/**
 * CHECKOUT STATUS BY ORDER_ID WITH FULL LOGGING
 */

$checkoutHost = 'https://api.leogcltd.com';
$statusUrl    = $checkoutHost . '/api/v1/payment/status';

// YOUR CREDENTIALS
$merchantKey   = 'a9375384-26f2-11f0-877d-022c42254708';
$merchantPass  = '554999c284e9f29cf95f090d9a8f3171';

$orderId = $_GET['order_id'] ?? '';

if ($orderId === '') {
    die('order_id is required (?order_id=...)');
}

$logFile = __DIR__ . '/logs/checkout_status.log';

// ===================================
// HASH sha1(md5(strtoupper(order_id + pass)))
// ===================================

$toMd5 = $orderId . $merchantPass;
$hash  = sha1(md5(strtoupper($toMd5)));

$payload = [
    'merchant_key' => $merchantKey,
    'order_id'     => $orderId,
    'hash'         => $hash,
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

$ch = curl_init($statusUrl);
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
    "CURL ERROR:\n" . $error . "\n\n",
    FILE_APPEND
);

// ===================================
// OUTPUT
// ===================================

header('Content-Type: text/plain; charset=utf-8');

echo "ORDER ID:\n$orderId\n\n";
echo "REQUEST:\n";
print_r($payload);
echo "\nRESPONSE:\n$response";
