<?php
/**
 * Example: SALE APM callback receiver with hash verification
 *
 * This script demonstrates how to:
 *  - receive SALE callback from LeoGC as HTTP POST (form-data)
 *  - log all incoming data in a human-readable format
 *  - calculate and verify the callback signature (hash)
 *
 * Signature algorithm used here is exactly the same as in the
 * "Sale callback signature" section of the LeoGC S2S APM documentation:
 *
 *   1. Take ALL callback parameters except "hash".
 *   2. Sort parameters alphabetically by their names.
 *   3. Reverse each value individually (strrev).
 *   4. Concatenate all reversed values into a single string.
 *   5. Convert this string to UPPERCASE.
 *   6. Append PASSWORD in UPPERCASE to the end of this string.
 *   7. Calculate MD5 of the resulting string.
 *
 *   hash = md5( strtoupper( concat( reversed values ) ) . strtoupper(PASSWORD) )
 */

header('Content-Type: text/html; charset=utf-8');

/* =======================================================
   CONFIGURATION
   ======================================================= */

/**
 * PASSWORD (SECRET) used for callback signature verification.
 *
 * IMPORTANT:
 *   - This value MUST be the same as the password configured
 *     for the merchant in the LeoGC backend.
 *   - Do NOT expose the real password in public examples.
 *   - Replace "PUT_YOUR_PASSWORD_HERE" with your real password
 *     only on your secure server.
 */
//$PASSWORD = '554999c284e9f29cf95f090d9a8f3171';
$PASSWORD = '41e813071b0bea0b0c700b0cf84f51df';


/**
 * Directory and file path for log output.
 * The script will append one entry per callback.
 */
$LOG_DIR  = __DIR__ . '/logs';
$LOG_FILE = $LOG_DIR . '/callback_sale_' . date('Y-m-d') . '.log';


/* =======================================================
   HELPER FUNCTIONS
   ======================================================= */

/**
 * HTML-escape helper for safe output in the browser.
 */
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Pretty-print arrays/JSON for HTML.
 * If a string looks like JSON, it will be decoded and re-encoded
 * with pretty formatting.
 */
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
 * Calculate SALE callback hash according to the official
 * "Sale callback signature" algorithm.
 *
 * Steps:
 *   - Remove "hash" from parameter list (hash itself is not part of the calculation).
 *   - Sort parameters by their names (ksort).
 *   - Reverse each value individually (strrev).
 *   - Concatenate all reversed values into a single string.
 *   - Convert this string to uppercase.
 *   - Append PASSWORD (also in uppercase).
 *   - Return MD5 of the final string.
 *
 * @param array  $params   All POST parameters received in callback.
 * @param string $password Merchant password (secret).
 * @param string|null $srcOut Optional, will contain the full string
 *                            used as input for MD5 (for debugging).
 *
 * @return string MD5 hash.
 */
function calc_sale_callback_hash(array $params, string $password, ?string &$srcOut = null): string
{
    // 1. Remove "hash" from parameters so it is not used in calculation.
    if (isset($params['hash'])) {
        unset($params['hash']);
    }

    // 2. Sort parameters alphabetically by key (parameter name).
    ksort($params);

    // 3. Reverse each parameter value individually.
    array_walk_recursive($params, static function (&$value) {
        $value = strrev((string)$value);
    });

    // 4. Concatenate all reversed values into one string (no separator).
    $concatenated = implode('', $params);

    // 5. Convert to uppercase and append PASSWORD (also uppercase).
    $prepared = strtoupper($concatenated) . strtoupper($password);

    // For debugging/logging: return the prepared string if requested.
    if ($srcOut !== null) {
        $srcOut = $prepared;
    }

    // 6–7. Calculate and return MD5.
    return md5($prepared);
}


/* =======================================================
   READ INCOMING REQUEST
   ======================================================= */

/**
 * Typical SALE callback is sent as HTTP POST with form-data.
 * All parameters are available in the $_POST array.
 */
$method    = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$remoteIp  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rawBody   = file_get_contents('php://input'); // raw request body
$post      = $_POST;                           // parsed form-data

// Hash value received from LeoGC in the callback.
$receivedHash = $post['hash'] ?? '';

$calculatedHash = '';
$preparedSrc    = '';
$hashMatch      = null;

if (!empty($post) && $PASSWORD !== 'PUT_YOUR_PASSWORD_HERE') {
    // Calculate hash only if we actually have a password configured.
    $calculatedHash = calc_sale_callback_hash($post, $PASSWORD, $preparedSrc);

    if ($receivedHash !== '') {
        // Compare hashes using hash_equals for safe comparison.
        $hashMatch = hash_equals($calculatedHash, $receivedHash);
    }
}


/* =======================================================
   LOGGING
   ======================================================= */

/**
 * Log everything into a text file in a human-readable format.
 * Each callback creates one separated block in the log file.
 */

// Ensure logs directory exists.
if (!is_dir($LOG_DIR)) {
    @mkdir($LOG_DIR, 0777, true);
}

$log  = "============================================================\n";
$log .= "Time:   " . date('Y-m-d H:i:s') . "\n";
$log .= "IP:     {$remoteIp}\n";
$log .= "Method: {$method}\n";
$log .= "------------------------------------------------------------\n";
$log .= "POST parameters:\n";

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
$log .= "Raw body:\n";
$log .= $rawBody !== '' ? "  {$rawBody}\n" : "  (empty)\n";

$log .= "------------------------------------------------------------\n";
$log .= "Hash from callback:\n";
$log .= "  Received:   " . ($receivedHash ?: '(none)') . "\n";
$log .= "  Calculated: " . ($calculatedHash ?: '(not calculated)') . "\n";
$log .= "  Match:      ";
if ($hashMatch === true) {
    $log .= "YES\n";
} elseif ($hashMatch === false) {
    $log .= "NO\n";
} else {
    $log .= "N/A\n";
}

$log .= "------------------------------------------------------------\n";
$log .= "Prepared string used for MD5 (UPPERCASE + PASSWORD):\n";
$log .= "  " . ($preparedSrc ?: '(empty or password not set)') . "\n";
$log .= "============================================================\n\n";

@file_put_contents($LOG_FILE, $log, FILE_APPEND);


/* =======================================================
   SIMPLE HTML OUTPUT (for manual debugging in browser)
   ======================================================= */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>SALE Callback Inspector</title>
<style>
:root{
  --bg:#0f1115;
  --panel:#171923;
  --b:#2a2f3a;
  --text:#e6e6e6;
  --muted:#9aa4af;
  --ok:#2ecc71;
  --bad:#ff6b6b;
}
html,body{
  background:var(--bg);
  color:var(--text);
  margin:0;
  font:14px/1.45 monospace;
}
.wrap{
  padding:22px;
  max-width:1100px;
  margin:0 auto;
}
.h{
  font-weight:700;
  margin:10px 0 6px;
}
.panel{
  background:var(--panel);
  border:1px solid var(--b);
  border-radius:12px;
  padding:16px;
  margin:14px 0;
}
.kv{
  color:var(--muted);
}
pre{
  background:#11131a;
  padding:12px;
  border-radius:10px;
  border:1px solid #232635;
  white-space:pre-wrap;
  word-break:break-all;
}
.ok{ color:var(--ok); }
.bad{ color:var(--bad); }
</style>
</head>
<body>
<div class="wrap">

  <div class="panel">
    <div class="h">SALE Callback Received</div>
    <div><span class="kv">Method:</span> <?=h($method)?></div>
    <div><span class="kv">Remote IP:</span> <?=h($remoteIp)?></div>
  </div>

  <div class="panel">
    <div class="h">POST Parameters</div>
    <pre><?=pretty($post)?></pre>
  </div>

  <div class="panel">
    <div class="h">Raw Body</div>
    <pre><?=pretty($rawBody)?></pre>
  </div>

  <div class="panel">
    <div class="h">Hash Verification</div>

    <div class="kv">Hash received from callback:</div>
    <pre><?=h($receivedHash)?></pre>

    <div class="kv">Hash calculated by this script:</div>
    <pre><?=h($calculatedHash)?></pre>

    <?php if ($hashMatch === true): ?>
      <div class="ok">Signature is valid (hash matches).</div>
    <?php elseif ($hashMatch === false): ?>
      <div class="bad">Signature is NOT valid (hash mismatch).</div>
    <?php else: ?>
      <div class="kv">Hash was not verified (no password configured or no hash in callback).</div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="h">Prepared String for MD5 (UPPER + PASSWORD)</div>
    <pre><?=h($preparedSrc)?></pre>
  </div>

  <div class="panel">
    <div class="h">Log file path</div>
    <div class="kv"><?=h($LOG_FILE)?></div>
  </div>

</div>
</body>
</html>
