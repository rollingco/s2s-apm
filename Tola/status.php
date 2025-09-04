<?php
/**
 * status.php — GET_TRANS_STATUS (single check + auto refresh)
 * Usage:
 *   /s2stest/Tola/status.php?trans_id=6c8656ce-87d2-11f0-ae91-7aa0fce6d172
 *   опц.: &hash=v2 — спробувати альтернативну формулу підпису
 */

header('Content-Type: text/html; charset=utf-8');

/* ==== CONFIG ==== */
$PAYMENT_URL = 'https://api.leogcltd.com/post-va';
$API_USER    = 'leogc';
$API_PASS    = 'ORuIO57N6KJyeJ';

$CLIENT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET      = '554999c284e9f29cf95f090d9a8f3171';

$ACTION      = 'GET_TRANS_STATUS';
$REFRESH_SEC = 20;

/* ==== INPUT ==== */
$trans_id = trim($_GET['trans_id'] ?? '');
if ($trans_id === '') { http_response_code(400); echo 'Pass ?trans_id=...'; exit; }
$hashMode = ($_GET['hash'] ?? 'v1'); // v1 (default) | v2

/* ==== Signature (Appendix A: GET_TRANS_STATUS) ==== */
/* В різних інсталяціях зустрічаються дві формули. Залишив обидві: */
function build_status_hash_v1($trans_id, $client_key, $secret){
  // поширений варіант: id + client_key + secret
  $src = $trans_id . $client_key . $secret;
  return md5(strtoupper(strrev($src)));
}
function build_status_hash_v2($trans_id, $secret){
  // інколи — тільки id + secret
  $src = $trans_id . $secret;
  return md5(strtoupper(strrev($src)));
}
$hash = ($hashMode === 'v2')
  ? build_status_hash_v2($trans_id, $SECRET)
  : build_status_hash_v1($trans_id, $CLIENT_KEY, $SECRET);

/* ==== Payload ==== */
$payload = [
  'action'     => $ACTION,
  'client_key' => $CLIENT_KEY,
  'trans_id'   => $trans_id,
  'hash'       => $hash,
];

/* ==== Request (one check) ==== */
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

$headers = ''; $body = '';
if ($raw !== false && isset($info['header_size'])) {
  $headers = substr($raw, 0, $info['header_size']);
  $body    = substr($raw, $info['header_size']);
}
$parsed = json_decode($body, true);

/* ==== Helpers ==== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function pretty($v){
  if (is_string($v)) { $d=json_decode($v,true); if(json_last_error()===JSON_ERROR_NONE) $v=$d; else return h($v); }
  return h(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}

/* ==== Final? (з доків) ==== 
   statuses: REDIRECT / PREPARE / DECLINED / SETTLED / REFUND / VOID
   Вважаємо фінальними: SETTLED (успіх), DECLINED, REFUND, VOID
*/
$isFinal = false;
$finalStatuses = ['SETTLED','DECLINED','REFUND','VOID'];
if (is_array($parsed)) {
  $st = strtoupper((string)($parsed['status'] ?? ''));
  if (in_array($st, $finalStatuses, true)) $isFinal = true;
}

/* ==== Hash source (для діагностики) ==== */
$hashSrc = ($hashMode === 'v2')
  ? ($trans_id . $SECRET)
  : ($trans_id . $CLIENT_KEY . $SECRET);

/* ==== Self link ==== */
$q = ['trans_id'=>$trans_id];
if ($hashMode==='v2') $q['hash']='v2';
$self = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?'.http_build_query($q);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>GET_TRANS_STATUS</title>
<?php if (!$isFinal): ?>
  <meta http-equiv="refresh" content="<?= (int)$REFRESH_SEC ?>;url=<?= h($self) ?>">
<?php endif; ?>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--ok:#2ecc71;--warn:#f1c40f;--err:#ff6b6b;--info:#00d1d1}
html,body{background:var(--bg);color:var(--text);margin:0;font:14px/1.45 ui-monospace,Menlo,Consolas,monospace}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:var(--muted)}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
.tag{display:inline-block;padding:2px 8px;border-radius:8px;font-size:12px;margin-right:6px}
.t-ok{background:rgba(46,204,113,.12);color:var(--ok)}
.t-err{background:rgba(255,107,107,.12);color:var(--err)}
.t-info{background:rgba(0,209,209,.12);color:var(--info)}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#2b7cff;color:#fff;text-decoration:none}
</style>
</head>
<body>
<div class="wrap">
  <div class="panel">
    <div class="kv">Action: <?=h($ACTION)?></div>
    <div class="kv">HTTP: <?= (int)($info['http_code'] ?? 0) ?></div>
    <div class="kv">Hash mode: <?=h($hashMode)?></div>
    <?php if ($isFinal): ?>
      <div class="tag t-ok">FINAL</div>
    <?php else: ?>
      <div class="tag t-info">NOT FINAL — auto refresh in <?= (int)$REFRESH_SEC ?>s</div>
      <a class="btn" href="<?=h($self)?>">Refresh now</a>
      <?php
        // Швидкий перемикач формули
        $alt = (isset($_GET['hash']) && $_GET['hash']==='v2') ? '' : 'v2';
        $altLink = (isset($_GET['hash']) && $_GET['hash']==='v2')
          ? (preg_replace('/([?&])hash=v2(&|$)/','$1',$self))
          : ($self.(strpos($self,'?')?'&':'?').'hash=v2');
      ?>
      <a class="btn" href="<?=h($altLink)?>">Switch hash <?= $alt ?: 'v1' ?></a>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="kv">Hash source</div>
    <pre><?=h($hashSrc)?></pre>
    <div class="kv">Hash</div>
    <pre><?=h($hash)?></pre>
  </div>

  <div class="panel">
    <div class="kv">➡ Payload</div>
    <pre><?=pretty($payload)?></pre>
  </div>

  <div class="panel">
    <div class="kv">⬅ Response headers</div>
    <pre><?=h(trim($headers))?></pre>
  </div>

  <div class="panel">
    <div class="kv">⬅ Response body</div>
    <pre><?=pretty($body)?></pre>
    <?php if (is_array($parsed)): ?>
      <div class="kv">Parsed</div>
      <pre><?=pretty($parsed)?></pre>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
