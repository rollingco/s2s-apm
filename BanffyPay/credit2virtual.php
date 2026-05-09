<?php
/**********
 * S2S CREDIT2VIRTUAL — BanffyPay PayOut
 *
 * Base request follows Akurateco CREDIT2VIRTUAL documentation.
 * Additional BanffyPay routing fields are added from SALE connector:
 * - country
 * - countryCode
 * - channel_id (provider/channel)
 * - payment_code = 999 for withdrawal
 * - parameters[provider]
 *
 * Endpoint for CREDIT2VIRTUAL: https://api.leogcltd.com/post
 *
 * IMPORTANT: order_amount is sent exactly as entered in the form.
 * No float/int conversion and no number_format.
 */

header('Content-Type: text/html; charset=utf-8');

/* ===================== CONFIG ===================== */
$PAYMENT_URL = 'https://api.leogcltd.com/post';

//$CLIENT_KEY = '5f306e12-0ff2-11f1-bac9-0a9a38974658';
//$SECRET     = '976d5c5d5eacbab78288b12bb15178ba';
$CLIENT_KEY = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET     = '554999c284e9f29cf95f090d9a8f3171';

$BRAND = 'leogc-bannf-dbm';
$WITHDRAWAL_PAYMENT_CODE = '999';

$STATUS_HELPER_URL = 'status_credit2virtual.php';

$COUNTRIES = [
  'BJ' => [
    'country' => 'Benin',
    'countryCode' => 'BJ',
    'currency' => 'XOF',
    'payee_country' => 'BJ',
    'providers' => [
      'Mtn' => '22951345789',
      'Moov' => '22995345789',
    ],
  ],
  'Togo' => [
    'country' => 'Togo',
    'countryCode' => 'Togo',
    'currency' => 'XOF',
    'payee_country' => 'Togo',
    'providers' => [
      'Togocel' => '',
      'Moov' => '',
    ],
  ],
  'SN' => [
    'country' => 'Senegal',
    'countryCode' => 'SN',
    'currency' => 'XOF',
    'payee_country' => 'SN',
    'providers' => [
      'orange-senegal' => '221773456789',
      'wave-senegal' => '',
    ],
  ],
  'CM' => [
    'country' => 'Cameroon',
    'countryCode' => 'CM',
    'currency' => 'XAF',
    'payee_country' => 'CM',
    'providers' => [
      'mtn-momo-cameroon' => '237653456789',
    ],
  ],
  'KE' => [
    'country' => 'Kenya',
    'countryCode' => 'KE',
    'currency' => 'KES',
    'payee_country' => 'KE',
    'providers' => [
      'airtel-kenya' => '',
      'equitel-kenya' => '',
      'safaricom-kenya' => '254703456789',
      't-kash-kenya' => '',
      'telkom-kenya' => '',
    ],
  ],
  'CI' => [
    'country' => 'Ivory Coast',
    'countryCode' => 'CI',
    'currency' => 'XOF',
    'payee_country' => 'CI',
    'providers' => [
      'moov-ic' => '',
      'orange-ic' => '2250734567890',
      'wave-ic' => '',
    ],
  ],
  'ML' => [
    'country' => 'Mali',
    'countryCode' => 'ML',
    'currency' => 'XOF',
    'payee_country' => 'ML',
    'providers' => [
      'orange-mali' => '',
      'moov-mali' => '',
    ],
  ],
  'BF' => [
    'country' => 'Burkina Faso',
    'countryCode' => 'BF',
    'currency' => 'XOF',
    'payee_country' => 'BF',
    'providers' => [
      'Moov' => '22602345678',
      'Orange' => '22607345678',
    ],
  ],
  'GH' => [
    'country' => 'Ghana',
    'countryCode' => 'GH',
    'currency' => 'GHS',
    'payee_country' => 'GH',
    'providers' => [
      'Mtn' => '233593456789',
      'Zeepay' => '',
      'Vodafone' => '233503456789',
      'Airtel-Tigo' => '233273456789',
    ],
  ],
  'ZM' => [
    'country' => 'Zambia',
    'countryCode' => 'ZM',
    'currency' => 'ZMW',
    'payee_country' => 'ZM',
    'providers' => [
      'Zeepay' => '',
    ],
  ],
  'NG' => [
    'country' => 'Nigeria',
    'countryCode' => 'NG',
    'currency' => 'NGN',
    'payee_country' => 'NG',
    'providers' => [
      'Originating Bank name' => '',
      'Access Bank' => '',
      'Zenith Bank' => '',
      'GTBank' => '',
      'First Bank' => '',
      'UBA' => '',
      'Opay' => '',
    ],
  ],
  'TZ' => [
    'country' => 'Tanzania',
    'countryCode' => 'TZ',
    'currency' => 'TZS',
    'payee_country' => 'TZ',
    'providers' => [
      'Vodacom' => '255763456789',
      'Airtel' => '255683456789',
      'Tigo' => '255713456789',
      'Halopesa' => '255623456789',
      'Azampesa' => '',
      'Mpesa' => '',
    ],
  ],
  'RW' => [
    'country' => 'Rwanda',
    'countryCode' => 'RW',
    'currency' => 'RWF',
    'payee_country' => 'RW',
    'providers' => [
      'Airtel' => '250733456789',
      'Tigo' => '',
      'Halopesa' => '',
      'Azampesa' => '',
      'Mpesa' => '',
    ],
  ],
  'SL' => [
    'country' => 'Sierra Leone',
    'countryCode' => 'SL',
    'currency' => 'SLE',
    'payee_country' => 'SL',
    'providers' => [
      'All Networks' => '',
    ],
  ],
  'LR' => [
    'country' => 'Liberia',
    'countryCode' => 'LR',
    'currency' => 'LRD',
    'payee_country' => 'LR',
    'providers' => [
      'MtnMomo' => '',
      'Mtn USD' => '',
      'OrangeMoney' => '',
      'Orange USD' => '',
    ],
  ],
  'CF' => [
    'country' => 'DRC',
    'countryCode' => 'CF',
    'currency' => 'CAF',
    'payee_country' => 'CF',
    'providers' => [
      'Vodacom MPesa' => '243813456789',
      'Africell' => '',
      'Airtel' => '243973456789',
      'Orange' => '243893456789',
    ],
  ],
  'CA' => [
    'country' => 'Canada',
    'countryCode' => 'CA',
    'currency' => 'CAD',
    'payee_country' => 'CA',
    'providers' => [
      'For 901' => '',
    ],
  ],
];

$DEFAULTS = [
  'countryCode'       => 'TZ',
  'provider'          => 'Airtel',
  'amount'            => '1000.00',
  'description'       => 'BanffyPay payout test',
  'payee_first_name'  => 'John',
  'payee_last_name'   => 'Doe',
  'payee_email'       => 'customer@example.com',
];

/* ===================== Helpers ===================== */
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

/**
 * CREDIT2VIRTUAL hash from documentation:
 * md5( strtoupper( strrev( order_id . amount . currency ) ) . SECRET )
 */
function build_credit2virtual_hash($order_id, $amount, $currency, $secret, &$srcOut = null){
  $inner = $order_id . $amount . $currency;
  $src = strtoupper(strrev($inner)) . $secret;
  if ($srcOut !== null) $srcOut = $src;
  return md5($src);
}


function current_url(){
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
}

/* ===================== Initial state ===================== */
$submitted = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

$selectedCountryCode = $_GET['countryCode'] ?? $DEFAULTS['countryCode'];
if (!isset($COUNTRIES[$selectedCountryCode])) $selectedCountryCode = $DEFAULTS['countryCode'];

$selectedCountry = $COUNTRIES[$selectedCountryCode];
$provider = $_GET['provider'] ?? $DEFAULTS['provider'];
if (!isset($selectedCountry['providers'][$provider])) $provider = array_key_first($selectedCountry['providers']);

$phone = $selectedCountry['providers'][$provider] ?? '';
$amount = $DEFAULTS['amount'];
$description = $DEFAULTS['description'];
$payee_first_name = $DEFAULTS['payee_first_name'];
$payee_last_name = $DEFAULTS['payee_last_name'];
$payee_email = $DEFAULTS['payee_email'];

$debug = [];
$responseBlocks = ['bodyRaw' => '', 'json' => null];
$errors = [];

/* ===================== Submit ===================== */
if ($submitted) {
  $selectedCountryCode = $_POST['countryCode'] ?? $DEFAULTS['countryCode'];
  if (!isset($COUNTRIES[$selectedCountryCode])) {
    $errors[] = 'Invalid country.';
    $selectedCountryCode = $DEFAULTS['countryCode'];
  }

  $selectedCountry = $COUNTRIES[$selectedCountryCode];

  $provider = $_POST['provider'] ?? '';
  if (!isset($selectedCountry['providers'][$provider])) {
    $errors[] = 'Invalid provider/channel for selected country.';
    $provider = array_key_first($selectedCountry['providers']);
  }

  $currency = $selectedCountry['currency'];
  $phone = preg_replace('/\s+/', '', $_POST['phone'] ?? '');
  $phone = ltrim($phone, '+');
  // IMPORTANT: keep amount exactly as entered in the form.
  // Do not cast to float/int and do not format it, otherwise 1000.00 becomes 1000.
  $amount = trim((string)($_POST['amount'] ?? ''));

  $description = trim((string)($_POST['description'] ?? $DEFAULTS['description']));
  $payee_first_name = trim((string)($_POST['payee_first_name'] ?? $DEFAULTS['payee_first_name']));
  $payee_last_name = trim((string)($_POST['payee_last_name'] ?? $DEFAULTS['payee_last_name']));
  $payee_email = trim((string)($_POST['payee_email'] ?? $DEFAULTS['payee_email']));

  if ($phone === '') $errors[] = 'Payee phone / MSISDN is required.';
  if ($amount === '' || !preg_match('/^\d+(\.\d+)?$/', $amount) || (float)$amount <= 0) $errors[] = 'Amount must be a positive number.';
  if ($description === '') $errors[] = 'Description is required.';
  if ($payee_email !== '' && !filter_var($payee_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Payee email format looks wrong.';

  if (!$errors) {
    $order_id = $selectedCountryCode . '_PAYOUT_' . time();
    $hash_src_dbg = '';
    $hash = build_credit2virtual_hash($order_id, $amount, $currency, $SECRET, $hash_src_dbg);

    $form = [
      // Akurateco CREDIT2VIRTUAL documented fields
      'action'            => 'CREDIT2VIRTUAL',
      'client_key'        => $CLIENT_KEY,
      'brand'             => $BRAND,
      'order_id'          => $order_id,
      'order_amount'      => $amount,
      'order_currency'    => $currency,
      'order_description' => $description,

      // BanffyPay routing fields from SALE connector
      'country'           => $selectedCountry['country'],
      'countryCode'       => $selectedCountry['countryCode'],
      'channel_id'        => $provider,
      'payment_code'      => $GLOBALS['WITHDRAWAL_PAYMENT_CODE'],

      // Payee fields from CREDIT2VIRTUAL docs
      'payee_first_name'  => $payee_first_name,
      'payee_last_name'   => $payee_last_name,
      'payee_country'     => $selectedCountry['payee_country'],
      'payee_phone'       => $phone,
      //'transactionType'   => 'sync',
      //'requestType'         => 'sync',
      //'transactionType'     => 'MOBILE_TRANSFER',
      //'requestType'         => 'MOBILE_TRANSFER',
    ];

    if ($payee_email !== '') {
      $form['payee_email'] = $payee_email;
    }

    // Documented parameters array for brand-specific data.
    // Akurateco/connector note: Provider should be the same as for PayIn.
    $form['parameters[provider]'] = $provider;
    $form['parameters[paymentCode]'] = $GLOBALS['WITHDRAWAL_PAYMENT_CODE'];
    $form['parameters[countryCode]'] = $selectedCountry['countryCode'];
    $form['parameters[beneficiaryCountryCode]'] = $selectedCountry['countryCode'];

    // Hash is required and added after all business fields.
    $form['hash'] = $hash;

    $debug = [
      'endpoint' => $PAYMENT_URL,
      'form'     => $form,
      'hash_formula' => 'md5( strtoupper( strrev( order_id . amount . currency ) ) . SECRET )',
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

    $start = microtime(true);
    $raw = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_errno($ch) ? curl_error($ch) : '';
    curl_close($ch);

    $debug['http_code'] = (int)($info['http_code'] ?? 0);
    $debug['duration_sec'] = number_format(microtime(true) - $start, 3, '.', '');
    if ($err) $debug['curl_error'] = $err;

    $responseBlocks['bodyRaw'] = (string)$raw;
    $json = json_decode($responseBlocks['bodyRaw'], true);
    if (json_last_error() === JSON_ERROR_NONE) {
      $responseBlocks['json'] = $json;
    }
  }
}

$self = current_url();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>BanffyPay CREDIT2VIRTUAL PayOut</title>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--err:#ff8080;--blue:#2b7cff}
body{background:var(--bg);color:var(--text);font:14px/1.45 ui-monospace,Menlo,Consolas,monospace;margin:0}
.wrap{padding:22px;max-width:1120px;margin:0 auto}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap;overflow:auto}
input,select{padding:8px 10px;border-radius:8px;background:#11131a;color:var(--text);border:1px solid var(--b);min-width:260px}
label{display:inline-block;min-width:180px;color:var(--muted)}
button,.btn{padding:10px 14px;border-radius:10px;background:var(--blue);color:#fff;border:0;text-decoration:none;display:inline-block;cursor:pointer}
.error{color:var(--err)}
.small{font-size:12px;color:var(--muted);margin-left:184px;margin-top:3px}
.row{margin:9px 0}
.kv{color:var(--muted)}
</style>
</head>
<body>
<div class="wrap">

<div class="panel">
  <h3>Create CREDIT2VIRTUAL — BanffyPay PayOut</h3>

  <?php if (!empty($errors)): ?>
    <div class="error"><pre><?=pretty($errors)?></pre></div>
  <?php endif; ?>

  <form action="<?=h($self)?>" method="post">
    <div class="row">
      <label>Country:</label>
      <select name="countryCode" id="countryCode">
        <?php foreach ($COUNTRIES as $code => $c): ?>
          <option value="<?=h($code)?>" <?=($selectedCountryCode === $code ? 'selected' : '')?>>
            <?=h($c['country'])?> / <?=h($code)?> / <?=h($c['currency'])?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="row">
      <label>Provider / Channel:</label>
      <select name="provider" id="provider"></select>
      <div class="small">Sent as channel_id and parameters[provider].</div>
    </div>

    <div class="row">
      <label>Payee phone / MSISDN:</label>
      <input type="text" name="phone" id="phone" value="<?=h($phone)?>">
      <div class="small">Sent as payee_phone.</div>
    </div>

    <div class="row">
      <label>Amount:</label>
      <input type="text" name="amount" id="amount" value="<?=h($amount)?>">
      <div class="small">Sent exactly as entered. No float/int conversion, no number_format. Example: 1000.00 stays 1000.00.</div>
    </div>

    <div class="row">
      <label>Description:</label>
      <input type="text" name="description" value="<?=h($description)?>">
    </div>

    <div class="row">
      <label>Payee first name:</label>
      <input type="text" name="payee_first_name" value="<?=h($payee_first_name)?>">
    </div>

    <div class="row">
      <label>Payee last name:</label>
      <input type="text" name="payee_last_name" value="<?=h($payee_last_name)?>">
    </div>

    <div class="row">
      <label>Payee email:</label>
      <input type="text" name="payee_email" value="<?=h($payee_email)?>">
    </div>

    <div class="row" style="margin-top:14px">
      <button type="submit">Send CREDIT2VIRTUAL</button>
    </div>
  </form>
</div>

<div class="panel">
  <h3>Static config</h3>
  <pre><?=pretty([
    'endpoint' => $PAYMENT_URL,
    'action' => 'CREDIT2VIRTUAL',
    'brand' => $BRAND,
    'payment_code' => $WITHDRAWAL_PAYMENT_CODE,
  ])?></pre>
</div>

<?php if (!empty($debug)): ?>
<div class="panel">
  <h3>Request summary</h3>
  <div><span class="kv">Endpoint:</span> <?=h($debug['endpoint'] ?? '')?></div>
  <div><span class="kv">HTTP:</span> <?=h($debug['http_code'] ?? '')?> <span class="kv" style="margin-left:12px">Duration:</span> <?=h($debug['duration_sec'] ?? '')?>s</div>
  <?php if (!empty($debug['curl_error'])): ?>
    <div class="error">cURL: <?=h($debug['curl_error'])?></div>
  <?php endif; ?>
</div>

<div class="panel">
  <h3>Sent form-data</h3>
  <pre><?=pretty($debug['form'])?></pre>
</div>

<div class="panel">
  <h3>Hash</h3>
  <div class="kv"><?=h($debug['hash_formula'])?></div>
  <pre><?=h($debug['hash_src'])?></pre>
  <pre><?=h($debug['hash'])?></pre>
</div>

<div class="panel">
  <h3>Response</h3>
  <pre><?=pretty($responseBlocks['bodyRaw'])?></pre>
  <?php if (is_array($responseBlocks['json'] ?? null)): ?>
    <h3>Parsed response</h3>
    <pre><?=pretty($responseBlocks['json'])?></pre>
    <?php if (!empty($responseBlocks['json']['trans_id'])): ?>
      <a class="btn" target="_blank" href="<?=h($STATUS_HELPER_URL)?>?trans_id=<?=h($responseBlocks['json']['trans_id'])?>">Open status helper</a>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

</div>

<script>
const countries = <?=json_encode($COUNTRIES, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
let selectedProvider = <?=json_encode($provider)?>;

function refreshProviders(keepSelected = true) {
  const countryCode = document.getElementById('countryCode').value;
  const providerSelect = document.getElementById('provider');
  const phoneInput = document.getElementById('phone');
  const providers = countries[countryCode].providers;

  providerSelect.innerHTML = '';

  Object.keys(providers).forEach(function(provider) {
    const option = document.createElement('option');
    option.value = provider;
    option.textContent = provider;
    if (keepSelected && provider === selectedProvider) option.selected = true;
    providerSelect.appendChild(option);
  });

  if (!providers.hasOwnProperty(providerSelect.value)) {
    providerSelect.selectedIndex = 0;
  }

  selectedProvider = providerSelect.value;
  phoneInput.value = providers[selectedProvider] || '';
}

document.getElementById('countryCode').addEventListener('change', function(){
  selectedProvider = '';
  refreshProviders(false);
});

document.getElementById('provider').addEventListener('change', function(){
  const countryCode = document.getElementById('countryCode').value;
  selectedProvider = this.value;
  document.getElementById('phone').value = countries[countryCode].providers[this.value] || '';
});

refreshProviders(true);
</script>
</body>
</html>
