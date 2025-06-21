<?php
$merchant_key = 'a9375190-26f2-11f0-be42-022c42254708';
$password = '554999c284e9f29cf95f090d9a8f3171';
$payment_url = 'https://pay.leogcltd.com/api/v1/session';

$order_id = 'ORDER_' . time();
$amount = '1.99';
$currency = 'USD';
$identifier = 'success@gmail.com';
$description = 'Test payment';
$payer_ip = $_SERVER['REMOTE_ADDR'];
$success_url = 'https://zal25.pp.ua/s2stest/callback.php';

// Хеш згідно з документацією
$hash = md5(strtoupper(strrev($identifier . $order_id . $amount . $currency . $password)));

$data = [
    "operation" => "SALE",
    "merchant_key" => $merchant_key,
    "success_url" => $success_url,
    "order" => [
        "id" => $order_id,
        "amount" => $amount,
        "currency" => $currency,
        "description" => $description
    ],
    "payer" => [
        "identifier" => $identifier,
        "ip" => $payer_ip
    ],
    "hash" => $hash
];

// Надсилання запиту як JSON
$json_payload = json_encode($data);

$ch = curl_init($payment_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($json_payload)
]);
$response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

// Парсимо відповідь
$response_data = json_decode($response, true);

// Вивід
echo "<pre>";
echo "🔹 <b>ORDER_ID:</b> $order_id\n\n";
echo "📤 <b>Sent JSON:</b>\n";
print_r($data);
echo "\n🔐 <b>HASH:</b> $hash\n";
echo "\n📥 <b>Response:</b>\n";
print_r($response_data);
if ($curl_error) {
    echo "\n❌ <b>CURL Error:</b> $curl_error\n";
}
echo "</pre>";

// Якщо є redirect_url — редирект на оплату
if (!empty($response_data['redirect_url'])) {
    echo "<p><a href='" . htmlspecialchars($response_data['redirect_url']) . "' target='_blank'>Перейти до оплати</a></p>";
}
