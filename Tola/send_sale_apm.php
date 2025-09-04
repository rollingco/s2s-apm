<?php
/**
 * S2S APM SALE — working config + status shortcut
 * ✔ multipart/form-data + Basic Auth
 * ✔ only payer_phone (NO msisdn)
 * ✔ auto button: "Check status once" → status_once.php?trans_id=...
 */

header('Content-Type: text/html; charset=utf-8');

/* ===== CONFIG (current pair) ===== */
$PAYMENT_URL = 'https://api.leogcltd.com/post-va';

$CLIENT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708'; // актуальний client_key
$SECRET      = '554999c284e9f29cf95f090d9a8f3171';     // пароль/секрет для хешу SALE

$API_USER    = 'leogc';
$API_PASS    = 'ORuIO57N6KJyeJ';

/* ===== RUNTIME (можеш міняти через query) ===== */
$brand       = $_GET['brand']  ?? 'afri-money';
$identifier  = $_GET['id']     ?? '111';
$order_ccy   = $_GET['ccy']    ?? 'SLE';
$order_amt   = $_GET['amt']    ?? '100.00';
$return_url  = $_GET['return'] ?? 'https://google.com';
$payer_phone = $_GET['phone']  ?? '23233310905'; // нормальний номер

$order_id    = 'ORDER_' . time();
$order_desc  = 'Important gift';
$payer_ip    = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

/* ===== SALE signature (Appendix A):
   md5(strtoupper(strrev(identifier + order_id + amount + currency + SECRET))) ===== */
function build_sale_hash($identifier, $order_id, $amount, $currency, $secret){
    $src = $identifier . $order_id . $amount . $currency . $secret;
    return md5(strtoupper(strrev($src)));
}
$hash = build_sale_hash($identifier, $order_id, $order_amt, $order_ccy, $SECRET);

/* ===== multipart/form-data payload (не ставимо Content-Type вручну) ===== */
$form = [
  'action'            => 'SALE',
  'client_key'        => $CLIENT_KEY,
  'brand'             => $brand,
  'order_id'          => $order_id,
  'order_amount'      => $order_amt,
  'order_currency'    => $order_ccy,
  'order_description' => $order_desc,
  'identifier'        => $identifier,
  'payer_ip'          => $payer_ip,
  'return_url'        => $return_url,
  'payer_phone'       => $payer_phone,  // тільки це поле з номером
  'hash'              => $hash,
];

/* ===== request ===== */
$ch = curl_init($PAYMENT_URL);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => $form,                        // multipart/form-data
  CURLOPT_USERPWD        => $API_USER . ':' . $API_PASS,  // Basic Auth
  CURLOPT_HEADER         => true,
  CURLOPT_TIMEOUT        => 60,
]);
$start = microtime(true);
$raw = curl_exec($ch);
$info = curl_getinfo($ch);
$err  = curl_errno($ch) ? curl_error($ch) : '';
curl_close($ch);
$dur = number_format(microtime(true) - $start, 3, '.', '');

/* ===== split headers/body ===== */
$respHeaders = '';
$respBody    = '';
if ($raw !== false && isset($info['header_size'])) {
  $respHeaders = substr($raw, 0, $info['header_size']);
  $respBody    = substr($raw, $info['header_size']);
}
$data = json_decode($respBody, true);

/* ===== helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function pretty($v){
  if (is_string($v)) { $d=json_decode($v,true); if(json_last_error()===JSON_ERROR_NONE) $v=$d; else return h($v); }
  return h(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}

/* ===== URLs ===== */
$basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$statusOnceUrl = $basePath . '/status_once.php';
$self = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>SALE — working + status shortcut</title>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--info:#00d1d1}
html,body{background:var(--bg);color:var(--text);margin:0;font:14px/1.45 ui-monospace,Menlo,Consolas,monospace}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.h{font-weight:700;margin:10px 0 6px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:var(--muted)}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#2b7cff;color:#fff;text-decoration:none}
.btn:hover{opacity:.9}
.tag{display:inline-block;padding:2px 8px;border-radius:8px;font-size:12px;margin-right:6px}
.t-info{background:rgba(0,209,209,.12);color:var(--info)}
input[type=text]{padding:6px 8px;border-radius:8px;border:1px solid #2a2f3a;background:#11131a;color:#e6e6e6}
</style>
</head>
<body>
<div class="wrap">
  <div class="panel">
    <div class="h">🟢 SALE sent (working config)</div>
    <div><span class="kv">Endpoint:</span> <?=h($PAYMENT_URL)?></div>
    <div><span class="kv">Client key:</span> <?=h($CLIENT_KEY)?></div>
    <div><span class="kv">Order ID:</span> <?=h($order_id)?> &nbsp; <span class="kv">Phone (payer_phone):</span> <?=h($payer_phone)?> &nbsp; <span class="kv">Duration:</span> <?=h($dur)?>s</div>
    <?php if ($err): ?><div class="tag t-info">cURL: <?=h($err)?></div><?php endif; ?>
  </div>

  <div class="panel">
    <div class="h">➡ Sent form-data</div>
    <pre><?=pretty($form)?></pre>
  </div>

  <div class="panel">
    <div class="h">⬅ Response headers</div>
    <pre><?=h(trim($respHeaders))?></pre>
  </div>

  <div class="panel">
    <div class="h">⬅ Response body</div>
    <pre><?=pretty($respBody)?></pre>
    <?php if (is_array($data)): ?>
      <div class="h">Parsed</div>
      <pre><?=pretty($data)?></pre>
      <?php if (!empty($data['trans_id'])):
          $transId = urlencode($data['trans_id']);
          $statusLink = $statusOnceUrl . '?trans_id=' . $transId;
      ?>
        <p>
          <a class="btn" href="<?=h($statusLink)?>" target="_blank">
            ➡ Check status once (trans_id=<?=h($data['trans_id'])?>)
          </a>
        </p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="h">🧪 Test MSISDN playground</div>
    <div>Quick-run SALE з типовими тест-номерами у <code>payer_phone</code> (без поля <code>msisdn</code>).</div><br>
    <?php
      $cases = [
        'Success'            => '254000000000',
        'Insufficient funds' => '254000000005',
        'MSISDN invalid'     => '254000000013',
        'Rejected (default)' => '254000000099',
      ];
      $baseParams = [
        'brand'  => $brand,
        'id'     => $identifier,
        'ccy'    => $order_ccy,
        'amt'    => $order_amt,
        'return' => $return_url,
      ];
      foreach ($cases as $label => $num) {
        $url = $self.'?'.http_build_query(array_merge($baseParams, ['phone'=>$num]));
        echo '<a class="btn" href="'.h($url).'" target="_blank">'.h($label).' → '.h($num).'</a> ';
      }
    ?>
    <form action="<?=h($self)?>" method="get" style="margin-top:12px">
      <?php foreach ($baseParams as $k=>$v): ?>
        <input type="hidden" name="<?=h($k)?>" value="<?=h($v)?>">
      <?php endforeach; ?>
      <label>Custom payer_phone:
        <input type="text" name="phone" value="<?=h($payer_phone)?>">
      </label>
      <button class="btn" type="submit">Send SALE</button>
    </form>
  </div>
</div>
</body>
</html>
