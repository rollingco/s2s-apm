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

$merchantKey  = '8af24560-1269-11f1-84a4-2a588e8348b1';
$merchantPass = '71d7e2e8a5bca26c7cc63776fc36078d';

/* ===================== INPUTS ===================== */
$orderNumber      = 'order-' . time();
$orderAmount      = '0.19';              // keep as string, e.g. "10.00"
$orderCurrency    = 'USD';
$orderDescription = 'Important gift';

$successUrl = 'https://sandbox.pp.ua/success';
$cancelUrl  = 'https://sandbox.pp.ua/cancel';

/* ===================== HELPERS ===================== */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function calc_hash(string $number, string $amount, string $currency, string $description, string $password): string {
    // Build exact string, uppercase it (IMPORTANT!)
    $input = strtoupper($number . $amount . $currency . $description . $password);

    // MD5 as HEX (32 chars), then SHA1 of that hex string
    $md5hex = md5($input);
    return sha1($md5hex);
}

function http_json(string $url, array $payload, int $timeout = 30): array {
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
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'ok'       => ($err === '' && $httpCode >= 200 && $httpCode < 300),
        'httpCode' => $httpCode,
        'error'    => $err,
        'raw'      => $respBody,
        'json'     => json_decode((string)$respBody, true),
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
    'success_url' => $successUrl,
    'cancel_url'  => $cancelUrl,
    'hash'        => $hash,
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
echo "<pre>" . h($res['raw']) . "</pre>";

$redirectUrl = '';
if (is_array($res['json'])) {
    // Most common: redirect_url; fallback: url
    if (!empty($res['json']['redirect_url'])) $redirectUrl = (string)$res['json']['redirect_url'];
    elseif (!empty($res['json']['url']))      $redirectUrl = (string)$res['json']['url'];
    elseif (!empty($res['json']['data']['redirect_url'])) $redirectUrl = (string)$res['json']['data']['redirect_url'];
}

if ($redirectUrl) {
    echo "<h3>Next step</h3>";
    echo "<p>Redirect the customer to:</p>";
    echo "<p><a href='" . h($redirectUrl) . "' target='_blank' rel='noopener noreferrer'>" . h($redirectUrl) . "</a></p>";
} else {
    echo "<h3>Next step</h3>";
    echo "<p><b>No redirect_url found</b>. Check response JSON fields (maybe different key name), or request validation error.</p>";
}

echo "<hr>";
echo "<h3>Hash debug</h3>";
echo "<div><b>Input string (UPPERCASE):</b></div>";
echo "<pre>" . h(strtoupper($orderNumber . $orderAmount . $orderCurrency . $orderDescription . $merchantPass)) . "</pre>";
echo "<div><b>MD5 hex:</b> " . h(md5(strtoupper($orderNumber . $orderAmount . $orderCurrency . $orderDescription . $merchantPass))) . "</div>";
echo "<div><b>SHA1(MD5hex):</b> " . h($hash) . "</div>";
