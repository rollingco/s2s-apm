<?php
/**
 * status.php — Single status check for an order/transaction with auto refresh.
 * Usage:
 *   /s2stest/Tola/status.php?order_id=ORDER_...            (or)
 *   /s2stest/Tola/status.php?trans_id=6c8656ce-...-d172
 *
 * The page performs ONE status request and renders the result.
 * If status is not final, it auto-refreshes every 20 seconds to avoid 504 timeouts.
 */

header('Content-Type: text/html; charset=utf-8');

/* ==== CONFIG ==== */
$PAYMENT_URL = 'https://api.leogcltd.com/post-va';
$API_USER    = 'leogc';
$API_PASS    = 'ORuIO57N6KJyeJ';

$CLIENT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET      = '554999c284e9f29cf95f090d9a8f3171';

// Name of the action for status-check in your connector (change if docs say otherwise)
$ACTION      = 'STATUS';

// Auto-refresh interval (seconds) when not final:
$REFRESH_SEC = 20;

/* ==== INPUT ==== */
$order_id = $_GET['order_id'] ?? '';
$trans_id = $_GET['trans_id'] ?? '';

if (!$order_id && !$trans_id) {
  http_response_code(400);
  echo 'Pass ?order_id=... or ?trans_id=...';
  exit;
}

/* ==== Build payload ==== */
$payload = [
  'action'     => $ACTION,
  'client_key' => $CLIENT_KEY,
];
if ($order_id) $payload['order_id'] = $order_id;
if ($trans_id) $payload['trans_id'] = $trans_id;

// If your STATUS call requires a signature, implement build_status_hash() per docs and add here.
// $payload['hash'] = build_status_hash($order_id ?: $trans_id, $CLIENT_KEY, $SECRET);

/* ==== Do ONE request ==== */
$ch = curl_init($PAYMENT_URL);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => http_build_query($payload),
  CURLOPT_USERPWD        => $API_USER . ':' . $API_PASS,
  CURLOPT_HEADER         => true,
  CURLOPT_TIMEOUT        => 30,
]);
$raw = curl_exec($ch);
$info= curl_getinfo($ch);
$err = curl_errno($ch) ? curl_error($ch) : '';
curl_close($ch);

$headers = '';
$body    = '';
if ($raw !== false && isset($info['header_size'])) {
  $headers = substr($raw, 0, $info['header_size']);
  $body    = substr($raw, $info['header_size']);
}
$parsed = json_decode($body, true);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function pretty($v){
  if (is_string($v)) { $d=json_decode($v,true); if(json_last_error()===JSON_ERROR_NONE) $v=$d; else return h($v); }
  return h(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}

/* ==== Decide if final ==== */
$isFinal = false;
if (is_array($parsed)) {
  $result = $parsed['result'] ?? '';
  $status = $parsed['status'] ?? '';
  if (in_array($status, ['SUCCESS','DECLINED'], true)) {
    $isFinal = true;
  } elseif ($result === 'SUCCESS' && $status !== 'PREPARE' && $status !== '') {
    $isFinal = true; // in case connector uses another final word
  }
}

// Build self link for manual refresh
$self = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?'.http_build_query(array_filter([
  'order_id' => $order_id,
  'trans_id' => $trans_id,
], fn($v)=>$v!=='' ));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Status check</title>
<?php if (!$isFinal): ?>
  <meta http-equiv="refresh" content="<?= (int)$REFRESH_SEC ?>;url=<?= h($self) ?>">
<?php endif; ?>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--ok:#2ecc71;--warn:#f1c40f;--err:#ff6b6b;--info:#00d1d1}
html,body{background:var(--bg);color:var(--text);margin:0;font:14px/1.45 ui-monospace,Menlo,Consolas,monospace}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.h{font-weight:700;margin:10px 0 6px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:var(--muted)}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
.tag{display:inline-block;padding:2px 8px;border-radius:8px;font-size:12px;margin-right:6px}
.t-ok{background:rgba(46,204,113,.12);color:var(--ok)}
.t-err{background:rgba(255,107,107,.12);color:var(--err)}
.t-info{background:rgba(0,209,209,.12);color:var(--info)}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#2b7cff;color:#fff;text-decoration:none}
.btn:hover{opacity:.9}
</style>
</head>
<body>
<div class="wrap">

  <div class="panel">
    <div class="h">Status check</div>
    <div><span class="kv">Checked at:</span> <?= h(date('Y-m-d H:i:s')) ?></div>
    <div><span class="kv">Target:</span>
      <?= $order_id ? 'order_id='.h($order_id) : 'trans_id='.h($trans_id) ?>
    </div>
    <div><span class="kv">HTTP:</span> <?= (int)($info['http_code'] ?? 0) ?></div>
    <?php if ($err): ?><div class="tag t-err"><?= h($err) ?></div><?php endif; ?>
    <?php if ($isFinal): ?>
      <div class="tag t-ok">FINAL</div>
    <?php else: ?>
      <div class="tag t-info">NOT FINAL — auto refresh in <?= (int)$REFRESH_SEC ?>s</div>
      <div style="margin-top:8px">
        <a class="btn" href="<?= h($self) ?>">Refresh now</a>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="h">➡ Payload</div>
    <pre><?= pretty($payload) ?></pre>
  </div>

  <div class="panel">
    <div class="h">⬅ Response headers</div>
    <pre><?= h(trim($headers)) ?></pre>
  </div>

  <div class="panel">
    <div class="h">⬅ Response body</div>
    <pre><?= pretty($body) ?></pre>
    <?php if (is_array($parsed)): ?>
      <div class="h">Parsed</div>
      <pre><?= pretty($parsed) ?></pre>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
