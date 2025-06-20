<?php
$client_key = 'ВАШ_CLIENT_KEY';
$password = 'ВАШ_PASSWORD';
$payment_url = 'https://test.apiurl.com/post-va'; // заміни, якщо в доках інший

$order_id = 'ORDER12345';
$amount = '1.99';
$currency = 'USD';
$brand = 'vcard';
$identifier = 'success@gmail.com';
$description = 'Test payment';
$payer_ip = $_SERVER['REMOTE_ADDR'];
$return_url = 'https://ВАШ_ДОМЕН/callback.php'; // заміни на свій реальний URL

$data_string = strrev($identifier . $order_id . $amount . $currency . $password);
$hash = md5(strtoupper($data_string));

$data = [
    'action' => 'SALE',
    'client_key' => $client_key,
    'brand' => $brand,
    'order_id' => $order_id,
    'order_amount' => $amount,
    'order_currency' => $currency,
    'order_description' => $description,
    'identifier' => $identifier,
    'payer_ip' => $payer_ip,
    'return_url' => $return_url,
    'hash' => $hash,
];

$ch = curl_init($payment_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
$response = curl_exec($ch);
curl_close($ch);

echo "<pre>";
print_r($response);
echo "</pre>";
