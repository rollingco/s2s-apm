<?php

/**
 * MPESA S2S APM - PAY-IN (SALE) TEST
 *
 * Currency: KES
 * Brand: mpesa
 * Environment: Sandbox / Production
 */

// ================================
// CONFIG
// ================================

$endpoint = "https://api.leogcltd.com/post-va"; // or sandbox endpoint if provided

$client_id = "a9375190-26f2-11f0-be42-022c42254708";
$client_password = "554999c284e9f29cf95f090d9a8f3171";

$order_id = "mpesa-sale-" . time();
$amount   = "100.00";
$currency = "KES";
$msisdn   = "254700000000"; // TEST KENYA NUMBER

// ================================
// SIGNATURE GENERATION
// ================================
// Classic LeoGC signature logic

$signature_string = $order_id . $amount . $currency . $client_password;
$signature = md5(strtoupper(strrev($signature_string)));

// ================================
// REQUEST BODY
// ================================

$data = [
    "action"         => "SALE",
    "client_key"     => $client_id,
    "order_id"       => $order_id,
    "order_amount"   => $amount,
    "order_currency" => $currency,
    "brand"          => "mpesa",
    "msisdn"         => $msisdn,
    "signature"     => $signature
];

// ================================
// CURL REQUEST
// ================================

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$error    = curl_error($ch);

curl_close($ch);

// ================================
// OUTPUT
// ================================

echo "<pre>";
echo "REQUEST:\n";
print_r($data);

echo "\nRESPONSE:\n";
echo $response;

if ($error) {
    echo "\nCURL ERROR:\n" . $error;
}
echo "</pre>";
