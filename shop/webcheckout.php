<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

/*
  webcheckout.php — MOCK Web Checkout for KE/NG
  - Accepts POST from shop_checkout.php with: country, brand, phone, amount
  - Shows brand logo/title (M-Pesa, Airtel Money, MTN MoMo)
  - Simulates outcomes: Approved / Pending / Failed
  - On Approved -> saves order to $_SESSION (like real SALE) + link to success.php
*/

$ASSET_URL = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/';
$BRANDS_ALL = [
  'm-pesa' => [
    'title' => 'M-Pesa',
    'logo'  => $ASSET_URL.'mpesa-logo.png',
    'hint'  => 'Kenya • Safaricom',
  ],
  'airtel-money' => [
    'title' => 'Airtel Money',
    'logo'  => $ASSET_URL.'airtel-money-logo-sim-company-icon-transparent-background-free-png.webp',
    'hint'  => 'Kenya • Airtel',
  ],
  'mtn-momo' => [
    'title' => 'MTN MoMo',
    'logo'  => $ASSET_URL.'mtn-logo-new.webp',
    'hint'  => 'Nigeria • MTN',
  ],
];

$ALLOWED = [
  'ke' => ['m-pesa','airtel-money'],
  'ng' => ['mtn-momo'],
];

/* Currency by country (for success page pretty view) */
$CURRENCY_BY_COUNTRY = [
  'ke' => 'KES',
  'ng' => 'NGN',
];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function err($m){ return '<div class="alert alert-err">'.$m.'</div>'; }
function ok($m){ return '<div class="alert alert-ok">'.$m.'</div>'; }
function warn($m){ return '<div class="alert alert-warn">'.$m.'</div>'; }

$country = strtolower(trim($_REQUEST['country'] ?? ''));
$brand   = strtolower(trim($_REQUEST['brand'] ?? ''));
$phone   = preg_replace('/\s+/', '', (string)($_REQUEST['phone'] ?? ''));
$amount  = number_format((float)preg_replace('/[^0-9.]/','', (string)($_REQUEST['amount'] ?? '0')), 2, '.', '');

$errors = [];
if (!in_array($country, ['ke','ng'], true)) $errors[] = 'This demo supports Kenya (KE) or Nigeria (NG) only.';
if (empty($ALLOWED[$country] ?? [])) $errors[] = 'No brands configured for selected country.';
if ($brand === '' || !in_array($brand, $ALLOWED[$country] ?? [], true)) $errors[] = 'Unsupported brand for selected country.';
if (!isset($BRANDS_ALL[$brand])) $errors[] = 'Unknown brand meta.';
if ($amount <= 0) $errors[] = 'Amount must be positive.';
if ($phone === '') $errors[] = 'Phone is required.';

$simulate = $_POST['simulate'] ?? '';

/* Prepare mock ids */
$order_id = 'ORDER_' . time();
$trans_id = 'T' . time() . rand(100,999);

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Web Checkout — Mock (<?=strtoupper(h($country))?>)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--accent:#2b7cff;--ok:#22c55e;--warn:#eab308;--err:#ef4444}
*{box-sizing:border-box}
body{background:var(--bg);color:var(--text);font:14px/1.5 ui-monospace,Menlo,Consolas,monospace;margin:0}
.wrap{max-width:900px;margin:0 auto;padding:22px}
.header{display:flex;align-items:center;justify-content:space-between;padding:14px 0}
.brand{display:flex;align-items:center;gap:10px}
.logo{width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg,#3b82f6,#06b6d4)}
.brand h1{font-size:16px;margin:0}
.nav a{color:#fff;text-decoration:none;margin-left:14px;opacity:.7}
.nav a:hover{opacity:1}
.panel{background:#11131a;border:1px solid #232635;border-radius:12px;padding:16px;margin:14px 0}
.section-title{margin:0 0 10px 0;font-weight:700}
.row{display:flex;gap:18px;flex-wrap:wrap;align-items:center}
.pm-logo{height:126px;width:auto;max-width:280px;object-fit:contain}
.kv{display:grid;grid-template-columns:160px 1fr;gap:6px;margin:10px 0}
.kv div{padding:4px 0;border-bottom:1px dashed rgba(255,255,255,.06)}
.btn{display:inline-block;padding:10px 16px;border-radius:10px;background:var(--accent);color:#fff;text-decoration:none;border:0;cursor:pointer}
.btn.sec{background:#2f3543}
.btn.ok{background:var(--ok)}
.btn.warn{background:var(--warn);color:#111}
.btn.err{background:var(--err)}
.alert{padding:10px 12px;border-radius:10px;margin:10px 0;border:1px solid #2a2f3a}
.alert-ok{border-color:#14532d;background:#052e16;color:#a7f3d0}
.alert-warn{border-color:#7a5a0a;background:#1f1a07;color:#fde68a}
.alert-err{border-color:#7f1d1d;background:#2f0a0a;color:#fecaca}
.small{color:#9aa4af;font-size:12px}
.footer{margin-top:14px}
@media(max-width:640px){.kv{grid-template-columns:120px 1fr}}
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <div class="brand">
      <div class="logo"></div>
      <h1>Mock Web Checkout</h1>
    </div>
    <div class="nav">
      <a href="shop_checkout.php?view=cart">← Back to cart</a>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="panel">
      <h3 class="section-title">Input errors</h3>
      <ul class="small" style="margin:0 0 8px 18px">
        <?php foreach ($errors as $e): ?><li><?=h($e)?></li><?php endforeach; ?>
      </ul>
      <a class="btn sec" href="shop_checkout.php?view=checkout">Back</a>
    </div>
  <?php else:
    $meta = $BRANDS_ALL[$brand];
  ?>
    <div class="panel">
      <div class="row">
        <?php if (!empty($meta['logo'])): ?>
          <img class="pm-logo" src="<?=h($meta['logo'])?>" alt="<?=h($meta['title'])?>">
        <?php endif; ?>
        <div>
          <h3 class="section-title"><?=h($meta['title'])?></h3>
          <div class="small"><?=h($meta['hint'])?> • Country: <strong><?=strtoupper(h($country))?></strong></div>
        </div>
      </div>

      <div class="kv">
        <div class="small">Amount</div><div><strong><?=h($amount)?></strong> <?=h($CURRENCY_BY_COUNTRY[$country] ?? '')?></div>
        <div class="small">Phone</div><div><?=h($phone)?></div>
        <div class="small">Brand</div><div><?=h($meta['title'])?></div>
      </div>

      <?php
        if ($simulate === 'approved') {
          // ✅ Save minimal order into session so success.php can show details
          if (!isset($_SESSION['orders'])) $_SESSION['orders'] = [];
          if (!isset($_SESSION['orders_by_tid'])) $_SESSION['orders_by_tid'] = [];

          $_SESSION['orders'][$order_id] = [
            'order_id'   => $order_id,
            'cart'       => $_SESSION['cart'] ?? [],
            'amount'     => $amount,
            'currency'   => $CURRENCY_BY_COUNTRY[$country] ?? '',
            'phone'      => $phone,
            'brand'      => $brand,          // key (m-pesa / airtel-money / mtn-momo)
            'created_at' => time(),
            'trans_id'   => $trans_id,
          ];
          $_SESSION['orders_by_tid'][$trans_id] = $order_id;

          echo ok('Payment approved (mock).');
          $success_link = 'success.php?order_id='.urlencode($order_id).'&trans_id='.urlencode($trans_id);
          echo '<div class="row" style="margin-top:10px">';
          echo '<a class="btn ok" href="'.h($success_link).'" target="_blank">Open success page</a>';
          echo '<a class="btn sec" href="shop_checkout.php?view=catalog">Continue shopping</a>';
          echo '</div>';
        } elseif ($simulate === 'pending') {
          echo warn('Payment pending (mock). Ask user to confirm in wallet (PIN/SMS/USSD).');
          echo '<div class="row" style="margin-top:10px">';
          echo '<form method="post">';
          foreach (['country'=>$country,'brand'=>$brand,'phone'=>$phone,'amount'=>$amount] as $k=>$v) {
            echo '<input type="hidden" name="'.h($k).'" value="'.h($v).'">';
          }
          echo '<button class="btn ok" name="simulate" value="approved" type="submit">Approve now</button>';
          echo '<button class="btn err" name="simulate" value="failed" type="submit">Fail</button>';
          echo '</form>';
          echo '</div>';
        } elseif ($simulate === 'failed') {
          echo err('Payment failed (mock).');
          echo '<div class="row" style="margin-top:10px">';
          echo '<a class="btn sec" href="shop_checkout.php?view=checkout">Try again</a>';
          echo '</div>';
        } else {
          // first screen with action buttons
          ?>
          <div class="small" style="margin:10px 0">This is a mock page for demo purposes only.</div>
          <form method="post" class="row" style="gap:10px;margin-top:6px">
            <input type="hidden" name="country" value="<?=h($country)?>">
            <input type="hidden" name="brand"   value="<?=h($brand)?>">
            <input type="hidden" name="phone"   value="<?=h($phone)?>">
            <input type="hidden" name="amount"  value="<?=h($amount)?>">
            <button class="btn ok"   name="simulate" value="approved" type="submit">Simulate Approved</button>
            <button class="btn warn" name="simulate" value="pending"  type="submit">Simulate Pending</button>
            <button class="btn err"  name="simulate" value="failed"   type="submit">Simulate Failed</button>
          </form>
          <?php
        }
      ?>
    </div>

    <div class="footer small">
      SL uses real SALE; KE/NG use this Mock Web Checkout for UI demonstration only.
    </div>
  <?php endif; ?>

</div>
</body>
</html>
