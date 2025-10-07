<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

/*
  Payment success page — shows confirmation and order details
  - Reads order_id & trans_id from query
  - Fetches order from session (saved in shop_checkout.php at SALE time)
  - Displays cart lines using the same PRODUCTS list (for titles/prices)
*/

/* (Опційно) — дублюємо каталог для відображення назв/цін у деталях */
$IMAGES = []; // не потрібні тут, але змінна лишена для сумісності
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

$oid = $_GET['order_id'] ?? '';
$tid = $_GET['trans_id'] ?? '';
$order = $oid && isset($_SESSION['orders'][$oid]) ? $_SESSION['orders'][$oid] : null;

// Порахувати суму по позиціях (для відображення)
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
$sumCalc = money2($sumCalc);
$amountShown = $order['amount'] ?? $sumCalc;
$currency = $order['currency'] ?? 'SLE';
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
.small{color:var(--muted)}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:var(--accent);color:#06281d;font-weight:700}
.line{display:flex;justify-content:space-between;border-bottom:1px dashed rgba(255,255,255,.06);padding:8px 0}
.btn{display:inline-block;padding:10px 14px;border-radius:8px;background:var(--accent2);color:#fff;text-decoration:none;margin-right:8px}
.table{width:100%;border-collapse:collapse;margin-top:10px}
.table th,.table td{padding:8px;border-bottom:1px dashed rgba(255,255,255,.08);text-align:left}
.totalRow td{font-weight:700}
.note{margin-top:12px}
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
      <div class="line"><div>Payer phone</div><div><?=h($order['phone'])?></div></div>
      <div class="line" style="border-bottom:0"><div>Created at</div><div><?=date('Y-m-d H:i:s', $order['created_at'])?></div></div>

      <?php if (!empty($lines)): ?>
        <h2 style="font-size:16px;margin:16px 0 6px 0">Order items</h2>
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
        Save these details for your records. If you need a downloadable invoice/receipt — скажи, згенеруємо PDF.
      </div>
    <?php else: ?>
      <div class="small">Sorry, we couldn’t find order details in this session.</div>
      <div style="margin-top:12px">
        <a class="btn" href="shop_checkout.php?view=catalog">Back to shop</a>
      </div>
      <div class="panel small" style="margin-top:12px">
        order_id: <?=h($oid)?> <br>
        trans_id: <?=h($tid)?>
      </div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
