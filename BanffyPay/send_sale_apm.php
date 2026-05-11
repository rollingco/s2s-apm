```php
<?php
/**
 * S2S APM SALE — multi-country example with channel_id
 */

header('Content-Type: text/html; charset=utf-8');

$PAYMENT_URL = 'https://api.leogcltd.com/post-va';

$CLIENT_KEY = '5f306e12-0ff2-11f1-bac9-0a9a38974658';
$SECRET     = '976d5c5d5eacbab78288b12bb15178ba';
//$CLIENT_KEY = 'a9375190-26f2-11f0-be42-022c42254708';
//$SECRET     = '554999c284e9f29cf95f090d9a8f3171';


$COUNTRIES = [
  'BJ' => [
    'country' => 'Benin',
    'countryCode' => 'BJ',
    'currency' => 'XOF',
    'payer_country' => 'BJ',
    'payment_code' => '101',
    'providers' => [
      'Mtn' => '22951345789',
      'Moov' => '22995345789',
    ],
  ],
  'Togo' => [
    'country' => 'Togo',
    'countryCode' => 'Togo',
    'currency' => 'XOF',
    'payer_country' => 'Togo',
    'payment_code' => '103',
    'providers' => [
      'Togocel' => '',
      'Moov' => '',
    ],
  ],
  'SN' => [
    'country' => 'Senegal',
    'countryCode' => 'SN',
    'currency' => 'XOF',
    'payer_country' => 'SN',
    'payment_code' => '201',
    'providers' => [
      'orange-senegal' => '221773456789',
      'wave-senegal' => '',
    ],
  ],
  'CM' => [
    'country' => 'Cameroon',
    'countryCode' => 'CM',
    'currency' => 'XAF',
    'payer_country' => 'CM',
    'payment_code' => '202',
    'providers' => [
      'mtn-momo-cameroon' => '237653456789',
    ],
  ],
  'KE' => [
    'country' => 'Kenya',
    'countryCode' => 'KE',
    'currency' => 'KES',
    'payer_country' => 'KE',
    'payment_code' => '203',
    'providers' => [
      //'airtel-kenya' => '',
      'Airtel' => '',
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
    'payer_country' => 'CI',
    'payment_code' => '204',
    'providers' => [
      //'moov-ivory-coast' => '',
      'moov-ic' => '',
      //'orange-ivory-coast' => '2250734567890',
      'orange-ic' => '2250734567890',
      //'wave-ivory-coast' => '',
      'wave-ic' => '',
    ],
  ],
  'ML' => [
    'country' => 'Mali',
    'countryCode' => 'ML',
    'currency' => 'XOF',
    'payer_country' => 'ML',
    'payment_code' => '205',
    'providers' => [
      'orange-mali' => '',
      'moov-mali' => '',
    ],
  ],
  'BF' => [
    'country' => 'Burkina Faso',
    'countryCode' => 'BF',
    'currency' => 'XOF',
    'payer_country' => 'BF',
    'payment_code' => '206',
    'providers' => [
      'Moov' => '22602345678',
      'Orange' => '22607345678',
    ],
  ],
  'GH' => [
    'country' => 'Ghana',
    'countryCode' => 'GH',
    'currency' => 'GHS',
    'payer_country' => 'GH',
    'payment_code' => '301',
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
    'payer_country' => 'ZM',
    'payment_code' => '302',
    'providers' => [
      'Zeepay' => '',
    ],
  ],
  'NG' => [
    'country' => 'Nigeria',
    'countryCode' => 'NG',
    'currency' => 'NGN',
    'payer_country' => 'NG',
    'payment_code' => '401',
    'providers' => [
    /*  
    'Originating Bank name' => '',
      'Access Bank' => '',
      'Zenith Bank' => '',
      'GTBank' => '',
      'First Bank' => '',
      'UBA' => '',
      'Opay (if the customer is paying via a fintech wallet)' => '',
      */
    ],
  ],
  'TZ' => [
    'country' => 'Tanzania',
    'countryCode' => 'TZ',
    'currency' => 'TZS',
    'payer_country' => 'TZ',
    'payment_code' => '501',
    'providers' => [
      'Vodacom' => '255763456789',
      'Airtel' => '255683456789',
      'Tigo' => '255713456789',
      'Halopesa' => '255623456789',
      'Azampesa' => '',
      'Mpesa' => '',
    ],
  ],
];

$DEFAULTS = [
  'country' => 'TZ',
  'provider' => 'Airtel',
];

$errors = [];
$response = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $selectedCountryCode = $_POST['country'] ?? $DEFAULTS['country'];

  if (!isset($COUNTRIES[$selectedCountryCode])) {
    $errors[] = 'Invalid country selected.';
    $selectedCountryCode = $DEFAULTS['country'];
  }

  $selectedCountry = $COUNTRIES[$selectedCountryCode];

  $provider = $_POST['provider'] ?? ($DEFAULTS['provider'] ?? '');

  // Nigeria does not require provider validation
  if ($selectedCountryCode !== 'NG') {
    if (!isset($selectedCountry['providers'][$provider])) {
      $errors[] = 'Invalid provider for selected country.';
      $provider = array_key_first($selectedCountry['providers']);
    }
  }

  $amount = trim($_POST['amount'] ?? '10');
  $msisdn = trim($_POST['msisdn'] ?? '');

  if ($msisdn === '' && $selectedCountryCode !== 'NG') {
    $msisdn = $selectedCountry['providers'][$provider] ?? '';
  }

  $orderId = 'ORDER-' . time();

  $request = [
    'client_key' => $CLIENT_KEY,
    'action' => 'SALE',
    'order_id' => $orderId,
    'order_amount' => $amount,
    'order_currency' => $selectedCountry['currency'],
    'order_description' => 'APM test payment',
    'payer_first_name' => 'John',
    'payer_last_name' => 'Doe',
    'payer_address' => 'Test address',
    'payer_country' => $selectedCountry['payer_country'],
    'payer_city' => 'Test City',
    'payer_zip' => '00000',
    'payer_email' => 'test@example.com',
    'payer_phone' => $msisdn,
    'payer_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
    'term_url_3ds' => 'https://example.com/3ds-return',
    'auth' => md5(strtoupper(strrev($msisdn . $SECRET))),
    'recurring_init' => 'N',
    'country' => $selectedCountry['country'],
    'countryCode' => $selectedCountry['countryCode'],
    'payment_code' => $selectedCountry['payment_code'],
    'channel_id' => ($selectedCountryCode === 'NG') ? '' : $provider,
  ];

  $curl = curl_init();

  curl_setopt_array($curl, [
    CURLOPT_URL => $PAYMENT_URL,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($request),
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/x-www-form-urlencoded',
    ],
  ]);

  $result = curl_exec($curl);
  $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  $curlError = curl_error($curl);

  curl_close($curl);

  $response = [
    'http_code' => $httpCode,
    'curl_error' => $curlError,
    'raw' => $result,
    'request' => $request,
  ];
}
?>
```
