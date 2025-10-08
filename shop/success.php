<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

/*
  Payment success page — shows confirmation and order details + PAYMENT BRAND
  - Reads order_id & trans_id from query
  - Fetches order from session (saved in shop_checkout.php at SALE time)
  - Shows selected brand with logo (orange-money / afri-money)
*/

/* Catalog (titles/prices for rendering lines) */
$PRODUCTS = [
  1 => ['title' => 'Premium Dog Food 2kg',   'price' => '12.50'],
  2 => ['title' => 'Cat Crunchies 1.5kg',    'price' => '9.40' ],
  3 => ['title' => 'Puppy Starter Pack 1kg', 'price' => '7.25' ],
  4 => ['title' => 'Senior Dog Mix 2kg',     'price' => '11.90'],
  5 => ['title' => 'Kitten Growth Mix 1kg',  'price' => '6.80' ],
  6 => ['title' => 'Grain-Free Dog Jerky',   'price' => '8.60' ],
  7 => ['title' => 'Cat Tuna Treats 400g',   'price' => '5.90' ],
  8 => ['title' => 'Dog Biscuits 900g',      'price' => '10.20'],
  9 => ['title' => 'Dental Chews (M) 10pcs', 'price' => '4.75' ],
 10 => ['title' => 'Cat Chicken Bites 500g', 'price' => '7.99' ],
];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function money2($n){ return number_format((float)$n, 2, '.', ''); }

/* Build asset base URL for this folder (e.g. /s2stest/shop/) */
$ASSET_URL = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/';
$BRANDS = [
  'orange-money' => ['title'=>'Orange Money','logo'=>$ASSET_URL.'Orange_Money-Logo.wine.png','hint'=>'Sierra Leone • Orange'],
  'afri-money'   => ['title'=>'AfriMoney','logo'=>$ASSET_URL.'afrimoney.png','hint'=>'Sierra Leone • AfriCell'],
  'mobile-money' => ['title'=>'Mobile Money (M-Pesa)','logo'=>$ASSET_URL.'mpesa-logo.png','hint'=>'Web checkout demo'],
  'card'         => ['title'=>'Card','logo'=>$ASSET_URL.'card-logo-png-transparent-png.png','hint'=>'Web checkout demo'],
];


$oid   = $_GET['order_id'] ?? '';
$tid   = $_GET['trans_id'] ?? '';
$order = $oid && isset($_SESSION['orders'][$oid]) ? $_SESSION['orders'][$oid] : null;

/* Calculate cart lines */
$lines = [];
$sumCalc = 0.00;
if ($order && !empty($order['cart']) && is_array($order['cart'])) {
  foreach ($order['cart'] as $pid => $qty) {
    if (!isset($PRODUCTS[$pid])) continue;
    $title = $PRODUCTS[$pid]['title'];
    $price = (float)$PRODUCTS[$pid]['price'];
    $qty   = (int)$qty;
    $lineTotal = $price * $qty;
    $sumCalc += $lineTotal;
    $lines[] = [
      'title' => $title,
      'qty'   => $qty,
      'price' => money2($price),
      'total' => money2($lineTotal),
    ];
  }
}
$sumCalc      = money2($sumCalc);
$amountShown  = $order['amount']   ?? $sumCalc;
$currency     = $order['currency'] ?? 'SLE';
$brandKey     = $order['brand']    ?? '';
$brandMeta    = $BRANDS[$brandKey] ?? null;
$createdAt    = !empty($order['created_at']) ? date('Y-m-d H:i:s', (int)$order['created_at']) : '-';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Payment Successful</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--accent:#10b981;--accent2:#2b7cff}
*{box-sizing:border-box}
body{background:var(--bg);color:var(--text);font:14px/1.5 ui-monospace,Menlo,Consolas,monospace;margin:0}
.wrap{max-width:900px;margin:0 auto;padding:22px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:16px;margin:16px 0}
h1{font-size:18px;margin:0 0 10px}
h2{font-size:16px;margin:16px 0 6px 0}
.small{color:var(--muted)}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:var(--accent);color:#06281d;font-weight:700}
.line{display:flex;justify-content:space-between;align-items:center;border-bottom:1px dashed rgba(255,255,255,.06);padding:8px 0}
.btn{display:inline-block;padding:10px 14px;border-radius:8px;background:var(--accent2);color:#fff;text-decoration:none;margin-right:8px}
.table{width:100%;border-collapse:collapse;margin-top:10px}
.table th,.table td{padding:8px;border-bottom:1px dashed rgba(255,255,255,.08);text-align:left}
.totalRow td{font-weight:700}
.note{margin-top:12px}

/* brand pill */
/* brand pill */
.brand-pill{display:inline-flex;align-items:center;gap:14px;padding:12px 16px;border:1px solid #2a2f3a;border-radius:16px;background:#11131a}
.brand-pill img{
  height:126px;   /* велике лого */
  width:auto;
  max-width:260px;
  object-fit:contain;
}
.brand-meta{display:flex;flex-direction:column}
.brand-title{font-weight:700}
.brand-hint{font-size:12px;color:var(--muted);margin-top:2px}

@media (max-width:640px){
  .brand-pill img{height:88px;max-width:200px}
}

</style>
</head>
<body>
<div class="wrap">

  <div class="panel">
    <h1>Payment status: <span class="badge">SUCCESS</span></h1>

    <?php if ($order): ?>
      <div class="line"><div>Order ID</div><div><?=h($order['order_id'])?></div></div>
      <div class="line"><div>Transaction ID</div><div><?=h($tid)?></div></div>
      <div class="line"><div>Amount</div><div><?=h($amountShown . ' ' . $currency)?></div></div>
      <div class="line"><div>Payer phone</div><div><?=h($order['phone'] ?? '')?></div></div>
      <div class="line"><div>Created at</div><div><?=h($createdAt)?></div></div>

      <!-- Payment brand -->
      <div class="line" style="border-bottom:0">
        <div>Payment method</div>
        <div>
          <?php if ($brandMeta): ?>
            <span class="brand-pill">
              <img src="<?=h($brandMeta['logo'])?>" alt="<?=h($brandMeta['title'])?>">
              <span class="brand-meta">
                <span class="brand-title"><?=h($brandMeta['title'])?></span>
                <span class="brand-hint"><?=h($brandMeta['hint'])?></span>
              </span>
            </span>
          <?php else: ?>
            <span class="small"><?=h($brandKey !== '' ? $brandKey : 'Unknown')?></span>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!empty($lines)): ?>
        <h2>Order items</h2>
        <table class="table">
          <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
          <tbody>
          <?php foreach ($lines as $ln): ?>
            <tr>
              <td><?=h($ln['title'])?></td>
              <td><?=h($ln['qty'])?></td>
              <td><?=h($ln['price'])?> <?=h($currency)?></td>
              <td><?=h($ln['total'])?> <?=h($currency)?></td>
            </tr>
          <?php endforeach; ?>
          <tr class="totalRow">
            <td colspan="3">Cart total (by items)</td>
            <td><?=h($sumCalc)?> <?=h($currency)?></td>
          </tr>
          </tbody>
        </table>
      <?php endif; ?>

      <div style="margin-top:14px">
        <a class="btn" href="shop_checkout.php?view=catalog">Continue shopping</a>
        <a class="btn" href="shop_checkout.php?view=cart">View cart</a>
      </div>

      <div class="note small">
        Save these details for your records. If a downloadable invoice is needed — say, and a PDF will be generated.
      </div>
    <?php else: ?>
      <div class="small">Sorry, order details were not found in this session.</div>
      <div style="margin-top:12px">
        <a class="btn" href="shop_checkout.php?view=catalog">Back to shop</a>
      </div>
      <div class="panel small" style="margin-top:12px">
        order_id: <?=h($oid)?><br>
        trans_id: <?=h($tid)?>
      </div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
