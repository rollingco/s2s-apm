<?php
/**
 * Create Checkout session (AWCC) — per latest support instructions
 * - order.currency: BRL (fiat)
 * - parameters.awcc: network_type (string), bech32 (boolean), crypto_type (string)
 * - signature: sha1(md5(strtoupper(number + amount + currency + description + merchant_pass)))
 * - HTML output with clickable link
 */

// ===== CONFIG =====
$CHECKOUT_HOST = 'https://pay.leogcltd.com';
$MERCHANT_KEY  = 'a9375384-26f2-11f0-877d-022c42254708';
$MERCHANT_PASS = '554999c284e9f29cf95f090d9a8f3171';

$SUCCESS_URL   = 'https://example.com/success';
$CANCEL_URL    = 'https://example.com/cancel';

// ===== ORDER (matches support curl) =====
$orderNumber   = 'order-1234';
$orderAmount   = '1000.19';  // string with two decimals
$orderCurrency = 'BRL';      // fiat currency (as in your case)
$orderDesc     = 'Important gift';

// ===== AWCC parameters (per support) =====
// If you want coin = ETH: set $cryptoType = 'ETH'. For USDT: 'USDT' + choose network_type ('eth' or 'tron').
$networkType = 'eth';        // string
$bech32      = false;        // boolean
$cryptoType  = 'USDT';       // string ('USDT' or 'ETH', etc.)

// ===== Build payload =====
$payload = [
    'merchant_key' => $MERCHANT_KEY,
    'operation'    => 'purchase',
    'methods'      => ['awcc'],
    'parameters'   => [
        'awcc' => [
            'network_type' => $networkType, // keep per support template
            'bech32'       => $bech32,      // boolean, not string
            'crypto_type'  => $cryptoType,
        ],
    ],
    'order' => [
        'number'      => $orderNumber,
        'amount'      => $orderAmount,
        'currency'    => $orderCurrency,
        'description' => $orderDesc,
    ],
    'cancel_url'  => $CANCEL_URL,
    'success_url' => $SUCCESS_URL,
    'customer'    => [
        'name'  => 'John Doe',
        'email' => 'test@gmail.com',
    ],
    'billing_address' => [
        'country' => 'US',
        'state'   => 'CA',
        'city'    => 'Los Angeles',
        'address' => 'Moor Building 35274',
        'zip'     => '123456',
        'phone'   => '347771112233',
    ],
];

// ===== Signature (Postman-style) =====
$toMd5Upper = strtoupper(
    $payload['order']['number'] .
    $payload['order']['amount'] .
    $payload['order']['currency'] .
    $payload['order']['description'] .
    $MERCHANT_PASS
);
$payload['hash'] = sha1(md5($toMd5Upper));

// ===== Send request =====
$endpoint = rtrim($CHECKOUT_HOST, '/').'/api/v1/session';
$res = httpPostJson($endpoint, $payload);

// ===== Output (HTML) =====
header('Content-Type: text/html; charset=utf-8');

echo "<h3>POST {$endpoint}</h3>";

echo "<h4>Request</h4>";
echo "<pre>".htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES)."</pre>";

echo "<h4>Response</h4>";
echo "<pre>HTTP {$res['code']}\n{$res['body']}</pre>";

$data = json_decode($res['body'], true);
if (is_array($data)) {
    foreach (['checkout_url','redirect_url','payment_url','url'] as $k) {
        if (!empty($data[$k])) {
            $url = htmlspecialchars($data[$k], ENT_QUOTES);
            echo "<p><strong>Open in browser:</strong> <a href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\">{$url}</a></p>";
            break;
        }
    }
}

// ===== Helper =====
function httpPostJson(string $url, array $data): array {
    $json = json_encode($data, JSON_UNESCAPED_SLASHES);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) { $body = "cURL error: $err"; }
    return ['code' => $code, 'body' => $body];
}
