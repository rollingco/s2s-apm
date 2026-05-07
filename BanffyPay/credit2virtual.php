<?php
/**
 * CREDIT2VIRTUAL — BanffyPay PayOut / Halopesa
 */

header('Content-Type: text/html; charset=utf-8');

$PAYMENT_URL = 'https://api.leogcltd.com/post';

$CLIENT_KEY = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET     = '554999c284e9f29cf95f090d9a8f3171';

$DEFAULTS = [
  'order_id'         => $_GET['order_id'] ?? ('halopesa-payout-' . time()),
  'amount'           => $_GET['amount'] ?? '10.00',
  'currency'         => $_GET['currency'] ?? 'TZS',
  'brand'            => $_GET['brand'] ?? 'leogc-bannf-dbm',
  'desc'             => $_GET['desc'] ?? 'Halopesa payout test',

  'phone'            => $_GET['phone'] ?? '255623456789',

  'country_code'     => $_GET['country_code'] ?? 'TZ',
  'country_name'     => $_GET['country_name'] ?? 'Tanzania',

  'payment_code'     => $_GET['payment_code'] ?? '999',
  'payment_provider' => $_GET['payment_provider'] ?? 'Halopesa',
  'provider'         => $_GET['provider'] ?? 'Halopesa',
];

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
  $src = strtoupper(strrev($order_id . $amount . $currency)) . $secret;

  if ($srcOut !== null) {
    $srcOut = $src;
  }

  return md5($src);
}

$submitted = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

if ($submitted) {
  $order_id_in      = trim((string)($_POST['order_id'] ?? ''));
  $amount           = trim((string)($_POST['amount'] ?? ''));
  $currency         = strtoupper(trim((string)($_POST['currency'] ?? '')));
  $brand            = trim((string)($_POST['brand'] ?? ''));
  $desc             = trim((string)($_POST['desc'] ?? ''));
  $phone            = trim((string)($_POST['phone'] ?? ''));

  $country_code     = strtoupper(trim((string)($_POST['country_code'] ?? '')));
  $country_name     = trim((string)($_POST['country_name'] ?? ''));

  $payment_code     = trim((string)($_POST['payment_code'] ?? ''));
  $payment_provider = trim((string)($_POST['payment_provider'] ?? ''));
  $provider         = trim((string)($_POST['provider'] ?? ''));

  $errors = [];

  if ($order_id_in === '') $errors[] = 'order_id is required.';
  if ($amount === '' || !preg_match('/^\d+(\.\d+)?$/', $amount)) $errors[] = 'order_amount format is wrong.';
  if ($currency === '') $errors[] = 'order_currency is required.';
  if ($brand === '') $brand = 'leogc-bannf-dbm';
  if ($desc === '') $desc = 'Halopesa payout test';
  if ($phone === '') $errors[] = 'payee_phone / msisdn is required.';

  if ($country_code === '') $country_code = 'TZ';
  if ($country_name === '') $country_name = 'Tanzania';

  if ($payment_code === '') $payment_code = '999';
  if ($payment_provider === '') $payment_provider = 'Halopesa';
  if ($provider === '') $provider = $payment_provider;

  if ($errors) {
    render_page([
      'errors' => $errors,
      'prefill' => compact(
        'order_id_in',
        'amount',
        'currency',
        'brand',
        'desc',
        'phone',
        'country_code',
        'country_name',
        'payment_code',
        'payment_provider',
        'provider'
      ),
      'debug' => [],
      'response' => [],
    ]);
    exit;
  }
} else {
  $order_id_in      = $DEFAULTS['order_id'];
  $amount           = $DEFAULTS['amount'];
  $currency         = $DEFAULTS['currency'];
  $brand            = $DEFAULTS['brand'];
  $desc             = $DEFAULTS['desc'];
  $phone            = $DEFAULTS['phone'];
  $country_code     = $DEFAULTS['country_code'];
  $country_name     = $DEFAULTS['country_name'];
  $payment_code     = $DEFAULTS['payment_code'];
  $payment_provider = $DEFAULTS['payment_provider'];
  $provider         = $DEFAULTS['provider'];
}

$debug = [];
$responseBlocks = [
  'bodyRaw' => '',
  'json'    => null,
];

if ($submitted) {
  $hash_src_dbg = '';
  $hash = build_credit2virtual_hash($order_id_in, $amount, $currency, $SECRET, $hash_src_dbg);

  /*
   * Built strictly from CREDIT2VIRTUAL docs:
   * action, client_key, brand, order_id, order_amount,
   * order_currency, order_description, parameters, hash.
   *
   * payee_phone/payee_country are optional documented fields.
   */
  $form = [
    'action'            => 'CREDIT2VIRTUAL',
    'client_key'        => $CLIENT_KEY,
    'brand'             => $brand,

    'order_id'          => $order_id_in,
    'order_amount'      => $amount,
    'order_currency'    => $currency,
    'order_description' => $desc,

    // Required parameters array for brand-specific data
    'parameters[msisdn]'          => $phone,
    'parameters[countryCode]'     => $country_code,
    'parameters[countryName]'     => $country_name,
    'parameters[paymentCode]'     => $payment_code,
    'parameters[paymentProvider]' => $payment_provider,
    'parameters[Provider]'        => $provider,

    // extraData from SALE-style Banffy flow, but still inside parameters
    'parameters[extraData][msisdn]'          => $phone,
    'parameters[extraData][countryCode]'     => $country_code,
    'parameters[extraData][countryName]'     => $country_name,
    'parameters[extraData][paymentCode]'     => $payment_code,
    'parameters[extraData][paymentProvider]' => $payment_provider,
    'parameters[extraData][Provider]'        => $provider,

    // Optional CREDIT2VIRTUAL documented payee fields
    'payee_phone'   => $phone,
    'payee_country' => $country_code,

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

render_page([
  'errors' => [],
  'prefill' => [
    'order_id_in'      => $order_id_in,
    'amount'           => $amount,
    'currency'         => $currency,
    'brand'            => $brand,
    'desc'             => $desc,
    'phone'            => $phone,
    'country_code'     => $country_code,
    'country_name'     => $country_name,
    'payment_code'     => $payment_code,
    'payment_provider' => $payment_provider,
    'provider'         => $provider,
  ],
  'debug'    => $debug,
  'response' => $responseBlocks,
]);

function render_page($ctx) {
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
<title>Halopesa CREDIT2VIRTUAL PayOut</title>
<style>
body{background:#0f1115;color:#e6e6e6;margin:0;font:14px/1.45 Consolas,monospace}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.panel{background:#171923;border:1px solid #2a2f3a;border-radius:12px;padding:14px 16px;margin:14px 0}
.h{font-weight:700;margin:10px 0 8px}
.kv{color:#9aa4af}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
input{padding:8px 10px;border-radius:8px;border:1px solid #2a2f3a;background:#11131a;color:#e6e6e6;width:340px}
label{display:inline-block;min-width:190px}
.btn{padding:10px 14px;border-radius:10px;background:#2b7cff;color:#fff;border:0;cursor:pointer}
.error{color:#ff6b6b}
.small{font-size:12px;color:#9aa4af}
</style>
</head>
<body>
<div class="wrap">

<div class="panel">
  <div class="h">Halopesa PayOut — CREDIT2VIRTUAL</div>

  <form method="post" action="<?=h($self)?>">
    <?php foreach ($errors as $e): ?>
      <div class="error">❌ <?=h($e)?></div>
    <?php endforeach; ?>

    <p><label>order_id:</label><input name="order_id" value="<?=h($prefill['order_id_in'] ?? '')?>"></p>
    <p><label>order_amount:</label><input name="amount" value="<?=h($prefill['amount'] ?? '')?>"></p>
    <p><label>order_currency:</label><input name="currency" value="<?=h($prefill['currency'] ?? '')?>"></p>
    <p><label>brand:</label><input name="brand" value="<?=h($prefill['brand'] ?? '')?>"></p>
    <p><label>order_description:</label><input name="desc" value="<?=h($prefill['desc'] ?? '')?>"></p>

    <p><label>phone / msisdn:</label><input name="phone" value="<?=h($prefill['phone'] ?? '')?>"></p>
    <p><label>countryCode:</label><input name="country_code" value="<?=h($prefill['country_code'] ?? '')?>"></p>
    <p><label>countryName:</label><input name="country_name" value="<?=h($prefill['country_name'] ?? '')?>"></p>
    <p><label>paymentCode:</label><input name="payment_code" value="<?=h($prefill['payment_code'] ?? '')?>"></p>
    <p><label>paymentProvider:</label><input name="payment_provider" value="<?=h($prefill['payment_provider'] ?? '')?>"></p>
    <p><label>Provider:</label><input name="provider" value="<?=h($prefill['provider'] ?? '')?>"></p>

    <button class="btn" type="submit">Send CREDIT2VIRTUAL</button>
  </form>
</div>

<?php if (!empty($debug)): ?>

<div class="panel">
  <div class="h">Request sent</div>
  <div><span class="kv">Endpoint:</span> <?=h($debug['endpoint'] ?? '')?></div>
  <div><span class="kv">HTTP:</span> <?=h($debug['http_code'] ?? '')?></div>
  <div><span class="kv">Duration:</span> <?=h($debug['duration_sec'] ?? '')?>s</div>
  <?php if (!empty($debug['curl_error'])): ?>
    <div class="error">cURL: <?=h($debug['curl_error'])?></div>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="h">Hash</div>
  <div class="kv">Source:</div>
  <pre><?=h($debug['hash_src'] ?? '')?></pre>
  <div class="kv">Hash:</div>
  <pre><?=h($debug['hash'] ?? '')?></pre>
</div>

<div class="panel">
  <div class="h">Sent form-data</div>
  <pre><?=pretty($debug['form'] ?? [])?></pre>
</div>

<div class="panel">
  <div class="h">Response body</div>
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