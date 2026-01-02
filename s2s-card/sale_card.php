<?php
/**
 * SALE Emulator — Pretty Logs + Full Request View + 3DS Redirect Button
 *
 * Endpoint: https://api.leogcltd.com/post
 * Request Content-Type: application/x-www-form-urlencoded
 *
 * Hash (SALE/RETRY) formula (from your screenshot):
 *   md5( strtoupper( strrev(email) . SECRET . strrev(first6 + last4) ) )
 * where first6 = substr(card_number, 0, 6)
 *       last4  = substr(card_number, -4)
 *
 * What this script does:
 * 1) Builds SALE request + calculates hash
 * 2) Shows outgoing HTTP request (endpoint/headers/body)
 * 3) Sends request to API
 * 4) Shows raw + parsed response
 * 5) If response requires 3DS redirect (REDIRECT + POST), shows a button that submits PaReq/TermUrl to ACS
 */

// --------------------- CONFIG ---------------------
$endpoint    = 'https://api.leogcltd.com/post';

$merchantKey = 'cef92030-e7c1-11f0-a03f-26da8de1cc77';
$secret      = 'ce137c3bd39d264f552bf3a0e316823a';

// Your card data
$cardNumber  = '4111111111111111';
$expMonth    = '01';
$expYear     = '2038';
$cvv         = '123';

// Payer
$email       = 'john.doe@example.com';

// Order
$orderId     = 'ORDER_' . date('Ymd_His');
$amount      = '10.50';
$currency    = 'USD';
$desc        = 'Test purchase';

// In your docs screenshot it looks required; keep it
$termUrl3ds  = 'https://merchant.example.com/3ds-return';

// Optional: AUTH only (no capture) if 'Y'. For SALE purchase keep 'N'
$authOnly    = 'N';

// --------------------- HELPERS ---------------------
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function maskCard(string $pan): string {
  $len = strlen($pan);
  if ($len < 10) return str_repeat('*', $len);
  return substr($pan, 0, 6) . str_repeat('*', max(0, $len - 10)) . substr($pan, -4);
}

function prettyJson($data): string {
  $json = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $decoded = json_decode($json, true);
  if ($decoded === null) return (string)$json;
  return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function renderTable(array $rows): string {
  $out = '<table class="tbl"><tbody>';
  foreach ($rows as $k => $v) {
    if (is_array($v) || is_object($v)) $v = prettyJson($v);
    $out .= '<tr><th>'.h($k).'</th><td><pre class="pre">'.h((string)$v).'</pre></td></tr>';
  }
  $out .= '</tbody></table>';
  return $out;
}

function buildFormBody(array $data): string {
  return http_build_query($data);
}

function maskRequest(array $data): array {
  $out = $data;

  if (isset($out['card_number'])) $out['card_number'] = maskCard((string)$out['card_number']);
  if (isset($out['card_cvv2']))   $out['card_cvv2']   = '***';

  // mask hash a bit in "masked request"
  if (isset($out['hash']) && is_string($out['hash']) && strlen($out['hash']) > 16) {
    $out['hash'] = substr($out['hash'], 0, 8) . '…' . substr($out['hash'], -8);
  }

  return $out;
}

function httpPostForm(string $url, array $fields): string {
  $inputs = '';
  foreach ($fields as $k => $v) {
    if (is_array($v)) {
      foreach ($v as $kk => $vv) {
        $name = $k . '[' . $kk . ']';
        $inputs .= '<input type="hidden" name="'.h($name).'" value="'.h($vv).'">';
      }
    } else {
      $inputs .= '<input type="hidden" name="'.h($k).'" value="'.h($v).'">';
    }
  }

  return '
    <form method="post" action="'.h($url).'" target="_self" class="form3ds">
      '.$inputs.'
      <button type="submit" class="btn">Continue 3DS (POST)</button>
      <div class="hint">This will submit redirect_params (PaReq/TermUrl, etc.) to ACS.</div>
    </form>
  ';
}

// --------------------- HASH CALC ---------------------
$first6 = substr($cardNumber, 0, 6);
$last4  = substr($cardNumber, -4);

$hashSource = strrev($email) . $secret . strrev($first6 . $last4);
$hash = md5(strtoupper($hashSource));

// --------------------- REQUEST BUILD ---------------------
$post = [
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
  'payer_address'     => '123 Main St',
  'payer_country'     => 'US',
  'payer_city'        => 'New York',
  'payer_zip'         => '10001',
  'payer_email'       => $email,
  'payer_phone'       => '+1234567890',
  'payer_ip'          => '192.168.1.1',

  'term_url_3ds'      => $termUrl3ds,
  'term_url_target'   => '_self',

  'auth'              => $authOnly,
  'hash'              => $hash,
];

$requestHeaders = [
  'Content-Type: application/x-www-form-urlencoded',
];

$requestBodyRaw    = buildFormBody($post);
$requestBodyMasked = buildFormBody(maskRequest($post));

// --------------------- EXECUTE ---------------------
$startedAt = microtime(true);

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => $requestBodyRaw,
  CURLOPT_HTTPHEADER     => $requestHeaders,
  CURLOPT_TIMEOUT        => 60,
]);

$rawResponse = curl_exec($ch);
$curlErr     = curl_error($ch);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$elapsedMs = (int)round((microtime(true) - $startedAt) * 1000);

$responseArr = null;
if (!$curlErr && is_string($rawResponse)) {
  $decoded = json_decode($rawResponse, true);
  if (is_array($decoded)) $responseArr = $decoded;
}

// 3DS redirect detection
$do3ds = false;
$redirectUrl = '';
$redirectParams = [];
$redirectMethod = '';

if (is_array($responseArr)) {
  $redirectUrl    = (string)($responseArr['redirect_url'] ?? '');
  $redirectParams = (array) ($responseArr['redirect_params'] ?? []);
  $redirectMethod = (string)($responseArr['redirect_method'] ?? '');

  $do3ds = (
    (($responseArr['result'] ?? '') === 'REDIRECT') &&
    (strtoupper($redirectMethod) === 'POST') &&
    !empty($redirectUrl) &&
    !empty($redirectParams)
  );
}

// --------------------- OUTPUT HTML ---------------------
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>SALE Emulator — Pretty Logs</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial; margin: 18px; background:#0b0f17; color:#e7eefc; }
    .wrap { max-width: 1120px; margin: 0 auto; }
    .card { background:#111a2b; border:1px solid #23304a; border-radius:14px; padding:16px; margin: 14px 0; box-shadow: 0 8px 25px rgba(0,0,0,.25); }
    h1 { font-size: 20px; margin: 0 0 10px; }
    h2 { font-size: 16px; margin: 0 0 10px; opacity: .95; }
    h3 { font-size: 14px; margin: 12px 0 8px; opacity: .95; }
    .meta { display:flex; gap:10px; flex-wrap:wrap; font-size: 13px; opacity:.95; }
    .pill { background:#0b1222; border:1px solid #263556; padding:6px 10px; border-radius:999px; }
    .pill.ok { border-color:#2b7a4b; }
    .pill.bad { border-color:#a34545; }
    .tbl { width:100%; border-collapse: collapse; overflow:hidden; border-radius:12px; border:1px solid #23304a; }
    .tbl th { width: 250px; text-align:left; vertical-align: top; padding:10px; background:#0b1222; border-bottom:1px solid #23304a; color:#bcd0ff; font-weight:600; }
    .tbl td { padding:10px; border-bottom:1px solid #23304a; }
    .pre { margin:0; white-space: pre-wrap; word-break: break-word; font-size: 12.5px; line-height: 1.35; }
    .split { display:grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media(max-width: 900px){ .split{ grid-template-columns: 1fr; } .tbl th{ width: 190px; } }
    .btn { appearance:none; border:1px solid #3a65d9; background:#2a4ec2; color:white; padding:10px 14px; border-radius:12px; font-weight:700; cursor:pointer; }
    .btn:hover { filter: brightness(1.08); }
    .hint { margin-top: 8px; font-size: 12px; opacity: .85; }
    .warn { color:#ffd08a; }
    .muted { opacity:.8; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="card">
    <h1>SALE Emulator — Pretty Logs</h1>
    <div class="meta">
      <div class="pill">Endpoint: <b><?=h($endpoint)?></b></div>
      <div class="pill <?=($httpCode>=200 && $httpCode<400 && !$curlErr)?'ok':'bad'?>">HTTP: <b><?=h($httpCode)?></b></div>
      <div class="pill">Time: <b><?=h($elapsedMs)?> ms</b></div>
      <div class="pill">order_id: <b><?=h($orderId)?></b></div>
    </div>

    <?php if ($curlErr): ?>
      <div class="pill bad" style="margin-top:10px;">cURL error: <b><?=h($curlErr)?></b></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Outgoing HTTP Request</h2>

    <?=renderTable([
      'Method'   => 'POST',
      'Endpoint' => $endpoint,
      'Headers'  => implode("\n", $requestHeaders),
    ])?>

    <h3>Request body (masked)</h3>
    <pre class="pre mono"><?=h($requestBodyMasked)?></pre>

    <h3>Request body (raw, debug)</h3>
    <pre class="pre mono warn"><?=h($requestBodyRaw)?></pre>

    <div class="hint warn">Raw body contains sensitive data. Do NOT use this output in production logs.</div>
  </div>

  <div class="split">
    <div class="card">
      <h2>Request (as fields, masked)</h2>
      <?php
        $maskedFields = maskRequest($post);
        echo renderTable($maskedFields);
      ?>
    </div>

    <div class="card">
      <h2>Signature (hash)</h2>
      <?=renderTable([
        'first6'      => $first6,
        'last4'       => $last4,
        'email'       => $email,
        'hash_source' => $hashSource,
        'hash'        => $hash,
      ])?>
      <div class="hint warn">Secret is shown only for debugging. Hide it in real logs.</div>
    </div>
  </div>

  <div class="card">
    <h2>Raw response</h2>
    <pre class="pre mono"><?=h((string)$rawResponse)?></pre>
  </div>

  <?php if (is_array($responseArr)): ?>
    <div class="card">
      <h2>Parsed response</h2>
      <?=renderTable($responseArr)?>
    </div>

    <?php if ($do3ds): ?>
      <div class="card">
        <h2>Next step: 3DS Redirect</h2>

        <div class="muted">Detected redirect flow:</div>
        <?=renderTable([
          'result'          => $responseArr['result'] ?? '',
          'status'          => $responseArr['status'] ?? '',
          'redirect_url'    => $redirectUrl,
          'redirect_method' => $redirectMethod,
          'redirect_params' => $redirectParams,
        ])?>

        <div style="margin-top:14px;">
          <?=httpPostForm($redirectUrl, $redirectParams);?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>

</div>
</body>
</html>
