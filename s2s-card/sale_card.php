<?php
/**
 * SALE Emulator — READABLE (Headers + Request + Response) + 3DS Button
 *
 * Sends request as: application/x-www-form-urlencoded
 * Shows request/response in: pretty JSON (human-readable)
 *
 * Hash (SALE/RETRY) formula (per your doc screenshot):
 *   md5( strtoupper( strrev(email) . SECRET . strrev(first6 + last4) ) )
 */

// ========================= CONFIG =========================
$endpoint    = 'https://api.leogcltd.com/post';

//$merchantKey = 'cef92030-e7c1-11f0-a03f-26da8de1cc77';
$merchantKey = 'cef922a6-e7c1-11f0-a027-26da8de1cc77';
$secret      = 'ce137c3bd39d264f552bf3a0e316823a';

// Card (TEST ONLY)
$cardNumber  = '4111111111111111';
$expMonth    = '01';
$expYear     = '2038';
$cvv         = '123';

// Payer
$payerEmail  = 'johndoe@gmail.com';

// Order
$orderId     = 'test-y0018';
$amount      = '0.99';
$currency    = 'USD';
$desc        = 'testInt';

$termUrl3ds  = 'http://3ds.localhost/v4/confirmhandler';
$termTarget  = '_self';
$authOnly    = 'N';

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

  if (isset($out['card_number'])) $out['card_number'] = mask_pan((string)$out['card_number']);
  if (isset($out['card_cvv2']))   $out['card_cvv2']   = '***';

  // show hash but shorten a bit for “readable masked view”
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
$first6 = substr($cardNumber, 0, 6);
$last4  = substr($cardNumber, -4);

$hashSource = strrev($payerEmail) . $secret . strrev($first6 . $last4);
$hash = md5(strtoupper($hashSource));

// ========================= REQUEST (fields) =========================
$requestFields = [
  'action'            => 'SALE',
  'client_key'        => $merchantKey,
  'order_id'          => $orderId,
  'order_amount'      => $amount,
  'order_currency'    => $currency,
  'order_description' => $desc,

  'card_number'       => $cardNumber,
  'card_exp_month'    => $expMonth,
  'card_exp_year'     => $expYear,
  'card_cvv2'         => $cvv,

  'payer_first_name'  => 'John',
  'payer_last_name'   => 'Doe',
  'payer_address'     => '106 New Address',
  'payer_country'     => 'US',
  'payer_city'        => 'New York',
  'payer_zip'         => '00100',
  'payer_email'       => $payerEmail,
  'payer_phone'       => '1234567890',
  'payer_ip'          => '210.71.106.164',

  'term_url_3ds'      => $termUrl3ds,
  'term_url_target'   => $termTarget,

  'auth'              => $authOnly,
  'hash'              => $hash,
];

// What we actually send:
$formBody = http_build_query($requestFields);

// ========================= OUTGOING HEADERS =========================
$outHeaders = [
  'Content-Type: application/x-www-form-urlencoded',
  'Accept: application/json',
  'User-Agent: SALE-Readable-Emulator/1.0',
  'Content-Length: ' . strlen($formBody),
];

// ========================= EXECUTE =========================
$start = microtime(true);

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => $formBody,
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
  <title>SALE Emulator — Readable Logs</title>
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
    .btn { appearance:none; border:1px solid #3a65d9; background:#2a4ec2; color:white; padding:10px 14px; border-radius:12px; font-weight:700; cursor:pointer; }
    .btn:hover { filter: brightness(1.08); }
    .hint { margin-top: 8px; font-size: 12px; opacity: .85; }
    .warn { color:#ffd08a; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="card">
    <h1>SALE Emulator — Readable view</h1>
    <div class="meta">
      <div class="pill">Endpoint: <b><?=h($endpoint)?></b></div>
      <div class="pill <?=($httpCode>=200 && $httpCode<400 && !$curlErr)?'ok':'bad'?>">HTTP: <b><?=h($httpCode)?></b></div>
      <div class="pill">Time: <b><?=h($ms)?> ms</b></div>
      <div class="pill">order_id: <b><?=h($orderId)?></b></div>
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
      <h2>Request (readable JSON, masked)</h2>
      <pre class="mono"><?=h(pretty_json(mask_sensitive($requestFields)))?></pre>
      <div class="hint warn">Masked view: PAN/CVV hidden, hash shortened.</div>
    </div>

    <div class="card">
      <h2>Signature debug</h2>
      <pre class="mono"><?=h(pretty_json([
        'email' => $payerEmail,
        'first6' => $first6,
        'last4' => $last4,
        'hash_source' => $hashSource,
        'hash' => $hash,
      ]))?></pre>
      <div class="hint warn">Не пиши secret у прод-логах. Тут для дебагу.</div>
    </div>
  </div>

  <div class="card">
    <h2>Request body (what is actually sent, form-urlencoded)</h2>
    <pre class="mono"><?=h($formBody)?></pre>
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

</div>
</body>
</html>
