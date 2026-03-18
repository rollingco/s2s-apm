<?php
header('Content-Type: text/html; charset=utf-8');

/* ==== CONFIG ==== */
$PAYMENT_URL = 'https://api.leogcltd.com/post-va';
$API_USER    = 'leogc';
$API_PASS    = 'ORuIO57N6KJyeJ';

$CLIENT_KEY  = '3aa576cc-3bc3-11f0-af08-26e0451c4912';
$PASSWORD    = '7cf670da6f33e1c31cf493f2650e90cc';
$ACTION      = 'GET_TRANS_STATUS';

/* ==== INPUT ==== */
$trans_id    = trim($_GET['trans_id'] ?? '');
$email       = trim($_GET['email'] ?? '');
$card_number = preg_replace('/\D+/', '', $_GET['card_number'] ?? '');

if ($trans_id === '') {
    http_response_code(400);
    echo 'Pass ?trans_id=...&email=...&card_number=...';
    exit;
}

if ($email === '') {
    http_response_code(400);
    echo 'Pass ?email=...';
    exit;
}

if (strlen($card_number) < 10) {
    http_response_code(400);
    echo 'Pass full card_number or at least first6+last4';
    exit;
}

/* ==== SIGNATURE (Formula 2) ==== */
/*
md5(
  strtoupper(
    strrev(email) .
    PASSWORD .
    trans_id .
    strrev(substr(card_number,0,6) . substr(card_number,-4))
  )
)
*/

$first6   = substr($card_number, 0, 6);
$last4    = substr($card_number, -4);
$cardPart = $first6 . $last4;

$hash_src = strrev($email) . $PASSWORD . $trans_id . strrev($cardPart);
$hash     = md5(strtoupper($hash_src));

/* ==== PAYLOAD ==== */
$payload = [
    'action'     => $ACTION,
    'client_key' => $CLIENT_KEY,
    'trans_id'   => $trans_id,
    'hash'       => $hash,
];

/* ==== REQUEST ==== */
$ch   = curl_init($PAYMENT_URL);
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
]);

$start = microtime(true);
$raw   = curl_exec($ch);
$info  = curl_getinfo($ch);
$err   = curl_errno($ch) ? curl_error($ch) : '';
curl_close($ch);
$dur = number_format(microtime(true) - $start, 3, '.', '');

/* ==== RESPONSE ==== */
$respBody = (string)$raw;
$parsed   = json_decode($respBody, true);

/* ==== HELPERS ==== */
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pretty($v) {
    if (is_string($v)) {
        $d = json_decode($v, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $v = $d;
        } else {
            return h($v);
        }
    }
    return h(json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>GET_TRANS_STATUS</title>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af}
html,body{background:var(--bg);color:var(--text);margin:0;font:14px/1.45 ui-monospace,Menlo,Consolas,monospace}
.wrap{max-width:1100px;margin:0 auto;padding:22px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:var(--muted)}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap;word-break:break-word}
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
    <div class="kv">Email:</div><pre><?=h($email)?></pre>
    <div class="kv">Card first6+last4:</div><pre><?=h($cardPart)?></pre>
    <div class="kv">Hash source (Formula 2):</div><pre><?=h($hash_src)?></pre>
    <div class="kv">Hash (md5 strtoupper(source)):</div><pre><?=h($hash)?></pre>
  </div>

  <div class="panel">
    <div class="kv">➡ Payload (array):</div>
    <pre><?=pretty($payload)?></pre>

    <div class="kv">➡ Payload raw (urlencoded):</div>
    <pre><?=h($body)?></pre>
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