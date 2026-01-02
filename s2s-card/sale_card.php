<?php
/**
 * Emulation of card SALE (purchase)
 * Endpoint: https://api.leogcltd.com/post
 * Hash (SALE/RETRY) formula (from your screenshot):
 *   md5( strtoupper( strrev(email) . SECRET . strrev( first6 + last4 ) ) )
 * where first6 = substr(card_number, 0, 6)
 *       last4  = substr(card_number, -4)
 */

$endpoint    = 'https://api.leogcltd.com/post';

$merchantKey = 'a9375190-26f2-11f0-be42-022c42254708';
$secret      = '554999c284e9f29cf95f090d9a8f3171';

// --- Test card data (example) ---
$cardNumber  = '4111111111111111';
$expMonth    = '12';
$expYear     = '2027';
$cvv         = '123';

// --- Payer data (required in your screenshot) ---
$email       = 'john.doe@example.com';

$orderId     = 'ORDER_' . date('Ymd_His');
$amount      = '10.50';
$currency    = 'USD';
$desc        = 'Test purchase';

// If your integration requires 3DS return URL (it is marked required in the screenshot)
$termUrl3ds  = 'https://merchant.example.com/3ds-return';

$first6 = substr($cardNumber, 0, 6);
$last4  = substr($cardNumber, -4);

// hash = md5( strtoupper( strrev(email) . SECRET . strrev(first6 . last4) ) )
$hashSource = strrev($email) . $secret . strrev($first6 . $last4);
$hash = md5(strtoupper($hashSource));

$post = [
  'action'            => 'SALE',
  'client_key'        => $merchantKey,
  'order_id'          => $orderId,
  'order_amount'      => $amount,
  'order_currency'    => $currency,
  'order_description' => $desc,

  'card_number'       => $cardNumber,
  'card_exp_month'    => $expMonth,
  'card_exp_year'     => $expYear,
  'card_cvv2'         => $cvv,

  'payer_first_name'  => 'John',
  'payer_last_name'   => 'Doe',
  'payer_address'     => '123 Main St',
  'payer_country'     => 'US',
  'payer_city'        => 'New York',
  'payer_zip'         => '10001',
  'payer_email'       => $email,
  'payer_phone'       => '+1234567890',
  'payer_ip'          => '192.168.1.1',

  'term_url_3ds'      => $termUrl3ds,
  'term_url_target'   => '_self',

  // auth=Y => AUTH only (without capture). For normal purchase keep N or omit.
  'auth'              => 'N',

  'hash'              => $hash,
];

// --- Execute request ---
$ch = curl_init($endpoint);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => http_build_query($post),
  CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
  CURLOPT_TIMEOUT        => 60,
]);

$response = curl_exec($ch);
$curlErr  = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// --- Output ---
echo "=== SALE EMULATION ===\n";
echo "Endpoint: $endpoint\n";
echo "order_id: $orderId\n";
echo "amount: $amount $currency\n";
echo "hash_source: $hashSource\n";
echo "hash: $hash\n";
echo "HTTP: $httpCode\n\n";

if ($curlErr) {
  echo "cURL error: $curlErr\n";
  exit(1);
}

echo "Response:\n$response\n";
