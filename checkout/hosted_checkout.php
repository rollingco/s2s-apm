<?php
/**
 * Hosted Checkout (Card) — Minimal working example (PHP)
 * - Creates session via /api/v1/session
 * - Calculates hash: SHA1(MD5( UPPERCASE(number+amount+currency+description+password) ))
 * - Prints redirect_url link
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

/* ===================== CONFIG ===================== */
$CHECKOUT_HOST = 'https://pay.leogcltd.com';
$SESSION_URL   = $CHECKOUT_HOST . '/api/v1/session';

$merchantKey  = 'bfd234ec-225a-11f1-9929-c2acaa9a99d6';
$merchantPass = 'd9e638df1988977ea8febd7b5fa70919';

/* ===================== INPUTS ===================== */
$orderNumber      = 'order-' . time();
$orderAmount      = '0.19'; // keep as string, e.g. "10.00"
$orderCurrency    = 'USD';
$orderDescription = 'Important gift';

$successUrl = 'https://sandbox.pp.ua/success';
$cancelUrl  = 'https://sandbox.pp.ua/cancel';

/* ===================== BILLING ADDRESS ===================== */
/*
 * Added according to the billing_address structure discussed for Hosted Checkout:
 * country / state / city / district / address / house_number / zip / phone
 */
$billingAddress = [
    'country'      => 'US',
    'state'        => 'TX',
    'city'         => 'New Braunfels',
    'district'     => 'New Braunfels',
    'address'      => '960 Tornado Ridge',
    'house_number' => '960',
    'zip'          => '78130',
    'phone'        => '7028068369',
];

/* ===================== HELPERS ===================== */
function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function calc_hash(string $number, string $amount, string $currency, string $description, string $password): string
{
    $input = strtoupper($number . $amount . $currency . $description . $password);
    $md5hex = md5($input);
    return sha1($md5hex);
}

function http_json(string $url, array $payload, int $timeout = 30): array
{
    $ch = curl_init($url);
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_TIMEOUT        => $timeout,
    ]);

    $respBody = curl_exec($ch);
    $err      = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        'ok'       => ($err === '' && $httpCode >= 200 && $httpCode < 300),
        'httpCode' => $httpCode,
        'error'    => $err,
        'raw'      => $respBody,
        'json'     => json_decode((string) $respBody, true),
        'sent'     => $payload,
    ];
}

/* ===================== BUILD REQUEST ===================== */
$hash = calc_hash($orderNumber, $orderAmount, $orderCurrency, $orderDescription, $merchantPass);

$request = [
    'merchant_key'   => $merchantKey,
    'operation'      => 'purchase',
    'methods'        => ['card'],
    'session_expiry' => 60,
    'order' => [
        'number'      => $orderNumber,
        'amount'      => $orderAmount,
        'currency'    => $orderCurrency,
        'description' => $orderDescription,
    ],
    'billing_address' => $billingAddress,
    'success_url'    => $successUrl,
    'cancel_url'     => $cancelUrl,
    'hash'           => $hash,
];

/* ===================== SEND ===================== */
$res = http_json($SESSION_URL, $request);

/* ===================== OUTPUT ===================== */
echo "<h2>Hosted Checkout (Card) — Create session</h2>";

echo "<h3>Request</h3>";
echo "<pre>" . h(json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "</pre>";

echo "<h3>Response</h3>";
echo "<div><b>HTTP:</b> " . h($res['httpCode']) . "</div>";

if ($res['error']) {
    echo "<div style='color:#b00'><b>cURL error:</b> " . h($res['error']) . "</div>";
}

echo "<pre>" . h((string) $res['raw']) . "</pre>";

$redirectUrl = '';
if (is_array($res['json'])) {
    if (!empty($res['json']['redirect_url'])) {
        $redirectUrl = (string) $res['json']['redirect_url'];
    } elseif (!empty($res['json']['url'])) {
        $redirectUrl = (string) $res['json']['url'];
    } elseif (!empty($res['json']['data']['redirect_url'])) {
        $redirectUrl = (string) $res['json']['data']['redirect_url'];
    }
}

echo "<h3>Next step</h3>";
if ($redirectUrl !== '') {
    echo "<p>Redirect the customer to:</p>";
    echo "<p><a href='" . h($redirectUrl) . "' target='_blank' rel='noopener noreferrer'>" . h($redirectUrl) . "</a></p>";
} else {
    echo "<p><b>No redirect_url found</b>. Check response JSON fields or request validation error.</p>";
}

echo "<hr>";
echo "<h3>Hash debug</h3>";

$hashInput = strtoupper($orderNumber . $orderAmount . $orderCurrency . $orderDescription . $merchantPass);
$md5hex    = md5($hashInput);

echo "<div><b>Input string (UPPERCASE):</b></div>";
echo "<pre>" . h($hashInput) . "</pre>";
echo "<div><b>MD5 hex:</b> " . h($md5hex) . "</div>";
echo "<div><b>SHA1(MD5hex):</b> " . h($hash) . "</div>";