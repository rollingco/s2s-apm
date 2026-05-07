<?php
/**
 * S2S CREDIT2VIRTUAL — Halopesa payout
 *
 * - endpoint: https://api.leogcltd.com/post
 * - brand: leogc-bannf-dbm
 * - signature: md5( strtoupper( strrev( order_id . amount . currency ) ) . SECRET )
 * - amount нормалізуємо до 2 знаків: 10 -> 10.00
 * - phone відправляємо як parameters[msisdn]
 * - Banffy params відправляємо через parameters[.......]
 */

header('Content-Type: text/html; charset=utf-8');

/* ===================== CONFIG ===================== */

$PAYMENT_URL = 'https://api.leogcltd.com/post';

$CLIENT_KEY = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET     = '554999c284e9f29cf95f090d9a8f3171';

$CHANNEL_ID = '';

$STATUS_HELPER_URL = 'status_credit2virtual.php';

/* ===================== DEFAULTS ===================== */

$DEFAULTS = [
  'order_id'         => isset($_GET['order_id']) ? (string)$_GET['order_id'] : ('halopesa-' . time()),
  'amount'           => isset($_GET['amount']) ? (string)$_GET['amount'] : '10.00',
  'currency'         => isset($_GET['currency']) ? (string)$_GET['currency'] : 'TZS',
  'brand'            => isset($_GET['brand']) ? (string)$_GET['brand'] : 'leogc-bannf-dbm',
  'desc'             => isset($_GET['desc']) ? (string)$_GET['desc'] : 'Halopesa payout test',

  'phone'            => isset($_GET['phone']) ? (string)$_GET['phone'] : '255623456789',

  'country_code'     => isset($_GET['country_code']) ? (string)$_GET['country_code'] : 'TZ',
  'country_name'     => isset($_GET['country_name']) ? (string)$_GET['country_name'] : 'Tanzania',

  'payment_code'     => isset($_GET['payment_code']) ? (string)$_GET['payment_code'] : '999',
  'payment_provider' => isset($_GET['payment_provider']) ? (string)$_GET['payment_provider'] : 'Halopesa',
  'provider'         => isset($_GET['provider']) ? (string)$_GET['provider'] : 'Halopesa',

  'email'            => isset($_GET['email']) ? (string)$_GET['email'] : '',
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

  return h(json_encode(
    $v,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
  ));
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

/**
 * Нормалізація суми до формату XX.XX
 */
function normalize_amount_2dec(string $raw): string {
  $s = preg_replace('/[^0-9.]/', '', $raw);

  if ($s === '') {
    return '';
  }

  if (substr_count($s, '.') > 1) {
    $parts = explode('.', $s);
    $s = array_shift($parts) . '.' . implode('', $parts);
  }

  if (strpos($s, '.') === false) {
    return $s . '.00';
  }

  list($int, $dec) = array_pad(explode('.', $s, 2), 2, '');

  if ($dec === '') {
    return $int . '.00';
  }

  if (strlen($dec) === 1) {
    return $int . '.' . $dec . '0';
  }

  return $int . '.' . substr($dec, 0, 2);
}

/* ===================== Read form ===================== */

$method    = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$submitted = ($method === 'POST');

if ($submitted) {
  $order_id_in      = trim((string)($_POST['order_id'] ?? ''));
  $amount_in        = (string)($_POST['amount'] ?? '');
  $currency         = strtoupper(trim((string)($_POST['currency'] ?? '')));
  $brand            = trim((string)($_POST['brand'] ?? ''));
  $desc             = trim((string)($_POST['desc'] ?? ''));

  $phone            = trim((string)($_POST['phone'] ?? ''));

  $country_code     = strtoupper(trim((string)($_POST['country_code'] ?? '')));
  $country_name     = trim((string)($_POST['country_name'] ?? ''));

  $payment_code     = trim((string)($_POST['payment_code'] ?? ''));
  $payment_provider = trim((string)($_POST['payment_provider'] ?? ''));
  $provider         = trim((string)($_POST['provider'] ?? ''));

  $email            = trim((string)($_POST['email'] ?? ''));

  $amount = trim($amount_in) === '' ? '' : normalize_amount_2dec($amount_in);

  $errors = [];

  if ($order_id_in === '') {
    $errors[] = 'order_id is required.';
  }

  if ($amount === '' || !preg_match('/^\d+\.\d{2}$/', $amount)) {
    $errors[] = 'Amount wrong format. Use e.g. 10.00, 100.00';
  }

  if ($currency === '') {
    $errors[] = 'Currency is required.';
  }

  if ($phone === '') {
    $errors[] = 'Phone / MSISDN is required.';
  }

  if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email format looks wrong.';
  }

  if ($brand === '') {
    $brand = 'leogc-bannf-dbm';
  }

  if ($desc === '') {
    $desc = 'Halopesa payout test';
  }

  if ($country_code === '') {
    $country_code = 'TZ';
  }

  if ($country_name === '') {
    $country_name = 'Tanzania';
  }

  if ($payment_code === '') {
    $payment_code = '999';
  }

  if ($payment_provider === '') {
    $payment_provider = 'Halopesa';
  }

  if ($provider === '') {
    $provider = $payment_provider;
  }

  if ($errors) {
    render_page([
      'errors' => $errors,
      'prefill' => [
        'order_id'         => $order_id_in,
        'amount'           => $amount_in,
        'currency'         => $currency ?: $DEFAULTS['currency'],
        'brand'            => $brand,
        'desc'             => $desc,
        'phone'            => $phone,
        'country_code'     => $country_code,
        'country_name'     => $country_name,
        'payment_code'     => $payment_code,
        'payment_provider' => $payment_provider,
        'provider'         => $provider,
        'email'            => $email,
      ],
      'debug' => [],
      'response' => [],
    ]);

    exit;
  }
} else {
  $order_id_in      = $DEFAULTS['order_id'];
  $amount           = normalize_amount_2dec($DEFAULTS['amount']);
  $amount_in        = $amount;
  $currency         = strtoupper($DEFAULTS['currency']);
  $brand            = $DEFAULTS['brand'];
  $desc             = $DEFAULTS['desc'];

  $phone            = $DEFAULTS['phone'];

  $country_code     = strtoupper($DEFAULTS['country_code']);
  $country_name     = $DEFAULTS['country_name'];

  $payment_code     = $DEFAULTS['payment_code'];
  $payment_provider = $DEFAULTS['payment_provider'];
  $provider         = $DEFAULTS['provider'];

  $email            = $DEFAULTS['email'];
}

/* ===================== Send CREDIT2VIRTUAL ===================== */

$debug = [];

$responseBlocks = [
  'bodyRaw' => '',
  'json'    => null,
];

if ($submitted) {
  $hash_src_dbg = '';

  $hash = build_credit2virtual_hash(
    $order_id_in,
    $amount,
    $currency,
    $SECRET,
    $hash_src_dbg
  );

  $form = [
    'action'            => 'CREDIT2VIRTUAL',
    'client_key'        => $CLIENT_KEY,

    'order_id'          => $order_id_in,
    'order_amount'      => $amount,
    'order_currency'    => $currency,
    'order_description' => $desc,

    'brand'             => $brand,

    /*
     * Основний робочий формат, як у робочому payout-коді:
     * все додаткове — через parameters[...]
     */
    'parameters[msisdn]'          => $phone,
    //'parameters[countryCode]'     => $country_code,
    //'parameters[countryName]'     => $country_name,
    //'parameters[paymentCode]'     => $payment_code,
    //'parameters[paymentProvider]' => $payment_provider,
    //'parameters[Provider]'        => $provider,
  ];

  if ($CHANNEL_ID !== '') {
    $form['channel_id'] = $CHANNEL_ID;
  }

  if ($email !== '') {
    $form['parameters[email]'] = $email;
  }

  $form['hash'] = $hash;

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

  $raw = curl_exec($ch);
  $info = curl_getinfo($ch);
  $err = curl_errno($ch) ? curl_error($ch) : '';

  curl_close($ch);

  $debug['duration_sec'] = number_format(microtime(true) - $start, 3, '.', '');
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
    'order_id'         => $order_id_in,
    'amount'           => isset($amount_in) ? $amount_in : $amount,
    'currency'         => $currency,
    'brand'            => $brand,
    'desc'             => $desc,
    'phone'            => $phone,
    'country_code'     => $country_code,
    'country_name'     => $country_name,
    'payment_code'     => $payment_code,
    'payment_provider' => $payment_provider,
    'provider'         => $provider,
    'email'            => $email,
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
<title>CREDIT2VIRTUAL — Halopesa payout</title>
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
input[type=text]{padding:8px 10px;border-radius:8px;border:1px solid #2a2f3a;background:#11131a;color:#e6e6e6;width:340px}
label{display:inline-block;min-width:190px}
.small{font-size:12px;color:var(--muted)}
</style>
</head>
<body>
<div class="wrap">

  <div class="panel">
    <div class="h">💸 CREDIT2VIRTUAL — Halopesa payout</div>

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
        <div class="small">Amount will be normalized to 2 decimals, e.g. 10 → 10.00</div>
      </div>

      <div style="margin:8px 0;">
        <label>currency:</label>
        <input type="text" name="currency" value="<?=h($prefill['currency'] ?? '')?>">
      </div>

      <div style="margin:8px 0;">
        <label>brand:</label>
        <input type="text" name="brand" value="<?=h($prefill['brand'] ?? '')?>">
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
        <label>countryCode:</label>
        <input type="text" name="country_code" value="<?=h($prefill['country_code'] ?? '')?>">
      </div>

      <div style="margin:8px 0;">
        <label>countryName:</label>
        <input type="text" name="country_name" value="<?=h($prefill['country_name'] ?? '')?>">
      </div>

      <div style="margin:8px 0;">
        <label>paymentCode:</label>
        <input type="text" name="payment_code" value="<?=h($prefill['payment_code'] ?? '')?>">
      </div>

      <div style="margin:8px 0;">
        <label>paymentProvider:</label>
        <input type="text" name="payment_provider" value="<?=h($prefill['payment_provider'] ?? '')?>">
      </div>

      <div style="margin:8px 0;">
        <label>Provider:</label>
        <input type="text" name="provider" value="<?=h($prefill['provider'] ?? '')?>">
      </div>

      <div style="margin:8px 0;">
        <label>email:</label>
        <input type="text" name="email" value="<?=h($prefill['email'] ?? '')?>">
      </div>

      <div style="margin-top:12px;">
        <button class="btn" type="submit">Send Halopesa payout</button>
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