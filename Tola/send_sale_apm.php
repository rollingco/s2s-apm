<?php
/**
 * S2S APM SALE (multipart/form-data like Postman)
 * - Endpoint: https://api.leogcltd.com/post-va
 * - Auth: HTTP Basic (API Username / API Password from MID)
 * - Body: multipart/form-data (form-data in Postman)
 * - Verbose HTML logging
 */

header('Content-Type: text/html; charset=utf-8');

/* ====== CONFIG ====== */
$PAYMENT_URL  = 'https://api.leogcltd.com/post-va';

$CLIENT_KEY   = 'a9375384-26f2-11f0-877d-022c42254708';
$SECRET       = '554999c284e9f29cf95f090d9a8f3171'; // пароль для побудови hash (Appendix A)

$API_USER     = 'leogc';
$API_PASS     = 'ORuIO57N6KJyeJ'; // <-- заміни при потребі

/* ====== INPUTS (можна міняти через ?brand=&amt=&ccy=&id=&phone= ...) ====== */
$brand        = $_GET['brand'] ?? 'afri-money';     // приклад з Postman
$identifier   = $_GET['id']    ?? '111';            // приклад з Postman
$order_ccy    = $_GET['ccy']   ?? 'SLE';            // згідно з MID
$order_amt    = $_GET['amt']   ?? '100.00';
$payer_phone  = $_GET['phone'] ?? '23233310905';    // приклад
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

/* ====== MULTIPART FORM-DATA PAYLOAD (як у Postman: Body → form-data) ======
   ВАЖЛИВО: не задаємо Content-Type вручну, cURL сам поставить boundary */
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

/* ====== REQUEST ====== */
$ch = curl_init($PAYMENT_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $form,          // <- масив = multipart/form-data
    CURLOPT_USERPWD        => $API_USER . ':' . $API_PASS, // Basic Auth
    CURLOPT_HEADER         => true,           // щоб бачити headers
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
<html lang="en"><head>
<meta charset="utf-8"><title>S2S APM SALE – multipart/form-data</title>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--ok:#2ecc71;--warn:#f1c40f;--err:#ff6b6b;--info:#00d1d1}
html,body{background:var(--bg);color:var(--text);margin:0;font:14px/1.45 ui-monospace,Menlo,Consolas,monospace}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.h{font-weight:700;margin:10px 0 6px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:var(--muted)} .tag{display:inline-block;padding:2px 8px;border-radius:8px;font-size:12px;margin-right:6px}
.t-ok{background:rgba(46,204,113,.12);color:var(--ok)} .t-err{background:rgba(255,107,107,.12);color:var(--err)} .t-info{background:rgba(0,209,209,.12);color:var(--info)}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
</style></head><body><div class="wrap">
  <div class="panel">
    <div class="h">🟢 S2S APM SALE (multipart/form-data, Basic Auth)</div>
    <div><span class="kv">Endpoint:</span> <?=h($PAYMENT_URL)?></div>
    <div><span class="kv">MID Auth:</span> <?=h($API_USER)?> : ******</div>
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

  <div class="panel">
    <?php $code = $info['http_code'] ?? 0; $cls = ($code>=200 && $code<300)?'t-ok':'t-err'; ?>
    <span class="tag <?=$cls?>">HTTP <?=$code?></span>
    <?php if ($error): ?><span class="tag t-err"><?=h($error)?></span><?php endif; ?>
    <?php if (is_array($data)): ?>
      <div class="h">Parsed</div>
      <pre><?=pretty($data)?></pre>
      <?php
      if (($data['result'] ?? '') === 'ERROR' && ($data['error_code'] ?? '') == '204006') {
          echo '<div class="tag t-err">204006: Payment system/brand not supported</div>';
          echo '<div>Перевір, що в Merchant/MID увімкнено <b>S2S APM mapping</b> і сам <b>brand</b> ('.h($brand).') для Tola-конектора; валюта цього MID — <b>SLE</b>.</div>';
      }
      ?>
    <?php endif; ?>
  </div>
</div></body></html>
