<?php

// ========================= CONFIG =========================
$endpoint    = 'https://api.leogcltd.com/post';

$merchantKey = '8af2433a-1269-11f1-9540-2a588e8348b1';
$secret      = '71d7e2e8a5bca26c7cc63776fc36078d';

// Card
$cardNumber  = '4111111111111111';
$expMonth    = '01';
$expYear     = '2038';
$cvv         = '123';

// Payer
$payerEmail  = 'talasef354@mekuron.com';

// 3DS
$termUrl3ds  = 'https://afripie.com/payment-methods/3ds';
$termTarget  = '_self';


// ========================= STEP 1 =========================
$first6 = substr($cardNumber, 0, 6);
$last4  = substr($cardNumber, -4);

// hash
$hashSource = strrev($payerEmail) . $secret . strrev($first6 . $last4);
$hash       = md5(strtoupper($hashSource));

$request = [
  'action'            => 'SALE',
  'client_key'        => $merchantKey,
  'order_id'          => 'o5aIiu6fCdFogLzdyfbxN',
  'order_amount'      => '0.00',
  'order_currency'    => 'USD',
  'order_description' => 'Afripie Payment Method Addition',

  'card_number'       => $cardNumber,
  'card_exp_month'    => $expMonth,
  'card_exp_year'     => $expYear,
  'card_cvv2'         => $cvv,

  'payer_first_name'  => 'Test',
  'payer_last_name'   => 'Alasef',
  'payer_address'     => '3 Third Ave',
  'payer_country'     => 'US',
  'payer_city'        => 'Melrose',
  'payer_zip'         => '01533',
  'payer_email'       => $payerEmail,
  'payer_phone'       => '6035484534',
  'payer_ip'          => '163.252.215.185',

  'term_url_3ds'      => $termUrl3ds,
  'term_url_target'   => $termTarget,

  'auth'              => 'Y',
  'req_token'         => 'Y',

  'hash'              => $hash
];