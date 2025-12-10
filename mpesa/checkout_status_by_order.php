<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$checkoutHost = 'https://api.leogcltd.com';
$statusUrl    = $checkoutHost . '/api/v1/payment/status';

// ✅ ТВОЇ ДАНІ
$merchantKey   = 'a9375384-26f2-11f0-877d-022c42254708';
$merchantPass  = '554999c284e9f29cf95f090d9a8f3171';

$orderId = $_GET['order_id'] ?? '';

if ($orderId === '') {
    die('❌ order_id is required (?order_id=...)');
}

// ✅ HASH
$toMd5 = $orderId . $merchantPass;
$hash  = sha1(md5(strtoupper($toMd5)));

$payload = [
    'merchant_key' => $merchantKey,
    'order_id'     => $orderId,
    'hash'         => $hash,
];

// =======================
// ✅ DEBUG: REQUEST
// =======================

echo "<pre>";
echo "==================== STATUS REQUEST ====================\n";
echo "URL:\n$statusUrl\n\n";
echo "HASH BASE STRING:\n$toMd5\n\n";
echo "FINAL HASH:\n$hash\n\n";
echo "JSON PAYLOAD:\n";
print_r($payload);

// =======================
// ✅ CURL
// =======================

$ch = curl_init($statusUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);

curl_close($ch);

// =======================
// ✅ DEBUG: RESPONSE
// =======================

echo "\n==================== STATUS RESPONSE ====================\n";
echo "HTTP CODE:\n$httpCode\n\n";
echo "RAW RESPONSE:\n$response\n\n";
echo "CURL ERROR:\n$error\n\n";
echo "</pre>";
