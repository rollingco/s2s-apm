<?php
/**
 * S2S APM SALE — BanffyPay Tanzania example
 */

header('Content-Type: text/html; charset=utf-8');

$PAYMENT_URL = 'https://api.leogcltd.com/post-va';

$CLIENT_KEY = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET     = '554999c284e9f29cf95f090d9a8f3171';

$DEFAULTS = [
  'brand'        => 'leogc-bannf',
  'identifier'   => '111',
  'currency'     => 'TZS',
  'return_url'   => 'https://google.com',
  'country'      => 'Tanzania',
  'provider'     => 'Airtel',
  'payment_code' => '', // TODO: confirm with Service Desk
  'phone'        => '255683456789',
  'amount'       => '1000.00',
];

$providers = [
  'Airtel'  => '255683456789',
  'Vodacom' => '255763456789',
  'Tigo'    => '255713456789',
  'Halotel' => '255623456789',
];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function pretty($v){
  if (is_string($v)) {
    $d = json_decode($v, true);
    if (json_last_error() === JSON_ERROR_NONE) $v = $d;
    else return h($v);
  }
  return h(json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function build_sale_hash($identifier, $order_id, $amount, $currency, $secret, &$srcOut = null){
  $src = $identifier . $order_id . $amount . $currency . $secret;
  if ($srcOut !== null) $srcOut = $src;
  return md5(strtoupper(strrev($src)));
}

$submitted = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

$payer_phone  = $DEFAULTS['phone'];
$order_amt    = $DEFAULTS['amount'];
$provider     = $DEFAULTS['provider'];
$payment_code = $DEFAULTS['payment_code'];

$debug = [];
$responseBlocks = ['bodyRaw' => '', 'json' => null];

if ($submitted) {
  $provider = $_POST['provider'] ?? $DEFAULTS['provider'];
  $payer_phone = preg_replace('/\s+/', '', $_POST['phone'] ?? '');
  $payer_phone = ltrim($payer_phone, '+');

  $rawAmt = preg_replace('/[^0-9.]/', '', $_POST['amount'] ?? '');
  $order_amt = number_format((float)$rawAmt, 2, '.', '');

  $payment_code = trim($_POST['payment_code'] ?? '');

  $errors = [];

  if (!isset($providers[$provider])) $errors[] = 'Invalid provider.';
  if ($payer_phone === '') $errors[] = 'Phone / MSISDN is required for Tanzania mobile money.';
  if (!is_numeric($order_amt) || (float)$order_amt <= 0) $errors[] = 'Amount must be a positive number.';

  if (!$errors) {
    $order_id   = 'TZ_ORDER_' . time();
    $order_desc = 'BanffyPay Tanzania APM payment';
    $payer_ip   = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $hash_src_dbg = '';
    $hash = build_sale_hash(
      $DEFAULTS['identifier'],
      $order_id,
      $order_amt,
      $DEFAULTS['currency'],
      $SECRET,
      $hash_src_dbg
    );

    $form = [
      'action'            => 'SALE',
      'client_key'        => $CLIENT_KEY,
      'brand'             => $DEFAULTS['brand'],

      'order_id'          => $order_id,
      'order_amount'      => $order_amt,
      'order_currency'    => $DEFAULTS['currency'],
      'order_description' => $order_desc,

      'identifier'        => $DEFAULTS['identifier'],
      'payer_ip'          => $payer_ip,
      'return_url'        => $DEFAULTS['return_url'],

      // BanffyPay-specific fields
      'country'           => $DEFAULTS['country'],
      'provider'          => $provider,
      'payment_code'      => $payment_code,

      // MSISDN / phone
      'payer_phone'       => $payer_phone,
      'msisdn'            => $payer_phone,

      'hash'              => $hash,
    ];

    $debug = [
      'endpoint' => $PAYMENT_URL,
      'form'     => $form,
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

    $raw = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_errno($ch) ? curl_error($ch) : '';
    curl_close($ch);

    $debug['http_code'] = (int)($info['http_code'] ?? 0);
    if ($err) $debug['curl_error'] = $err;

    $responseBlocks['bodyRaw'] = (string)$raw;
    $json = json_decode($responseBlocks['bodyRaw'], true);
    if (json_last_error() === JSON_ERROR_NONE) {
      $responseBlocks['json'] = $json;
    }
  }
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>BanffyPay Tanzania SALE</title>
<style>
body{background:#0f1115;color:#e6e6e6;font:14px/1.45 monospace;margin:0}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.panel{background:#171923;border:1px solid #2a2f3a;border-radius:12px;padding:14px 16px;margin:14px 0}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
input,select{padding:8px 10px;border-radius:8px;background:#11131a;color:#e6e6e6;border:1px solid #2a2f3a}
label{display:inline-block;min-width:140px}
button{padding:10px 14px;border-radius:10px;background:#2b7cff;color:#fff;border:0}
</style>
</head>
<body>
<div class="wrap">

<div class="panel">
  <h3>Create SALE — Tanzania / BanffyPay</h3>

  <form method="post">
    <div>
      <label>Provider:</label>
      <select name="provider">
        <?php foreach ($providers as $p => $samplePhone): ?>
          <option value="<?=h($p)?>" <?=($provider === $p ? 'selected' : '')?>>
            <?=h($p)?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <br>

    <div>
      <label>Phone / MSISDN:</label>
      <input type="text" name="phone" value="<?=h($payer_phone)?>">
    </div>

    <br>

    <div>
      <label>Amount:</label>
      <input type="text" name="amount" value="<?=h($order_amt)?>">
    </div>

    <br>

    <div>
      <label>Payment Code:</label>
      <input type="text" name="payment_code" value="<?=h($payment_code)?>" placeholder="Confirm with Service Desk">
    </div>

    <br>

    <button type="submit">Send SALE</button>
  </form>
</div>

<?php if (!empty($debug)): ?>
<div class="panel">
  <h3>Sent form-data</h3>
  <pre><?=pretty($debug['form'])?></pre>
</div>

<div class="panel">
  <h3>Hash</h3>
  <pre><?=h($debug['hash_src'])?></pre>
  <pre><?=h($debug['hash'])?></pre>
</div>

<div class="panel">
  <h3>Response</h3>
  <pre><?=pretty($responseBlocks['bodyRaw'])?></pre>
</div>
<?php endif; ?>

</div>
</body>
</html>