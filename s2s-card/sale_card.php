<?php
/**
 * Pretty SALE emulator with 3DS redirect button
 * - Endpoint: https://api.leogcltd.com/post
 * - Hash formula (SALE/RETRY):
 *   md5( strtoupper( strrev(email) . SECRET . strrev(first6 + last4) ) )
 */

$endpoint    = 'https://api.leogcltd.com/post';

$merchantKey = 'a9375190-26f2-11f0-be42-022c42254708';
$secret      = '554999c284e9f29cf95f090d9a8f3171';

// --- Your card data ---
$cardNumber  = '4111111111111111';
$expMonth    = '05';
$expYear     = '2038';
$cvv         = '123';

// --- Payer data ---
$email       = 'john.doe@example.com';

$orderId     = 'ORDER_' . date('Ymd_His');
$amount      = '10.50';
$currency    = 'USD';
$desc        = 'Test purchase';

// Some setups require it (in your screenshot it is required)
$termUrl3ds  = 'https://merchant.example.com/3ds-return';

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
    $out .= '<tr><th>'.h($k).'</th><td><pre class="pre">'.h($v).'</pre></td></tr>';
  }
  $out .= '</tbody></table>';
  return $out;
}

function httpPostForm(string $url, array $fields): string {
  // Builds HTML form which auto-submits on button click (not auto-run)
  $inputs = '';
  foreach ($fields as $k => $v) {
    if (is_array($v)) {
      // Nested arrays not expected for 3DS here, but handle anyway
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
      <div class="hint">This will submit PaReq + TermUrl to ACS.</div>
    </form>
  ';
}

// --- Hash build ---
$first6 = substr($cardNumber, 0, 6);
$last4  = substr($cardNumber, -4);

$hashSource = strrev($email) . $secret . strrev($first6 . $last4);
$hash = md5(strtoupper($hashSource));

// --- Prepare request ---
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

  'auth'              => 'N',
  'hash'              => $hash,
];

// --- Execute request ---
$startedAt = microtime(true);

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => http_build_query($post),
  CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
  CURLOPT_TIMEOUT        => 60,
]);

$raw = curl_exec($ch);
$curlErr  = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$elapsedMs = (int)round((microtime(true) - $startedAt) * 1000);

$responseArr = null;
if (!$curlErr && is_string($raw)) {
  $decoded = json_decode($raw, true);
  if (is_array($decoded)) $responseArr = $decoded;
}

// --- Render HTML ---
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>SALE Emulator (Pretty Logs)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial; margin: 18px; background:#0b0f17; color:#e7eefc; }
    .wrap { max-width: 1100px; margin: 0 auto; }
    .card { background:#111a2b; border:1px solid #23304a; border-radius:14px; padding:16px; margin: 14px 0; box-shadow: 0 8px 25px rgba(0,0,0,.25); }
    h1 { font-size: 20px; margin: 0 0 10px; }
    h2 { font-size: 16px; margin: 0 0 10px; opacity: .95; }
    .meta { display:flex; gap:10px; flex-wrap:wrap; font-size: 13px; opacity:.9; }
    .pill { background:#0b1222; border:1px solid #263556; padding:6px 10px; border-radius:999px; }
    .ok { border-color:#2b7a4b; }
    .bad { border-color:#a34545; }
    .tbl { width:100%; border-collapse: collapse; overflow:hidden; border-radius:12px; border:1px solid #23304a; }
    .tbl th { width: 230px; text-align:left; vertical-align: top; padding:10px; background:#0b1222; border-bottom:1px solid #23304a; color:#bcd0ff; font-weight:600; }
    .tbl td { padding:10px; border-bottom:1px solid #23304a; }
    .pre { margin:0; white-space: pre-wrap; word-break: break-word; font-size: 12.5px; line-height: 1.35; }
    .split { display:grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media(max-width: 900px){ .split{ grid-template-columns: 1fr; } .tbl th{ width: 180px; } }
    .btn { appearance:none; border:1px solid #3a65d9; background:#2a4ec2; color:white; padding:10px 14px; border-radius:12px; font-weight:700; cursor:pointer; }
    .btn:hover { filter: brightness(1.08); }
    .hint { margin-top: 8px; font-size: 12px; opacity: .85; }
    .warn { color:#ffd08a; }
    .muted { opacity:.8; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="card">
    <h1>SALE Emulator — Pretty Logs</h1>
    <div class="meta">
      <div class="pill">Endpoint: <b><?=h($endpoint)?></b></div>
      <div class="pill">HTTP: <b><?=h($httpCode)?></b></div>
      <div class="pill">Time: <b><?=h($elapsedMs)?> ms</b></div>
      <div class="pill">order_id: <b><?=h($orderId)?></b></div>
    </div>
    <?php if ($curlErr): ?>
      <div class="pill bad" style="margin-top:10px;">cURL error: <b><?=h($curlErr)?></b></div>
    <?php endif; ?>
  </div>

  <div class="split">
    <div class="card">
      <h2>Request (masked)</h2>
      <?php
        $masked = $post;
        $masked['card_number'] = maskCard($cardNumber);
        $masked['card_cvv2'] = '***';
        echo renderTable($masked);
      ?>
    </div>

    <div class="card">
      <h2>Signature (hash)</h2>
      <?php
        echo renderTable([
          'first6'      => $first6,
          'last4'       => $last4,
          'email'       => $email,
          'hash_source' => $hashSource,
          'hash'        => $hash,
        ]);
      ?>
      <div class="hint warn">Don’t share SECRET in logs for production. Here it’s shown only because you asked to see everything.</div>
    </div>
  </div>

  <div class="card">
    <h2>Raw response</h2>
    <pre class="pre"><?=h((string)$raw)?></pre>
  </div>

  <?php if (is_array($responseArr)): ?>
    <div class="card">
      <h2>Parsed response</h2>
      <?=renderTable($responseArr)?>
    </div>

    <?php
      // Build 3DS POST form if redirect required
      $do3ds = (
        ($responseArr['result'] ?? '') === 'REDIRECT'
        && ($responseArr['redirect_method'] ?? '') === 'POST'
        && !empty($responseArr['redirect_url'])
        && !empty($responseArr['redirect_params'])
        && is_array($responseArr['redirect_params'])
      );
    ?>

    <?php if ($do3ds): ?>
      <div class="card">
        <h2>Next step: 3DS Redirect</h2>
        <div class="muted">We will submit these fields to ACS:</div>
        <?=renderTable([
          'redirect_url'    => $responseArr['redirect_url'],
          'redirect_method' => $responseArr['redirect_method'],
          'redirect_params' => $responseArr['redirect_params'],
        ])?>
        <div style="margin-top:14px;">
          <?=httpPostForm($responseArr['redirect_url'], $responseArr['redirect_params']);?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>

</div>
</body>
</html>
