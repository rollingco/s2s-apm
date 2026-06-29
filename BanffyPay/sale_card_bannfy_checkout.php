<?php
/**
 * Checkout Session Emulator — JSON request
 *
 * Sends request as: application/json
 * Shows request/response in: pretty JSON (human-readable)
 *
 * Hash formula for /api/v1/session from Checkout Integration docs:
 *   SHA1( MD5( strtoupper(order.number + order.amount + order.currency + order.description + merchant.pass) ) )
 */

// ========================= CONFIG =========================
$endpoint    = 'https://pay.leogcltd.com/api/v1/session';


$merchantKey = 'ab1167a8-6422-11f1-9281-fa3c02bf8d26';
$secret      = '1904a0264cd3cb85de83801513c23bac';

// Card — used for HASH calculation only. Not sent in this JSON request.
$cardNumber  = '4441111087875187';
$expMonth    = '03';
$expYear     = '2030';
$cvv         = '501';

// Payer
$payerFirstName = 'Vasil';
$payerLastName  = 'Kachalo';
$payerEmail     = 'vasiliy.kachalo@gmail.com';
$payerPhone     = '380673812507';
$payerIp        = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';

// Billing address
$payerAddress = 'Shmidta 19';
$payerCountry = 'TZ';
$payerCity    = 'K-P';
$payerState   = 'KHM';
$payerZip     = '32316';

// Order
$orderId  = 'Vasil test order #' . time();
$amount   = '0.15';
$currency = 'USD';
$desc     = 'DP122111';

// Fields that are not present in the old code — takegggggn from the screenshot structure/sample.
$successUrl = 'https://google.com/';
$cancelUrl  = 'https://yahoo.com/';
$birthDate  = '1980-02-18';
$requestType = 'sync';
$countryCode = 'TZ';
$district = 'KHM';
$houseNumber = '19';

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

function mask_pan(string $pan): string {
  $len = strlen($pan);
  if ($len < 10) return str_repeat('*', $len);
  return substr($pan, 0, 6) . str_repeat('*', $len - 10) . substr($pan, -4);
}

function mask_sensitive(array $arr): array {
  $out = $arr;

  foreach (['card_number', 'card_cvv2'] as $key) {
    if (isset($out[$key])) {
      $out[$key] = ($key === 'card_number') ? mask_pan((string)$out[$key]) : '***';
    }
  }

  if (isset($out['hash']) && is_string($out['hash']) && strlen($out['hash']) > 16) {
    $out['hash'] = substr($out['hash'], 0, 10) . '…' . substr($out['hash'], -10);
  }

  return $out;
}

function build_3ds_form(string $url, array $params): string {
  $inputs = '';
  foreach ($params as $k => $v) {
    $inputs .= '<input type="hidden" name="'.h($k).'" value="'.h($v).'">';
  }

  return '
    <form method="post" action="'.h($url).'" class="form3ds">
      '.$inputs.'
      <button type="submit" class="btn">Continue 3DS (POST)</button>
      <div class="hint">This will POST redirect_params to ACS (PaReq + TermUrl, etc.).</div>
    </form>
  ';
}

// ========================= HASH =========================
// Formula from Checkout Integration docs for /api/v1/session:
//   SHA1( MD5( strtoupper(order.number + order.amount + order.currency + order.description + merchant.pass) ) )
//
// Important: merchant_key / payment_public_id is NOT included in this hash formula.
$merchantPass = $secret;

$hashSource =
  $orderId .
  $amount .
  $currency .
  $desc .
  $merchantPass;

$hashUpper = strtoupper($hashSource);
$hashMd5   = md5($hashUpper);
$hash      = sha1($hashMd5);

// Debug hash calculation. Delete this block when testing is finished.
file_put_contents(
  __DIR__ . '/hash.log',
  date('Y-m-d H:i:s') . PHP_EOL .
  'FORMULA          : SHA1(MD5(STRTOUPPER(order.number + order.amount + order.currency + order.description + merchant.pass)))' . PHP_EOL .
  'order.number     : ' . $orderId . PHP_EOL .
  'order.amount     : ' . $amount . PHP_EOL .
  'order.currency   : ' . $currency . PHP_EOL .
  'order.description: ' . $desc . PHP_EOL .
  'merchant.pass    : ' . $merchantPass . PHP_EOL .
  'SOURCE           : ' . $hashSource . PHP_EOL .
  'UPPER            : ' . $hashUpper . PHP_EOL .
  'MD5              : ' . $hashMd5 . PHP_EOL .
  'SHA1             : ' . $hash . PHP_EOL .
  str_repeat('-', 80) . PHP_EOL,
  FILE_APPEND
);

// ========================= REQUEST JSON =========================
$requestFields = [
  'merchant_key' => $merchantKey,
  'operation'    => 'purchase',

  'order' => [
    'number'      => $orderId,
    'description' => $desc,
    'amount'      => $amount,
    'currency'    => $currency,
  ],

  'customer' => [
    'name'       => trim($payerFirstName . ' ' . $payerLastName),
    'email'      => $payerEmail,
    'birth_date' => $birthDate,
  ],

  'billing_address' => [
    'country'      => $payerCountry,
    'state'        => $payerState,
    'city'         => $payerCity,
    'address'      => $payerAddress,
    'zip'          => $payerZip,
    'phone'        => $payerPhone,
    'district'     => $district,
    'house_number' => $houseNumber,
  ],

  'success_url'  => $successUrl,
  'cancel_url'   => $cancelUrl,
  'hash'         => $hash,
  'msisdn'       => $payerPhone,
  'currencyCode' => $currency,
  'amount'       => $amount,
  'requestType'  => $requestType,
  'description'  => $desc,
  'countryCode'  => $countryCode,
];

// What we actually send:
$jsonBody = json_encode($requestFields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// ========================= OUTGOING HEADERS =========================
$outHeaders = [
  'Content-Type: application/json',
  'Accept: application/json',
  'User-Agent: Checkout-Session-JSON-Emulator/1.0',
  'Content-Length: ' . strlen($jsonBody),
];

// ========================= cURL COMMAND (debug) =========================
$curlParts = [
  "curl --request POST '" . $endpoint . "'",
];

foreach ($outHeaders as $header) {
  $curlParts[] = "--header '" . str_replace("'", "'\\''", $header) . "'";
}

$curlParts[] = "--data '" . str_replace("'", "'\\''", $jsonBody) . "'";
$curlCommand = implode(" \\\n", $curlParts);

// ========================= EXECUTE =========================
$start = microtime(true);

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => $jsonBody,
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

// GET_TRANS_STATUS link (uses trans_id returned by response)
$statusTransId = '';
$getStatusUrl = '';
if (is_array($responseArr)) {
  $statusTransId = (string)($responseArr['trans_id'] ?? $responseArr['transaction_id'] ?? '');
  if ($statusTransId !== '') {
    $getStatusUrl = 'get_trans_status.php?trans_id=' . rawurlencode($statusTransId);
  }
}

// 3DS detection
$do3ds = false;
$redirectUrl = '';
$redirectMethod = '';
$redirectParams = [];

if (is_array($responseArr)) {
  $redirectUrl    = (string)($responseArr['redirect_url'] ?? '');
  $redirectMethod = (string)($responseArr['redirect_method'] ?? '');
  $redirectParams = (array) ($responseArr['redirect_params'] ?? []);

  $do3ds = (
    (($responseArr['result'] ?? '') === 'REDIRECT') &&
    (strtoupper($redirectMethod) === 'POST') &&
    $redirectUrl !== '' &&
    !empty($redirectParams)
  );
}

// ========================= HTML OUTPUT =========================
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Checkout Session Emulator — JSON Logs</title>
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
    .btn { appearance:none; border:1px solid #3a65d9; background:#2a4ec2; color:white; padding:10px 14px; border-radius:12px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-block; }
    .btn:hover { filter: brightness(1.08); }
    .hint { margin-top: 8px; font-size: 12px; opacity: .85; }
    .warn { color:#ffd08a; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="card">
    <h1>Checkout Session Emulator — JSON readable view</h1>
    <div class="meta">
      <div class="pill">Endpoint: <b><?=h($endpoint)?></b></div>
      <div class="pill <?=($httpCode>=200 && $httpCode<400 && !$curlErr)?'ok':'bad'?>">HTTP: <b><?=h($httpCode)?></b></div>
      <div class="pill">Time: <b><?=h($ms)?> ms</b></div>
      <div class="pill">order.number: <b><?=h($orderId)?></b></div>
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

  <div class="grid">
    <div class="card">
      <h2>Request JSON (masked)</h2>
      <pre class="mono"><?=h(pretty_json(mask_sensitive($requestFields)))?></pre>
      <div class="hint warn">Masked view: hash shortened.</div>
    </div>

    <div class="card">
      <h2>Signature debug</h2>
      <pre class="mono"><?=h(pretty_json([
        'formula' => 'sha1(md5(strtoupper(order.number + order.amount + order.currency + order.description + merchant.pass)))',
        'order.number' => $orderId,
        'order.amount' => $amount,
        'order.currency' => $currency,
        'order.description' => $desc,
        'hash_source' => $hashSource,
        'upper' => $hashUpper,
        'md5' => $hashMd5,
        'hash' => $hash,
      ]))?></pre>
      <div class="hint warn">Не пиши secret у прод-логах. Тут для дебагу.</div>
    </div>
  </div>

  <div class="card">
    <h2>Request body (what is actually sent, JSON)</h2>
    <pre class="mono"><?=h(pretty_json($jsonBody))?></pre>
  </div>

  <div class="card">
    <h2>cURL request (what is actually sent)</h2>
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

  <?php if ($getStatusUrl !== ''): ?>
    <div class="card">
      <h2>Manual status check</h2>
      <a class="btn" href="<?=h($getStatusUrl)?>" target="_blank" rel="noopener">Get_Trans_Status</a>
      <div class="hint">Opens GET_TRANS_STATUS check for trans_id: <span class="mono"><?=h($statusTransId)?></span></div>
      <div class="hint warn">Callback notification should still be used as the main source for final transaction status.</div>
    </div>
  <?php endif; ?>

  <?php if ($do3ds): ?>
    <div class="card">
      <h2>Next step: 3DS redirect</h2>
      <pre class="mono"><?=h(pretty_json([
        'redirect_url' => $redirectUrl,
        'redirect_method' => $redirectMethod,
        'redirect_params' => $redirectParams,
      ]))?></pre>

      <div style="margin-top:14px;">
        <?=build_3ds_form($redirectUrl, $redirectParams)?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($responseArr['redirect_url'])): ?>
  <div class="card">
    <h2>Checkout URL</h2>

    <a class="btn"
       href="<?=h($responseArr['redirect_url'])?>"
       target="_blank"
       rel="noopener">
      Open Checkout
    </a>

    <div class="hint mono" style="margin-top:10px;">
      <?=h($responseArr['redirect_url'])?>
    </div>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
