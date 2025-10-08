<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

/*
  Mock Web Checkout for KE/NG
  - Accepts POST from shop_checkout.php: country (ke/ng), brand (mobile-money|card), phone, amount
  - Shows branded web page; on "Pay" creates DEMO order in session and redirects to success.php
*/

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function money2($n){ return number_format((float)$n, 2, '.', ''); }

$country = $_POST['country'] ?? $_GET['country'] ?? 'ke';
$brand   = $_POST['brand']   ?? $_GET['brand']   ?? 'mobile-money';
$phone   = trim($_POST['phone'] ?? $_GET['phone'] ?? '');
$amount  = money2($_POST['amount'] ?? $_GET['amount'] ?? '0');

$currencyByCountry = ['ke'=>'KES','ng'=>'NGN'];
$currency = $currencyByCountry[$country] ?? 'KES';

$ASSET_URL = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/';
$BRANDS = [
  'mobile-money' => ['title'=>'Mobile Money','logo'=>null],
  'card'         => ['title'=>'Card','logo'=>null],
];

$errors = [];
if (!in_array($country,['ke','ng'], true)) $errors[]='Unsupported country for web checkout.';
if (!in_array($brand,['mobile-money','card'], true)) $errors[]='Unsupported payment method.';
if ($amount <= 0) $errors[]='Amount must be positive.';
if ($phone === '') $errors[]='Phone is required.';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['do_pay']) && empty($errors)) {
  // Create DEMO order in session and redirect to success
  $order_id = 'DEMO_' . time();
  if (!isset($_SESSION['orders'])) $_SESSION['orders'] = [];
  $_SESSION['orders'][$order_id] = [
    'order_id'   => $order_id,
    'cart'       => $_SESSION['cart'] ?? [],
    'amount'     => $amount,
    'currency'   => $currency,
    'phone'      => $phone,
    'brand'      => $brand,
    'created_at' => time(),
  ];
  $trans_id = 'DEMO_TID_' . substr(md5($order_id),0,10);
  header('Location: success.php?order_id='.urlencode($order_id).'&trans_id='.urlencode($trans_id));
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Web Checkout (Demo)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--accent:#2b7cff;--ok:#10b981}
*{box-sizing:border-box}
body{background:var(--bg);color:var(--text);font:14px/1.5 ui-monospace,Menlo,Consolas,monospace;margin:0}
.wrap{max-width:560px;margin:0 auto;padding:22px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:16px;margin:16px 0}
.h{display:flex;justify-content:space-between;align-items:center}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:#2f3543}
.input{padding:10px;border-radius:8px;border:1px solid #2a2f3a;background:#11131a;color:var(--text);width:100%}
.btn{display:inline-block;padding:10px 14px;border-radius:8px;background:var(--accent);color:#fff;text-decoration:none;border:0;cursor:pointer}
.small{color:var(--muted)}
.err{color:#ff6b6b}
</style>
</head>
<body>
<div class="wrap">

  <div class="panel">
    <div class="h">
      <h1 style="margin:0;font-size:18px">Web Checkout (Demo)</h1>
      <span class="badge"><?=strtoupper(h($country))?> • <?=h($currency)?></span>
    </div>
    <div class="small" style="margin-top:4px">Method: <strong><?=h($BRANDS[$brand]['title'] ?? $brand)?></strong></div>
  </div>

  <div class="panel">
    <form method="post">
      <input type="hidden" name="country" value="<?=h($country)?>">
      <input type="hidden" name="brand"   value="<?=h($brand)?>">
      <div style="margin:8px 0">
        <label>Phone</label><br>
        <input class="input" type="text" name="phone" value="<?=h($phone)?>" placeholder="<?= $country==='ke'?'254700000000':'234800000000' ?>">
      </div>
      <div style="margin:8px 0">
        <label>Amount</label><br>
        <input class="input" type="text" name="amount" value="<?=h($amount)?>" readonly>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="err" style="margin:8px 0">
          <?php foreach ($errors as $e) echo '<div>'.h($e).'</div>'; ?>
        </div>
      <?php endif; ?>

      <div style="margin-top:12px">
        <button class="btn" name="do_pay" type="submit">Pay (simulate)</button>
        <a class="btn" style="background:#2f3543;margin-left:8px" href="shop_checkout.php?view=checkout">Back</a>
      </div>

      <div class="small" style="margin-top:10px">
        This page is UI-only to demonstrate the flow required by operators. No real payment is processed.
      </div>
    </form>
  </div>

</div>
</body>
</html>
