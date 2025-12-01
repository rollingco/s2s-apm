<?php
/**
 * STATUS helper for CREDIT2VIRTUAL / APM
 *
 * Використовує S2S APM GET_TRANS_STATUS:
 *   POST https://api.leogcltd.com/post-va
 *   action    = GET_TRANS_STATUS
 *   client_key
 *   trans_id
 *   hash = md5(strtoupper(strrev($trans_id)) . $PASSWORD);
 */

header('Content-Type: text/html; charset=utf-8');

/* ===================== CONFIG ===================== */

/** Статус-ендпойнт з доки */
$STATUS_URL = 'https://api.leogcltd.com/post-va';

/** Ті ж ключі, що й у твоїх APM/CREDIT2VIRTUAL скриптах */
$CLIENT_KEY = '01158d9a-9de6-11f0-ac32-ca759a298692';
$SECRET     = '4b486f4c7bee7cb42ccca2a5a980910e';

/**
 * Якщо в доках PASSWORD збігається з SECRET — використовуємо той самий.
 * Якщо ні — сюди впиши окремий пароль з доки.
 */
$PASSWORD   = $SECRET;

/* ===================== Helpers ===================== */

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function pretty($v){
    if (is_string($v)) {
        $d = json_decode($v,true);
        if (json_last_error()===JSON_ERROR_NONE) $v=$d; else return h($v);
    }
    return h(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}

/**
 * GET_TRANS_STATUS signature з доки:
 *
 * $hash = md5(strtoupper(strrev($trans_id)) . $PASSWORD);
 */
function build_status_hash(string $transId, string $password, ?string &$srcOut = null): string {
    $src = strtoupper(strrev($transId)) . $password;
    if ($srcOut !== null) {
        $srcOut = $src;
    }
    return md5($src);
}

function build_status_payload(string $transId, string $clientKey, string $password, ?string &$hashSrcOut = null): array {
    $hash = build_status_hash($transId, $password, $hashSrcOut);

    $payload = [
        'action'     => 'GET_TRANS_STATUS',
        'client_key' => $clientKey,
        'trans_id'   => $transId,
        'hash'       => $hash,
    ];

    return [$payload, $hash];
}

/* ===================== Read input ===================== */

$method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$transId = '';

if ($method === 'POST') {
    $transId = trim((string)($_POST['trans_id'] ?? ''));
} else {
    // можна передавати trans_id у GET, напр. ?trans_id=...
    $transId = trim((string)($_GET['trans_id'] ?? ''));
}

$errors = [];
$debug  = [];
$resp   = ['bodyRaw' => '', 'json' => null];

if ($method === 'POST') {
    if ($transId === '') {
        $errors[] = 'trans_id is required.';
    } elseif ($STATUS_URL === '') {
        $errors[] = 'STATUS_URL is not configured.';
    } else {
        $hashSrc = '';
        [$payload, $hash] = build_status_payload($transId, $CLIENT_KEY, $PASSWORD, $hashSrc);

        $debug['endpoint'] = $STATUS_URL;
        $debug['payload']  = $payload;
        $debug['hash_src'] = $hashSrc;
        $debug['hash']     = $hash;

        $ch = curl_init($STATUS_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
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

        $resp['bodyRaw'] = (string)$raw;
        $j = json_decode($resp['bodyRaw'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $resp['json'] = $j;
        }
    }
}

render_page([
    'trans_id' => $transId,
    'errors'   => $errors,
    'debug'    => $debug,
    'response' => $resp,
]);

/* ===================== View ===================== */

function render_page($ctx){
    $transId = $ctx['trans_id'] ?? '';
    $errors  = $ctx['errors'] ?? [];
    $debug   = $ctx['debug'] ?? [];
    $resp    = $ctx['response'] ?? [];

    $self = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>GET_TRANS_STATUS helper</title>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--err:#ff6b6b}
html,body{background:var(--bg);color:var(--text);margin:0;font:14px/1.45 ui-monospace,Menlo,Consolas,monospace}
.wrap{padding:22px;max-width:1000px;margin:0 auto}
.h{font-weight:700;margin:10px 0 6px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:var(--muted)}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#2b7cff;color:#fff;text-decoration:none;cursor:pointer}
.error{color:var(--err);margin:6px 0}
input[type=text]{padding:8px 10px;border-radius:8px;border:1px solid #2a2f3a;background:#11131a;color:#e6e6e6;width:260px}
label{display:inline-block;min-width:120px}
.small{font-size:12px;color:var(--muted)}
a.link{color:#5fa8ff;text-decoration:none}
</style>
</head>
<body>
<div class="wrap">

  <div class="panel">
    <div class="h">🕒 GET_TRANS_STATUS (by trans_id)</div>
    <form action="<?=h($self)?>" method="post">
      <?php if ($errors): foreach ($errors as $e): ?>
        <div class="error">❌ <?=h($e)?></div>
      <?php endforeach; endif; ?>

      <div style="margin:8px 0;">
        <label>trans_id:</label>
        <input type="text" name="trans_id" value="<?=h($transId)?>" placeholder="f47ac10b-58cc-4372-a567-0e02b2c3d479">
      </div>

      <div style="margin-top:12px;">
        <button class="btn" type="submit">Check status</button>
        <span class="small" style="margin-left:10px;">
          Надішле GET_TRANS_STATUS у /post-va і покаже відповідь.
        </span>
      </div>
    </form>
    <div class="small" style="margin-top:8px;">
      ⬅ <a class="link" href="credit2virtual.php">Back to CREDIT2VIRTUAL form</a>
    </div>
  </div>

  <?php if (!empty($debug)): ?>
  <div class="panel">
    <div class="h">📤 Status request</div>
    <div><span class="kv">Endpoint:</span> <?=h($debug['endpoint'] ?? '')?></div>
    <div>
      <span class="kv">HTTP:</span> <?=h($debug['http_code'] ?? '')?>
      <span class="kv" style="margin-left:12px;">Duration:</span> <?=h($debug['duration_sec'] ?? '')?>s
    </div>
    <?php if (!empty($debug['curl_error'])): ?>
      <div class="error">cURL: <?=h($debug['curl_error'])?></div>
    <?php endif; ?>

    <div class="h" style="margin-top:10px;">Payload</div>
    <pre><?=pretty($debug['payload'] ?? [])?></pre>

    <div class="h">Hash debug</div>
    <div class="kv">Source string:</div>
    <pre><?=h($debug['hash_src'] ?? '')?></pre>
    <div class="kv">Hash:</div>
    <pre><?=h($debug['hash'] ?? '')?></pre>
  </div>

  <div class="panel">
    <div class="h">📥 Status response body</div>
    <pre><?=pretty($resp['bodyRaw'] ?? '')?></pre>
    <?php if (is_array($resp['json'] ?? null)): ?>
      <div class="h">Parsed JSON</div>
      <pre><?=pretty($resp['json'])?></pre>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
<?php
}
