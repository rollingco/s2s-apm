<?php

// ----------------------
// CONFIG
// ----------------------
$CLIENT_KEY  = '01158d9a-9de6-11f0-ac32-ca759a298692';
$SECRET      = '4b486f4c7bee7cb42ccca2a5a980910e';
$ENDPOINT    = 'https://api.leogcltd.com/post';

// ----------------------
// ORDER DATA
// ----------------------
$order_id       = 'afrimoney-' . time();
$order_amount   = '10.00';
$order_currency = 'SLE';

// ----------------------
// SIGNATURE (exact formula)
// md5(strtoupper(strrev(order_id + amount + currency)) + SECRET)
// ----------------------
$hash = md5(
    strtoupper(
        strrev($order_id . $order_amount . $order_currency)
    ) . $SECRET
);

// ----------------------
// BUILD REQUEST
// ----------------------
$data = [
    'action'            => 'CREDIT2VIRTUAL',
    'client_key'        => $CLIENT_KEY,
    'brand'             => 'afri-money-dbm',   // Brand for AfriMoney
    'order_id'          => $order_id,
    'order_amount'      => $order_amount,
    'order_currency'    => $order_currency,
    'order_description' => 'AfriMoney payout test',
    'hash'              => $hash
];

// ----------------------
// SEND REQUEST
// ----------------------
$ch = curl_init($ENDPOINT);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

$responseRaw = curl_exec($ch);
curl_close($ch);

// ----------------------
// OUTPUT
// ----------------------
echo "REQUEST:\n";
print_r($data);

echo "\n\nRESPONSE:\n";
echo $responseRaw . "\n\n";

$response = json_decode($responseRaw, true);

if (!empty($response['status']) && $response['status'] === 'REDIRECT') {
    echo "REDIRECT URL:\n";
    echo $response['redirect_url'] . "\n";
    echo "Open this URL — the recipient will enter the MSISDN manually.\n";
}
