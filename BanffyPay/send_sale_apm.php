<?php
/**
 * S2S SALE — BanffyPay (MID-based) with debug
 */

header('Content-Type: text/html; charset=utf-8');

$URL = 'https://api.leogcltd.com/post-va';

$CLIENT_KEY = '5f306e12-0ff2-11f1-bac9-0a9a38974658';
$SECRET     = '976d5c5d5eacbab78288b12bb15178ba';

// === DATA ===
$order_id   = 'TZ_' . time();
$amount     = '1000.00';
$currency   = 'TZS'; // уточнити
$identifier = '111';

$payer_phone = '255683456789';
$payer_email = 'test@test.com';
$payer_first = 'John';
$payer_last  = 'Doe';
$payer_ip    = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// === HASH ===
$hash_src = $identifier . $order_id . $amount . $currency . $SECRET;
$hash = md5(strtoupper(strrev($hash_src)));

// === REQUEST ===
$data = [
    'action'            => 'SALE',
    'client_key'        => $CLIENT_KEY,
    'brand'             => 'leogc-bannf',

    'order_id'          => $order_id,
    'order_amount'      => $amount,
    'order_currency'    => $currency,
    'order_description' => 'BanffyPay test',

    'identifier'        => $identifier,
    'payer_ip'          => $payer_ip,

    // ці поля підхопляться MID mapping
    'payer_phone'       => $payer_phone,
    'payer_email'       => $payer_email,
    'payer_first_name'  => $payer_first,
    'payer_last_name'   => $payer_last,

    'return_url'        => 'https://google.com',

    'hash'              => $hash,
];

// === CURL ===
$ch = curl_init($URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $data,
    CURLOPT_TIMEOUT        => 60,
]);

$response = curl_exec($ch);
$info     = curl_getinfo($ch);
$error    = curl_errno($ch) ? curl_error($ch) : '';
curl_close($ch);

// === DEBUG OUTPUT ===
echo "<h3>Request</h3>";
echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";

echo "<h3>Hash source</h3>";
echo "<pre>$hash_src</pre>";

echo "<h3>Hash</h3>";
echo "<pre>$hash</pre>";

echo "<h3>Response</h3>";
echo "<pre>$response</pre>";

echo "<h3>HTTP Code</h3>";
echo "<pre>" . ($info['http_code'] ?? 'N/A') . "</pre>";

if ($error) {
    echo "<h3>CURL Error</h3>";
    echo "<pre>$error</pre>";
}