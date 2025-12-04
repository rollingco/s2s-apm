<?php
/**
 * Universal callback receiver for LeoGC (current merchant)
 *
 * - Logs incoming callbacks в читабельному вигляді
 * - Пробує 2 алгоритми:
 *     1) APM SALE callback signature (all params except hash, ksort, reverse each value)
 *     2) Credit2Virtual callback signature (trans_id + order_id + status)
 */

header('Content-Type: text/html; charset=utf-8');

/* =======================================================
   CONFIG
   ======================================================= */

// СЕКРЕТ САМЕ ТОГО МЕРЧАНТА, ЯКИЙ ТИ ЗАРАЗ ТЕСТИШ
$PASSWORD = '554999c284e9f29cf95f090d9a8f3171';

// Папка та файл логів
$LOG_DIR  = __DIR__ . '/logs';
$LOG_FILE = $LOG_DIR . '/callback_universal_' . date('Y-m-d') . '.log';


/* =======================================================
   HELPERS
   ======================================================= */

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
    return h(json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

/**
 * APM SALE callback signature:
 * - Use all parameters except 'hash'
 * - Sort keys (ksort)
 * - Reverse each value
 * - Join
 * - Uppercase
 * - Append PASSWORD (uppercase)
 * - md5()
 */
function calc_hash_apm_sale(array $params, string $password, ?string &$srcOut = null): string
{
    if (isset($params['hash'])) {
        unset($params['hash']);
    }

    ksort($params);

    array_walk_recursive($params, static function (&$value) {
        $value = strrev((string)$value);
    });

    $src = implode('', $params);
    $prepared = strtoupper($src) . strtoupper($password);

    if ($srcOut !== null) {
        $srcOut = $prepared;
    }

    return md5($prepared);
}

/**
 * Credit2Virtual callback signature:
 * md5(strtoupper(strrev(trans_id . order_id . status)) . PASSWORD)
 */
function calc_hash_credit2virtual(array $params, string $password, ?string &$srcOut = null): ?string
{
    if (!isset($params['trans_id'], $params['order_id'], $params['status'])) {
        return null;
    }

    $src = (string)$params['trans_id'] . (string)$params['order_id'] . (string)$params['status'];
    $rev = strrev($src);
    $prepared = strtoupper($rev) . strtoupper($password);

    if ($srcOut !== null) {
        $srcOut = $prepared;
    }

    return md5($prepared);
}


/* =======================================================
   READ REQUEST
   ======================================================= */

$method    = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$remoteIp  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rawBody   = file_get_contents('php://input');
$post      = $_POST;

$receivedHash = $post['hash'] ?? '';

$calcSale    = '';
$calcSaleSrc = '';
$matchSale   = null;

$calcC2V     = null;
$calcC2VSrc  = '';
$matchC2V    = null;

if (!empty($post)) {

    // Алгоритм 1: APM SALE
    $calcSale = calc_hash_apm_sale($post, $PASSWORD, $calcSaleSrc);
    if ($receivedHash !== '') {
        $matchSale = hash_equals($calcSale, $receivedHash);
    }

    // Алгоритм 2: Credit2Virtual
    $calcC2V = calc_hash_credit2virtual($post, $PASSWORD, $calcC2VSrc);
    if ($calcC2V !== null && $receivedHash !== '') {
        $matchC2V = hash_equals($calcC2V, $receivedHash);
    }
}


/* =======================================================
   LOGGING
   ======================================================= */

if (!is_dir($LOG_DIR)) {
    @mkdir($LOG_DIR, 0777, true);
}

$log  = "============================================================\n";
$log .= "📅 " . date('Y-m-d H:i:s') . "\n";
$log .= "🌐 IP:       {$remoteIp}\n";
$log .= "🔧 Method:   {$method}\n";
$log .= "------------------------------------------------------------\n";
$log .= "📨 POST parameters:\n";

if (!empty($post)) {
    foreach ($post as $k => $v) {
        if (is_scalar($v)) {
            $log .= "  - {$k}: {$v}\n";
        } else {
            $log .= "  - {$k}: " . json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
} else {
    $log .= "  (none)\n";
}

$log .= "------------------------------------------------------------\n";
$log .= "🧾 Raw body:\n";
$log .= $rawBody !== '' ? "  {$rawBody}\n" : "  (empty)\n";

$log .= "------------------------------------------------------------\n";
$log .= "🔐 Hash from callback:\n";
$log .= "  Received: " . ($receivedHash ?: '(none)') . "\n";

$log .= "------------------------------------------------------------\n";
$log .= "🧮 Algorithm #1: APM SALE\n";
$log .= "  Calculated: " . ($calcSale ?: '(none)') . "\n";
$log .= "  Match:      " . ($matchSale === null ? 'N/A' : ($matchSale ? 'YES' : 'NO')) . "\n";
$log .= "  SRC:        " . ($calcSaleSrc ?: '(empty)') . "\n";

$log .= "------------------------------------------------------------\n";
$log .= "🧮 Algorithm #2: Credit2Virtual\n";
$log .= "  Calculated: " . ($calcC2V ?? '(not applicable)') . "\n";
$log .= "  Match:      " . ($matchC2V === null ? 'N/A' : ($matchC2V ? 'YES' : 'NO')) . "\n";
$log .= "  SRC:        " . ($calcC2VSrc ?: '(empty or not applicable)') . "\n";

$log .= "============================================================\n\n";

@file_put_contents($LOG_FILE, $log, FILE_APPEND);


/* =======================================================
   HTML OUTPUT
   ======================================================= */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Universal Callback Inspector</title>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--ok:#2ecc71;--bad:#ff6b6b}
html,body{background:var(--bg);color:var(--text);margin:0;font:14px/1.45 monospace}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.h{font-weight:700;margin:10px 0 6px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:16px;margin:14px 0}
.kv{color:var(--muted)}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap;word-break:break-all}
.ok{color:var(--ok)}
.bad{color:var(--bad)}
</style>
</head>
<body>
<div class="wrap">

  <div class="panel">
    <div class="h">📥 Callback Received</div>
    <div><span class="kv">Method:</span> <?=h($method)?></div>
    <div><span class="kv">Remote IP:</span> <?=h($remoteIp)?></div>
  </div>

  <div class="panel">
    <div class="h">📨 POST Parameters</div>
    <pre><?=pretty($post)?></pre>
  </div>

  <div class="panel">
    <div class="h">🧾 Raw Body</div>
    <pre><?=pretty($rawBody)?></pre>
  </div>

  <div class="panel">
    <div class="h">🔐 Hash from Callback</div>
    <pre><?=h($receivedHash)?></pre>
  </div>

  <div class="panel">
    <div class="h">🧮 Algorithm #1: APM SALE</div>
    <div class="kv">Calculated hash:</div>
    <pre><?=h($calcSale)?></pre>
    <?php if ($matchSale !== null): ?>
      <div class="<?= $matchSale ? 'ok' : 'bad' ?>">
        <?= $matchSale ? '✅ MATCH' : '❌ MISMATCH' ?>
      </div>
    <?php else: ?>
      <div class="kv">N/A</div>
    <?php endif; ?>
    <div class="kv">Src:</div>
    <pre><?=h($calcSaleSrc)?></pre>
  </div>

  <div class="panel">
    <div class="h">🧮 Algorithm #2: Credit2Virtual</div>
    <div class="kv">Calculated hash:</div>
    <pre><?=h($calcC2V ?? '')?></pre>
    <?php if ($matchC2V !== null): ?>
      <div class="<?= $matchC2V ? 'ok' : 'bad' ?>">
        <?= $matchC2V ? '✅ MATCH' : '❌ MISMATCH' ?>
      </div>
    <?php else: ?>
      <div class="kv">N/A (not applicable)</div>
    <?php endif; ?>
    <div class="kv">Src:</div>
    <pre><?=h($calcC2VSrc)?></pre>
  </div>

  <div class="panel">
    <div class="h">📁 Log file</div>
    <div class="kv"><?=h($LOG_FILE)?></div>
  </div>

</div>
</body>
</html>
