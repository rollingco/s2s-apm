<?php
/**
 * S2S APM SALE — phone + amount → minimal logs
 * - NO headers logging at all
 */

header('Content-Type: text/html; charset=utf-8');

/* ===================== CONFIG ===================== */
$PAYMENT_URL = 'https://api.leogcltd.com/post-va';
//$CLIENT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
//$CLIENT_KEY  = 'a9375384-26f2-11f0-877d-022c42254708';
$CLIENT_KEY  = '01158d9a-9de6-11f0-ac32-ca759a298692';
$SECRET      = '4b486f4c7bee7cb42ccca2a5a980910e';
$API_USER    = 'leogc';
$API_PASS    = 'ORuIO57N6KJyeJ';

$DEFAULTS = [
  'brand'       => 'afri-money',
  'identifier'  => '111',
  'currency'    => 'SLE',
  'return_url'  => 'https://google.com',
  'phone'       => '23233310905',
  'amount'      => '0.99',
];

/* ===================== Helpers ===================== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function pretty($v){
  if (is_string($v)) { $d = json_decode($v,true); if (json_last_error()===JSON_ERROR_NONE) $v=$d; else return h($v); }
  return h(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}
function build_sale_hash($identifier, $order_id, $amount, $currency, $secret, &$srcOut = null){
  $src = $identifier . $order_id . $amount . $currency . $secret;
  if ($srcOut !== null) $srcOut = $src;
  return md5(strtoupper(strrev($src)));
}

/* ===================== Read form ===================== */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$submitted = ($method === 'POST');

$brand       = $DEFAULTS['brand'];
$identifier  = $DEFAULTS['identifier'];
$order_ccy   = $DEFAULTS['currency'];
$return_url  = $DEFAULTS['return_url'];

if ($submitted) {
  $payer_phone = preg_replace('/\s+/', '', $_POST['phone'] ?? '');
  $payer_phone = ltrim($payer_phone, '+');
  $rawAmt      = preg_replace('/[^0-9.]/', '', $_POST['amount'] ?? '');
  $order_amt   = number_format((float)$rawAmt, 2, '.', '');
  //$order_amt = rtrim(rtrim($rawAmt, '0'), '.');


  $errors = [];
  if ($payer_phone === '') $errors[] = 'Phone is required.';
  if (!is_numeric($order_amt) || (float)$order_amt <= 0) $errors[] = 'Amount must be a positive number.';

  if (!empty($errors)) {
    render_page([
      'showForm'   => true,
      'errors'     => $errors,
      'prefill'    => ['phone' => $_POST['phone'] ?? $DEFAULTS['phone'], 'amount' => $_POST['amount'] ?? $DEFAULTS['amount']],
      'debug'      => [],
      'response'   => [],
      'statusLink' => '',
    ]);
    exit;
  }
} else {
  $payer_phone = $DEFAULTS['phone'];
  $order_amt   = $DEFAULTS['amount'];
}

/* ===================== Send SALE ===================== */
$debug = [];
$responseBlocks = ['bodyRaw' => '', 'json' => null];
$statusLink = '';

if ($submitted) {
  $order_id    = 'ORDER_' . time();
  $order_desc  = 'APM payment';
  $payer_ip    = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

  $hash_src_dbg = '';
  $hash = build_sale_hash($identifier, $order_id, $order_amt, $order_ccy, $SECRET, $hash_src_dbg);

  $form = [
    'action'            => 'SALE',
    'client_key'        => $CLIENT_KEY,
    'brand'             => $brand,
    'order_id'          => $order_id,
    'order_amount'      => $order_amt,
    'order_currency'    => $order_ccy,
    'order_description' => $order_desc,
    'identifier'        => $identifier,
    'payer_ip'          => $payer_ip,
    'return_url'        => $return_url,
    'payer_phone'       => $payer_phone,
    'hash'              => $hash,
  ];

  $debug = [
    'endpoint'   => $PAYMENT_URL,
    'client_key' => $CLIENT_KEY,
    'order_id'   => $order_id,
    'form'       => $form,
    'hash_src'   => $hash_src_dbg,
    'hash'       => $hash,
  ];

  $ch = curl_init($PAYMENT_URL);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $form,
    //CURLOPT_USERPWD        => $API_USER . ':' . $API_PASS,
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
  'showForm'   => true,
  'errors'     => [],
  'prefill'    => ['phone' => $payer_phone, 'amount' => $order_amt],
  'debug'      => $debug,
  'response'   => $responseBlocks,
  'statusLink' => $statusLink,
]);

/* ---------------------- View ---------------------- */
function render_page($ctx){
  $showForm   = $ctx['showForm'];
  $errors     = $ctx['errors'] ?? [];
  $prefill    = $ctx['prefill'] ?? ['phone'=>'','amount'=>''];
  $debug      = $ctx['debug'] ?? [];
  $resp       = $ctx['response'] ?? [];
  $statusLink = $ctx['statusLink'] ?? '';

  $self = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
  ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>SALE — phone+amount → minimal</title>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--err:#ff6b6b}
html,body{background:var(--bg);color:var(--text);margin:0;font:14px/1.45 ui-monospace,Menlo,Consolas,monospace}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.h{font-weight:700;margin:10px 0 6px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:var(--muted)}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#2b7cff;color:#fff;text-decoration:none}
.btn:hover{opacity:.9}
.error{color:var(--err);margin:6px 0}
input[type=text]{padding:8px 10px;border-radius:8px;border:1px solid #2a2f3a;background:#11131a;color:#e6e6e6}
label{display:inline-block;min-width:120px}
</style>
</head>
<body>
<div class="wrap">

  <div class="panel">
    <div class="h">📨 Create SALE</div>
    <form action="<?=h($self)?>" method="post">
      <?php if ($errors): ?>
        <?php foreach ($errors as $e): ?><div class="error">❌ <?=h($e)?></div><?php endforeach; ?>
      <?php endif; ?>
      <div style="margin:8px 0;">
        <label>Phone (payer_phone):</label>
        <input type="text" name="phone" value="<?=h($prefill['phone'])?>" placeholder="23233310905">
      </div>
      <div style="margin:8px 0;">
        <label>Amount:</label>
        <input type="text" name="amount" value="<?=h($prefill['amount'])?>" placeholder="100.00">
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
    <div><span class="kv">HTTP:</span> <?=h($debug['http_code'] ?? '')?> <span class="kv" style="margin-left:12px;">Duration:</span> <?=h($debug['duration_sec'] ?? '')?>s</div>
    <?php if (!empty($debug['curl_error'])): ?>
      <div class="error">cURL: <?=h($debug['curl_error'])?></div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="h">🧮 SALE hash</div>
    <div class="kv">md5( strtoupper( strrev( identifier + order_id + amount + currency + SECRET ) ) )</div>
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
