<?php
/**
 * CREDITVOID Emulator — READABLE (Refund / Reversal)
 *
 * Hash formula (Formula 2):
 * md5(strtoupper(strrev(email).PASSWORD.trans_id.strrev(first6+last4)))
 */

$endpoint = 'https://api.leogcltd.com/post';

$merchantKey = 'a9375190-26f2-11f0-be42-022c42254708';
$secret      = '554999c284e9f29cf95f090d9a8f3171';

// ===================== ORIGINAL TRANSACTION =====================

$transId = 'e5098d62-6de8-11eb-9da3-0242ac120013'; // trans_id from SALE response

// card used in original payment
$cardNumber = '4111111111111111';

// payer email used in SALE
$payerEmail = 'jon.doe@gmail.com';

// refund amount (optional)
// comment this line for full refund
$amount = '0.50';

// ===================== HELPERS =====================

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pretty_json($data): string {
    if (is_string($data)) {
        $decoded = json_decode($data, true);
        if (is_array($decoded)) {
            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        return $data;
    }
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function mask_pan($pan){
    return substr($pan,0,6).'******'.substr($pan,-4);
}

// ===================== HASH =====================

$first6 = substr($cardNumber,0,6);
$last4  = substr($cardNumber,-4);

$hashSource =
      strrev($payerEmail)
    . $secret
    . $transId
    . strrev($first6.$last4);

$hash = md5(strtoupper($hashSource));

// ===================== REQUEST =====================

$requestFields = [

    'action'     => 'CREDITVOID',
    'client_key' => $merchantKey,
    'trans_id'   => $transId,

    'hash'       => $hash,
];

// optional partial refund
if(isset($amount)){
    $requestFields['amount'] = $amount;
}

$formBody = http_build_query($requestFields);

// ===================== HEADERS =====================

$headers = [
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json'
];

// ===================== CURL =====================

$start = microtime(true);

$ch = curl_init($endpoint);

curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $formBody,
    CURLOPT_HTTPHEADER => $headers
]);

$response = curl_exec($ch);
$err      = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

$time = round((microtime(true)-$start)*1000);

// ===================== OUTPUT =====================
?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>CREDITVOID Test</title>

<style>
body{
font-family:system-ui;
background:#0b0f17;
color:#fff;
margin:20px;
}

.card{
background:#111a2b;
padding:16px;
margin-bottom:15px;
border-radius:12px;
}

pre{
background:#0b1222;
padding:12px;
border-radius:8px;
overflow:auto;
}
</style>

</head>
<body>

<div class="card">
<h2>CREDITVOID request</h2>

<b>HTTP:</b> <?=h($httpCode)?> <br>
<b>Time:</b> <?=$time?> ms
</div>


<div class="card">
<h3>Request JSON</h3>

<pre><?=pretty_json([
'action'=>'CREDITVOID',
'client_key'=>$merchantKey,
'trans_id'=>$transId,
'amount'=>$amount ?? 'FULL',
'card'=>mask_pan($cardNumber)
])?></pre>

</div>


<div class="card">
<h3>Signature debug</h3>

<pre><?=pretty_json([
'email'=>$payerEmail,
'first6'=>$first6,
'last4'=>$last4,
'hash_source'=>$hashSource,
'hash'=>$hash
])?></pre>

</div>


<div class="card">
<h3>Raw request body</h3>

<pre><?=h($formBody)?></pre>
</div>


<div class="card">
<h3>Response</h3>

<pre><?=pretty_json($response)?></pre>
</div>


</body>
</html>