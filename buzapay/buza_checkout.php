<?php
/**
 * BUZAPAY CHECKOUT — USD
 * Session API example
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ===================== CONFIG ===================== */
$checkoutHost = 'https://pay.leogcltd.com';
$sessionUrl   = $checkoutHost . '/api/v1/session';

/* ✅ BUZAPAY CREDENTIALS */
$merchantKey  = 'a9375384-26f2-11f0-877d-022c42254708';
$merchantPass = '554999c284e9f29cf95f090d9a8f3171';

/* IMPORTANT:
 * method code must match buzapay connector code in Akurateco
 * change ONLY if backend says different
 */
$methodCode = 'buzapay';

/* URLs */
$successUrl = 'https://zal25.pp.ua/success.php';
$cancelUrl  = 'https://zal25.pp.ua/cancel.php';

/* Fixed currency */
$currency = 'USD';

/* ===================== ORDER ===================== */
$orderNumber   = 'buzapay-checkout-' . time();
$orderAmount   = '10.00';
$orderCurrency = $currency;
$orderDescr    = 'Test Buzapay checkout payment';

/* ===================== HASH ===================== */
/**
 * sha1(md5(strtoupper(
 *   order_number + amount + currency + description + merchant_pass
 * )))
 */
$hashBase = $orderNumber . $orderAmount . $orderCurrency . $orderDescr . $merchantPass;
$hash     = sha1(md5(strtoupper($hashBase)));

/* ===================== PAYLOAD ===================== */
$payload = [
    'merchant_key' => $merchantKey,
    'operation'    => 'purchase',
    'methods'      => [$methodCode],
    'order'        => [
        'number'      => $orderNumber,
        'amount'      => $orderAmount,
        'currency'    => $orderCurrency,
        'description' => $orderDescr,
    ],
    'success_url' => $successUrl,
    'cancel_url'  => $cancelUrl,
    'hash'        => $hash,
];

/* ===================== DEBUG: REQUEST ===================== */
echo "<pre>";
echo "==================== REQUEST ====================\n";
echo "URL:\n$sessionUrl\n\n";
echo "HASH BASE STRING:\n$hashBase\n\n";
echo "FINAL HASH:\n$hash\n\n";
echo "JSON PAYLOAD:\n";
print_r($payload);

/* ===================== CURL ===================== */
$ch = curl_init($sessionUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

/* ===================== DEBUG: RESPONSE ===================== */
echo "\n==================== RESPONSE ====================\n";
echo "HTTP CODE:\n$httpCode\n\n";
echo "RAW RESPONSE:\n$response\n\n";
echo "CURL ERROR:\n$error\n\n";

/* ===================== REDIRECT ===================== */
$data = json_decode($response, true);

if (!empty($data['redirect_url'])) {
    echo "✅ REDIRECT URL FOUND:\n" . $data['redirect_url'] . "\n";
    echo "</pre>";
    header('Location: ' . $data['redirect_url']);
    exit;
}

echo "❌ NO redirect_url FOUND\n";
echo "</pre>";
