<?php
/**
 * CREDIT2CARD Emulator — READABLE (Headers + Request + Response)
 *
 * Sends request as: application/x-www-form-urlencoded
 * Shows request/response in: pretty JSON (human-readable)
 *
 * Hash (CREDIT2CARD) formula (per your doc screenshot, Formula 5):
 *   md5( strtoupper( PASSWORD . strrev( first6 + last4 ) ) )
 *
 * If card_token is used:
 *   md5( strtoupper( PASSWORD . strrev( card_token ) ) )
 */

// ========================= CONFIG =========================
$endpoint    = 'https://api.leogcltd.com/post';

// Credentials from your SALE example
$merchantKey = 'a9375190-26f2-11f0-be42-022c42254708'; // client_key
$secret      = '554999c284e9f29cf95f090d9a8f3171';      // PASSWORD in Formula 5

// Optional: if you need sub-account (channel)
$channelId   = 'test'; // or '' to omit

// Recipient card (TEST ONLY)
$cardNumber  = '4111111111111111';

// Optional token flow
$useCardToken = false;
$cardToken    = ''; // set token here if $useCardToken = true

// Order
$orderId     = 'Vasyl s2s_test CREDIT2CARD_' . time();
$amount      = '1.03';
$currency    = 'USD';
$desc        = 'CREDIT2CARD test payout';

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
  if (isset($out['hash']) && is_string($out['hash']) && strlen($out['hash']) > 16) {
    $out['hash'] = substr($out['hash'], 0, 10) . '…' . substr($out['hash'], -10);
  }
  if (isset($out['card_token']) && is_string($out['card_token']) && strlen($out['card_token']) > 12) {
    $out['card_token'] = substr($out['card_token'], 0, 6) . '…' . substr($out['card_token'], -6);
  }
  return $out;
}

// ========================= HASH (Formula 5) =========================
$first6 = $useCardToken ? '' : substr($cardNumber, 0, 6);
$last4  = $useCardToken ? '' : substr($cardNumber, -4);

if ($useCardToken) {
  // md5(strtoupper(PASSWORD . strrev(card_token)))
  $hashSource = $secret . strrev($cardToken);
} else {
  // md5(strtoupper(PASSWORD . strrev(first6 + last4)))
  $hashSource = $secret . strrev($first6 . $last4);
}

$hash = md5(strtoupper($hashSource));

// ========================= REQUEST (fields) =========================
$requestFields = [
  'action'            => 'CREDIT2CARD',
  'client_key'        => $merchantKey,

  // optional
  // 'channel_id'      => $channelId,

  'order_id'          => $orderId,
  'order_amount'      => $amount,
  'order_currency'    => $currency,
  'order_description' => $desc,

  // Recipient (Payee) card data
  // per doc: card_number is required
  'card_number'       => $cardNumber,

  // Optional: Payee details (recipient)
  'payee_first_name'  => 'John',
  'payee_last_name'   => 'Doe',
  'payee_birth_date'  => '1970-02-17',
  'payee_country'     => 'US',
  'payee_city'        => 'New York',
  'payee_zip'         => '00100',
  'payee_email'       => 'jon.doe@gmail.com',
  'payee_phone'       => '1234567890',

  // Optional: Payer details (sender/customer)
  'payer_first_name'  => 'Vasyl',
  'payer_last_name'   => 'Test',
  'payer_birth_date'  => '1989-01-01',
  'payer_country'     => 'US',
  'payer_city'        => 'New York',
  'payer_zip'         => '00100',
  'payer_email'       => 'payer@example.com',
  'payer_phone'       => '1234567890',
  'payer_ip'          => '210.71.106.164',

  // Optional: extra acquirer params as JSON string/object (depends on platform)
  // 'parameters'      => json_encode(['param1' => 'value1']),

  'hash'              => $hash,
];

// Add optional channel_id only if set
if (!empty($channelId)) {
  $requestFields['channel_id'] = $channelId;
}

// If token mode: send card_token instead of card_number (only if your platform supports it for this action)
if ($useCardToken) {
  unset($requestFields['card_number']);
  $requestFields['card_token'] = $cardToken;
}

// What we actually send:
$formBody = http_build_query($requestFields);

// ========================= OUTGOING HEADERS =========================
$outHeaders = [
  'Content-Type: application/x-www-form-urlencoded',
  'Accept: application/json',
  'User-Agent: CREDIT2CARD-Readable-Emulator/1.0',
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

// ========================= HTML OUTPUT =========================
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>CREDIT2CARD Emulator — Readable Logs</title>
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
    .hint { margin-top: 8px; font-size: 12px; opacity: .85; }
    .warn { color:#ffd08a; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="card">
    <h1>CREDIT2CARD Emulator — Readable view</h1>
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
      <div class="hint warn">Masked view: PAN hidden, hash shortened.</div>
    </div>

    <div class="card">
      <h2>Signature debug</h2>
      <pre class="mono"><?=h(pretty_json([
        'mode' => $useCardToken ? 'card_token' : 'card_number',
        'first6' => $useCardToken ? null : $first6,
        'last4' => $useCardToken ? null : $last4,
        'card_token' => $useCardToken ? ($cardToken ?: null) : null,
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

</div>
</body>
</html>