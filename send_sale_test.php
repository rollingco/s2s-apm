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
$fail_url = 'https://zal25.pp.ua/s2stest/fail.php';
$cancel_url = 'https://zal25.pp.ua/s2stest/cancel.php';

// Correct hash formula: identifier + order_id + amount + currency + PASSWORD
$hash_source = $identifier . $order_id . $amount . $currency . $password;
$hash = md5(strtoupper(strrev($hash_source)));

$data = [
    "operation" => "sale", // lowercase required!
    "merchant_key" => $merchant_key,
    "success_url" => $success_url,
    "fail_url" => $fail_url,
    "cancel_url" => $cancel_url,
    "order" => [
        "number" => $order_id,
        "amount" => $amount,
        "currency" => $currency,
        "description" => $description
    ],
    "customer" => [ // this must be "customer", not "payer"
        "identifier" => $identifier,
        "ip" => $payer_ip
    ],
    "hash" => $hash
];

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

$response_data = json_decode($response, true);

// Output
echo "<pre>";
echo "🔹 ORDER_ID: $order_id\n\n";
echo "📤 Sent:\n";
print_r($data);
echo "\n📥 Response:\n";
print_r($response_data);
if ($curl_error) {
    echo "\n❌ CURL Error: $curl_error\n";
}
echo "</pre>";

// Redirect link
if (!empty($response_data['redirect_url'])) {
    echo "<p><a href='" . htmlspecialchars($response_data['redirect_url']) . "' target='_blank'>➡ Proceed to payment</a></p>";
} elseif (!empty($response_data['errors'])) {
    echo "<p style='color: red;'>❌ API Errors:</p><pre>";
    print_r($response_data['errors']);
    echo "</pre>";
} else {
    echo "<p style='color: orange;'>⚠️ No redirect_url in response.</p>";
}
