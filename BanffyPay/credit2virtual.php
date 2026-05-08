<?php
/**********
 * BanffyPay PayOut test
 * Request body is built according to the WhatsApp example.
 *
 * IMPORTANT:
 * - No Akurateco CREDIT2VIRTUAL form-data fields here.
 * - No action, client_key, order_amount, order_currency, payee_*, parameters[...].
 * - Payload is JSON with camelCase keys and extraData object.
 */

header('Content-Type: text/html; charset=utf-8');

/* ===================== CONFIG ===================== */
$PAYMENT_URL = 'https://api.leogcltd.com/post';

// Brand mentioned in WhatsApp message as the MID routing brand.
// It is shown in static config only and is NOT included into Banffy JSON payload,
// because the WhatsApp payload example does not contain brand.
$BRAND = 'leogc-bannf-dbm';

$WITHDRAWAL_PAYMENT_CODE = '999';

$COUNTRIES = [
  'TZ' => [
    'country' => 'Tanzania',
    'countryCode' => 'TZ',
    'currencyCode' => 'TZS',
    'providers' => [
      'Halopesa' => '255623456789',
      'Airtel'   => '255683456789',
      'Vodacom'  => '255763456789',
      'Tigo'     => '255713456789',
      'Azampesa' => '',
      'Mpesa'    => '',
    ],
  ],
  'BJ' => [
    'country' => 'Benin',
    'countryCode' => 'BJ',
    'currencyCode' => 'XOF',
    'providers' => [
      'Mtn'  => '22951345789',
      'Moov' => '22995345789',
    ],
  ],
  'SN' => [
    'country' => 'Senegal',
    'countryCode' => 'SN',
    'currencyCode' => 'XOF',
    'providers' => [
      'orange-senegal' => '221773456789',
      'wave-senegal'   => '',
    ],
  ],
  'CM' => [
    'country' => 'Cameroon',
    'countryCode' => 'CM',
    'currencyCode' => 'XAF',
    'providers' => [
      'mtn-momo-cameroon' => '237653456789',
    ],
  ],
  'KE' => [
    'country' => 'Kenya',
    'countryCode' => 'KE',
    'currencyCode' => 'KES',
    'providers' => [
      'safaricom-kenya' => '254703456789',
      'airtel-kenya'    => '',
      'equitel-kenya'   => '',
      't-kash-kenya'    => '',
      'telkom-kenya'    => '',
    ],
  ],
  'CI' => [
    'country' => 'Ivory Coast',
    'countryCode' => 'CI',
    'currencyCode' => 'XOF',
    'providers' => [
      'orange-ic' => '2250734567890',
      'moov-ic'   => '',
      'wave-ic'   => '',
    ],
  ],
  'GH' => [
    'country' => 'Ghana',
    'countryCode' => 'GH',
    'currencyCode' => 'GHS',
    'providers' => [
      'Mtn'         => '233593456789',
      'Vodafone'    => '233503456789',
      'Airtel-Tigo' => '233273456789',
      'Zeepay'      => '',
    ],
  ],
  'NG' => [
    'country' => 'Nigeria',
    'countryCode' => 'NG',
    'currencyCode' => 'NGN',
    'providers' => [
      'Access Bank' => '',
      'Zenith Bank' => '',
      'GTBank'      => '',
      'First Bank'  => '',
      'UBA'         => '',
      'Opay'        => '',
    ],
  ],
  'RW' => [
    'country' => 'Rwanda',
    'countryCode' => 'RW',
    'currencyCode' => 'RWF',
    'providers' => [
      'Airtel' => '250733456789',
      'Tigo'   => '',
    ],
  ],
  'SL' => [
    'country' => 'Sierra Leone',
    'countryCode' => 'SL',
    'currencyCode' => 'SLE',
    'providers' => [
      'All Networks' => '',
    ],
  ],
  'LR' => [
    'country' => 'Liberia',
    'countryCode' => 'LR',
    'currencyCode' => 'LRD',
    'providers' => [
      'MtnMomo'     => '',
      'Mtn USD'     => '',
      'OrangeMoney' => '',
      'Orange USD'  => '',
    ],
  ],
];

$DEFAULTS = [
  'countryCode'      => 'TZ',
  'provider'         => 'Halopesa',
  'amount'           => '10.00',
  'description'      => 'BanffyPay payout test',
  'first_name'       => 'John',
  'last_name'        => 'Doe',
  'email'            => 'customer@example.com',
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

function current_url(){
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
}

function make_callback_url($orderId){
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  $scheme = $https ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'www.sandbox.pp.ua';
  return $scheme . '://' . $host . '/callback/' . rawurlencode($orderId);
}

/* ===================== Initial state ===================== */
$submitted = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

$selectedCountryCode = $_POST['countryCode'] ?? $_GET['countryCode'] ?? $DEFAULTS['countryCode'];
if (!isset($COUNTRIES[$selectedCountryCode])) $selectedCountryCode = $DEFAULTS['countryCode'];
$selectedCountry = $COUNTRIES[$selectedCountryCode];

$provider = $_POST['provider'] ?? $_GET['provider'] ?? $DEFAULTS['provider'];
if (!isset($selectedCountry['providers'][$provider])) $provider = array_key_first($selectedCountry['providers']);

$msisdn = $_POST['msisdn'] ?? ($selectedCountry['providers'][$provider] ?? '');
$amount = $_POST['amount'] ?? $DEFAULTS['amount'];
$description = $_POST['description'] ?? $DEFAULTS['description'];
$firstName = $_POST['first_name'] ?? $DEFAULTS['first_name'];
$lastName = $_POST['last_name'] ?? $DEFAULTS['last_name'];
$email = $_POST['email'] ?? $DEFAULTS['email'];

$debug = [];
$responseBlocks = ['bodyRaw' => '', 'json' => null];
$errors = [];

/* ===================== Submit ===================== */
if ($submitted) {
  $msisdn = preg_replace('/\s+/', '', (string)$msisdn);
  $msisdn = ltrim($msisdn, '+');

  $amount = trim((string)$amount);
  $description = trim((string)$description);
  $firstName = trim((string)$firstName);
  $lastName = trim((string)$lastName);
  $email = trim((string)$email);

  if ($msisdn === '') $errors[] = 'MSISDN is required.';
  if ($amount === '' || !preg_match('/^\d+(\.\d+)?$/', $amount) || (float)$amount <= 0) $errors[] = 'Amount must be a positive number.';
  if ($description === '') $errors[] = 'Description is required.';
  if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email format looks wrong.';

  if (!$errors) {
    $merchantTransactionID = $selectedCountryCode . '_PAYOUT_' . time();
    $merchantURL = make_callback_url($merchantTransactionID);
    $beneficiaryName = trim($firstName . ' ' . $lastName);

    // EXACT structure from WhatsApp screenshot.
    $payload = [
      'requestOrigin'         => 'Api',
      'paymentCode'           => $GLOBALS['WITHDRAWAL_PAYMENT_CODE'],
      'paymentProvider'       => $provider,
      'merchantURL'           => $merchantURL,
      'merchantTransactionID' => $merchantTransactionID,
      'currencyCode'          => $selectedCountry['currencyCode'],
      'amount'                => $amount,
      'description'           => $description,
      'countryCode'           => $selectedCountry['countryCode'],
      'msisdn'                => $msisdn,
      'extraData'             => [
        'transactionType'         => 'MOBILE_TRANSFER',
        'beneficiaryProvider'     => $provider,
        'beneficiaryName'         => $beneficiaryName,
        'beneficiaryEmail'        => $email,
        'beneficiaryMsisdn'       => $msisdn,
        'beneficiaryCountryCode'  => $selectedCountry['countryCode'],
      ],
    ];

    $jsonBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $debug = [
      'endpoint' => $PAYMENT_URL,
      'brand_for_mid_routing' => $BRAND,
      'headers' => [
        'Content-Type: application/json',
        'Accept: application/json',
      ],
      'json_payload' => $payload,
      'raw_json_body' => $jsonBody,
    ];

    $ch = curl_init($PAYMENT_URL);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
      ],
      CURLOPT_POSTFIELDS     => $jsonBody,
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
<title>BanffyPay PayOut — WhatsApp JSON format</title>
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
.row{margin:9px 0}.kv{color:var(--muted)}
</style>
</head>
<body>
<div class="wrap">

<div class="panel">
  <h3>Create BanffyPay PayOut — WhatsApp JSON format</h3>

  <?php if (!empty($errors)): ?>
    <div class="error"><pre><?=pretty($errors)?></pre></div>
  <?php endif; ?>

  <form action="<?=h($self)?>" method="post">
    <div class="row">
      <label>Country:</label>
      <select name="countryCode" id="countryCode">
        <?php foreach ($COUNTRIES as $code => $c): ?>
          <option value="<?=h($code)?>" <?=($selectedCountryCode === $code ? 'selected' : '')?>>
            <?=h($c['country'])?> / <?=h($code)?> / <?=h($c['currencyCode'])?>
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
      <input type="text" name="msisdn" id="msisdn" value="<?=h($msisdn)?>">
      <div class="small">Sent as msisdn and extraData.beneficiaryMsisdn.</div>
    </div>

    <div class="row">
      <label>Amount:</label>
      <input type="text" name="amount" value="<?=h($amount)?>">
      <div class="small">Sent exactly as entered. Example: 10.00 stays 10.00.</div>
    </div>

    <div class="row">
      <label>Description:</label>
      <input type="text" name="description" value="<?=h($description)?>">
    </div>

    <div class="row">
      <label>Beneficiary first name:</label>
      <input type="text" name="first_name" value="<?=h($firstName)?>">
    </div>

    <div class="row">
      <label>Beneficiary last name:</label>
      <input type="text" name="last_name" value="<?=h($lastName)?>">
    </div>

    <div class="row">
      <label>Beneficiary email:</label>
      <input type="text" name="email" value="<?=h($email)?>">
    </div>

    <div class="row" style="margin-top:14px">
      <button type="submit">Send JSON payout</button>
    </div>
  </form>
</div>

<div class="panel">
  <h3>Static config</h3>
  <pre><?=pretty([
    'endpoint' => $PAYMENT_URL,
    'brand_for_mid_routing' => $BRAND,
    'paymentCode' => $WITHDRAWAL_PAYMENT_CODE,
    'body_format' => 'application/json',
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
  <h3>Sent JSON to LeoGC /post</h3>
  <pre><?=pretty($debug['json_payload'])?></pre>
</div>

<div class="panel">
  <h3>Raw JSON body</h3>
  <pre><?=h($debug['raw_json_body'])?></pre>
</div>

<div class="panel">
  <h3>Response</h3>
  <pre><?=pretty($responseBlocks['bodyRaw'])?></pre>
  <?php if (is_array($responseBlocks['json'] ?? null)): ?>
    <h3>Parsed response</h3>
    <pre><?=pretty($responseBlocks['json'])?></pre>
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
  const msisdnInput = document.getElementById('msisdn');
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
  msisdnInput.value = providers[selectedProvider] || '';
}

document.getElementById('countryCode').addEventListener('change', function(){
  selectedProvider = '';
  refreshProviders(false);
});

document.getElementById('provider').addEventListener('change', function(){
  const countryCode = document.getElementById('countryCode').value;
  selectedProvider = this.value;
  document.getElementById('msisdn').value = countries[countryCode].providers[this.value] || '';
});

refreshProviders(true);
</script>
</body>
</html>
