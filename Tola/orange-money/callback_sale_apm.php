<?php
/**
 * S2S APM SALE — Callback receiver + logger + hash checker
 *
 * - Logs callback in a clean, human-readable format
 * - Validates callback signature using “Sale callback signature” formula
 * - Displays callback info in HTML for quick debugging
 */

header('Content-Type: text/html; charset=utf-8');

/* =======================================================
   CONFIG
   ======================================================= */

// PASSWORD (SECRET) for this merchant (1X example)
$PASSWORD = '4b486f4c7bee7cb42ccca2a5a980910e';

// Folder for logs
$LOG_DIR  = __DIR__ . '/logs';
$LOG_FILE = $LOG_DIR . '/callback_sale_' . date('Y-m-d') . '.log';


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
 * SALE callback signature calculation per doc:
 *
 * - Use all callback fields except "hash"
 * - Sort fields by name
 * - Reverse each value individually (strrev)
 * - Concatenate all reversed values
 * - Uppercase
 * - Append PASSWORD (UPPERCASE)
 * - md5()
 */
function calc_sale_callback_hash(array $params, string $password, ?string &$fullSrc = null): string
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

    if ($fullSrc !== null) {
        $fullSrc = $prepared;
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

$receivedHash  = $post['hash'] ?? '';
$calcHash      = '';
$calcHashSrc   = '';
$hashMatchFlag = null;

if (!empty($post)) {
    $calcHash = calc_sale_callback_hash($post, $PASSWORD, $calcHashSrc);
    if ($receivedHash !== '') {
        $hashMatchFlag = hash_equals($calcHash, $receivedHash);
    }
}


/* =======================================================
   LOGGING
   ======================================================= */

// Ensure logs folder exists
if (!is_dir($LOG_DIR)) {
    @mkdir($LOG_DIR, 0777, true);
}

// Pretty log entry
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
$log .= "🔐 Hash verification:\n";
$log .= "  Received:   " . ($receivedHash ?: '(none)') . "\n";
$log .= "  Calculated: " . ($calcHash ?: '(none)') . "\n";
$log .= "  Match:      ";

if ($hashMatchFlag === true)  $log .= "YES\n";
elseif ($hashMatchFlag === false) $log .= "NO\n";
else $log .= "N/A\n";

$log .= "------------------------------------------------------------\n";
$log .= "🔣 Prepared string used for md5():\n";
$log .= "  " . ($calcHashSrc ?: '(empty)') . "\n";
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
<title>SALE Callback Inspector</title>
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
    <div class="h">📥 SALE Callback Received</div>
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
    <div class="h">🔐 Hash Verification</div>

    <div class="kv">Received hash:</div>
    <pre><?=h($receivedHash)?></pre>

    <div class="kv">Calculated hash:</div>
    <pre><?=h($calcHash)?></pre>

    <?php if ($hashMatchFlag !== null): ?>
      <div class="<?= $hashMatchFlag ? 'ok' : 'bad' ?>">
        <?= $hashMatchFlag ? '✅ Hash is valid (MATCH)' : '❌ Hash mismatch' ?>
      </div>
    <?php else: ?>
      <div class="kv">(Callback did not contain a 'hash' field)</div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="h">🔣 Prepared String (UPPERCASE + PASSWORD)</div>
    <pre><?=h($calcHashSrc)?></pre>
  </div>

  <div class="panel">
    <div class="h">📁 Log File</div>
    <div class="kv"><?=h($LOG_FILE)?></div>
  </div>

</div>
</body>
</html>
