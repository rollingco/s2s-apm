<?php
header('Content-Type: text/html; charset=utf-8');

/* ===== CONFIG ===== */
$PAYMENT_URL = 'https://api.leogcltd.com/post-va';

$CLIENT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708'; // <-- правильний
$SECRET      = '554999c284e9f29cf95f090d9a8f3171';     // <-- той самий

$API_USER    = 'leogc';
$API_PASS    = 'ORuIO57N6KJyeJ';

/* ===== RUNTIME ===== */
$brand       = $_GET['brand']        ?? 'afri-money';
$identifier  = $_GET['id']           ?? '111';
$order_ccy   = $_GET['ccy']          ?? 'SLE';
$order_amt   = $_GET['amt']          ?? '100.00';
$return_url  = $_GET['return']       ?? 'https://google.com';
$msisdn      = $_GET['msisdn']       ?? '254000000000';
$useEncoded  = ($_GET['body'] ?? '') === 'urlencoded';   // ?body=urlencoded -> x-www-form-urlencoded
$noMsisdn    = isset($_GET['no_msisdn']);                // ?no_msisdn=1 -> не слати msisdn (лишити payer_phone)

$order_id    = 'ORDER_' . time();
$order_desc  = 'Important gift';
$payer_ip    = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

/* ===== hash: md5(strtoupper(strrev(identifier+order_id+amount+currency+SECRET))) ===== */
$srcForHash  = $identifier . $order_id . $order_amt . $order_ccy . $SECRET;
$hash        = md5(strtoupper(strrev($srcForHash)));

/* ===== payload (повний) ===== */
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
  'payer_phone'       => $msisdn,       // головне поле з номером
  'hash'              => $hash,
];

if (!$noMsisdn) {
  $form['msisdn'] = $msisdn;           // інколи це зайве — можна відключити ?no_msisdn=1
}

/* ===== відправка ===== */
$ch = curl_init($PAYMENT_URL);
if ($useEncoded) {
  $body = http_build_query($form);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,    // x-www-form-urlencoded
    CURLOPT_HTTPHEADER     => [
      'Content-Type: application/x-www-form-urlencoded',
      'Content-Length: ' . strlen($body),
    ],
    CURLOPT_USERPWD        => $API_USER . ':' . $API_PASS,
    CURLOPT_HEADER         => true,
    CURLOPT_TIMEOUT        => 60,
  ]);
} else {
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $form,    // multipart/form-data
    CURLOPT_USERPWD        => $API_USER . ':' . $API_PASS,
    CURLOPT_HEADER         => true,
    CURLOPT_TIMEOUT        => 60,
  ]);
}

$start = microtime(true);
$raw = curl_exec($ch);
$info = curl_getinfo($ch);
$err  = curl_errno($ch) ? curl_error($ch) : '';
curl_close($ch);
$dur = number_format(microtime(true) - $start, 3, '.', '');

/* ===== split headers/body ===== */
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
$me = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>SALE – diag</title>
<style>
body{font:14px/1.45 ui-monospace,Menlo,Consolas,monospace;background:#0f1115;color:#e6e6e6;margin:0}
.wrap{max-width:1100px;margin:0 auto;padding:22px}
.panel{background:#171923;border:1px solid #2a2f3a;border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:#9aa4af}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
a.btn{display:inline-block;padding:8px 12px;border-radius:8px;background:#2b7cff;color:#fff;text-decoration:none;margin-right:8px}
a.btn:hover{opacity:.9}
</style>
</head>
<body><div class="wrap">
  <div class="panel">
    <div><span class="kv">Endpoint:</span> <?=h($PAYMENT_URL)?></div>
    <div><span class="kv">Client key:</span> <?=h($CLIENT_KEY)?></div>
    <div><span class="kv">Body format:</span> <?= $useEncoded ? 'x-www-form-urlencoded' : 'multipart/form-data' ?></div>
    <div><span class="kv">Order ID:</span> <?=h($order_id)?> &nbsp; <span class="kv">MSISDN:</span> <?=h($msisdn)?> &nbsp; <span class="kv">Duration:</span> <?=h($dur)?>s</div>
  </div>

  <div class="panel">
    <div class="kv">Hash source (identifier+order_id+amount+currency+SECRET):</div>
    <pre><?=h($srcForHash)?></pre>
    <div class="kv">Hash:</div>
    <pre><?=h($hash)?></pre>
  </div>

  <div class="panel">
    <div>➡ Sent payload</div>
    <pre><?=pretty($form)?></pre>
    <a class="btn" href="<?=h($me.'?'.http_build_query(array_merge($_GET,['body'=>$useEncoded?'':'urlencoded'])))?>"><?= $useEncoded ? 'Switch to multipart' : 'Switch to x-www-form-urlencoded' ?></a>
    <a class="btn" href="<?=h($me.'?'.http_build_query(array_merge($_GET,['no_msisdn'=> $noMsisdn?0:1])))?>"><?= $noMsisdn ? 'Send with msisdn' : 'Send without msisdn' ?></a>
  </div>

  <div class="panel">
    <div>⬅ Response headers</div>
    <pre><?=h(trim($respHeaders))?></pre>
  </div>

  <div class="panel">
    <div>⬅ Response body</div>
    <pre><?=pretty($respBody)?></pre>
  </div>
</div></body>
</html>
