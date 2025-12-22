<?php
/**
 * MPESA S2S APM - PAY-IN (SALE) TEST
 *
 * Last update: 2025-12-22  (server time)
 *
 * Currency: KES
 * Brand: mpesa
 * Environment: Sandbox / Production
 */

// ================================
// CONFIG
// ================================
$endpoint = "https://api.leogcltd.com/post-va";
//sdgfdgffdg
$client_id       = "a9375190-26f2-11f0-be42-022c42254708";
$client_password = "554999c284e9f29cf95f090d9a8f3171"; // SECRET

$order_id = "mpesa-sale-" . time();
$amount   = number_format(10, 2, '.', '');   // always "10.00"
$currency = "KES";
$msisdn   = "254700000000"; // TEST KENYA NUMBER

// ================================
// SIGNATURE GENERATION (LeoGC classic)
// md5( strtoupper(strrev(order_id . amount . currency)) . SECRET )
// ================================
$signature_base = strtoupper(strrev($order_id . $amount . $currency));
$signature      = md5($signature_base . $client_password);

// ================================
// REQUEST BODY
// (msisdn is placed inside parameters - common for APM methods)
// ================================
$data = [
  "action"         => "SALE",
  "client_key"     => $client_id,
  "order_id"       => $order_id,
  "order_amount"   => $amount,
  "order_currency" => $currency,
  "brand"          => "mpesa-express",
  "parameters"     => [
    "msisdn" => $msisdn
  ],
  "signature"      => $signature
];

$request_json = json_encode($data, JSON_UNESCAPED_SLASHES);

// ================================
// CURL REQUEST
// ================================
$ch = curl_init($endpoint);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
  CURLOPT_POSTFIELDS     => $request_json,
]);

$response = curl_exec($ch);
$error    = curl_error($ch);
$info     = curl_getinfo($ch);
$httpCode = $info['http_code'] ?? null;

curl_close($ch);

// ================================
// OUTPUT (SCREEN ONLY)
// ================================
echo "<pre style='background:#0b1020;color:#e8e8e8;padding:14px;border-radius:10px;white-space:pre-wrap;font-size:13px;line-height:1.35'>";

echo "SCRIPT: " . basename(__FILE__) . "\n";
echo "LAST UPDATE (filemtime): " . date('Y-m-d H:i:s', filemtime(__FILE__)) . "\n";
echo "TIMEZONE: " . date_default_timezone_get() . "\n\n";

echo "ENDPOINT: {$endpoint}\n";
echo "ACTION: SALE | BRAND: {$brand} | CURRENCY: {$currency}\n\n";

echo "SIGNATURE BASE (upper+rev of order_id+amount+currency):\n{$signature_base}\n\n";
echo "SIGNATURE (md5(base + SECRET)):\n{$signature}\n\n";

echo "REQUEST (array):\n";
print_r($data);

echo "\nREQUEST (json):\n{$request_json}\n\n";

echo "HTTP CODE:\n" . ($httpCode !== null ? $httpCode : "n/a") . "\n\n";

echo "CURL INFO:\n";
print_r($info);

echo "\nRESPONSE:\n";
echo ($response !== false ? $response : "false");

if (!empty($error)) {
  echo "\n\nCURL ERROR:\n{$error}";
}

echo "</pre>";
