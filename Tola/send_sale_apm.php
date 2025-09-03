<?php
/**
 * S2S APM SALE — version with auto link to poll_status.php
 * ✔ 200 OK / result=SUCCESS, status=PREPARE
 * ☎ only payer_phone (normal number, no msisdn)
 */

header('Content-Type: text/html; charset=utf-8');

/* ===== CONFIG (current pair) ===== */
$PAYMENT_URL = 'https://api.leogcltd.com/post-va';

$CLIENT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708'; // <- новий робочий ключ
$SECRET      = '554999c284e9f29cf95f090d9a8f3171';     // секрет для hash

$API_USER    = 'leogc';
$API_PASS    = 'ORuIO57N6KJyeJ';

/* ===== RUNTIME (можна міняти через query) ===== */
$brand       = $_GET['brand']  ?? 'afri-money';
$identifier  = $_GET['id']     ?? '111';
$order_ccy   = $_GET['ccy']    ?? 'SLE';
$order_amt   = $_GET['amt']    ?? '100.00';
$return_url  = $_GET['return'] ?? 'https://google.com';
$payer_phone = $_GET['phone']  ?? '23233310905';

$order_id    = 'ORDER_' . time();
$order_desc  = 'Important gift';
$payer_ip    = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

/* ===== HASH ===== */
function build_sale_hash($identifier, $order_id, $amount, $currency, $secret){
    $src = $identifier . $order_id . $amount . $currency . $secret;
    return md5(strtoupper(strrev($src)));
}
$hash = build_sale_hash($identifier, $order_id, $order_amt, $order_ccy, $SECRET);

/* ===== PAYLOAD ===== */
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

/* ===== REQUEST ===== */
$ch = curl_init($PAYMENT_URL);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => $form,                        // multipart/form-data
  CURLOPT_USERPWD        => $API_USER . ':' . $API_PASS,  // Basic Auth
  CURLOPT_HEADER         => true,
  CURLOPT_TIMEOUT        => 60,
]);
$start = microtime(true);
$raw = curl_exec($ch);
$info = curl_getinfo($ch);
$err  = curl_errno($ch) ? curl_error($ch) : '';
curl_close($ch);
$dur = number_format(microtime(true) - $start, 3, '.', '');

/* split headers/body */
$respHeaders = '';
$respBody    = '';
if ($raw !== false && isset($info['header_size'])) {
  $respHeaders = substr($raw, 0, $info['header_size']);
  $respBody    = substr($raw, $info['header_size']);
}
$data = json_decode($respBody, true);

/* ===== helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function pretty($v){
  if (is_string($v)) { $d=json_decode($v,true); if(json_last_error()===JSON_ERROR_NONE) $v=$d; else return h($v); }
  return h(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}

/* build auto poll link */
$pollLink = '';
if (is_array($data)) {
  $oid = $data['order_id'] ?? $order_id;
  $tid = $data['trans_id'] ?? '';
  $params = [];
  if ($oid) $params['order_id'] = $oid;
  if ($tid) $params['trans_id'] = $tid;
  if ($params) {
    $pollLink = "https://www.zal25.pp.ua/s2stest/Tola/poll_status.php?" . http_build_query($params);
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>SALE — auto poll link</title>
<style>
body{font:14px/1.45 ui-monospace,Menlo,Consolas,monospace;background:#0f1115;color:#e6e6e6;margin:0}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.panel{background:#171923;border:1px solid #2a2f3a;border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:#9aa4af}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
a.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#2b7cff;color:#fff;text-decoration:none}
a.btn:hover{opacity:.9}
.tag{display:inline-block;padding:2px 8px;border-radius:8px;font-size:12px;margin-right:6px}
.t-info{background:rgba(0,209,209,.12);color:#00d1d1}
</style>
</head>
<body>
<div class="wrap">
  <div class="panel">
    <div>Endpoint: <?=h($PAYMENT_URL)?></div>
    <div>Client key: <?=h($CLIENT_KEY)?></div>
    <div>Order ID: <?=h($order_id)?></div>
    <div>Phone: <?=h($payer_phone)?></div>
    <div>Duration: <?=$dur?>s</div>
  </div>

  <div class="panel">
    <div>➡ Sent form-data</div>
    <pre><?=pretty($form)?></pre>
  </div>

  <div class="panel">
    <div>⬅ Response headers</div>
    <pre><?=h(trim($respHeaders))?></pre>
  </div>

  <div class="panel">
    <div>⬅ Response body</div>
    <pre><?=pretty($respBody)?></pre>
    <?php if (($data['result'] ?? '') === 'SUCCESS' && ($data['status'] ?? '') === 'PREPARE' && $pollLink): ?>
      <span class="tag t-info">PREPARE — waiting for final status</span><br><br>
      <a class="btn" href="<?=h($pollLink)?>" target="_blank">Check status now</a>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
