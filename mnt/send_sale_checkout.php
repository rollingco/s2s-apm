<?php
/** test    
 * CHECKOUT Session (paytota / MTN MoMo) — phone + amount → nice logs + redirect
 * - Shows form first (phone, amount)
 * - Sends JSON to /api/v1/session
 * - Prints: endpoint, order, hash source, hash, payload, raw response, parsed response
 * - Redirects to redirect_url if present
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

/* ===================== CONFIG ===================== */
$checkoutHost = 'https://pay.leogcltd.com';
$sessionUrl   = $checkoutHost . '/api/v1/session';

$merchantKey  = 'a9375190-26f2-11f0-be42-022c42254708';
$merchantPass = '554999c284e9f29cf95f090d9a8f3171';

// MTN MoMo currency depends on MID/country. Put the one configured for your MID.
$DEFAULTS = [
  'method'   => 'paytota',
  'currency' => 'LRD',
  'phone'    => '256700000000', // E.164 without +
  'amount'   => '7.00',
];

$successUrl = 'https://zal25.pp.ua/success.php';
$cancelUrl  = 'https://zal25.pp.ua/cancel.php';

/* ===================== Helpers ===================== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

function pretty($v){
  if (is_string($v)) {
    $d = json_decode($v, true);
    if (json_last_error() === JSON_ERROR_NONE) $v = $d;
    else return h($v);
  }
  return h(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}

// same formula as your sample:
function build_checkout_hash($orderNumber, $amount, $currency, $descr, $merchantPass, &$srcOut = null){
  $toMd5 = $orderNumber . $amount . $currency . $descr . $merchantPass;
  if ($srcOut !== null) $srcOut = $toMd5;
  return sha1(md5(strtoupper($toMd5)));
}

/* ===================== Read form ===================== */
$method    = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$submitted = ($method === 'POST');

$payerPhone = $DEFAULTS['phone'];
$orderAmt   = $DEFAULTS['amount'];

if ($submitted) {
  $payerPhone = preg_replace('/\s+/', '', $_POST['phone'] ?? '');
  $payerPhone = ltrim($payerPhone, '+');

  $rawAmt   = preg_replace('/[^0-9.]/', '', $_POST['amount'] ?? '');
  $orderAmt = number_format((float)$rawAmt, 2, '.', '');

  $errors = [];
  if ($payerPhone === '') $errors[] = 'Phone is required.';
  if (!is_numeric($orderAmt) || (float)$orderAmt <= 0) $errors[] = 'Amount must be a positive number.';

  if ($errors) {
    render_page([
      'showForm' => true,
      'errors'   => $errors,
      'prefill'  => ['phone' => $_POST['phone'] ?? $DEFAULTS['phone'], 'amount' => $_POST['amount'] ?? $DEFAULTS['amount']],
      'debug'    => [],
      'resp'     => ['bodyRaw'=>'', 'json'=>null],
    ]);
    exit;
  }
}

/* ===================== Create session ===================== */
$debug = [];
$resp  = ['bodyRaw'=>'', 'json'=>null];

if ($submitted) {
  $orderNumber   = 'mtnmomo-checkout-' . time();
  $orderCurrency = $DEFAULTS['currency'];
  $orderDescr    = 'Test MTN MoMo checkout payment';

  $hash_src = '';
  $hash     = build_checkout_hash($orderNumber, $orderAmt, $orderCurrency, $orderDescr, $merchantPass, $hash_src);

  $payload = [
    'merchant_key' => $merchantKey,
    'operation'    => 'purchase',
    'methods'      => [$DEFAULTS['method']],
    'order'        => [
      'number'      => $orderNumber,
      'amount'      => $orderAmt,
      'currency'    => $orderCurrency,
      'description' => $orderDescr,
    ],
    // keep phone (if checkout supports it, good; if not, ignored)
    'customer'     => [
      'phone' => $payerPhone,
    ],
    'success_url'  => $successUrl,
    'cancel_url'   => $cancelUrl,
    'hash'         => $hash,
  ];

  $debug = [
    'endpoint'       => $sessionUrl,
    'merchant_key'   => $merchantKey,
    'method'         => $DEFAULTS['method'],
    'order_number'   => $orderNumber,
    'amount'         => $orderAmt,
    'currency'       => $orderCurrency,
    'description'    => $orderDescr,
    'phone'          => $payerPhone,
    'hash_src'       => $hash_src,
    'hash'           => $hash,
    'payload'        => $payload,
  ];

  $ch = curl_init($sessionUrl);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT        => 60,
  ]);

  $start = microtime(true);
  $raw   = curl_exec($ch);
  $info  = curl_getinfo($ch);
  $err   = curl_errno($ch) ? curl_error($ch) : '';
  curl_close($ch);

  $debug['duration_sec'] = number_format(microtime(true) - $start, 3, '.', '');
  $debug['http_code']    = (int)($info['http_code'] ?? 0);
  if ($err) $debug['curl_error'] = $err;

  $resp['bodyRaw'] = (string)$raw;

  $json = json_decode($resp['bodyRaw'], true);
  if (json_last_error() === JSON_ERROR_NONE) {
    $resp['json'] = $json;

    // redirect if provided
    if (!empty($json['redirect_url'])) {
      // show logs and auto-redirect (small delay) + button
      $debug['redirect_url'] = $json['redirect_url'];
    }
  }
}

/* ===================== Render ===================== */
render_page([
  'showForm' => true,
  'errors'   => [],
  'prefill'  => ['phone' => $payerPhone, 'amount' => $orderAmt],
  'debug'    => $debug,
  'resp'     => $resp,
]);

/* ===================== View ===================== */
function render_page($ctx){
  $errors = $ctx['errors'] ?? [];
  $prefill = $ctx['prefill'] ?? ['phone'=>'','amount'=>''];
  $debug = $ctx['debug'] ?? [];
  $resp  = $ctx['resp'] ?? ['bodyRaw'=>'', 'json'=>null];

  $self = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
  $redirectUrl = $debug['redirect_url'] ?? '';

  ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>CHECKOUT Session — paytota (MTN) — phone+amount</title>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--err:#ff6b6b}
html,body{background:var(--bg);color:var(--text);margin:0;font:14px/1.45 ui-monospace,Menlo,Consolas,monospace}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.h{font-weight:700;margin:10px 0 6px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:var(--muted)}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#2b7cff;color:#fff;text-decoration:none;border:0;cursor:pointer}
.btn:hover{opacity:.9}
.error{color:var(--err);margin:6px 0}
input[type=text]{padding:8px 10px;border-radius:8px;border:1px solid #2a2f3a;background:#11131a;color:#e6e6e6}
label{display:inline-block;min-width:190px}
.small{font-size:12px;color:var(--muted)}
</style>
<?php if ($redirectUrl): ?>
<meta http-equiv="refresh" content="2;url=<?=h($redirectUrl)?>">
<?php endif; ?>
</head>
<body>
<div class="wrap">

  <div class="panel">
    <div class="h">📨 Create CHECKOUT session (paytota / MTN)</div>
    <form action="<?=h($self)?>" method="post">
      <?php if ($errors): ?>
        <?php foreach ($errors as $e): ?><div class="error">❌ <?=h($e)?></div><?php endforeach; ?>
      <?php endif; ?>

      <div style="margin:8px 0;">
        <label>Phone (customer.phone):</label>
        <input type="text" name="phone" value="<?=h($prefill['phone'])?>" placeholder="256700000000">
        <div class="small">E.164 without +, only digits</div>
      </div>

      <div style="margin:8px 0;">
        <label>Amount:</label>
        <input type="text" name="amount" value="<?=h($prefill['amount'])?>" placeholder="10.00">
      </div>

      <div style="margin-top:12px;">
        <button class="btn" type="submit">Create session</button>
      </div>
    </form>
  </div>

  <?php if (!empty($debug)): ?>
  <div class="panel">
    <div class="h">🟢 Session request sent</div>
    <div><span class="kv">Endpoint:</span> <?=h($debug['endpoint'] ?? '')?></div>
    <div><span class="kv">Merchant key:</span> <?=h($debug['merchant_key'] ?? '')?></div>
    <div><span class="kv">Method:</span> <?=h($debug['method'] ?? '')?></div>
    <div><span class="kv">Order number:</span> <?=h($debug['order_number'] ?? '')?></div>
    <div><span class="kv">Amount:</span> <?=h($debug['amount'] ?? '')?> <span class="kv" style="margin-left:12px;">Currency:</span> <?=h($debug['currency'] ?? '')?></div>
    <div><span class="kv">HTTP:</span> <?=h($debug['http_code'] ?? '')?> <span class="kv" style="margin-left:12px;">Duration:</span> <?=h($debug['duration_sec'] ?? '')?>s</div>
    <?php if (!empty($debug['curl_error'])): ?>
      <div class="error">cURL: <?=h($debug['curl_error'])?></div>
    <?php endif; ?>
    <?php if (!empty($debug['redirect_url'])): ?>
      <div style="margin-top:10px;">
        <div class="small">Auto-redirect in 2 seconds…</div>
        <a class="btn" href="<?=h($debug['redirect_url'])?>" target="_blank">➡ Open redirect_url</a>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="h">🧮 Hash</div>
    <div class="kv">sha1( md5( strtoupper( order_number + amount + currency + description + merchantPass ) ) )</div>
    <div class="kv">Source string:</div>
    <pre><?=h($debug['hash_src'] ?? '')?></pre>
    <div class="kv">Hash:</div>
    <pre><?=h($debug['hash'] ?? '')?></pre>
  </div>

  <div class="panel">
    <div class="h">➡ JSON payload</div>
    <pre><?=pretty($debug['payload'] ?? [])?></pre>
  </div>

  <div class="panel">
    <div class="h">⬅ Response body</div>
    <pre><?=pretty($resp['bodyRaw'] ?? '')?></pre>
    <?php if (is_array($resp['json'] ?? null)): ?>
      <div class="h">Parsed</div>
      <pre><?=pretty($resp['json'])?></pre>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
<?php
}
