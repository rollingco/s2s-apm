<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }

$order_id = (string)($_GET['order_id'] ?? '');
$trans_id = (string)($_GET['trans_id'] ?? '');

$order = $_SESSION['orders'][$order_id] ?? null;
$status = 'SUCCESS'; // у нас success page; якщо треба — зробимо універсально

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Payment result</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--ok:#22c55e}
*{box-sizing:border-box}
body{background:var(--bg);color:var(--text);font:14px/1.5 ui-monospace,Menlo,Consolas,monospace;margin:0}
.wrap{max-width:900px;margin:0 auto;padding:22px}
.panel{background:#11131a;border:1px solid #232635;border-radius:12px;padding:16px;margin:14px 0}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:#065f46}
.badge.ok{background:#065f46}
.small{color:#9aa4af}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#2b7cff;color:#fff;text-decoration:none;border:0}
.pre{background:#0c0f14;border:1px solid #232635;border-radius:10px;padding:12px;white-space:pre-wrap}
</style>
</head>
<body>
<div class="wrap">
  <div class="panel">
    <h3 style="margin:0 0 8px 0">Payment status: <span class="badge ok"><?=h($status)?></span></h3>

    <?php if (!$order): ?>
      <div class="small" style="margin:8px 0 14px 0">
        No order record found in this session. Showing URL parameters only.
      </div>
    <?php endif; ?>

    <a class="btn" href="shop_checkout.php?view=catalog">Back to shop</a>

    <div class="pre" style="margin-top:12px">
order_id: <?=h($order_id) . "\n" ?>
trans_id: <?=h($trans_id) . "\n" ?>
<?php if ($order): ?>
amount:   <?=h($order['amount']) . ' ' . h($order['currency'] ?? '') . "\n" ?>
brand:    <?=h($order['brand']) . "\n" ?>
phone:    <?=h($order['phone']) . "\n" ?>
<?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
