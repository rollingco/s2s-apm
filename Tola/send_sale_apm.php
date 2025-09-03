<?php
/**
 * S2S APM SALE (multipart/form-data, Basic Auth) — webhook set on connector side
 * URL: https://api.leogcltd.com/post-va (same for test/live)
 * Auth: MID API Username/Password via HTTP Basic
 * Body: multipart/form-data (як у Postman)
 * Currency: SLE (per your MID)
 *
 * NOTE: callback/webhook URL не передаємо в SALE — його пропише AM у налаштуваннях MID.
 */

header('Content-Type: text/html; charset=utf-8');

/* ====== CONFIG ====== */
$PAYMENT_URL  = 'https://api.leogcltd.com/post-va';

$CLIENT_KEY   = 'a9375384-26f2-11f0-877d-022c42254708';
$SECRET       = '554999c284e9f29cf95f090d9a8f3171'; // секрет для побудови hash (Appendix A)

// MID credentials (Basic Auth)
$API_USER     = 'leogc';
$API_PASS     = 'ORuIO57N6KJyeJ';

// FYI: ваш серверний вебхук (налаштований AM на боці конектора)
$SERVER_WEBHOOK_INFO = 'https://www.zal25.pp.ua/s2stest/Tola/callback.php';

/* ====== RUNTIME PARAMS (можна міняти через query) ====== */
$brand        = $_GET['brand'] ?? 'afri-money';
$identifier   = $_GET['id']    ?? '111';
$order_ccy    = $_GET['ccy']   ?? 'SLE';
$order_amt    = $_GET['amt']   ?? '0.19';
$payer_phone  = $_GET['phone'] ?? '23233310905';
$return_url   = $_GET['return']?? 'https://google.com';

$order_id     = 'ORDER_' . time();
$order_desc   = 'Important gift';
$payer_ip     = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

/* ====== HASH (Appendix A) ======
   md5(strtoupper(strrev(identifier + order_id + amount + currency + SECRET))) */
function build_sale_hash($identifier, $order_id, $amount, $currency, $secret) {
    $src = $identifier . $order_id . $amount . $currency . $secret;
    return md5(strtoupper(strrev($src)));
}
$hash = build_sale_hash($identifier, $order_id, $order_amt, $order_ccy, $SECRET);

/* ====== MULTIPART FORM-DATA (як у вашому Postman) ======
   ВАЖЛИВО: НЕ задаємо Content-Type вручну — cURL сам поставить boundary. */
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
    // НІЯКИХ callback_url тут не передаємо — webhook пропише AM у MID.
];

/* ====== REQUEST ====== */
$ch = curl_init($PAYMENT_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $form,                          // multipart/form-data
    CURLOPT_USERPWD        => $API_USER . ':' . $API_PASS,    // Basic Auth
    CURLOPT_HEADER         => true,                           // щоб бачити headers
    CURLOPT_TIMEOUT        => 60,
]);

$start = microtime(true);
$raw    = curl_exec($ch);
$info   = curl_getinfo($ch);
$errno  = curl_errno($ch);
$error  = $errno ? curl_error($ch) : '';
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

/* ====== VIEW ====== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function pretty($v){
    if (is_string($v)) { $d=json_decode($v,true); if(json_last_error()===JSON_ERROR_NONE) $v=$d; else return h($v); }
    return h(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>S2S APM SALE – Tola/Akurateco</title>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--ok:#2ecc71;--warn:#f1c40f;--err:#ff6b6b;--info:#00d1d1}
html,body{background:var(--bg);color:var(--text);margin:0;font:14px/1.45 ui-monospace,Menlo,Consolas,monospace}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.h{font-weight:700;margin:10px 0 6px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:var(--muted)} .tag{display:inline-block;padding:2px 8px;border-radius:8px;font-size:12px;margin-right:6px}
.t-ok{background:rgba(46,204,113,.12);color:var(--ok)} .t-err{background:rgba(255,107,107,.12);color:var(--err)} .t-info{background:rgba(0,209,209,.12);color:var(--info)}
pre{background:#11131a;padding:12px;border-radius:10px;overflow:auto;border:1px solid #232635;white-space:pre-wrap}
</style>
</head>
<body>
<div class="wrap">
  <div class="panel">
    <div class="h">🟢 S2S APM SALE (multipart/form-data, Basic Auth)</div>
    <div><span class="kv">Endpoint:</span> <?=h($PAYMENT_URL)?></div>
    <div><span class="kv">MID Auth:</span> <?=h($API_USER)?> : ******</div>
    <div><span class="kv">Webhook (set by AM):</span> <?=h($SERVER_WEBHOOK_INFO)?></div>
    <div><span class="kv">Order ID:</span> <?=h($order_id)?> &nbsp; <span class="kv">Duration:</span> <?=h($dur)?>s</div>
  </div>

  <div class="panel">
    <div class="h">➡ Sent form-data</div>
    <pre><?=pretty($form)?></pre>
  </div>

  <div class="panel">
    <div class="h">⬅ Response headers</div>
    <pre><?=h(trim($respHeaders))?></pre>
  </div>

  <div class="panel">
    <div class="h">⬅ Response body</div>
    <pre><?=pretty($respBody)?></pre>
  </div>

  <?php
  $code = $info['http_code'] ?? 0; $cls = ($code>=200 && $code<300)?'t-ok':'t-err';
  echo '<div class="panel"><span class="tag '.$cls.'">HTTP '.$code.'</span>';
  if ($error) echo '<span class="tag t-err">'.h($error).'</span>';

  if (is_array($data)) {
      echo '<div class="h">Parsed</div><pre>'.pretty($data).'</pre>';

      $result = $data['result'] ?? '';
      $status = $data['status'] ?? '';
      if ($result === 'SUCCESS' && $status === 'PREPARE') {
          echo '<div class="tag t-info">PREPARE</div> ';
          echo '<div>Payment created. Final status will arrive via <b>server webhook</b> from the connector (configured by AM). ';
          echo 'Optionally you may poll status by <code>order_id</code>/<code>trans_id</code> until webhook arrives.</div>';
      }
  }
  echo '</div>';
  ?>

</div>
</body>
</html>
