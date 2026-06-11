<?php
/**
 * Checkout Session Emulator — JSON request
 *
 * Sends request as: application/json
 */

// ========================= CONFIG =========================
$endpoint = 'https://pay.leogcltd.com/api/v1/session';

$requestJson = [
  'merchant_key' => 'ab1167a8-6422-11f1-9281-fa3c02bf8d26',
  'operation'    => 'purchase',
  'order'        => [
    'number'      => '62',
    'description' => 'Payment Order # 62 in the store https://www.sandbox.pp.ua/',
    'amount'      => '0.01',
    'currency'    => 'USD',
  ],
  'customer' => [
    'name'       => 'Vasyl Vasyl',
    'email'      => 'vasiliy.kachalo@gmail.com',
    'birth_date' => '1980-01-01',
  ],
  'billing_address' => [
    'country'      => 'US',
    'state'        => 'Wisconsin',
    'city'         => 'K-P',
    'address'      => 'Shmidta 19',
    'zip'          => '28000',
    'phone'        => '255714641171',
    'district'     => 'TX',
    'house_number' => '123',
  ],
  'success_url'  => 'https://www.sandbox.pp.ua/checkout/order-received/62/?key=wc_order_OuKdJQpmBFt3J',
  'cancel_url'   => 'https://www.sandbox.pp.ua/my-account/view-order/62/',
  'hash'         => '9c0c207e356363ad12dad6e9fbe8da30ae653e1a',
  'msisdn'       => '255714641171',
  'currencyCode' => 'USD',
  'amount'       => '0.01',
  'requestType'  => 'sync',
  'description'  => 'WooPlugin Test 62',
  'countryCode'  => 'TZ',
];

// ========================= HELPERS =========================
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function pretty_json($data): string {
  if (is_string($data)) {
    $decoded = json_decode($data, true);
    if (is_array($decoded)) {
      return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    return $data;
  }
  return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

// What we actually send:
$body = json_encode($requestJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// ========================= OUTGOING HEADERS =========================
$outHeaders = [
  'Content-Type: application/json',
  'Accept: application/json',
  'User-Agent: Checkout-JSON-Emulator/1.0',
  'Content-Length: ' . strlen($body),
];

// ========================= cURL COMMAND (debug) =========================
$curlParts = [
  "curl --request POST '" . $endpoint . "'",
];

foreach ($outHeaders as $header) {
  $curlParts[] = "--header '" . str_replace("'", "'\\''", $header) . "'";
}

$curlParts[] = "--data '" . str_replace("'", "'\\''", $body) . "'";
$curlCommand = implode(" \\\n", $curlParts);

// ========================= EXECUTE =========================
$start = microtime(true);

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => $body,
  CURLOPT_HTTPHEADER     => $outHeaders,
  CURLOPT_TIMEOUT        => 60,
]);

$rawResponse = curl_exec($ch);
$curlErr     = curl_error($ch);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$ms = (int)round((microtime(true) - $start) * 1000);

$responseArr = null;
if (!$curlErr && is_string($rawResponse)) {
  $decoded = json_decode($rawResponse, true);
  if (is_array($decoded)) $responseArr = $decoded;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Checkout JSON Emulator</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial; margin: 18px; background:#0b0f17; color:#e7eefc; }
    .wrap { max-width: 1100px; margin: 0 auto; }
    .card { background:#111a2b; border:1px solid #23304a; border-radius:14px; padding:16px; margin: 14px 0; box-shadow: 0 8px 25px rgba(0,0,0,.25); }
    h1 { font-size: 20px; margin: 0 0 10px; }
    h2 { font-size: 16px; margin: 0 0 10px; opacity: .95; }
    .meta { display:flex; gap:10px; flex-wrap:wrap; font-size: 13px; opacity:.95; margin-top: 8px; }
    .pill { background:#0b1222; border:1px solid #263556; padding:6px 10px; border-radius:999px; }
    .ok { border-color:#2b7a4b; }
    .bad { border-color:#a34545; }
    pre { margin:0; white-space: pre-wrap; word-break: break-word; font-size: 13px; line-height: 1.35; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }
    .grid { display:grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media(max-width: 900px){ .grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
<div class="wrap">

  <div class="card">
    <h1>Checkout JSON Emulator</h1>
    <div class="meta">
      <div class="pill">Endpoint: <b><?=h($endpoint)?></b></div>
      <div class="pill <?=($httpCode>=200 && $httpCode<400 && !$curlErr)?'ok':'bad'?>">HTTP: <b><?=h($httpCode)?></b></div>
      <div class="pill">Time: <b><?=h($ms)?> ms</b></div>
      <div class="pill">Order number: <b><?=h($requestJson['order']['number'])?></b></div>
    </div>
    <?php if ($curlErr): ?>
      <div class="meta" style="margin-top:10px;">
        <div class="pill bad">cURL error: <b><?=h($curlErr)?></b></div>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Headers (outgoing)</h2>
    <pre class="mono"><?=h(implode("\n", $outHeaders))?></pre>
  </div>

  <div class="card">
    <h2>Request JSON</h2>
    <pre class="mono"><?=h(pretty_json($requestJson))?></pre>
  </div>

  <div class="card">
    <h2>Request body (what is actually sent)</h2>
    <pre class="mono"><?=h($body)?></pre>
  </div>

  <div class="card">
    <h2>cURL request</h2>
    <pre class="mono"><?=h($curlCommand)?></pre>
  </div>

  <div class="grid">
    <div class="card">
      <h2>Response (raw)</h2>
      <pre class="mono"><?=h((string)$rawResponse)?></pre>
    </div>

    <div class="card">
      <h2>Response (readable JSON)</h2>
      <pre class="mono"><?=h(is_array($responseArr) ? pretty_json($responseArr) : (string)$rawResponse)?></pre>
    </div>
  </div>

</div>
</body>
</html>
