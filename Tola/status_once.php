<?php
/**
 * STATUS ONCE — check transaction status by trans_id
 * - No header logs, minimal clean output
 */

header('Content-Type: text/html; charset=utf-8');

/* ===================== CONFIG ===================== */
$PAYMENT_URL = 'https://api.leogcltd.com/post-va';
$CLIENT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET      = '554999c284e9f29cf95f090d9a8f3171';
$API_USER    = 'leogc';
$API_PASS    = 'ORuIO57N6KJyeJ';

$DEFAULTS = [
  'brand'      => 'afri-money',
  'identifier' => '111',
  'currency'   => 'SLE',
];

/* ===================== Helpers ===================== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function pretty($v){
  if (is_string($v)) { $d = json_decode($v,true); if (json_last_error()===JSON_ERROR_NONE) $v=$d; else return h($v); }
  return h(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}
function build_status_hash($identifier, $trans_id, $secret, &$srcOut = null){
  $src = $identifier . $trans_id . $secret;
  if ($srcOut !== null) $srcOut = $src;
  return md5(strtoupper(strrev($src)));
}

/* ===================== Read params ===================== */
$trans_id = $_GET['trans_id'] ?? '';
if ($trans_id === '') {
  render_page(['error' => 'Missing trans_id parameter.', 'debug' => [], 'response' => []]);
  exit;
}

/* ===================== Send STATUS request ===================== */
$debug = [];
$responseBlocks = ['bodyRaw' => '', 'json' => null];
$status = '';

$identifier = $DEFAULTS['identifier'];
$hash_src_dbg = '';
$hash = build_status_hash($identifier, $trans_id, $SECRET, $hash_src_dbg);

$form = [
  'action'      => 'STATUS',
  'client_key'  => $CLIENT_KEY,
  'identifier'  => $identifier,
  'trans_id'    => $trans_id,
  'hash'        => $hash,
];

$debug = [
  'endpoint'   => $PAYMENT_URL,
  'client_key' => $CLIENT_KEY,
  'trans_id'   => $trans_id,
  'form'       => $form,
  'hash_src'   => $hash_src_dbg,
  'hash'       => $hash,
];

$ch = curl_init($PAYMENT_URL);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => $form,
  CURLOPT_USERPWD        => $API_USER . ':' . $API_PASS,
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
}

/* ===================== Render ===================== */
render_page([
  'error'     => '',
  'debug'     => $debug,
  'response'  => $responseBlocks,
]);

/* ---------------------- View ---------------------- */
function render_page($ctx){
  $error = $ctx['error'] ?? '';
  $debug = $ctx['debug'] ?? [];
  $resp  = $ctx['response'] ?? [];
  ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Check STATUS — trans_id</title>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--err:#ff6b6b}
html,body{background:var(--bg);color:var(--text);margin:0;font:14px/1.45 ui-monospace,Menlo,Consolas,monospace}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.h{font-weight:700;margin:10px 0 6px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:var(--muted)}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
.error{color:var(--err);margin:6px 0}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#2b7cff;color:#fff;text-decoration:none}
.btn:hover{opacity:.9}
</style>
</head>
<body>
<div class="wrap">

  <?php if ($error): ?>
    <div class="panel error">❌ <?=h($error)?></div>
  <?php endif; ?>

  <div class="panel">
    <div class="h">🔎 STATUS request</div>
    <div><span class="kv">Endpoint:</span> <?=h($debug['endpoint'] ?? '')?></div>
    <div><span class="kv">Client key:</span> <?=h($debug['client_key'] ?? '')?></div>
    <div><span class="kv">Transaction ID:</span> <?=h($debug['trans_id'] ?? '')?></div>
    <div><span class="kv">HTTP:</span> <?=h($debug['http_code'] ?? '')?> <span class="kv" style="margin-left:12px;">Duration:</span> <?=h($debug['duration_sec'] ?? '')?>s</div>
    <?php if (!empty($debug['curl_error'])): ?>
      <div class="error">cURL: <?=h($debug['curl_error'])?></div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="h">🧮 STATUS hash</div>
    <div class="kv">md5( strtoupper( strrev( identifier + trans_id + SECRET ) ) )</div>
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
    <?php endif; ?>
  </div>

</div>
</body>
</html>
<?php
}
