<?php
/**
 * Tokenization + Payment by card_token (single file) — HASH FIXED
 *
 * Step 1: SALE with req_token=Y (tokenization)
 *   hash = md5(strtoupper(strrev(email) . PASSWORD . strrev(first6+last4)))
 *
 * Step 2: SALE with card_token (payment)
 *   hash = md5(strtoupper(strrev(email) . PASSWORD . strrev(card_token)))
 */

// ========================= CONFIG =========================
$endpoint    = 'https://api.leogcltd.com/post';

$merchantKey = 'a9375190-26f2-11f0-be42-022c42254708';
$secret      = '554999c284e9f29cf95f090d9a8f3171'; // PASSWORD from docs

// Card (TEST ONLY)
$cardNumber  = '4111111111111111';
$expMonth    = '01';
$expYear     = '2038';
$cvv         = '123';

// Payer
$payerEmail  = 'jon.doe@gmail.com';

// 3DS
$termUrl3ds  = 'http://3ds.localhost/v4/confirmhandler';
$termTarget  = '_self';

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

  if (isset($out['hash']) && is_string($out['hash']) && strlen($out['hash']) > 16) {
    $out['hash'] = substr($out['hash'], 0, 10) . '…' . substr($out['hash'], -10);
  }

  if (isset($out['card_token']) && is_string($out['card_token']) && strlen($out['card_token']) > 16) {
    $out['card_token'] = substr($out['card_token'], 0, 8) . '…' . substr($out['card_token'], -6);
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

function do_post_form(string $endpoint, array $fields): array {
  $formBody = http_build_query($fields);

  $outHeaders = [
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json',
    'User-Agent: Token-Then-Pay-Emulator/1.1',
    'Content-Length: ' . strlen($formBody),
  ];

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

  return [
    'http_code' => $httpCode,
    'ms' => $ms,
    'curl_error' => $curlErr,
    'headers_out' => $outHeaders,
    'body_out' => $formBody,
    'raw' => $rawResponse,
    'json' => $responseArr,
    'do3ds' => $do3ds,
    'redirect_url' => $redirectUrl,
    'redirect_method' => $redirectMethod,
    'redirect_params' => $redirectParams,
  ];
}

// ========================= STEP 1: TOKENIZATION =========================
$first6 = substr($cardNumber, 0, 6);
$last4  = substr($cardNumber, -4);

// FIX: PASSWORD is concatenated directly (no dot), per doc screenshots.
//      md5(strtoupper(strrev(email).PASSWORD.strrev(first6+last4)))
$hashSource1 = strrev($payerEmail) . $secret . strrev($first6 . $last4);
$hashStep1   = md5(strtoupper($hashSource1));

$orderIdToken = 'Vasyl TOKEN_INIT_' . time();

$reqStep1 = [
  'action'            => 'SALE',
  'client_key'        => $merchantKey,
  'order_id'          => $orderIdToken,
  'order_amount'      => '0.00',
  'order_currency'    => 'USD',
  'order_description' => 'Tokenization init (AUTH 0.00)',

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

  'auth'              => 'Y',
  'req_token'         => 'Y',

  'hash'              => $hashStep1,
];

$resStep1 = do_post_form($endpoint, $reqStep1);

$cardToken = '';
if (is_array($resStep1['json'])) {
  $cardToken = (string)($resStep1['json']['card_token'] ?? '');
}

// ========================= STEP 2: PAYMENT BY TOKEN =========================
$orderIdPay = 'Vasyl TOKEN_PAY_' . time();

// FIX: hash for card_token case
// md5(strtoupper(strrev(email).PASSWORD.strrev(card_token)))
$hashSource2 = strrev($payerEmail) . $secret . strrev($cardToken);
$hashStep2   = $cardToken !== '' ? md5(strtoupper($hashSource2)) : '';

$reqStep2 = [
  'action'            => 'SALE',
  'client_key'        => $merchantKey,
  'order_id'          => $orderIdPay,
  'order_amount'      => '0.99',
  'order_currency'    => 'USD',
  'order_description' => 'Payment by card_token',

  'card_token'        => $cardToken,

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

  'hash'              => $hashStep2,
];

$resStep2 = null;
if ($cardToken !== '') {
  $resStep2 = do_post_form($endpoint, $reqStep2);
}

// ========================= HTML OUTPUT =========================
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Token → Pay Emulator</title>
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
    <h1>Token → Pay Emulator (HASH FIXED)</h1>
    <div class="meta">
      <div class="pill">Endpoint: <b><?=h($endpoint)?></b></div>
      <div class="pill">client_key: <b><?=h($merchantKey)?></b></div>
    </div>
  </div>

  <div class="card">
    <h2>STEP 1 — Tokenization (SALE + auth=Y + 0.00 + req_token=Y)</h2>
    <div class="meta">
      <div class="pill <?=($resStep1['http_code']>=200 && $resStep1['http_code']<400 && !$resStep1['curl_error'])?'ok':'bad'?>">HTTP: <b><?=h($resStep1['http_code'])?></b></div>
      <div class="pill">Time: <b><?=h($resStep1['ms'])?> ms</b></div>
      <div class="pill">order_id: <b><?=h($orderIdToken)?></b></div>
      <div class="pill">card_token: <b><?=h($cardToken ?: '—')?></b></div>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <h2>Step 1 — Request (masked)</h2>
      <pre class="mono"><?=h(pretty_json(mask_sensitive($reqStep1)))?></pre>
    </div>
    <div class="card">
      <h2>Step 1 — Hash debug</h2>
      <pre class="mono"><?=h(pretty_json([
        'hash_source' => $hashSource1,
        'hash' => $hashStep1,
      ]))?></pre>
      <div class="hint warn">Не пиши secret у прод-логах. Тут лише для дебагу.</div>
    </div>
  </div>

  <div class="card">
    <h2>Step 1 — Response (readable JSON)</h2>
    <pre class="mono"><?=h(is_array($resStep1['json']) ? pretty_json($resStep1['json']) : (string)$resStep1['raw'])?></pre>
  </div>

  <?php if ($resStep1['do3ds']): ?>
    <div class="card">
      <h2>Step 1 — Next step: 3DS redirect</h2>
      <pre class="mono"><?=h(pretty_json([
        'redirect_url' => $resStep1['redirect_url'],
        'redirect_method' => $resStep1['redirect_method'],
        'redirect_params' => $resStep1['redirect_params'],
      ]))?></pre>
      <div style="margin-top:14px;">
        <?=build_3ds_form($resStep1['redirect_url'], $resStep1['redirect_params'])?>
      </div>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2>STEP 2 — Payment by card_token (SALE + card_token + hash)</h2>

    <?php if ($cardToken === ''): ?>
      <div class="meta">
        <div class="pill bad">Skipped: <b>card_token is empty</b></div>
      </div>
    <?php else: ?>
      <div class="meta">
        <div class="pill <?=($resStep2 && $resStep2['http_code']>=200 && $resStep2['http_code']<400 && !$resStep2['curl_error'])?'ok':'bad'?>">HTTP: <b><?=h($resStep2['http_code'])?></b></div>
        <div class="pill">Time: <b><?=h($resStep2['ms'])?> ms</b></div>
        <div class="pill">order_id: <b><?=h($orderIdPay)?></b></div>
      </div>

      <div class="grid">
        <div class="card">
          <h2>Step 2 — Request (masked)</h2>
          <pre class="mono"><?=h(pretty_json(mask_sensitive($reqStep2)))?></pre>
        </div>

        <div class="card">
          <h2>Step 2 — Hash debug (card_token)</h2>
          <pre class="mono"><?=h(pretty_json([
            'hash_source' => $hashSource2,
            'hash' => $hashStep2,
          ]))?></pre>
        </div>
      </div>

      <div class="card">
        <h2>Step 2 — Response (readable JSON)</h2>
        <pre class="mono"><?=h(is_array($resStep2['json']) ? pretty_json($resStep2['json']) : (string)$resStep2['raw'])?></pre>
      </div>

      <?php if ($resStep2['do3ds']): ?>
        <div class="card">
          <h2>Step 2 — Next step: 3DS redirect</h2>
          <pre class="mono"><?=h(pretty_json([
            'redirect_url' => $resStep2['redirect_url'],
            'redirect_method' => $resStep2['redirect_method'],
            'redirect_params' => $resStep2['redirect_params'],
          ]))?></pre>
          <div style="margin-top:14px;">
            <?=build_3ds_form($resStep2['redirect_url'], $resStep2['redirect_params'])?>
          </div>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>

</div>
</body>
</html>