<?php
// ---------- HTML wrapper + CSS ----------
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Tola Mock Logger</title>
<style>
  :root { --bg:#0f1115; --panel:#171923; --text:#e6e6e6; --muted:#9aa4af; --ok:#2ecc71; --warn:#f1c40f; --err:#ff6b6b; --info:#00d1d1; }
  html,body { background:var(--bg); color:var(--text); margin:0; font:14px/1.4 ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; }
  .wrap { padding:22px; max-width:1100px; margin:0 auto; }
  .block { background:var(--panel); border:1px solid #2a2f3a; border-radius:12px; margin:18px 0; box-shadow: 0 4px 18px rgba(0,0,0,.25); }
  .block > .hdr { padding:12px 16px; font-weight:700; letter-spacing:.4px; }
  .mock  .hdr { border-left:6px solid #d86cff; }
  .test  .hdr { border-left:6px solid #ffd166; }
  .live  .hdr { border-left:6px solid #5bff95; }
  .body { padding:12px 16px 16px; }
  .kv   { color:var(--muted); }
  .line { margin:6px 0; }
  .tag  { padding:2px 6px; border-radius:6px; font-size:12px; margin-right:6px; }
  .t-info  { background: rgba(0,209,209,.12); color: var(--info); }
  .t-debug { background: rgba(241,196,15,.12); color: var(--warn); }
  .t-ok    { background: rgba(46,204,113,.12); color: var(--ok); }
  .t-err   { background: rgba(255,107,107,.12); color: var(--err); }
  pre { background:#11131a; padding:12px; border-radius:10px; overflow:auto; border:1px solid #232635; }
</style>
</head>
<body>
<div class="wrap">
<?php
// ---------- CONFIG (Stoplight mock) ----------
$endpoint = "https://stoplight.io/mocks/tolamobile/api-docs/39881367/transaction";

// NB: У Stoplight авторизація часто не потрібна. Якщо бачиш 401 — спробуй БЕЗ заголовка Authorization.
$useAuth = false;                 // ← можеш швидко вимкнути/увімкнути
$authHdr = "Authorization: Basic 123";

$headers = [
  "Accept: application/json",
  "Content-Type: application/json",
];
if ($useAuth) $headers[] = $authHdr;

// payload з твого прикладу
$payload = [
  "msisdn"          => "254000000001",
  "type"            => "disbursement",   // або "charge"
  "channel"         => "KENYA.SAFARICOM",
  "currency"        => "KES",
  "amount"          => 100,
  "sourcereference" => "8FD2KuZNJnBPLKmz",
];

// ---------- RENDER HELPERS ----------
function h($s){ return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function pretty($data){
  if (is_string($data)) {
    $d = json_decode($data, true);
    if (json_last_error() === JSON_ERROR_NONE) $data = $d; else return h($data);
  }
  return h(json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}
function tag($txt,$cls){ echo '<span class="tag '.$cls.'">'.h($txt).'</span>'; }
function section_start($title,$cls){ echo '<div class="block '.$cls.'"><div class="hdr">'.h($title).'</div><div class="body">'; }
function section_end(){ echo '</div></div>'; }

// ---------- SEND ----------
section_start('MOCKING (Stoplight)', 'mock');

echo '<div class="line">'; tag(date('Y-m-d H:i:s'),'t-info'); echo ' <span class="kv">Endpoint:</span> '.h($endpoint).'</div>';
echo '<div class="line">'; tag('HEADERS','t-debug'); echo '</div><pre>'.pretty($headers).'</pre>';
echo '<div class="line">'; tag('Payload','t-debug'); echo '</div><pre>'.pretty($payload).'</pre>';

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_HTTPHEADER     => $headers,
  CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
  CURLOPT_HEADER         => true,   // ← щоб побачити і заголовки відповіді
  CURLOPT_TIMEOUT        => 60,
]);

$start = microtime(true);
$raw    = curl_exec($ch);
$info   = curl_getinfo($ch);
$errno  = curl_errno($ch);
$error  = $errno ? curl_error($ch) : '';
curl_close($ch);
$dur = number_format(microtime(true) - $start, 3, '.', '');

// Розділяємо заголовки/тіло
$respHeaders = '';
$respBody    = '';
if ($raw !== false && isset($info['header_size'])) {
  $respHeaders = substr($raw, 0, $info['header_size']);
  $respBody    = substr($raw, $info['header_size']);
}

$code = $info['http_code'] ?? 0;
$cls  = ($code >= 200 && $code < 300) ? 't-ok' : ($code >= 400 ? 't-err' : 't-info');

echo '<div class="line">'; tag("HTTP $code", $cls); echo ' <span class="kv">Duration:</span> '.h($dur).' sec</div>';
if ($error) {
  echo '<div class="line">'; tag('cURL ERROR','t-err'); echo ' '.h($error).'</div>';
}

echo '<div class="line"><span class="kv">Response headers:</span></div><pre>'.h(trim($respHeaders)).'</pre>';
echo '<div class="line"><span class="kv">Response body:</span></div><pre>'.pretty($respBody).'</pre>';

section_end();
?>
</div>
</body>
</html>
