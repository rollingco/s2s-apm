<?php
/**
 * S2S CREDITVOID — refund (full / partial) by trans_id → minimal logs
 * - signature: md5( strtoupper( strrev( trans_id + SECRET ) ) )
 * - amount is optional; if present — нормалізуємо до 2 знаків (1 -> 1.00, 1.5 -> 1.50)
 */

header('Content-Type: text/html; charset=utf-8');

/* ===================== CONFIG ===================== */
$PAYMENT_URL = 'https://api.leogcltd.com/post-va';
//$CLIENT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
//$CLIENT_KEY  = 'a9375384-26f2-11f0-877d-022c42254708';
$CLIENT_KEY  = '01158d9a-9de6-11f0-ac32-ca759a298692';
$SECRET      = '4b486f4c7bee7cb42ccca2a5a980910e';

/* Prefill from GET (автономний режим, без лінків з SALE) */
$DEFAULTS = [
  'trans_id' => isset($_GET['trans_id']) ? (string)$_GET['trans_id'] : '',
  'amount'   => '',
];

/* ===================== Helpers ===================== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function pretty($v){
  if (is_string($v)) { $d = json_decode($v,true); if (json_last_error()===JSON_ERROR_NONE) $v=$d; else return h($v); }
  return h(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}
function build_creditvoid_hash($trans_id, $secret, &$srcOut = null){
  $src = $trans_id . $secret; // Appendix A
  if ($srcOut !== null) $srcOut = $src;
  return md5(strtoupper(strrev($src)));
}
/**
 * Нормалізація amount до 2 знаків після крапки:
 * - видаляємо пробіли й зайві символи
 * - одна крапка максимум
 * - якщо немає дробової частини → додаємо .00
 * - якщо 1 знак → додаємо 0
 * - якщо 2+ знаків → залишаємо як є (під 3/4-exponent теж ок)
 */
function normalize_amount_2dec(string $raw): string {
  $s = preg_replace('/[^0-9.]/', '', $raw);
  if ($s === '') return '';

  // лишаємо лише першу крапку
  if (substr_count($s, '.') > 1) {
    $parts = explode('.', $s);
    $s = array_shift($parts) . '.' . implode('', $parts); // зліпити, щоб була одна дробова частина
  }

  if (strpos($s, '.') === false) {
    return $s . '.00';
  }
  list($int, $dec) = array_pad(explode('.', $s, 2), 2, '');
  if ($dec === '') return $int . '.00';
  if (strlen($dec) === 1) return $int . '.' . $dec . '0';
  return $int . '.' . $dec; // 2+ знаків — не чіпаємо
}

/* ===================== Read form ===================== */
$method    = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$submitted = ($method === 'POST');

if ($submitted) {
  $trans_id = trim((string)($_POST['trans_id'] ?? ''));
  $amount_in = (string)($_POST['amount'] ?? '');

  // Нормалізуємо лише якщо користувач щось ввів
  $amount = trim($amount_in) === '' ? '' : normalize_amount_2dec($amount_in);

  $errors = [];
  if ($trans_id === '') $errors[] = 'trans_id is required.';
  if ($amount !== '' && !preg_match('/^\d+\.\d+$/', $amount)) {
    $errors[] = 'Amount wrong format. Use e.g. 1.00, 10.50, 100.00';
  }

  if ($errors) {
    render_page([
      'errors'   => $errors,
      'prefill'  => ['trans_id'=>$trans_id, 'amount'=>$amount_in],
      'debug'    => [],
      'response' => [],
    ]);
    exit;
  }
} else {
  $trans_id = $DEFAULTS['trans_id'];
  $amount   = $DEFAULTS['amount'];
}

/* ===================== Send CREDITVOID ===================== */
$debug = [];
$responseBlocks = ['bodyRaw' => '', 'json' => null];

if ($submitted) {
  $hash_src_dbg = '';
  $hash = build_creditvoid_hash($trans_id, $SECRET, $hash_src_dbg);

  $form = [
    'action'     => 'CREDITVOID',
    'client_key' => $CLIENT_KEY,
    'trans_id'   => $trans_id,
    'hash'       => $hash,
  ];
  if ($amount !== '') $form['amount'] = $amount;

  $debug = [
    'endpoint'   => $PAYMENT_URL,
    'client_key' => $CLIENT_KEY,
    'form'       => $form,
    'hash_src'   => $hash_src_dbg,
    'hash'       => $hash,
  ];

  $ch = curl_init($PAYMENT_URL);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $form,
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
}

/* ===================== Render ===================== */
render_page([
  'errors'   => [],
  'prefill'  => ['trans_id'=>$trans_id, 'amount'=>isset($amount_in)?$amount_in:$amount],
  'debug'    => $debug,
  'response' => $responseBlocks,
]);

/* ---------------------- View ---------------------- */
function render_page($ctx){
  $errors = $ctx['errors'] ?? [];
  $prefill= $ctx['prefill'] ?? ['trans_id'=>'','amount'=>''];
  $debug  = $ctx['debug'] ?? [];
  $resp   = $ctx['response'] ?? [];

  $self = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
  ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>CREDITVOID — refund by trans_id</title>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--err:#ff6b6b}
html,body{background:var(--bg);color:var(--text);margin:0;font:14px/1.45 ui-monospace,Menlo,Consolas,monospace}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.h{font-weight:700;margin:10px 0 6px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:var(--muted)}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#2b7cff;color:#fff;text-decoration:none}
.error{color:var(--err);margin:6px 0}
input[type=text]{padding:8px 10px;border-radius:8px;border:1px solid #2a2f3a;background:#11131a;color:#e6e6e6}
label{display:inline-block;min-width:150px}
.small{font-size:12px;color:var(--muted)}
</style>
</head>
<body>
<div class="wrap">

  <div class="panel">
    <div class="h">↩️ Create CREDITVOID (refund)</div>
    <form action="<?=h($self)?>" method="post">
      <?php if ($errors): foreach ($errors as $e): ?>
        <div class="error">❌ <?=h($e)?></div>
      <?php endforeach; endif; ?>
      <div style="margin:8px 0;">
        <label>trans_id:</label>
        <input type="text" name="trans_id" value="<?=h($prefill['trans_id'])?>" placeholder="e.g. 12345-abc...">
      </div>
      <div style="margin:8px 0;">
        <label>amount (optional):</label>
        <input type="text" name="amount" value="<?=h($prefill['amount'])?>" placeholder="1.00, 10.50, 100.00">
        <div class="small">If provided, it will be sent as XX.XX (1 → 1.00, 1.5 → 1.50).</div>
      </div>
      <div style="margin-top:12px;">
        <button class="btn" type="submit">Send CREDITVOID</button>
      </div>
    </form>
  </div>

  <?php if (!empty($debug)): ?>
  <div class="panel">
    <div class="h">🟢 CREDITVOID sent</div>
    <div><span class="kv">Endpoint:</span> <?=h($debug['endpoint'] ?? '')?></div>
    <div><span class="kv">Client key:</span> <?=h($debug['client_key'] ?? '')?></div>
    <div><span class="kv">HTTP:</span> <?=h($debug['http_code'] ?? '')?> <span class="kv" style="margin-left:12px;">Duration:</span> <?=h($debug['duration_sec'] ?? '')?>s</div>
    <?php if (!empty($debug['curl_error'])): ?>
      <div class="error">cURL: <?=h($debug['curl_error'])?></div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="h">🧮 CREDITVOID hash</div>
    <div class="kv">md5( strtoupper( strrev( trans_id + PASSWORD ) ) )</div>
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
  <?php endif; ?>

</div>
</body>
</html>
<?php
}
