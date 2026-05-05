<?php
/**
 * S2S APM SALE — multi-country example with paymentProvider in extraData ----
 */

header('Content-Type: text/html; charset=utf-8');

$PAYMENT_URL = 'https://api.leogcltd.com/post-va';

$CLIENT_KEY = 'd67e5242-47cc-11f1-bcc3-8e68cdc8846e';
$SECRET     = '2c2b0c17afdf0a4bcb8eb6eb2a54e541';

$COUNTRIES = [
  'TZ' => [
    'country'      => 'Tanzania',
    'countryCode'  => 'TZ',
    'currency'     => 'TZS',
    'payer_country'=> 'TZ',
    'providers'    => [
      'Airtel'  => '255683456789',
      'Vodacom' => '255763456789',
      'Tigo'    => '255713456789',
      'Halotel' => '255623456789',
    ],
  ],
];

$DEFAULTS = [
  'brand'             => 'leogc-bannf',
  'identifier'        => '111',
  'return_url'        => 'https://google.com',
  'countryCode'       => 'TZ',
  'provider'          => 'Airtel',
  'payment_code'      => '401',
  'amount'            => '200.00',
  'email'             => 'customer@example.com',
  'payer_first_name'  => 'John',
  'payer_last_name'   => 'Doe',
];

function h($s){
  return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pretty($v){
  if (is_string($v)) {
    $d = json_decode($v, true);
    if (json_last_error() === JSON_ERROR_NONE) $v = $d;
    else return h($v);
  }
  return h(json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function build_sale_hash($identifier, $order_id, $amount, $currency, $secret, &$srcOut = null){
  $src = $identifier . $order_id . $amount . $currency . $secret;
  if ($srcOut !== null) $srcOut = $src;
  return md5(strtoupper(strrev($src)));
}

$submitted = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

$selectedCountryCode = $DEFAULTS['countryCode'];
$selectedCountry     = $COUNTRIES[$selectedCountryCode];
$provider            = $DEFAULTS['provider'];
$payer_phone         = $selectedCountry['providers'][$provider];
$order_amt           = $DEFAULTS['amount'];
$payment_code        = $DEFAULTS['payment_code'];

$debug = [];
$responseBlocks = ['bodyRaw' => '', 'json' => null];
$errors = [];

if ($submitted) {
  $selectedCountryCode = $_POST['countryCode'] ?? $DEFAULTS['countryCode'];

  if (!isset($COUNTRIES[$selectedCountryCode])) {
    $errors[] = 'Invalid country.';
    $selectedCountryCode = $DEFAULTS['countryCode'];
  }

  $selectedCountry = $COUNTRIES[$selectedCountryCode];

  $provider = $_POST['provider'] ?? $DEFAULTS['provider'];

  if (!isset($selectedCountry['providers'][$provider])) {
    $errors[] = 'Invalid provider for selected country.';
    $provider = array_key_first($selectedCountry['providers']);
  }

  $payer_phone = preg_replace('/\s+/', '', $_POST['phone'] ?? '');
  $payer_phone = ltrim($payer_phone, '+');

  $rawAmt = preg_replace('/[^0-9.]/', '', $_POST['amount'] ?? '');
  $order_amt = number_format((float)$rawAmt, 2, '.', '');

  $payment_code = trim($_POST['payment_code'] ?? '');

  if ($payer_phone === '') {
    $errors[] = 'Phone / MSISDN is required.';
  }

  if (!is_numeric($order_amt) || (float)$order_amt <= 0) {
    $errors[] = 'Amount must be a positive number.';
  }

  if (!$errors) {
    $order_id   = $selectedCountryCode . '_ORDER_' . time();
    $order_desc = ' ' . $selectedCountry['country'] . ' APM payment';
    $payer_ip   = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $hash_src_dbg = '';
    $hash = build_sale_hash(
      $DEFAULTS['identifier'],
      $order_id,
      $order_amt,
      $selectedCountry['currency'],
      $SECRET,
      $hash_src_dbg
    );

  $form = [
    'action'            => 'SALE',
    'client_key'        => $CLIENT_KEY,
    'brand'             => $DEFAULTS['brand'],

    'order_id'          => $order_id,
    'order_amount'      => $order_amt,
    'order_currency'    => $selectedCountry['currency'],
    'order_description' => $order_desc,

    'identifier'        => $DEFAULTS['identifier'],
    'payer_ip'          => $payer_ip,
    'return_url'        => $DEFAULTS['return_url'],

    'country'           => $selectedCountry['country'],
    'countryCode'       => $selectedCountry['countryCode'],

    'payment_code'      => $payment_code,

    'payer_phone'       => $payer_phone,
    'payer_country'     => $selectedCountry['payer_country'],
    'payer_email'       => $DEFAULTS['email'],
    'payer_first_name'  => $DEFAULTS['payer_first_name'],
    'payer_last_name'   => $DEFAULTS['payer_last_name'],

    // ✅ Ось тут вся магія
    'extraData' => json_encode([
        'firstName'       => $DEFAULTS['payer_first_name'],
        'lastName'        => $DEFAULTS['payer_last_name'],
        'name'            => $DEFAULTS['payer_first_name'] . ' ' . $DEFAULTS['payer_last_name'],
        'email'           => $DEFAULTS['email'],
        'paymentProvider' => $provider // Airtel / Vodacom / Tigo / Halotel
    ]),

    'hash' => $hash,
];

    $debug = [
      'endpoint' => $PAYMENT_URL,
      'form'     => $form,
      'hash_src' => $hash_src_dbg,
      'hash'     => $hash,
    ];

    $ch = curl_init($PAYMENT_URL);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => $form,
      CURLOPT_TIMEOUT        => 60,
    ]);

    $raw = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_errno($ch) ? curl_error($ch) : '';
    curl_close($ch);

    $debug['http_code'] = (int)($info['http_code'] ?? 0);
    if ($err) $debug['curl_error'] = $err;

    $responseBlocks['bodyRaw'] = (string)$raw;
    $json = json_decode($responseBlocks['bodyRaw'], true);
    if (json_last_error() === JSON_ERROR_NONE) {
      $responseBlocks['json'] = $json;
    }
  }
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>BanffyPay SALE</title>
<style>
body{background:#0f1115;color:#e6e6e6;font:14px/1.45 monospace;margin:0}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.panel{background:#171923;border:1px solid #2a2f3a;border-radius:12px;padding:14px 16px;margin:14px 0}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
input,select{padding:8px 10px;border-radius:8px;background:#11131a;color:#e6e6e6;border:1px solid #2a2f3a}
label{display:inline-block;min-width:140px}
button{padding:10px 14px;border-radius:10px;background:#2b7cff;color:#fff;border:0}
.error{color:#ff8080}
</style>
</head>
<body>
<div class="wrap">

<div class="panel">
  <h3>Create SALE — BanffyPay</h3>

  <?php if (!empty($errors)): ?>
    <div class="error">
      <pre><?=pretty($errors)?></pre>
    </div>
  <?php endif; ?>

  <form method="post">
    <div>
      <label>Country:</label>
      <select name="countryCode" id="countryCode">
        <?php foreach ($COUNTRIES as $code => $c): ?>
          <option value="<?=h($code)?>" <?=($selectedCountryCode === $code ? 'selected' : '')?>>
            <?=h($c['country'])?> / <?=h($code)?> / <?=h($c['currency'])?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <br>

    <div>
      <label>Provider:</label>
      <select name="provider" id="provider"></select>
    </div>

    <br>

    <div>
      <label>Phone / MSISDN:</label>
      <input type="text" name="phone" id="phone" value="<?=h($payer_phone)?>">
    </div>

    <br>

    <div>
      <label>Amount:</label>
      <input type="text" name="amount" value="<?=h($order_amt)?>">
    </div>

    <br>

    <div>
      <label>Payment Code:</label>
      <input type="text" name="payment_code" value="<?=h($payment_code)?>">
    </div>

    <br>

    <button type="submit">Send SALE</button>
  </form>
</div>

<?php if (!empty($debug)): ?>
<div class="panel">
  <h3>Sent form-data</h3>
  <pre><?=pretty($debug['form'])?></pre>
</div>

<div class="panel">
  <h3>Hash</h3>
  <pre><?=h($debug['hash_src'])?></pre>
  <pre><?=h($debug['hash'])?></pre>
</div>

<div class="panel">
  <h3>Response</h3>
  <pre><?=pretty($responseBlocks['bodyRaw'])?></pre>
</div>
<?php endif; ?>

</div>

<script>
const countries = <?=json_encode($COUNTRIES, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
const selectedProvider = <?=json_encode($provider)?>;

function refreshProviders() {
  const countryCode = document.getElementById('countryCode').value;
  const providerSelect = document.getElementById('provider');
  const phoneInput = document.getElementById('phone');

  providerSelect.innerHTML = '';

  const providers = countries[countryCode].providers;
  Object.keys(providers).forEach(function(provider) {
    const option = document.createElement('option');
    option.value = provider;
    option.textContent = provider;

    if (provider === selectedProvider) {
      option.selected = true;
    }

    providerSelect.appendChild(option);
  });

  if (!providers[providerSelect.value]) {
    providerSelect.selectedIndex = 0;
  }

  phoneInput.value = providers[providerSelect.value];
}

document.getElementById('countryCode').addEventListener('change', refreshProviders);

document.getElementById('provider').addEventListener('change', function() {
  const countryCode = document.getElementById('countryCode').value;
  document.getElementById('phone').value = countries[countryCode].providers[this.value];
});

refreshProviders();
</script>

</body>
</html>