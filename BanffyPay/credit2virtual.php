<?php
/**
 * S2S CREDIT2VIRTUAL — PayOut
 *
 * Endpoint: https://api.leogcltd.com/post
 * Action: CREDIT2VIRTUAL
 * Brand/Method: leogc-bannf-dbm
 * Payment Code Withdrawal: 999
 *
 * Provider should be the same as for PayIn.
 */

header('Content-Type: text/html; charset=utf-8');

/* ===================== CONFIG ===================== */
$PAYMENT_URL = 'https://api.leogcltd.com/post';

$CLIENT_KEY = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET     = '554999c284e9f29cf95f090d9a8f3171';

$STATUS_HELPER_URL = 'status_credit2virtual.php';

/* ===================== DEFAULTS ===================== */
$DEFAULTS = [
  'order_id' => $_GET['order_id'] ?? ('payout-' . time()),
  'amount'   => $_GET['amount']   ?? '1',
  'currency' => $_GET['currency'] ?? 'TZS',
  'brand'    => $_GET['brand']    ?? 'leogc-bannf-dbm',
  'desc'     => $_GET['desc']     ?? 'payout test',
  'phone'    => $_GET['phone']    ?? '255683456789',

  // Provider should be the same as for PayIn
  'provider' => $_GET['provider'] ?? 'Airtel',

  // Withdrawal payment code
  'payment_code' => $_GET['payment_code'] ?? '999',
];

/* ===================== Helpers ===================== */
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

  return h(json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/**
 * CREDIT2VIRTUAL hash:
 * md5( strtoupper( strrev( order_id . amount . currency ) ) . SECRET )
 */
function build_credit2virtual_hash($order_id, $amount, $currency, $secret, &$srcOut = null) {
  $inner = $order_id . $amount . $currency;
  $src   = strtoupper(strrev($inner)) . $secret;

  if ($srcOut !== null) {
    $srcOut = $src;
  }

  return md5($src);
}

/* ===================== Read form ===================== */
$method    = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$submitted = ($method === 'POST');

if ($submitted) {
  $order_id_in  = trim((string)($_POST['order_id'] ?? ''));
  $amount       = trim((string)($_POST['amount'] ?? ''));
  $currency     = strtoupper(trim((string)($_POST['currency'] ?? '')));
  $brand        = trim((string)($_POST['brand'] ?? ''));
  $desc         = trim((string)($_POST['desc'] ?? ''));
  $phone        = trim((string)($_POST['phone'] ?? ''));
  $provider     = trim((string)($_POST['provider'] ?? ''));
  $payment_code = trim((string)($_POST['payment_code'] ?? ''));

  $errors = [];

  if ($order_id_in === '') {
    $errors[] = 'order_id is required.';
  }

  if ($amount === '' || !preg_match('/^\d+(\.\d+)?$/', $amount)) {
    $errors[] = 'Amount wrong format. Use e.g. 1, 10.5, 100.00';
  }

  if ($currency === '') {
    $errors[] = 'Currency is required.';
  }

  if ($brand === '') {
    $brand = 'leogc-bannf-dbm';
  }

  if ($phone === '') {
    $errors[] = 'Phone / MSISDN is required.';
  }

  if ($provider === '') {
    $errors[] = 'Provider is required.';
  }

  if ($payment_code === '') {
    $payment_code = '999';
  }

  if ($errors) {
    render_page([
      'errors' => $errors,
      'prefill' => [
        'order_id'     => $order_id_in,
        'amount'       => $amount,
        'currency'     => $currency,
        'brand'        => $brand,
        'desc'         => $desc,
        'phone'        => $phone,
        'provider'     => $provider,
        'payment_code' => $payment_code,
      ],
      'debug' => [],
      'response' => [],
    ]);
    exit;
  }
} else {
  $order_id_in  = $DEFAULTS['order_id'];
  $amount       = $DEFAULTS['amount'];
  $currency     = $DEFAULTS['currency'];
  $brand        = $DEFAULTS['brand'];
  $desc         = $DEFAULTS['desc'];
  $phone        = $DEFAULTS['phone'];
  $provider     = $DEFAULTS['provider'];
  $payment_code = $DEFAULTS['payment_code'];
}

/* ===================== Send CREDIT2VIRTUAL ===================== */
$debug = [];
$responseBlocks = [
  'bodyRaw' => '',
  'json'    => null,
];

if ($submitted) {
  $hash_src_dbg = '';
  $hash = build_credit2virtual_hash($order_id_in, $amount, $currency, $SECRET, $hash_src_dbg);

  $form = [
    'action'            => 'CREDIT2VIRTUAL',
    'client_key'        => $CLIENT_KEY,
    'order_id'          => $order_id_in,
    'order_amount'      => $amount,
    'order_currency'    => $currency,
    'order_description' => $desc,
    'brand'             => $brand,

    // Recipient phone
    'parameters[msisdn]' => $phone,

    // BanffyPay payout params
    'parameters[paymentCode]' => $payment_code,
    'parameters[Provider]'    => $provider,

    'hash' => $hash,
  ];

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

  if ($err) {
    $debug['curl_error'] = $err;
  }

  $responseBlocks['bodyRaw'] = (string)$raw;

  $json = json_decode($responseBlocks['bodyRaw'], true);
  if (json_last_error() === JSON_ERROR_NONE) {
    $responseBlocks['json'] = $json;
  }
}

/* ===================== Render ===================== */
render_page([
  'errors' => [],
  'prefill' => [
    'order_id'     => $order_id_in,
    'amount'       => $amount,
    'currency'     => $currency,
    'brand'        => $brand,
    'desc'         => $desc,
    'phone'        => $phone,
    'provider'     => $provider,
    'payment_code' => $payment_code,
  ],
  'debug'    => $debug,
  'response' => $responseBlocks,
]);

/* ===================== View ===================== */
function render_page($ctx) {
  global $STATUS_HELPER_URL;

  $errors  = $ctx['errors'] ?? [];
  $prefill = $ctx['prefill'] ?? [];
  $debug   = $ctx['debug'] ?? [];
  $resp    = $ctx['response'] ?? [];

  $self = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>BanffyPay PayOut — CREDIT2VIRTUAL</title>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--err:#ff6b6b}
html,body{background:var(--bg);color:var(--text);margin:0;font:14px/1.45 ui-monospace,Menlo,Consolas,monospace}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.h{font-weight:700;margin:10px 0 6px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:var(--muted)}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#2b7cff;color:#fff;text-decoration:none;cursor:pointer}
.error{color:var(--err);margin:6px 0}
input[type=text]{padding:8px 10px;border-radius:8px;border:1px solid #2a2f3a;background:#11131a;color:#e6e6e6;width:320px}
label{display:inline-block;min-width:160px}
.small{font-size:12px;color:var(--muted)}
</style>
</head>
<body>
<div class="wrap">

  <div class="panel">
    <div class="h">💸 BanffyPay PayOut / CREDIT2VIRTUAL</div>

    <form action="<?=h($self)?>" method="post">
      <?php if ($errors): foreach ($errors as $e): ?>
        <div class="error">❌ <?=h($e)?></div>
      <?php endforeach; endif; ?>

      <div style="margin:8px 0;">
        <label>order_id:</label>
        <input type="text" name="order_id" value="<?=h($prefill['order_id'] ?? '')?>">
      </div>

      <div style="margin:8px 0;">
        <label>amount:</label>
        <input type="text" name="amount" value="<?=h($prefill['amount'] ?? '')?>">
        <div class="small">Amount is sent exactly as entered. No forced .00.</div>
      </div>

      <div style="margin:8px 0;">
        <label>currency:</label>
        <input type="text" name="currency" value="<?=h($prefill['currency'] ?? '')?>">
      </div>

      <div style="margin:8px 0;">
        <label>brand:</label>
        <input type="text" name="brand" value="<?=h($prefill['brand'] ?? '')?>">
        <div class="small">Default: leogc-bannf-dbm</div>
      </div>

      <div style="margin:8px 0;">
        <label>description:</label>
        <input type="text" name="desc" value="<?=h($prefill['desc'] ?? '')?>">
      </div>

      <div style="margin:8px 0;">
        <label>phone / MSISDN:</label>
        <input type="text" name="phone" value="<?=h($prefill['phone'] ?? '')?>">
      </div>

      <div style="margin:8px 0;">
        <label>Provider:</label>
        <input type="text" name="provider" value="<?=h($prefill['provider'] ?? '')?>">
        <div class="small">Should be the same as for PayIn.</div>
      </div>

      <div style="margin:8px 0;">
        <label>Payment Code:</label>
        <input type="text" name="payment_code" value="<?=h($prefill['payment_code'] ?? '')?>">
        <div class="small">Withdrawal code: 999</div>
      </div>

      <div style="margin-top:12px;">
        <button class="btn" type="submit">Send PayOut</button>
      </div>
    </form>
  </div>

  <?php if (!empty($debug)): ?>
  <div class="panel">
    <div class="h">🟢 Request sent</div>
    <div><span class="kv">Endpoint:</span> <?=h($debug['endpoint'] ?? '')?></div>
    <div><span class="kv">Client key:</span> <?=h($debug['client_key'] ?? '')?></div>
    <div>
      <span class="kv">HTTP:</span> <?=h($debug['http_code'] ?? '')?>
      <span class="kv" style="margin-left:12px;">Duration:</span> <?=h($debug['duration_sec'] ?? '')?>s
    </div>

    <?php if (!empty($debug['curl_error'])): ?>
      <div class="error">cURL: <?=h($debug['curl_error'])?></div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="h">🧮 Hash</div>
    <div class="kv">md5( strtoupper( strrev( order_id . amount . currency ) ) . SECRET )</div>
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

      <?php if (!empty($resp['json']['trans_id'])): ?>
        <div class="h" style="margin-top:16px;">🕒 Check transaction status</div>
        <a class="btn" target="_blank" href="<?=h($STATUS_HELPER_URL)?>?trans_id=<?=h($resp['json']['trans_id'])?>">
          Open status helper
        </a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
<?php
}