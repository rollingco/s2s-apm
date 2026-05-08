<?php
/**********
 * S2S CREDIT2VIRTUAL — BanffyPay PayOut
 *
 * Request is sent to LeoGC gateway /post with brand leogc-bannf-dbm.
 * Fields are prepared according to the BanffyPay example:
 *
 * Connector-side JSON should be formed as:
 * {
 *   requestOrigin,
 *   paymentCode: "999",
 *   paymentProvider,
 *   merchantURL,
 *   merchantTransactionID,
 *   currencyCode,
 *   amount,
 *   description,
 *   countryCode,
 *   msisdn,
 *   extraData: {
 *     transactionType: "MOBILE_TRANSFER",
 *     beneficiaryProvider,
 *     beneficiaryName,
 *     beneficiaryEmail,
 *     beneficiaryMsisdn,
 *     beneficiaryCountryCode
 *   }
 * }
 *
 * IMPORTANT: order_amount is sent exactly as entered in the form.
 * No float/int conversion and no number_format.
 */

header('Content-Type: text/html; charset=utf-8');

/* ===================== CONFIG ===================== */
$PAYMENT_URL = 'https://api.leogcltd.com/post';

$CLIENT_KEY = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET     = '554999c284e9f29cf95f090d9a8f3171';

$BRAND = 'leogc-bannf-dbm';
$WITHDRAWAL_PAYMENT_CODE = '999';

$STATUS_HELPER_URL = 'status_credit2virtual.php';

$COUNTRIES = [
  'TZ' => [
    'country' => 'Tanzania',
    'countryCode' => 'TZ',
    'currency' => 'TZS',
    'providers' => [
      'Vodacom'  => '255763456789',
      'Airtel'   => '255683456789',
      'Tigo'     => '255713456789',
      'Halopesa' => '255623456789',
      'Azampesa' => '',
      'Mpesa'    => '',
    ],
  ],
  'BJ' => [
    'country' => 'Benin',
    'countryCode' => 'BJ',
    'currency' => 'XOF',
    'providers' => [
      'Mtn'  => '22951345789',
      'Moov' => '22995345789',
    ],
  ],
  'TG' => [
    'country' => 'Togo',
    'countryCode' => 'TG',
    'currency' => 'XOF',
    'providers' => [
      'Togocel' => '',
      'Moov'    => '',
    ],
  ],
  'SN' => [
    'country' => 'Senegal',
    'countryCode' => 'SN',
    'currency' => 'XOF',
    'providers' => [
      'orange-senegal' => '221773456789',
      'wave-senegal'   => '',
    ],
  ],
  'CM' => [
    'country' => 'Cameroon',
    'countryCode' => 'CM',
    'currency' => 'XAF',
    'providers' => [
      'mtn-momo-cameroon' => '237653456789',
    ],
  ],
  'KE' => [
    'country' => 'Kenya',
    'countryCode' => 'KE',
    'currency' => 'KES',
    'providers' => [
      'airtel-kenya'   => '',
      'equitel-kenya'  => '',
      'safaricom-kenya'=> '254703456789',
      't-kash-kenya'   => '',
      'telkom-kenya'   => '',
    ],
  ],
  'CI' => [
    'country' => 'Ivory Coast',
    'countryCode' => 'CI',
    'currency' => 'XOF',
    'providers' => [
      'moov-ic'   => '',
      'orange-ic' => '2250734567890',
      'wave-ic'   => '',
    ],
  ],
  'ML' => [
    'country' => 'Mali',
    'countryCode' => 'ML',
    'currency' => 'XOF',
    'providers' => [
      'orange-mali' => '',
      'moov-mali'   => '',
    ],
  ],
  'BF' => [
    'country' => 'Burkina Faso',
    'countryCode' => 'BF',
    'currency' => 'XOF',
    'providers' => [
      'Moov'   => '22602345678',
      'Orange' => '22607345678',
    ],
  ],
  'GH' => [
    'country' => 'Ghana',
    'countryCode' => 'GH',
    'currency' => 'GHS',
    'providers' => [
      'Mtn'        => '233593456789',
      'Zeepay'     => '',
      'Vodafone'   => '233503456789',
      'Airtel-Tigo'=> '233273456789',
    ],
  ],
  'ZM' => [
    'country' => 'Zambia',
    'countryCode' => 'ZM',
    'currency' => 'ZMW',
    'providers' => [
      'Zeepay' => '',
    ],
  ],
  'NG' => [
    'country' => 'Nigeria',
    'countryCode' => 'NG',
    'currency' => 'NGN',
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
  'RW' => [
    'country' => 'Rwanda',
    'countryCode' => 'RW',
    'currency' => 'RWF',
    'providers' => [
      'Airtel'   => '250733456789',
      'Tigo'     => '',
      'Halopesa' => '',
      'Azampesa' => '',
      'Mpesa'    => '',
    ],
  ],
  'SL' => [
    'country' => 'Sierra Leone',
    'countryCode' => 'SL',
    'currency' => 'SLE',
    'providers' => [
      'All Networks' => '',
    ],
  ],
  'LR' => [
    'country' => 'Liberia',
    'countryCode' => 'LR',
    'currency' => 'LRD',
    'providers' => [
      'MtnMomo'     => '',
      'Mtn USD'     => '',
      'OrangeMoney' => '',
      'Orange USD'  => '',
    ],
  ],
  'CD' => [
    'country' => 'DRC',
    'countryCode' => 'CD',
    'currency' => 'CDF',
    'providers' => [
      'Vodacom MPesa' => '243813456789',
      'Africell'      => '',
      'Airtel'        => '243973456789',
      'Orange'        => '243893456789',
    ],
  ],
  'CA' => [
    'country' => 'Canada',
    'countryCode' => 'CA',
    'currency' => 'CAD',
    'providers' => [
      'For 901' => '',
    ],
  ],
];

$DEFAULTS = [
  'countryCode'      => 'TZ',
  'provider'         => 'Halopesa',
  'amount'           => '10.00',
  'description'      => 'BanffyPay payout test',
  'beneficiary_name' => 'John Doe',
  'beneficiary_email'=> 'customer@example.com',
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
 * CREDIT2VIRTUAL hash from Akurateco documentation:
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

function callback_url_for_order($order_id){
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  $scheme = $https ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $scheme . '://' . $host . '/callback/' . rawurlencode($order_id);
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
$beneficiary_name = $DEFAULTS['beneficiary_name'];
$beneficiary_email = $DEFAULTS['beneficiary_email'];

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
    $errors[] = 'Invalid paymentProvider for selected country.';
    $provider = array_key_first($selectedCountry['providers']);
  }

  $currency = $selectedCountry['currency'];
  $countryCode = $selectedCountry['countryCode'];

  $phone = preg_replace('/\s+/', '', $_POST['phone'] ?? '');
  $phone = ltrim($phone, '+');

  // IMPORTANT: keep amount exactly as entered in the form.
  // Do not cast to float/int and do not format it, otherwise 1000.00 becomes 1000.
  $amount = trim((string)($_POST['amount'] ?? ''));

  $description = trim((string)($_POST['description'] ?? $DEFAULTS['description']));
  $beneficiary_name = trim((string)($_POST['beneficiary_name'] ?? $DEFAULTS['beneficiary_name']));
  $beneficiary_email = trim((string)($_POST['beneficiary_email'] ?? $DEFAULTS['beneficiary_email']));

  if ($phone === '') $errors[] = 'MSISDN is required.';
  if ($amount === '' || !preg_match('/^\d+(\.\d+)?$/', $amount) || (float)$amount <= 0) $errors[] = 'Amount must be a positive number.';
  if ($description === '') $errors[] = 'Description is required.';
  if ($beneficiary_name === '') $errors[] = 'Beneficiary name is required.';
  if ($beneficiary_email !== '' && !filter_var($beneficiary_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Beneficiary email format looks wrong.';

  if (!$errors) {
    $order_id = $countryCode . '_PAYOUT_' . time();
    $merchant_url = callback_url_for_order($order_id);

    $hash_src_dbg = '';
    $hash = build_credit2virtual_hash($order_id, $amount, $currency, $SECRET, $hash_src_dbg);

    $form = [
      // Akurateco gateway fields
      'action'            => 'CREDIT2VIRTUAL',
      'client_key'        => $CLIENT_KEY,
      'brand'             => $BRAND,
      'order_id'          => $order_id,
      'order_amount'      => $amount,
      'order_currency'    => $currency,
      'order_description' => $description,
      'hash'              => $hash,

      // Fields which should be mapped by connector to BanffyPay top-level JSON
      'payment_code'      => $GLOBALS['WITHDRAWAL_PAYMENT_CODE'],
      'paymentCode'       => $GLOBALS['WITHDRAWAL_PAYMENT_CODE'],
      'paymentProvider'   => $provider,
      'countryCode'       => $countryCode,
      'currencyCode'      => $currency,
      'amount'            => $amount,
      'description'       => $description,
      'merchantURL'       => $merchant_url,
      'merchantTransactionID' => $order_id,
      'msisdn'            => $phone,

      // Legacy/common fields. Keep them too, because some mappings may read these names.
      'country'           => $selectedCountry['country'],
      'channel_id'        => $provider,
      'payee_country'     => $countryCode,
      'payee_phone'       => $phone,
      'payee_first_name'  => $beneficiary_name,
      'payee_last_name'   => '',
    ];

    if ($beneficiary_email !== '') {
      $form['payee_email'] = $beneficiary_email;
    }

    // Connector extraData according to the provided example.
    $form['parameters[transactionType]'] = 'MOBILE_TRANSFER';
    $form['parameters[beneficiaryProvider]'] = $provider;
    $form['parameters[beneficiaryName]'] = $beneficiary_name;
    $form['parameters[beneficiaryEmail]'] = $beneficiary_email;
    $form['parameters[beneficiaryMsisdn]'] = $phone;
    $form['parameters[beneficiaryCountryCode]'] = $countryCode;

    // Additional aliases in case MID mapping still uses old names.
    $form['parameters[provider]'] = $provider;
    $form['parameters[paymentProvider]'] = $provider;
    $form['parameters[paymentCode]'] = $GLOBALS['WITHDRAWAL_PAYMENT_CODE'];
    $form['parameters[countryCode]'] = $countryCode;
    $form['parameters[msisdn]'] = $phone;

    $expectedConnectorJson = [
      'requestOrigin' => 'Api',
      'paymentCode' => $GLOBALS['WITHDRAWAL_PAYMENT_CODE'],
      'paymentProvider' => $provider,
      'merchantURL' => $merchant_url,
      'merchantTransactionID' => $order_id,
      'currencyCode' => $currency,
      'amount' => $amount,
      'description' => $description,
      'countryCode' => $countryCode,
      'msisdn' => $phone,
      'extraData' => [
        'transactionType' => 'MOBILE_TRANSFER',
        'beneficiaryProvider' => $provider,
        'beneficiaryName' => $beneficiary_name,
        'beneficiaryEmail' => $beneficiary_email,
        'beneficiaryMsisdn' => $phone,
        'beneficiaryCountryCode' => $countryCode,
      ],
    ];

    $debug = [
      'endpoint' => $PAYMENT_URL,
      'form' => $form,
      'expected_connector_json' => $expectedConnectorJson,
      'hash_formula' => 'md5( strtoupper( strrev( order_id . amount . currency ) ) . SECRET )',
      'hash_src' => $hash_src_dbg,
      'hash' => $hash,
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
label{display:inline-block;min-width:210px;color:var(--muted)}
button,.btn{padding:10px 14px;border-radius:10px;background:var(--blue);color:#fff;border:0;text-decoration:none;display:inline-block;cursor:pointer}
.error{color:var(--err)}
.small{font-size:12px;color:var(--muted);margin-left:214px;margin-top:3px}
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
      <label>Payment provider:</label>
      <select name="provider" id="provider"></select>
      <div class="small">Sent as paymentProvider and extraData.beneficiaryProvider.</div>
    </div>

    <div class="row">
      <label>MSISDN:</label>
      <input type="text" name="phone" id="phone" value="<?=h($phone)?>">
      <div class="small">Sent as msisdn and extraData.beneficiaryMsisdn.</div>
    </div>

    <div class="row">
      <label>Amount:</label>
      <input type="text" name="amount" id="amount" value="<?=h($amount)?>">
      <div class="small">Sent exactly as entered. No float/int conversion, no number_format. Example: 10.00 stays 10.00.</div>
    </div>

    <div class="row">
      <label>Description:</label>
      <input type="text" name="description" value="<?=h($description)?>">
    </div>

    <div class="row">
      <label>Beneficiary name:</label>
      <input type="text" name="beneficiary_name" value="<?=h($beneficiary_name)?>">
    </div>

    <div class="row">
      <label>Beneficiary email:</label>
      <input type="text" name="beneficiary_email" value="<?=h($beneficiary_email)?>">
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
  <h3>Sent form-data to LeoGC /post</h3>
  <pre><?=pretty($debug['form'])?></pre>
</div>

<div class="panel">
  <h3>Expected connector JSON to BanffyPay</h3>
  <pre><?=pretty($debug['expected_connector_json'])?></pre>
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
