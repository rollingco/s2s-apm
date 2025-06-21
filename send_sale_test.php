<?php
$client_key = 'a9375190-26f2-11f0-be42-022c42254708';
$password = '554999c284e9f29cf95f090d9a8f3171';
//$payment_url = 'https://pay.leogcltd.com'; // заміни на актуальний, якщо є
$payment_url = 'https://pay.leogcltd.com/api/v1/session';

$order_id = 'ORDER_' . time(); // Динамічний order_id
$amount = '1.99';
$currency = 'USD';
$brand = 'vcard';
$identifier = 'success@gmail.com';
$description = 'Test payment';
$payer_ip = $_SERVER['REMOTE_ADDR'];
$return_url = 'https://zal25.pp.ua/s2stest/callback.php'; // Заміни на актуальний, якщо інший

// Правильна формула згідно з документацією
$data_to_sign = strrev($identifier . $order_id . $amount . $currency . $password);
$hash = md5(strtoupper($data_to_sign));

// Формуємо масив даних
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

// CURL-запит
$ch = curl_init($payment_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
$response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

// Вивід на екран
echo "<pre>";
echo "🔹 <b>ORDER_ID:</b> $order_id\n\n";
echo "📤 <b>Sent Data:</b>\n";
print_r($data);
echo "\n🔐 <b>Hash Source:</b> identifier + order_id + amount + currency + password\n";
echo "STR (before reverse): " . $identifier . $order_id . $amount . $currency . $password . "\n";
echo "STR (after reverse):  " . strrev($identifier . $order_id . $amount . $currency . $password) . "\n";
echo "🔑 <b>HASH:</b> $hash\n";
echo "\n📥 <b>Response:</b>\n";
print_r($response);
if ($curl_error) {
    echo "\n❌ <b>CURL Error:</b> $curl_error\n";
}
echo "</pre>";



