<?php
echo "<h3>🟢 File send_sale_test.php starting...</h3>";

$client_key = 'a9375190-26f2-11f0-be42-022c42254708';

$password = '554999c284e9f29cf95f090d9a8f3171';
$payment_url = 'https://api.leogcltd.com/post-va';
$payment_url = 'https://api.leogcltd.com/callback/aquanow';

$order_id =  'ORDER_' . time();
$amount = '1.99';
$currency = 'USD';
$brand = 'vcard'; 
$identifier = 'success@gmail.com';
$description = 'Test payment';
$payer_ip = $_SERVER['REMOTE_ADDR'];
$return_url = 'https://zal25.pp.ua/s2stest/callback.php';

// HASH = md5(strtoupper(strrev(identifier + order_id + amount + currency + password)))
$hash_source = $identifier . $order_id . $amount . $currency . $password;
$hash = md5(strtoupper(strrev($hash_source)));

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
    'hash' => $hash
];

$postFields = http_build_query($data);

$ch = curl_init($payment_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Content-Length: ' . strlen($postFields)
]);

$response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

// Спроба розпарсити JSON-відповідь
$response_data = json_decode($response, true);

echo "<pre>";
echo "🔹 ORDER_ID: $order_id\n\n";
echo "📤 Sent:\n";
print_r($data);
echo "\n📥 Raw Response:\n";
print_r($response);
echo "\n📥 Parsed Response:\n";
print_r($response_data);
if ($curl_error) {
    echo "\n❌ CURL Error: $curl_error\n";
}
echo "</pre>";

// Якщо є redirect_url — показуємо лінк
if (!empty($response_data['redirect_url'])) {
    echo "<p><a href='" . htmlspecialchars($response_data['redirect_url']) . "' target='_blank'>➡ Proceed to payment</a></p>";
} elseif (!empty($response_data['error_message'])) {
    echo "<p style='color: red;'>❌ API Error:</p><pre>";
    print_r($response_data);
    echo "</pre>";
} else {
    echo "<p style='color: orange;'>⚠️ No redirect_url in response.</p>";
}
