<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

$checkoutHost = 'https://pay.leogcltd.com';
$sessionUrl   = $checkoutHost . '/api/v1/session';

// ✅ MERCHANT (MID)
$merchantKey   = 'a9375190-26f2-11f0-be42-022c42254708';
$merchantPass  = '554999c284e9f29cf95f090d9a8f3171';

// ✅ ORDER
$orderNumber   = 'mtnmomo-checkout-' . time();
$orderAmount   = '10.00';

// MTN MoMo currency depends on country. For example: UGX (Uganda), GHS (Ghana), XAF (Cameroon), etc.
// Put the currency you were told to use for this MID / test environment.
$orderCurrency = 'LRD';

$orderDescr    = 'Test MTN MoMo checkout payment';

$successUrl = 'https://zal25.pp.ua/success.php';
$cancelUrl  = 'https://zal25.pp.ua/cancel.php';

// ✅ Optional: customer phone (E.164, usually without +)
$payerPhone = '256700000000';

// ✅ HASH (same as your example)
$toMd5 = $orderNumber . $orderAmount . $orderCurrency . $orderDescr . $merchantPass;
$hash  = sha1(md5(strtoupper($toMd5)));

// ✅ PAYLOAD
$payload = [
    'merchant_key' => $merchantKey,
    'operation'    => 'purchase',

    // ✅ MTN MoMo method (Akurateco brand is paytota)
    // If your checkout expects "paytota" or "paytota_mtn" etc — this is the ONLY line to change.
    'methods'      => ['paytota'],

    'order'        => [
        'number'      => $orderNumber,
        'amount'      => $orderAmount,
        'currency'    => $orderCurrency,
        'description' => $orderDescr,
    ],

    // ✅ If checkout supports passing phone — keep it.
    // If not supported, it will be ignored.
    'customer' => [
        'phone' => $payerPhone,
    ],

    'success_url' => $successUrl,
    'cancel_url'  => $cancelUrl,
    'hash'        => $hash,
];

// =======================
// ✅ DEBUG: REQUEST
// =======================

echo "<pre>";
echo "==================== REQUEST ====================\n";
echo "URL:\n$sessionUrl\n\n";
echo "HASH BASE STRING:\n$toMd5\n\n";
echo "FINAL HASH:\n$hash\n\n";
echo "JSON PAYLOAD:\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// =======================
// ✅ CURL
// =======================

$ch = curl_init($sessionUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);

curl_close($ch);

// =======================
// ✅ DEBUG: RESPONSE
// =======================

echo "==================== RESPONSE ====================\n";
echo "HTTP CODE:\n$httpCode\n\n";
echo "RAW RESPONSE:\n$response\n\n";
echo "CURL ERROR:\n$error\n\n";

// =======================
// ✅ REDIRECT CHECK
// =======================

$data = json_decode($response, true);

if (!empty($data['redirect_url'])) {
    echo "✅ REDIRECT URL FOUND:\n" . $data['redirect_url'] . "\n";
    echo "</pre>";
    header('Location: ' . $data['redirect_url']);
    exit;
}

echo "❌ NO redirect_url FOUND\n";
echo "</pre>";
