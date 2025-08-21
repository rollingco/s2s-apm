<?php
/**
 * Create Checkout session (AWCC) — Akurateco/LEOGC -> AquaNow (fiat flow)
 *
 * Key points:
 * - order.currency MUST be fiat (USD/EUR/CAD) on LEOGC side.
 * - order.amount is a string but without decimals ("10") to map downstream to AquaNow fiatReceivable: 10 (integer).
 * - accountId is included at top-level so the connector can pass it to AquaNow.
 * - Signature: sha1(md5(strtoupper(order.number + order.amount + order.currency + order.description + merchant.pass))).
 * - Output is HTML with a clickable “Open in browser” link.
 */

////////////////////////////////////////
// CONFIG
////////////////////////////////////////
$CHECKOUT_HOST = 'https://pay.leogcltd.com';
// $MERCHANT_KEY  = 'a9375190-26f2-11f0-877d-022c42254708'; // old
$MERCHANT_KEY  = 'a9375384-26f2-11f0-877d-022c42254708';   // current
$MERCHANT_PASS = '554999c284e9f29cf95f090d9a8f3171';

$SUCCESS_URL   = 'https://example.com/success';
$CANCEL_URL    = 'https://example.com/cancel';

// toggle verbose hash debug block in HTML output
const DEBUG_HASH = true;

////////////////////////////////////////
// ORDER (fiat on LEOGC)
////////////////////////////////////////
$orderNumber   = 'order-'.time();
$orderAmount   = '10';       // no decimals → AquaNow expects fiatReceivable as integer (10)
$orderCurrency = 'USD';      // fiat currency per LEOGC side
$orderDesc     = 'Important gift';

// AquaNow-side identifier you provided (must be forwarded by the connector)
$accountId     = 'CA1001161C';

////////////////////////////////////////
// Build payload for LEOGC
////////////////////////////////////////
$payload = [
  'merchant_key' => $MERCHANT_KEY,
  'operation'    => 'purchase',
  'methods'      => ['awcc'],

  // Put accountId at the top level so Akurateco connector can forward it to AquaNow.
  'accountId'    => $accountId,

  // For fiat flow we do NOT send network_type here (used only for USDT crypto mapping downstream).
  'parameters'   => [
    'awcc' => [
      'bech32' => 'false',
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

  'customer' => [
    'name'  => 'John Doe',
    'email' => 'test@example.com',
  ],
  'billing_address' => [
    'country' => 'US',
    'state'   => 'CA',
    'city'    => 'Los Angeles',
    'address' => 'Moor Building 35274',
    'zip'     => '123456',
    'phone'   => '347777112233',
  ],
];

////////////////////////////////////////
// Signature (Postman-style)
////////////////////////////////////////
$toMd5Upper = strtoupper(
  $payload['order']['number'] .
  $payload['order']['amount'] .
  $payload['order']['currency'] .
  $payload['order']['description'] .
  $MERCHANT_PASS
);
$payload['hash'] = sha1(md5($toMd5Upper));

////////////////////////////////////////
// Send request
////////////////////////////////////////
$endpoint = rtrim($CHECKOUT_HOST, '/').'/api/v1/session';
$res = httpPostJson($endpoint, $payload);

////////////////////////////////////////
// Output (HTML)
////////////////////////////////////////
header('Content-Type: text/html; charset=utf-8');

echo "<h3>POST {$endpoint}</h3>";

if (DEBUG_HASH) {
  echo "<h4>Hash debug</h4><pre>";
  echo "string_to_sign:   ".htmlspecialchars(
    $payload['order']['number'] .
    $payload['order']['amount'] .
    $payload['order']['currency'] .
    $payload['order']['description'] .
    $MERCHANT_PASS,
    ENT_QUOTES
  )."\n";
  echo "uppercased:       ".htmlspecialchars($toMd5Upper, ENT_QUOTES)."\n";
  echo "md5(upper):       ".md5($toMd5Upper)."\n";
  echo "sha1(md5_hex):    ".$payload['hash']."\n";
  echo "</pre>";
}

echo "<h4>Request</h4>";
echo "<pre>".htmlspecialchars(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), ENT_QUOTES)."</pre>";

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

////////////////////////////////////////
// Helpers
////////////////////////////////////////
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
