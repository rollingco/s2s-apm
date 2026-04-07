<?php
/**
 * SALE Emulator — with CURL log output
 */

$endpoint    = 'https://api.leogcltd.com/post';
$merchantKey = '4bf7a70c-3252-11f1-9e2c-1600b4aec292';
$secret      = 'f0eba05582c6cfe69a84090530efc0ba';

$cardNumber  = '4111111111111111';
$expMonth    = '05';
$expYear     = '2038';
$cvv         = '123';

$payerEmail  = 'jon.doe@gmail.com';
$orderId     = 'Vasyl s2s_test ORDER_' . time();
$amount      = '0.99';
$currency    = 'EUR';
$desc        = '05/2038 test';

$termUrl3ds  = 'http://3ds.localhost/v4/confirmhandler';
$termTarget  = '_self';
$authOnly    = 'N';

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$first6 = substr($cardNumber, 0, 6);
$last4  = substr($cardNumber, -4);
$hashSource = strrev($payerEmail) . $secret . strrev($first6 . $last4);
$hash = md5(strtoupper($hashSource));

$requestFields = [
  'action' => 'SALE',
  'client_key' => $merchantKey,
  'order_id' => $orderId,
  'order_amount' => $amount,
  'order_currency' => $currency,
  'order_description' => $desc,
  'card_number' => $cardNumber,
  'card_exp_month' => $expMonth,
  'card_exp_year' => $expYear,
  'card_cvv2' => $cvv,
  'payer_first_name' => 'John',
  'payer_last_name' => 'Doe',
  'payer_address' => '106 New Address',
  'payer_country' => 'AE',
  'payer_city' => 'Dubai',
  'payer_zip' => '00100',
  'payer_email' => $payerEmail,
  'payer_phone' => '+1234567890',
  'payer_ip' => '210.71.106.164',
  'term_url_3ds' => $termUrl3ds,
  'term_url_target' => $termTarget,
  'auth' => $authOnly,
  'hash' => $hash,
];

$formBody = http_build_query($requestFields);

$headers = [
  'Content-Type: application/x-www-form-urlencoded',
  'Accept: application/json'
];

// ===== CURL LOG GENERATION =====
$curlLog = "curl --request POST '" . $endpoint . "' \\\n";
foreach ($headers as $h) {
    $curlLog .= "--header '" . $h . "' \\\n";
}
$curlLog .= "--data '" . $formBody . "'";

// ===== EXECUTE =====
$ch = curl_init($endpoint);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => $formBody,
  CURLOPT_HTTPHEADER => $headers,
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
?>

<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>CURL Debug</title>
  <style>
    body { font-family: monospace; background:#111; color:#eee; padding:20px; }
    pre { background:#1e1e1e; padding:15px; border-radius:10px; overflow:auto; }
  </style>
</head>
<body>

<h2>CURL Request</h2>
<pre><?=h($curlLog)?></pre>

<h2>Response</h2>
<pre><?=h($response)?></pre>

<h2>HTTP Code</h2>
<pre><?=h($httpCode)?></pre>

<?php if ($error): ?>
<h2>Error</h2>
<pre><?=h($error)?></pre>
<?php endif; ?>

</body>
</html>
