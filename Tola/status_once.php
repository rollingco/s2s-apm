<?php
header('Content-Type: text/html; charset=utf-8');

/* ==== CONFIG ==== */
$PAYMENT_URL = 'https://api.leogcltd.com/post-va';
$API_USER    = 'leogc';
$API_PASS    = 'ORuIO57N6KJyeJ';

$CLIENT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$PASSWORD    = '554999c284e9f29cf95f090d9a8f3171'; // той самий, що для SALE

$ACTION      = 'GET_TRANS_STATUS';

/* ==== INPUT ==== */
$trans_id = trim($_GET['trans_id'] ?? '');
if ($trans_id === '') { http_response_code(400); echo 'Pass ?trans_id=...'; exit; }

/* ==== SIGNATURE (з доків) ==== */
// $hash = md5(strtoupper(strrev($trans_id)) . $PASSWORD);
$hash_src = strtoupper(strrev($trans_id)) . $PASSWORD;
$hash     = md5($hash_src);

/* ==== PAYLOAD ==== */
$payload = [
  'action'     => $ACTION,
  'client_key' => $CLIENT_KEY,
  'trans_id'   => $trans_id,
  'hash'       => $hash,
];

/* ==== REQUEST (one shot, без логів хедерів) ==== */
$ch = curl_init($PAYMENT_URL);
$body = http_build_query($payload);

curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => $body,
  CURLOPT_HTTPHEADER     => [
    'Content-Type: application/x-www-form-urlencoded',
    'Content-Length: ' . strlen($body),
  ],
  CURLOPT_USERPWD        => $API_USER . ':' . $API_PASS,
  CURLOPT_TIMEOUT        => 30,
  // НІЯКИХ: CURLOPT_HEADER / CURLOPT_VERBOSE / CURLOPT_STDERR
]);

$start = microtime(true);
$raw   = curl_exec($ch);
$info  = curl_getinfo($ch);
$err   = curl_errno($ch) ? curl_error($ch) : '';
curl_close($ch);
$dur = number_format(microtime(true) - $start, 3, '.', '');

/* ==== Відповідь ==== */
$respBody = (string)$raw;
$parsed   = json_decode($respBody, true);

/* ==== helpers ==== */
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
<title>GET_TRANS_STATUS (single)</title>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af}
html,body{background:var(--bg);color:var(--text);margin:0;font:14px/1.45 ui-monospace,Menlo,Consolas,monospace}
.wrap{max-width:1100px;margin:0 auto;padding:22px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:var(--muted)}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
</style>
</head>
<body>
<div class="wrap">

  <div class="panel">
    <div class="kv">Endpoint:</div><pre><?=h($PAYMENT_URL)?></pre>
    <div class="kv">Action:</div><pre><?=h($ACTION)?></pre>
    <div class="kv">Duration:</div><pre><?=h($dur)?> s</pre>
    <div class="kv">HTTP code:</div><pre><?= (int)($info['http_code'] ?? 0) ?></pre>
    <?php if ($err): ?><div class="kv">cURL error:</div><pre><?=h($err)?></pre><?php endif; ?>
  </div>

  <div class="panel">
    <div class="kv">Hash source (strtoupper(strrev(trans_id)) . PASSWORD):</div><pre><?=h($hash_src)?></pre>
    <div class="kv">Hash (md5):</div><pre><?=h($hash)?></pre>
  </div>

  <div class="panel">
    <div class="kv">➡ Payload (urlencoded):</div>
    <pre><?=pretty($payload)?></pre>
  </div>

  <div class="panel">
    <div class="kv">⬅ Response body (raw):</div>
    <pre><?=pretty($respBody)?></pre>
    <?php if (is_array($parsed)): ?>
      <div class="kv">Parsed JSON:</div>
      <pre><?=pretty($parsed)?></pre>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
