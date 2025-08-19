<?php
/**
 * Create Checkout session (AWCC) — PHP (browser-friendly output)
 * - This variant uses amount + BRL (as per your current test)
 * - Signature: SHA1( MD5( UPPER(order.number + order.amount + order.currency + order.description + merchant.pass) ) )
 * - HTML output with clickable link (target=_blank)
 */

// ===== CONFIG (your MID) =====
$CHECKOUT_HOST = 'https://pay.leogcltd.com';
$MERCHANT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$MERCHANT_PASS = '554999c284e9f29cf95f090d9a8f3171';

$SUCCESS_URL   = 'https://example.com/success';
$CANCEL_URL    = 'https://example.com/cancel';

// ===== Order test data =====
$orderNumber   = 'order-'.time();
$orderAmount   = '10.00';        // amount is present in this scenario
$orderCurrency = 'BRL';          // BRL per your test (for USDT use 'USDT' + network_type)
$orderDesc     = 'Important gift';

// ===== AWCC specific (Aquanow) =====
$networkType = 'eth';            // 'eth' or 'tron' (relevant mainly for USDT)
$bech32      = false;

// ===== Build payload =====
$payload = [
  'merchant_key' => $MERCHANT_KEY,
  'operation'    => 'purchase',
  'methods'      => ['awcc'],
  'parameters'   => [
      'awcc' => [
          'network_type' => $networkType,          // optional for BRL, required for USDT
          'bech32'       => $bech32 ? 'true' : 'false',
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
  'customer' => ['name' => 'John Doe', 'email' => 'test@example.com'],
  'billing_address' => [
    'country' => 'US','state' => 'CA','city' => 'Los Angeles',
    'address' => 'Moor Building 35274','zip' => '123456','phone' => '347777112233',
  ],
];

// ===== Hash with amount (Postman-style) =====
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

echo "<h3>POST $endpoint</h3>";

echo "<h4>Hash debug</h4>";
echo "<pre>";
echo "string_to_sign:   ".htmlspecialchars(
    $payload['order']['number'] .
    $payload['order']['amount'] .
    $payload['order']['currency'] .
    $payload['order']['description'] .
    $MERCHANT_PASS, ENT_QUOTES
)."\n";
echo "uppercased:       ".htmlspecialchars($toMd5Upper, ENT_QUOTES)."\n";
echo "md5(upper):       ".md5($toMd5Upper)."\n";
echo "sha1(md5_hex):    ".$payload['hash']."\n";
echo "</pre>";

echo "<h4>Request</h4>";
echo "<pre>".htmlspecialchars(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), ENT_QUOTES)."</pre>";

echo "<h4>Response</h4>";
echo "<pre>HTTP {$res['code']}\n{$res['body']}</pre>";

$data = json_decode($res['body'], true);
if (is_array($data)) {
    foreach (['checkout_url','redirect_url','payment_url','url'] as $k) {
        if (!empty($data[$k])) {
            $url = htmlspecialchars($data[$k], ENT_QUOTES);
            echo "<p><strong>Open in browser:</strong> <a href=\"$url\" target=\"_blank\" rel=\"noopener noreferrer\">$url</a></p>";
            break;
        }
    }
}

// ================= helpers =================
function httpPostJson(string $url, array $data): array {
    $json = json_encode($data, JSON_UNESCAPED_SLASHES);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) { $body = "cURL error: $err"; }
    return ['code'=>$code, 'body'=>$body];
}
