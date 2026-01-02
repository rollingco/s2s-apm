<?php
/**
 * SALE Emulator — Human Readable Request + Headers + 3DS Button
 */

// ================= CONFIG =================
$endpoint    = 'https://api.leogcltd.com/post';

$merchantKey = 'cef92030-e7c1-11f0-a03f-26da8de1cc77';
$secret      = 'ce137c3bd39d264f552bf3a0e316823a';

// Card
$cardNumber  = '4111111111111111';
$expMonth    = '01';
$expYear     = '2038';
$cvv         = '123';

// Payer
$email       = 'john.doe@example.com';

// Order
$orderId     = 'ORDER_' . date('Ymd_His');
$amount      = '10.50';
$currency    = 'USD';
$desc        = 'Test purchase';

$termUrl3ds  = 'https://merchant.example.com/3ds-return';
$authOnly    = 'N';

// ================= HELPERS =================
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }

function maskCard($pan) {
  return substr($pan,0,6).'******'.substr($pan,-4);
}

function renderTable(array $rows): string {
  $html = '<table class="tbl">';
  foreach ($rows as $k => $v) {
    if (is_array($v)) {
      $v = json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    $html .= "<tr><th>".h($k)."</th><td><pre>".h($v)."</pre></td></tr>";
  }
  return $html.'</table>';
}

// ================= HASH =================
$first6 = substr($cardNumber,0,6);
$last4  = substr($cardNumber,-4);

$hashSource = strrev($email).$secret.strrev($first6.$last4);
$hash = md5(strtoupper($hashSource));

// ================= REQUEST =================
$request = [
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
  'auth'              => $authOnly,
  'hash'              => $hash,
];

// ================= EXECUTE =================
$headers = [
  'Content-Type: application/x-www-form-urlencoded',
];

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => http_build_query($request),
  CURLOPT_HTTPHEADER     => $headers,
]);

$responseRaw = curl_exec($ch);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$response = json_decode($responseRaw, true);

// ================= OUTPUT =================
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>SALE Emulator</title>
<style>
body { background:#0b0f17; color:#e7eefc; font-family:system-ui; }
.wrap { max-width:1100px; margin:20px auto; }
.card { background:#111a2b; border:1px solid #23304a; border-radius:14px; padding:16px; margin-bottom:16px; }
h2 { margin-top:0; }
.tbl { width:100%; border-collapse:collapse; }
.tbl th { width:260px; background:#0b1222; padding:10px; text-align:left; }
.tbl td { padding:10px; }
pre { margin:0; white-space:pre-wrap; }
.btn { background:#2a4ec2; color:#fff; border:none; padding:10px 16px; border-radius:10px; font-weight:bold; cursor:pointer; }
</style>
</head>
<body>
<div class="wrap">

<div class="card">
<h2>HTTP Request — readable</h2>
<?=renderTable([
  'Method'   => 'POST',
  'Endpoint' => $endpoint,
  'Action'   => 'SALE',
  'Amount'   => "$amount $currency",
  'Order ID' => $orderId,
])?>
</div>

<div class="card">
<h2>HTTP Headers</h2>
<pre><?=implode("\n",$headers)?></pre>
</div>

<div class="card">
<h2>Request Parameters (readable, masked)</h2>
<?php
$masked = $request;
$masked['card_number'] = maskCard($cardNumber);
$masked['card_cvv2'] = '***';
echo renderTable($masked);
?>
</div>

<div class="card">
<h2>Raw HTTP Body (debug)</h2>
<pre><?=h(http_build_query($request))?></pre>
</div>

<div class="card">
<h2>Response (HTTP <?=$httpCode?>)</h2>
<pre><?=h($responseRaw)?></pre>
</div>

<?php if (($response['result'] ?? '') === 'REDIRECT'): ?>
<div class="card">
<h2>3DS Redirect</h2>
<?=renderTable($response)?>
<form method="post" action="<?=h($response['redirect_url'])?>">
<?php foreach ($response['redirect_params'] as $k=>$v): ?>
<input type="hidden" name="<?=h($k)?>" value="<?=h($v)?>">
<?php endforeach; ?>
<button class="btn">Continue 3DS</button>
</form>
</div>
<?php endif; ?>

</div>
</body>
</html>
