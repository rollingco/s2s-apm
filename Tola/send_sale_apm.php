<?php
/**
 * S2S APM SALE — minimal form (phone + amount) → full logs + status shortcut
 */

header('Content-Type: text/html; charset=utf-8');

/* ===================== CONFIG ===================== */
$PAYMENT_URL = 'https://api.leogcltd.com/post-va';
$CLIENT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET      = '554999c284e9f29cf95f090d9a8f3171';

$API_USER    = 'leogc';
$API_PASS    = 'ORuIO57N6KJyeJ';

$DEFAULTS = [
  'brand'       => 'afri-money',
  'identifier'  => '111',
  'currency'    => 'SLE',
  'return_url'  => 'https://google.com',
  'phone'       => '254000000000', // successful test case
  'amount'      => '100.00',
];

/* ===================== Helpers ===================== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function pretty($v){
  if (is_string($v)) { $d = json_decode($v,true); if (json_last_error()===JSON_ERROR_NONE) $v=$d; else return h($v); }
  return h(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}

/**
 * SALE signature (Appendix A):
 * md5( strtoupper( strrev( identifier + order_id + amount + currency + SECRET ) ) )
 */
function build_sale_hash($identifier, $order_id, $amount, $currency, $secret, &$src){
  $src = $identifier . $order_id . $amount . $currency . $secret;
  return md5(strtoupper(strrev($src)));
}

/* ===================== Read form ===================== */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$submitted = ($method === 'POST');

$brand       = $DEFAULTS['brand'];
$identifier  = $DEFAULTS['identifier'];
$order_ccy   = $DEFAULTS['currency'];
$return_url  = $DEFAULTS['return_url'];

if ($submitted) {
  $payer_phone = preg_replace('/\s+/', '', $_POST['phone'] ?? '');
  $payer_phone = ltrim($payer_phone, '+');
  $rawAmt = preg_replace('/[^0-9.]/', '', $_POST['amount'] ?? '');
  $order_amt = number_format((float)$rawAmt, 2, '.', '');
  $errors = [];
  if ($payer_phone === '') $errors[] = 'Phone is required.';
  if (!is_numeric($order_amt) || (float)$order_amt <= 0) $errors[] = 'Amount must be positive.';
  if (!empty($errors)) {
    render_page([
      'showForm'=>true,'errors'=>$errors,
      'prefill'=>['phone'=>$_POST['phone'] ?? $DEFAULTS['phone'],'amount'=>$_POST['amount'] ?? $DEFAULTS['amount']],
      'debug'=>[],'response'=>[],'statusLink'=>''
    ]);
    exit;
  }
} else {
  $payer_phone = $DEFAULTS['phone'];
  $order_amt   = $DEFAULTS['amount'];
}

/* ===================== Build SALE ===================== */
$debug = [];
$responseBlocks = ['headersRaw'=>'','bodyRaw'=>'','json'=>null];
$statusLink = '';

if ($submitted) {
  $order_id   = 'ORDER_' . time();
  $order_desc = 'APM payment';
  $payer_ip   = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

  $hash_src = '';
  $hash = build_sale_hash($identifier, $order_id, $order_amt, $order_ccy, $SECRET, $hash_src);

  $form = [
    'action'=>'SALE','client_key'=>$CLIENT_KEY,'brand'=>$brand,
    'order_id'=>$order_id,'order_amount'=>$order_amt,'order_currency'=>$order_ccy,
    'order_description'=>$order_desc,'identifier'=>$identifier,
    'payer_ip'=>$payer_ip,'return_url'=>$return_url,
    'payer_phone'=>$payer_phone,'hash'=>$hash,
  ];

  $debug['endpoint']=$PAYMENT_URL;
  $debug['client_key']=$CLIENT_KEY;
  $debug['order_id']=$order_id;
  $debug['form']=$form;
  $debug['hash_src']=$hash_src;
  $debug['hash']=$hash;

  $ch = curl_init($PAYMENT_URL);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>$form,
    CURLOPT_USERPWD=>$API_USER.':'.$API_PASS,
    CURLOPT_HEADER=>true,
    CURLOPT_TIMEOUT=>60,
  ]);
  $start=microtime(true);
  $raw=curl_exec($ch);
  $info=curl_getinfo($ch);
  $err=curl_errno($ch)?curl_error($ch):'';
  curl_close($ch);
  $dur=number_format(microtime(true)-$start,3,'.','');

  $debug['duration_sec']=$dur;
  $debug['http_code']=(int)($info['http_code'] ?? 0);
  $debug['curl_error']=$err;

  if ($raw!==false && isset($info['header_size'])) {
    $responseBlocks['headersRaw']=substr($raw,0,$info['header_size']);
    $responseBlocks['bodyRaw']=substr($raw,$info['header_size']);
  } else {
    $responseBlocks['bodyRaw']=(string)$raw;
  }

  $json=json_decode($responseBlocks['bodyRaw'],true);
  if (json_last_error()===JSON_ERROR_NONE) {
    $responseBlocks['json']=$json;
    if (!empty($json['trans_id'])) {
      $basePath=rtrim(dirname($_SERVER['PHP_SELF']),'/');
      $statusOnce=$basePath.'/status_once.php';
      $statusLink=$statusOnce.'?trans_id='.urlencode($json['trans_id']);
    }
  }
}

/* ===================== Render ===================== */
render_page([
  'showForm'=>true,'errors'=>[],'prefill'=>['phone'=>$payer_phone,'amount'=>$order_amt],
  'debug'=>$debug,'response'=>$responseBlocks,'statusLink'=>$statusLink
]);

/* ---------------------- View ---------------------- */
function render_page($ctx){
  $self=(isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
  $debug=$ctx['debug']; $resp=$ctx['response']; $statusLink=$ctx['statusLink'];
  ?>
<!doctype html><html lang="en"><head><meta charset="utf-8">
<title>SALE — phone+amount → logs</title>
<style>body{background:#111;color:#eee;font:14px monospace}pre{background:#222;padding:10px;border-radius:8px}</style>
</head><body>
<h2>Create SALE</h2>
<form action="<?=h($self)?>" method="post">
  Phone: <input type="text" name="phone" value="<?=h($ctx['prefill']['phone'])?>"><br>
  Amount: <input type="text" name="amount" value="<?=h($ctx['prefill']['amount'])?>"><br>
  <button type="submit">Send SALE</button>
</form>

<?php if ($debug): ?>
<h3>Hash calculation</h3>
<p>Source: identifier+order_id+amount+currency+SECRET =<br><pre><?=h($debug['hash_src'])?></pre></p>
<p>Final md5(strtoupper(strrev(src))): <b><?=h($debug['hash'])?></b></p>

<h3>Sent form-data</h3>
<pre><?=pretty($debug['form'])?></pre>

<h3>Response headers</h3>
<pre><?=h($resp['headersRaw'])?></pre>

<h3>Response body</h3>
<pre><?=pretty($resp['bodyRaw'])?></pre>

<?php if ($statusLink): ?><p><a href="<?=h($statusLink)?>" target="_blank">Check status (trans_id)</a></p><?php endif; ?>
<?php endif; ?>
</body></html>
<?php }
