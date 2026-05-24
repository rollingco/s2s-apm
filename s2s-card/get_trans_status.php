<?php
/**
 * GET_TRANS_STATUS Emulator — READABLE (Headers + Request + Response)
 *
 * Opens by link, for example:
 *   get_trans_status.php?trans_id=UUID
 *
 * Sends request as: application/x-www-form-urlencoded
 *
 * Hash formula from PDF:
 *   md5(strtoupper(strrev(email) . PASSWORD . trans_id . strrev(substr(card_number,0,6) . substr(card_number,-4))))
 *
 * Important:
 *   Use callback notification as the main source for the final transaction status.
 *   This file is only for manual status checking.
 */

// ========================= CONFIG =========================
$endpoint    = 'https://api.leogcltd.com/post';

$merchantKey = '9a1cc9fe-55c4-11f1-8e6e-de23b7cf21d1';
$secret      = 'aba1a6c2192932508728997065c3fa9d';

// Same payer/card data used in SALE request because it is required for hash calculation.
$cardNumber  = '4441111087875187';
$payerEmail  = 'garik.m@pay.cc';

// ========================= HELPERS =========================
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function pretty_json($data): string {
  if (is_string($data)) {
    $decoded = json_decode($data, true);
    if (is_array($decoded)) {
      return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    return $data;
  }
  return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function mask_sensitive(array $arr): array {
  $out = $arr;
  if (isset($out['hash']) && is_string($out['hash']) && strlen($out['hash']) > 16) {
    $out['hash'] = substr($out['hash'], 0, 10) . '…' . substr($out['hash'], -10);
  }
  return $out;
}

// ========================= INPUT =========================
$transId = trim((string)($_GET['trans_id'] ?? $_POST['trans_id'] ?? ''));

$rawResponse = '';
$curlErr = '';
$httpCode = 0;
$ms = 0;
$responseArr = null;
$requestFields = [];
$formBody = '';
$outHeaders = [];
$curlCommand = '';
$hashSource = '';
$hash = '';
$first6 = substr($cardNumber, 0, 6);
$last4  = substr($cardNumber, -4);

if ($transId !== '') {
  // ========================= HASH =========================
  $hashSource = strrev($payerEmail) . $secret . $transId . strrev($first6 . $last4);
  $hash = md5(strtoupper($hashSource));

  // ========================= REQUEST (fields) =========================
  $requestFields = [
    'action'     => 'GET_TRANS_STATUS',
    'client_key' => $merchantKey,
    'trans_id'   => $transId,
    'hash'       => $hash,
  ];

  // What we actually send:
  $formBody = http_build_query($requestFields);

  // ========================= OUTGOING HEADERS =========================
  $outHeaders = [
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json',
    'User-Agent: GET-TRANS-STATUS-Readable-Emulator/1.0',
    'Content-Length: ' . strlen($formBody),
  ];

  // ========================= cURL COMMAND (debug) =========================
  $curlParts = [
    "curl --request POST '" . $endpoint . "'",
  ];

  foreach ($outHeaders as $header) {
    $curlParts[] = "--header '" . str_replace("'", "'\\''", $header) . "'";
  }

  $curlParts[] = "--data '" . str_replace("'", "'\\''", $formBody) . "'";
  $curlCommand = implode(" \\\n", $curlParts);

  // ========================= EXECUTE =========================
  $start = microtime(true);

  $ch = curl_init($endpoint);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $formBody,
    CURLOPT_HTTPHEADER     => $outHeaders,
    CURLOPT_TIMEOUT        => 60,
  ]);

  $rawResponse = curl_exec($ch);
  $curlErr     = curl_error($ch);
  $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  $ms = (int)round((microtime(true) - $start) * 1000);

  if (!$curlErr && is_string($rawResponse)) {
    $decoded = json_decode($rawResponse, true);
    if (is_array($decoded)) $responseArr = $decoded;
  }
}

// ========================= HTML OUTPUT =========================
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>GET_TRANS_STATUS Emulator — Readable Logs</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial; margin: 18px; background:#0b0f17; color:#e7eefc; }
    .wrap { max-width: 1100px; margin: 0 auto; }
    .card { background:#111a2b; border:1px solid #23304a; border-radius:14px; padding:16px; margin: 14px 0; box-shadow: 0 8px 25px rgba(0,0,0,.25); }
    h1 { font-size: 20px; margin: 0 0 10px; }
    h2 { font-size: 16px; margin: 0 0 10px; opacity: .95; }
    .meta { display:flex; gap:10px; flex-wrap:wrap; font-size: 13px; opacity:.95; margin-top: 8px; }
    .pill { background:#0b1222; border:1px solid #263556; padding:6px 10px; border-radius:999px; }
    .ok { border-color:#2b7a4b; }
    .bad { border-color:#a34545; }
    pre { margin:0; white-space: pre-wrap; word-break: break-word; font-size: 13px; line-height: 1.35; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }
    .grid { display:grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media(max-width: 900px){ .grid { grid-template-columns: 1fr; } }
    .btn { appearance:none; display:inline-block; text-decoration:none; border:1px solid #3a65d9; background:#2a4ec2; color:white; padding:10px 14px; border-radius:12px; font-weight:700; cursor:pointer; }
    .input { width:100%; max-width:520px; background:#0b1222; color:#e7eefc; border:1px solid #263556; border-radius:12px; padding:10px 12px; }
    .hint { margin-top: 8px; font-size: 12px; opacity: .85; }
    .warn { color:#ffd08a; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="card">
    <h1>GET_TRANS_STATUS Emulator — Readable view</h1>
    <div class="meta">
      <div class="pill">Endpoint: <b><?=h($endpoint)?></b></div>
      <?php if ($transId !== ''): ?>
        <div class="pill <?=($httpCode>=200 && $httpCode<400 && !$curlErr)?'ok':'bad'?>">HTTP: <b><?=h($httpCode)?></b></div>
        <div class="pill">Time: <b><?=h($ms)?> ms</b></div>
        <div class="pill">trans_id: <b><?=h($transId)?></b></div>
      <?php endif; ?>
    </div>
    <div class="hint warn">Final status should be taken from callback notification. This page is only for manual checking.</div>
    <?php if ($curlErr): ?>
      <div class="meta" style="margin-top:10px;">
        <div class="pill bad">cURL error: <b><?=h($curlErr)?></b></div>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Check transaction status</h2>
    <form method="get">
      <input class="input mono" type="text" name="trans_id" value="<?=h($transId)?>" placeholder="Paste trans_id here" required>
      <button type="submit" class="btn">Get_Trans_Status</button>
    </form>
  </div>

  <?php if ($transId === ''): ?>
    <div class="card">
      <h2>No trans_id provided</h2>
      <pre class="mono">Open this file as: get_trans_status.php?trans_id=UUID</pre>
    </div>
  <?php else: ?>

    <div class="card">
      <h2>Headers (outgoing)</h2>
      <pre class="mono"><?=h(implode("\n", $outHeaders))?></pre>
    </div>

    <div class="grid">
      <div class="card">
        <h2>Request (readable JSON, masked)</h2>
        <pre class="mono"><?=h(pretty_json(mask_sensitive($requestFields)))?></pre>
        <div class="hint warn">Masked view: hash shortened.</div>
      </div>

      <div class="card">
        <h2>Signature debug</h2>
        <pre class="mono"><?=h(pretty_json([
          'email' => $payerEmail,
          'trans_id' => $transId,
          'first6' => $first6,
          'last4' => $last4,
          'hash_source' => $hashSource,
          'hash' => $hash,
        ]))?></pre>
        <div class="hint warn">Do not log secret/hash source in production. This is only for debugging.</div>
      </div>
    </div>

    <div class="card">
      <h2>Request body (what is actually sent, form-urlencoded)</h2>
      <pre class="mono"><?=h($formBody)?></pre>
    </div>

    <div class="card">
      <h2>cURL request (what is actually sent)</h2>
      <pre class="mono"><?=h($curlCommand)?></pre>
    </div>

    <div class="grid">
      <div class="card">
        <h2>Response (raw)</h2>
        <pre class="mono"><?=h((string)$rawResponse)?></pre>
      </div>

      <div class="card">
        <h2>Response (readable JSON)</h2>
        <pre class="mono"><?=h(is_array($responseArr) ? pretty_json($responseArr) : (string)$rawResponse)?></pre>
      </div>
    </div>

  <?php endif; ?>

</div>
</body>
</html>
