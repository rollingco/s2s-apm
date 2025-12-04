<?php
/**
 * S2S APM SALE callback logger + hash checker
 *
 * - Accepts callback (webhook) from LeoGC
 * - Logs everything to a file
 * - Shows nice HTML with data + hash verification
 */

header('Content-Type: text/html; charset=utf-8');

/* ===================== CONFIG ===================== */

/**
 * PASSWORD (SECRET) for this merchant.
 * Постав тут той SECRET, який відповідає клієнту.
 * Для 1X у тебе був:
 *   4b486f4c7bee7cb42ccca2a5a980910e
 */
$PASSWORD = '4b486f4c7bee7cb42ccca2a5a980910e';

// Лог-файл (створи папку logs і дай на неї права на запис)
$LOG_DIR  = __DIR__ . '/logs';
$LOG_FILE = $LOG_DIR . '/callback_sale_' . date('Y-m-d') . '.log';

/* ===================== Helpers ===================== */

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

function pretty($v){
    if (is_string($v)) {
        $d = json_decode($v, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $v = $d;
        } else {
            return h($v);
        }
    }
    return h(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}

/**
 * Calculate SALE callback hash according to "Sale callback signature" doc:
 *
 * - use all params except 'hash'
 * - sort by parameter name (ksort)
 * - reverse each value
 * - concatenate all reversed values
 * - strtoupper
 * - append PASSWORD (UPPERCASE)
 * - md5()
 */
function calc_sale_callback_hash(array $params, string $password, ?string &$srcOut = null): string
{
    // Викидаємо hash із розрахунку
    if (isset($params['hash'])) {
        unset($params['hash']);
    }

    // Сортуємо за іменами параметрів
    ksort($params);

    // Реверсимо кожне значення (якщо масив – рекурсивно)
    array_walk_recursive($params, static function (&$value) {
        $value = strrev((string)$value);
    });

    // Склеюємо
    $src = implode('', $params);

    // Uppercase + PASSWORD (теж uppercase)
    $prepared = strtoupper($src) . strtoupper($password);

    if ($srcOut !== null) {
        $srcOut = $prepared; // можна зберегти вже "готовий" рядок
    }

    return md5($prepared);
}

/* ===================== Read request ===================== */

$method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$remoteIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rawBody  = file_get_contents('php://input');
$post     = $_POST; // Для form-data callback цього достатньо

// Обчислюємо hash, якщо є що обчислювати
$calcHash      = '';
$calcHashSrc   = '';
$receivedHash  = $post['hash'] ?? '';
$hashMatchFlag = null;

if (!empty($post)) {
    $calcHash = calc_sale_callback_hash($post, $PASSWORD, $calcHashSrc);
    if ($receivedHash !== '') {
        $hashMatchFlag = hash_equals($calcHash, $receivedHash);
    }
}

/* ===================== Logging ===================== */

// Створюємо папку logs, якщо немає
if (!is_dir($LOG_DIR)) {
    @mkdir($LOG_DIR, 0777, true);
}

$logEntry = [
    'time'        => date('Y-m-d H:i:s'),
    'remote_ip'   => $remoteIp,
    'method'      => $method,
    'query'       => $_GET,
    'post'        => $post,
    'raw_body'    => $rawBody,
    'receivedHash'=> $receivedHash,
    'calcHash'    => $calcHash,
    'hashMatch'   => $hashMatchFlag,
];

$logLine = '[' . $logEntry['time'] . '] ' . json_encode($logEntry, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . PHP_EOL;
@file_put_contents($LOG_FILE, $logLine, FILE_APPEND);

/* ===================== Simple HTML output ===================== */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>SALE Callback Inspector</title>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--ok:#2ecc71;--bad:#ff6b6b}
html,body{background:var(--bg);color:var(--text);margin:0;font:14px/1.45 ui-monospace,Menlo,Consolas,monospace}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.h{font-weight:700;margin:10px 0 6px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:var(--muted)}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap;word-break:break-all}
.bad{color:var(--bad);}
.ok{color:var(--ok);}
</style>
</head>
<body>
<div class="wrap">
  <div class="panel">
    <div class="h">📥 SALE callback received</div>
    <div><span class="kv">Method:</span> <?=h($method)?></div>
    <div><span class="kv">Remote IP:</span> <?=h($remoteIp)?></div>
  </div>

  <div class="panel">
    <div class="h">🔢 POST params</div>
    <pre><?=pretty($post)?></pre>
  </div>

  <div class="panel">
    <div class="h">🧾 Raw body</div>
    <pre><?=pretty($rawBody)?></pre>
  </div>

  <div  class="panel">
    <div class="h">🔐 Hash verification (Sale callback signature)</div>
    <div class="kv">Received hash (from callback):</div>
    <pre><?=h($receivedHash)?></pre>

    <div class="kv">Calculated hash (this script):</div>
    <pre><?=h($calcHash)?></pre>

    <div class="kv">Prepared string (UPPER+PASSWORD) used for md5():</div>
    <pre><?=h($calcHashSrc)?></pre>

    <?php if ($hashMatchFlag !== null): ?>
      <div class="<?= $hashMatchFlag ? 'ok' : 'bad' ?>">
        <?= $hashMatchFlag ? '✅ Hash is valid (matches).' : '❌ Hash mismatch!' ?>
      </div>
    <?php else: ?>
      <div class="kv">No hash parameter in callback to compare.</div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="h">📁 Log file</div>
    <div class="kv"><?=h($LOG_FILE)?></div>
  </div>
</div>
</body>
</html>
