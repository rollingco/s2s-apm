<?php
/**
 * Tokenization + Payment by card_token (single file) — HASH FIXED
 */

$endpoint    = 'https://api.leogcltd.com/post';

$merchantKey = '8af2433a-1269-11f1-9540-2a588e8348b1';
$secret      = '71d7e2e8a5bca26c7cc63776fc36078d';

$cardNumber  = '4111111111111111';
$expMonth    = '01';
$expYear     = '2038';
$cvv         = '123';

$payerEmail  = 'talasef354@mekuron.com';

$termUrl3ds  = 'https://afripie.com/payment-methods/3ds';
$termTarget  = '_self';

function do_post_form(string $endpoint, array $fields): array {

  $formBody = http_build_query($fields);

  $ch = curl_init($endpoint);

  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $formBody,
    CURLOPT_TIMEOUT => 60
  ]);

  $raw = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  curl_close($ch);

  return [
    'http'=>$code,
    'error'=>$err,
    'raw'=>$raw,
    'json'=>json_decode($raw,true)
  ];
}

function mask($arr){

  if(isset($arr['card_number']))
    $arr['card_number']=substr($arr['card_number'],0,6).'******'.substr($arr['card_number'],-4);

  if(isset($arr['card_cvv2']))
    $arr['card_cvv2']='***';

  return $arr;
}

function j($v){return json_encode($v,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}


// ================= STEP 1 =================

$first6=substr($cardNumber,0,6);
$last4=substr($cardNumber,-4);

$hashSource1=strrev($payerEmail).$secret.strrev($first6.$last4);
$hashStep1=md5(strtoupper($hashSource1));

$orderIdToken='o5aIiu6fCdFogLzdyfbxN';

$reqStep1=[

'action'=>'SALE',
'client_key'=>$merchantKey,

'order_id'=>$orderIdToken,
'order_amount'=>'0.00',
'order_currency'=>'USD',
'order_description'=>'Afripie Payment Method Addition',

'card_number'=>$cardNumber,
'card_exp_month'=>$expMonth,
'card_exp_year'=>$expYear,
'card_cvv2'=>$cvv,

'payer_first_name'=>'Test',
'payer_last_name'=>'Alasef',
'payer_address'=>'3 Third Ave',
'payer_country'=>'US',
'payer_city'=>'Melrose',
'payer_zip'=>'01533',
'payer_email'=>$payerEmail,
'payer_phone'=>'6035484534',
'payer_ip'=>'163.252.215.185',

'term_url_3ds'=>$termUrl3ds,
'term_url_target'=>$termTarget,

'auth'=>'Y',
'req_token'=>'Y',

'hash'=>$hashStep1

];

$resStep1=do_post_form($endpoint,$reqStep1);

$cardToken='';

if(is_array($resStep1['json']))
$cardToken=$resStep1['json']['card_token']??'';


// ================= STEP 2 =================

$orderIdPay='TOKEN_PAY_'.time();

$hashSource2=strrev($payerEmail).$secret.strrev($cardToken);
$hashStep2=$cardToken?md5(strtoupper($hashSource2)):'';

$reqStep2=[

'action'=>'SALE',
'client_key'=>$merchantKey,

'order_id'=>$orderIdPay,
'order_amount'=>'0.99',
'order_currency'=>'USD',
'order_description'=>'Payment by card_token',

'card_token'=>$cardToken,

'payer_first_name'=>'Test',
'payer_last_name'=>'Alasef',
'payer_address'=>'3 Third Ave',
'payer_country'=>'US',
'payer_city'=>'Melrose',
'payer_zip'=>'01533',
'payer_email'=>$payerEmail,
'payer_phone'=>'6035484534',
'payer_ip'=>'163.252.215.185',

'term_url_3ds'=>$termUrl3ds,
'term_url_target'=>$termTarget,

'hash'=>$hashStep2

];

$resStep2=null;

if($cardToken)
$resStep2=do_post_form($endpoint,$reqStep2);

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Token → Pay Emulator</title>
<style>
body{font-family:monospace;background:#111;color:#eee;padding:20px}
pre{background:#000;padding:15px;border-radius:8px}
</style>
</head>
<body>

<h2>STEP1 REQUEST</h2>
<pre><?=j(mask($reqStep1))?></pre>

<h2>STEP1 HASH</h2>
<pre><?=j(['source'=>$hashSource1,'hash'=>$hashStep1])?></pre>

<h2>STEP1 RESPONSE</h2>
<pre><?=j($resStep1)?></pre>

<h2>STEP2 REQUEST</h2>
<pre><?=j(mask($reqStep2))?></pre>

<h2>STEP2 HASH</h2>
<pre><?=j(['source'=>$hashSource2,'hash'=>$hashStep2])?></pre>

<h2>STEP2 RESPONSE</h2>
<pre><?=j($resStep2)?></pre>

</body>
</html>