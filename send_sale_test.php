<?php
echo "<h3>🟢 File send_sale_test.php starting...</h3>";

$merchant_key = 'a9375190-26f2-11f0-be42-022c42254708';
$password = '554999c284e9f29cf95f090d9a8f3171';
$payment_url = 'https://api.leogcltd.com/api/v1/payment'; // наданий саппортом

$order_id = 'ORDER_' . time();
$amount = '1.99';
$currency = 'USD';
$identifier = 'success@gmail.com';
$description = 'Test payment';
$payer_ip = $_SERVER['REMOTE_ADDR'];
$success_url = 'https://zal25.pp.ua/s2stest/callback.php';
$fail_url = 'https://zal25.pp.ua/s2stest/fail.php';
$cancel_url = 'https://zal25.pp.ua/s2stest/cancel.php';

// Hash формула згідно S2S APM: md5(strtoupper(strrev(identifier + order_id + amount + currency + PASSWORD)))
$hash_source = $identifier . $order_id . $amount . $currency . $password;
$hash = md5(strtoupper(strrev($hash_source)));

$data = [
    'operation' => 'sale',
    'action' => 'SALE',
    'merchant_key' => $merchant_key,
    'success_url' => $success_url,
    'fail_url' => $fail_url,
    'cancel_url' => $cancel_url,
    'order_number' => $order_id,
    'order_amount' => $amount,
    'order_currency' => $currency,
    'order_description' => $description,
    'customer_identifier' => $identifier,
    'customer_ip' => $payer_ip,
    'hash' => $hash
];

// Створюємо форму для POST-запиту у форматі application/x-www-form-urlencoded
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

// Обробка відповіді
parse_str($response, $response_data); // якщо відповідь також url-encoded
if (empty($response_data)) {
    $response_data = json_decode($response, true); // fallback if JSON
}

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

// Якщо є redirect_url — даємо лінк
if (!empty($response_data['redirect_url'])) {
    echo "<p><a href='" . htmlspecialchars($response_data['redirect_url']) . "' target='_blank'>➡ Proceed to payment</a></p>";
} elseif (!empty($response_data['errors']) || !empty($response_data['error_message'])) {
    echo "<p style='color: red;'>❌ API Error:</p><pre>";
    print_r($response_data);
    echo "</pre>";
} else {
    echo "<p style='color: orange;'>⚠️ No redirect_url in response.</p>";
}
