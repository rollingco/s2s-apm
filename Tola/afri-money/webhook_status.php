<?php
/**
 * Minimal webhook receiver for payment status updates.
 * Goal: be easy to read, easy to test, and safe enough for production.
 *
 * What it does:
 * 1) Accepts POST with JSON.
 * 2) (Optional) Verifies signature.
 * 3) Ensures idempotency (don’t process same event twice).
 * 4) Updates order status (stub) and returns HTTP 200 quickly.
 *
 * NOTE: This is a template. Adjust field names and signature rules
 * to your gateway’s exact specification.
 */

header('Content-Type: text/plain; charset=utf-8');

/* ========================= CONFIG ========================= */
// Use your real secret here (or a dedicated “webhook secret” if provided).
//$WEBHOOK_SECRET = 'REPLACE_WITH_YOUR_SECRET';
$WEBHOOK_SECRET = 'a9375384-26f2-11f0-877d-022c42254708';

// Directory for simple file logs and idempotency flags.
// Make sure the web server user can write here.
$LOG_DIR   = __DIR__ . '/logs';
$STATE_DIR = __DIR__ . '/state';

/* ======================= BOOTSTRAP ======================== */
@is_dir($LOG_DIR)   || @mkdir($LOG_DIR, 0775, true);
@is_dir($STATE_DIR) || @mkdir($STATE_DIR, 0775, true);
$LOG_FILE = $LOG_DIR . '/webhook_' . date('Ymd') . '.log';

/** Small helpers */
function log_line($msg){
  global $LOG_FILE;
  @file_put_contents($LOG_FILE, '['.date('Y-m-d H:i:s')."] $msg\n", FILE_APPEND);
}
function quit($code, $msg){
  http_response_code($code);
  echo $msg;
  exit;
}

/* Optional: GET = quick diagnostics to see the last log lines in a browser. */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  if (is_file($LOG_FILE)) {
    $lines = @file($LOG_FILE) ?: [];
    echo implode("", array_slice($lines, -30));
  } else {
    echo "No logs yet.";
  }
  exit;
}

/* ======================== INPUT =========================== */
// Webhooks must be POST with JSON body.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  quit(400, 'Bad Request: POST expected');
}

$raw = file_get_contents('php://input');
$hdr = function_exists('getallheaders') ? getallheaders() : [];
log_line('HEADERS: '.json_encode($hdr, JSON_UNESCAPED_SLASHES));
log_line('RAW: '.$raw);

// Parse JSON
$data = json_decode($raw, true);
if (!is_array($data)) {
  quit(400, 'Invalid JSON');
}

/* ===================== EXTRACT FIELDS =====================
 * Adjust field names to match your gateway’s payload.
 */
$event     = (string)($data['event']     ?? 'payment.status');
$transId   = (string)($data['trans_id']  ?? '');
$orderId   = (string)($data['order_id']  ?? '');
$status    = strtoupper((string)($data['status'] ?? ''));
$amount    = (string)($data['amount']    ?? '');
$currency  = strtoupper((string)($data['currency'] ?? ''));
$signature = (string)($data['signature'] ?? ($hdr['X-Signature'] ?? ''));

// Basic validation: we need at least trans_id and status.
if ($transId === '' || $status === '') {
  quit(400, 'Missing required fields (trans_id/status)');
}

/* =================== SIGNATURE CHECK (OPTIONAL) ===================
 * Choose ONE method that matches your gateway’s spec.
 * Keep both examples here for clarity; enable the correct one.
 */

// Example A: HMAC-SHA256 over raw JSON, Base64-encoded, sent in header X-Signature or body "signature".
$expectedHmac = base64_encode(hash_hmac('sha256', $raw, $WEBHOOK_SECRET, true));

// Example B: MD5 of uppercased reversed concatenation (illustrative; adjust fields to your spec).
// $identifier = (string)($data['identifier'] ?? '');
// $src        = $identifier.$transId.$status.$amount.$currency.$WEBHOOK_SECRET;
// $expectedMd5 = md5(strtoupper(strrev($src)));

// Choose the check you actually need:
$valid = hash_equals($expectedHmac, $signature);
// $valid = hash_equals($expectedMd5, $signature);

if ($signature !== '' && !$valid) {
  log_line("Invalid signature. got={$signature}");
  quit(400, 'Invalid signature');
}

/* ======================= IDEMPOTENCY ======================= 
 * We don’t want to process the same update twice.
 * Use DB unique keys in production; a file flag is enough for a demo.
 */
$flag = $STATE_DIR . '/done_' . preg_replace('~[^a-zA-Z0-9_\-]~', '_', $transId . '_' . $status) . '.flag';
if (is_file($flag)) {
  log_line("Duplicate event ignored: trans_id=$transId status=$status");
  quit(200, 'OK (duplicate)');
}
@file_put_contents($flag, date('c'));

/* ===================== BUSINESS LOGIC =====================
 * Here you would:
 * - fetch the order by $orderId or by $transId
 * - verify amount/currency if needed
 * - map gateway status to your internal status
 * - mark order as paid/failed/expired, etc.
 * Replace this stub with your real code.
 */
try {
  // Example mapping (adjust as needed):
  $final =
      ($status === 'SUCCESS') ? 'paid' :
      (($status === 'DECLINE' || $status === 'FAILED') ? 'failed' :
      (($status === 'EXPIRED') ? 'expired' : 'pending'));

  // update_order_status($orderId, $final); // ← your function
  log_line("Handled: order_id=$orderId trans_id=$transId status=$status → $final amount=$amount $currency");

} catch (Throwable $e) {
  // If your processing fails, let retries happen: delete idempotency flag.
  @unlink($flag);
  log_line('Processing error: '.$e->getMessage());
  quit(500, 'Processing error');
}

/* ==================== FAST, CLEAR REPLY ==================== */
quit(200, 'OK');
