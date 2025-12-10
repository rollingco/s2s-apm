<?php
/**
 * CHECKOUT GET_TRANS_STATUS by order_id
 * POST {{CHECKOUT_HOST}}/api/v1/payment/status
 */

$checkoutHost  = 'https://api.leogcltd.com';
$statusUrl     = $checkoutHost . '/api/v1/payment/status';

$merchantKey   = 'a9375384-26f2-11f0-877d-022c42254708';
$merchantPass  = '554999c284e9f29cf95f090d9a8f3171';

// order_id беремо з GET-параметра
$orderId = $_GET['order_id'] ?? '';

if ($orderId === '') {
    die('order_id is required (?order_id=...)');
}

// hash = sha1(md5(strtoupper(order_id + merchant_pass)))
$toMd5  = $orderId . $merchantPass;
$hash   = sha1(md5(strtoupper($toMd5)));

$payload = [
    'merchant_key' => $merchantKey,
    'order_id'     => $orderId,
    'hash'         => $hash,
];

$ch = curl_init($statusUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$error    = curl_error($ch);
curl_close($ch);

header('Content-Type: text/plain; charset=utf-8');

if ($error) {
    echo "cURL ERROR:\n" . $error . "\n\n";
}

echo "REQUEST:\n";
print_r($payload);

echo "\nRESPONSE:\n";
echo $response;
