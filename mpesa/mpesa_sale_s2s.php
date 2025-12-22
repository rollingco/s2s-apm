<?php
/**
 * S2S APM SALE — MPESA — full required fields (AfriMoney-style)
 * - UI form: msisdn + amount (+ optional identifier/return_url/description)
 * - Sends form-data (CURLOPT_POSTFIELDS array)
 * - Minimal logs on screen
 */

header('Content-Type: text/html; charset=utf-8');

/* ===================== CONFIG ===================== */
$PAYMENT_URL = 'https://api.leogcltd.com/post-va';

$CLIENT_KEY = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET     = '554999c284e9f29cf95f090d9a8f3171';

$DEFAULTS = [
  'brand'       => 'mpesa',
  'identifier'  => '111',                 // переносимо як в AfriMoney
  'currency'    => 'KES',
  'return_url'  => 'https://google.com',
  'description' => 'APM payment',
  'msisdn'      => '254700000000',
  'amount'      => '10.00',
];

/* ===================== UPDATE MARKERS ===================== */
$scriptFile      = __FILE__;
$scriptName      = basename($scriptFile);
$scriptPath      = realpath($scriptFile) ?: $scriptFile;
$timezone        = date_default_timezone_get();
$lastUpdateTs    = @filemtime($scriptFile);
$lastUpdateHuman = $lastUpdateTs ? date('Y-m-d H:i:s', $lastUpdateTs) : 'n/a';

/* ===================== Helpers ===================== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function pretty($v){
  if (is_string($v)) { $d = json_decode($v,true); if (json_last_error()===JSON_ERROR_NONE) $v=$d; else return h($v); }
  return h(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}

/**
 * MPESA signature (your mpesa sample):
 * md5( strtoupper( strrev( order_id + amount + currency + SECRET ) ) )
 */
function build_mpesa_hash($order_id, $amount, $currency, $secret, &$srcOut = null){
  $src = $order_id . $amount . $currency . $secret;
  if ($srcOut !== null) $srcOut = $src;
  return md5(strtoupper(strrev($src)));
}

/* ===================== Read form ===================== */
$method    = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$submitted = ($method === 'POST');

if ($submitted) {
  $msisdn = preg_replace('/\s+/', '', $_POST['msisdn'] ?? '');
  $msisdn = ltrim($msisdn, '+');

  $rawAmt    = preg_replace('/[^0-9.]/', '', $_POST['amount'] ?? '');
  $order_amt = number_format((float)$rawAmt, 2, '.', '');

  $identifier = trim((string)($_POST['identifier'] ?? ''));
  $return_url = trim((string)($_POST['return_url'] ?? ''));
  $order_desc = trim((string)($_POST['order_description'] ?? ''));

  $errors = [];
  if ($msisdn === '') $errors[] = 'MSISDN is required.';
  if (!is_numeric($order_amt) || (float)$order_amt <= 0) $errors[] = 'Amount must be a positive number.';
  if ($identifier === '') $errors[] = 'identifier is required.';
  if ($return_url === '') $errors[] = 'return_url is required.';
  if ($order_desc === '') $errors[] = 'order_description is required.';

  if (!empty($errors)) {
    render_page([
      'errors'  => $errors,
      'prefill' => [
        'msisdn'            => $_POST['msisdn'] ?? $DEFAULTS['msisdn'],
        'amount'            => $_POST['amount'] ?? $DEFAULTS['amount'],
        'identifier'        => $_POST['identifier'] ?? $DEFAULTS['identifier'],
        'return_url'        => $_POST['return_url'] ?? $DEFAULTS['return_url'],
        'order_description' => $_POST['order_description'] ?? $DEFAULTS['description'],
      ],
      'debug'      => [],
      'response'   => [],
      'statusLink' => '',
      'meta'       => compact('scriptName','scriptPath','timezone','lastUpdateHuman'),
    ]);
    exit;
  }
} else {
  $msisdn     = $DEFAULTS['msisdn'];
  $order_amt  = $DEFAULTS['amount'];
  $identifier = $DEFAULTS['identifier'];
  $return_url = $DEFAULTS['return_url'];
  $order_desc = $DEFAULTS['description'];
}

/* ===================== Send SALE ===================== */
$debug = [];
$responseBlocks = ['bodyRaw' => '', 'json' => null];
$statusLink = '';

if ($submitted) {
  $brand      = $DEFAULTS['brand'];
  $currency   = $DEFAULTS['currency'];
  $order_id   = 'mpesa_sale_' . time();
  $payer_ip   = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

  $hash_src_dbg = '';
  $hash = build_mpesa_hash($order_id, $order_amt, $currency, $SECRET, $hash_src_dbg);

  // переносимо ПОВНИЙ каркас як в AfriMoney
  $form = [
    'action'            => 'SALE',
    'client_key'        => $CLIENT_KEY,
    'brand'             => $brand,

    'order_id'          => $order_id,
    'order_amount'      => $order_amt,
    'order_currency'    => $currency,
    'order_description' => $order_desc,

    'identifier'        => $identifier,
    'payer_ip'          => $payer_ip,
    'return_url'        => $return_url,

    // phone fields — щоб не вгадувати назву
    'msisdn'            => $msisdn,
    'payer_phone'       => $msisdn,

    // hash/signature — теж щоб не вгадувати
    'hash'              => $hash,
    'signature'         => $hash,
  ];

  $debug = [
    'endpoint'   => $PAYMENT_URL,
    'client_key' => $CLIENT_KEY,
    'order_id'   => $order_id,
    'payer_ip'   => $payer_ip,
    'form'       => $form,
    'hash_src'   => $hash_src_dbg,
    'hash'       => $hash,
  ];

  $ch = curl_init($PAYMENT_URL);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $form,
    CURLOPT_TIMEOUT        => 60,
  ]);

  $start = microtime(true);
  $raw   = curl_exec($ch);
  $info  = curl_getinfo($ch);
  $err   = curl_errno($ch) ? curl_error($ch) : '';
  curl_close($ch);
  $dur = number_format(microtime(true) - $start, 3, '.', '');

  $debug['duration_sec'] = $dur;
  $debug['http_code']    = (int)($info['http_code'] ?? 0);
  if ($err) $debug['curl_error'] = $err;

  $responseBlocks['bodyRaw'] = (string)$raw;
  $json = json_decode($responseBlocks['bodyRaw'], true);
  if (json_last_error() === JSON_ERROR_NONE) {
    $responseBlocks['json'] = $json;
    if (!empty($json['trans_id'])) {
      $basePath   = rtrim(dirname($_SERVER['PHP_SELF']), '/');
      $statusOnce = $basePath . '/status_once.php';
      $statusLink = $statusOnce . '?trans_id=' . urlencode($json['trans_id']);
    }
  }
}

/* ===================== Render ===================== */
render_page([
  'errors'     => [],
  'prefill'    => [
    'msisdn'            => $msisdn,
    'amount'            => $order_amt,
    'identifier'        => $identifier,
    'return_url'        => $return_url,
    'order_description' => $order_desc,
  ],
  'debug'      => $debug,
  'response'   => $responseBlocks,
  'statusLink' => $statusLink,
  'meta'       => compact('scriptName','scriptPath','timezone','lastUpdateHuman'),
]);

/* ---------------------- View ---------------------- */
function render_page($ctx){
  $errors     = $ctx['errors'] ?? [];
  $prefill    = $ctx['prefill'] ?? ['msisdn'=>'','amount'=>'','identifier'=>'','return_url'=>'','order_description'=>''];
  $debug      = $ctx['debug'] ?? [];
  $resp       = $ctx['response'] ?? [];
  $statusLink = $ctx['statusLink'] ?? '';
  $meta       = $ctx['meta'] ?? [];

  $self = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
  ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MPESA SALE — full required fields</title>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--err:#ff6b6b}
html,body{background:var(--bg);color:var(--text);margin:0;font:14px/1.45 ui-monospace,Menlo,Consolas,monospace}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.h{font-weight:700;margin:10px 0 6px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:var(--muted)}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#2b7cff;color:#fff;text-decoration:none;border:none;cursor:pointer}
.btn:hover{opacity:.9}
.error{color:var(--err);margin:6px 0}
input[type=text]{padding:8px 10px;border-radius:8px;border:1px solid #2a2f3a;background:#11131a;color:#e6e6e6;width:520px;max-width:100%}
label{display:inline-block;min-width:180px}
.small{font-size:12px}
</style>
</head>
<body>
<div class="wrap">

  <div class="panel">
    <div class="h">🧾 File info</div>
    <div><span class="kv">Script:</span> <?=h($meta['scriptName'] ?? '')?></div>
    <div><span class="kv">Path:</span> <?=h($meta['scriptPath'] ?? '')?></div>
    <div><span class="kv">Timezone:</span> <?=h($meta['timezone'] ?? '')?></div>
    <div><span class="kv">Last update (filemtime):</span> <?=h($meta['lastUpdateHuman'] ?? '')?></div>
  </div>

  <div class="panel">
    <div class="h">📨 Create MPESA SALE</div>

    <form action="<?=h($self)?>" method="post">
      <?php if ($errors): ?>
        <?php foreach ($errors as $e): ?><div class="error">❌ <?=h($e)?></div><?php endforeach; ?>
      <?php endif; ?>

      <div style="margin:8px 0;">
        <label>MSISDN:</label>
        <input type="text" name="msisdn" value="<?=h($prefill['msisdn'])?>" placeholder="254700000000">
      </div>

      <div style="margin:8px 0;">
        <label>Amount:</label>
        <input type="text" name="amount" value="<?=h($prefill['amount'])?>" placeholder="10.00">
      </div>

      <div style="margin:8px 0;">
        <label>Identifier:</label>
        <input type="text" name="identifier" value="<?=h($prefill['identifier'])?>" placeholder="111">
        <div class="kv small">Copied from AfriMoney template</div>
      </div>

      <div style="margin:8px 0;">
        <label>Return URL:</label>
        <input type="text" name="return_url" value="<?=h($prefill['return_url'])?>" placeholder="https://google.com">
        <div class="kv small">Required</div>
      </div>

      <div style="margin:8px 0;">
        <label>Order description:</label>
        <input type="text" name="order_description" value="<?=h($prefill['order_description'])?>" placeholder="APM payment">
        <div class="kv small">Required</div>
      </div>

      <div style="margin-top:12px;">
        <button class="btn" type="submit">Send SALE</button>
      </div>
    </form>
  </div>

  <?php if (!empty($debug)): ?>
  <div class="panel">
    <div class="h">🟢 SALE sent</div>
    <div><span class="kv">Endpoint:</span> <?=h($debug['endpoint'] ?? '')?></div>
    <div><span class="kv">Client key:</span> <?=h($debug['client_key'] ?? '')?></div>
    <div><span class="kv">Order ID:</span> <?=h($debug['order_id'] ?? '')?></div>
    <div><span class="kv">Payer IP:</span> <?=h($debug['payer_ip'] ?? '')?></div>
    <div><span class="kv">HTTP:</span> <?=h($debug['http_code'] ?? '')?> <span class="kv" style="margin-left:12px;">Duration:</span> <?=h($debug['duration_sec'] ?? '')?>s</div>
    <?php if (!empty($debug['curl_error'])): ?>
      <div class="error">cURL: <?=h($debug['curl_error'])?></div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="h">🧮 MPESA hash</div>
    <div class="kv">md5( strtoupper( strrev( order_id + amount + currency + SECRET ) ) )</div>
    <div class="kv">Source string:</div>
    <pre><?=h($debug['hash_src'] ?? '')?></pre>
    <div class="kv">Hash:</div>
    <pre><?=h($debug['hash'] ?? '')?></pre>
  </div>

  <div class="panel">
    <div class="h">➡ Sent form-data</div>
    <pre><?=pretty($debug['form'] ?? [])?></pre>
  </div>

  <div class="panel">
         <div class="h">⬅ Response body</div>
    <pre><?=pretty($resp['bodyRaw'] ?? '')?></pre>
    <?php if (is_array($resp['json'] ?? null)): ?>
      <div class="h">Parsed</div>
      <pre><?=pretty($resp['json'])?></pre>
      <?php if (!empty($statusLink)): ?>
        <p><a class="btn" href="<?=h($statusLink)?>" target="_blank">➡ Check status once (trans_id)</a></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
<?php
}
