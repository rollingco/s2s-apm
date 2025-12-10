<?php

/**
 * APM STATUS CHECK (MPESA / AIRTEL / TOLA)
 *
 * Action: STATUS
 * Use this file after SALE or CREDIT
 */

// ================================
// CONFIG (YOUR DATA)
// ================================

$endpoint = "https://api.leogcltd.com/post";

$client_id = "a9375384-26f2-11f0-877d-022c42254708";
$client_password = "554999c284e9f29cf95f090d9a8f3171";

// ✅ ВАЖЛИВО: сюди підставляєш order_id із Checkout або S2S
$order_id = $_GET['order_id'] ?? 'mpesa-checkout-TEST-PASTE-HERE';


// ================================
// SIGNATURE
// ================================

$signature_string = $order_id . $client_password;
$signature = md5(strtoupper(strrev($signature_string)));


// ================================
// REQUEST
// ================================

$data = [
    "action"     => "STATUS",
    "client_key"=> $client_id,
    "order_id"  => $order_id,
    "signature" => $signature
];


// ================================
// CURL
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
echo "ORDER ID:\n" . $order_id . "\n\n";

echo "REQUEST:\n";
print_r($data);

echo "\nRESPONSE:\n";
echo $response;

if ($error) {
    echo "\nCURL ERROR:\n" . $error;
}

echo "</pre>";
