<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

/*
  Simple demo shop + checkout that wraps the existing SALE flow.
  - product list -> add to cart -> cart -> checkout (SALE)
  - uses same PAYMENT_URL / creds as send_sale_apm.php
  - no header logging, minimal debug (endpoint, form, response, trans_id)
*/

/* ============ CONFIG ============ */
$PAYMENT_URL = 'https://api.leogcltd.com/post-va';
$API_USER    = 'leogc';
$API_PASS    = 'ORuIO57N6KJyeJ';
$CLIENT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET      = '554999c284e9f29cf95f090d9a8f3171';
$IDENTIFIER  = '111';
$CURRENCY    = 'SLE';
$RETURN_URL  = 'https://google.com';

/* ============ PRODUCTS (demo) ============ */
$PRODUCTS = [
  1 => ['title' => 'Solar Lantern', 'price' => '12.50'],
  2 => ['title' => 'Portable Radio', 'price' => '8.99'],
  3 => ['title' => 'Water Filter', 'price' => '24.00'],
];

/* ============ helpers ============ */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function pretty($v){ if (is_string($v)) { $d=json_decode($v,true); if(json_last_error()===JSON_ERROR_NONE) $v=$d; else return h($v);} return h(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)); }
function build_sale_hash($identifier, $order_id, $amount, $currency, $secret, &$srcOut=null){
  $src = $identifier . $order_id . $amount . $currency . $secret;
  if ($srcOut !== null) $srcOut = $src;
  return md5(strtoupper(strrev($src)));
}

/* ============ CART actions ============ */
// init cart
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// add product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
  $pid = (int)($_POST['product_id'] ?? 0);
  if ($pid && isset($PRODUCTS[$pid])) {
    // increment qty
    if (!isset($_SESSION['cart'][$pid])) $_SESSION['cart'][$pid] = 0;
    $_SESSION['cart'][$pid] += 1;
  }
  header('Location: ' . $_SERVER['PHP_SELF']);
  exit;
}

// remove product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_cart'])) {
  $pid = (int)($_POST['product_id'] ?? 0);
  if ($pid && isset($_SESSION['cart'][$pid])) {
    unset($_SESSION['cart'][$pid]);
  }
  header('Location: ' . $_SERVER['PHP_SELF'] . '?view=cart');
  exit;
}

/* ============ Checkout (SALE) ============ */
$checkoutDebug = [];
$checkoutResp = ['bodyRaw' => '', 'json' => null];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
  // read buyer phone and maybe name
  $payer_phone = preg_replace('/\s+/', '', $_POST['phone'] ?? '');
  $payer_phone = ltrim($payer_phone, '+');
  $rawAmt = preg_replace('/[^0-9.]/', '', $_POST['amount'] ?? '0');
  $order_amt = number_format((float)$rawAmt, 2, '.', '');

  // basic validation
  $errors = [];
  if ($payer_phone === '') $errors[] = 'Phone is required.';
  if (!is_numeric($order_amt) || (float)$order_amt <= 0) $errors[] = 'Amount must be a positive number.';

  if (empty($errors)) {
    $order_id = 'ORDER_' . time();
    $order_desc = 'Purchase from demo shop';
    $payer_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $hash_src_dbg = '';
    $hash = build_sale_hash($IDENTIFIER, $order_id, $order_amt, $CURRENCY, $SECRET, $hash_src_dbg);

    $form = [
      'action'            => 'SALE',
      'client_key'        => $CLIENT_KEY,
      'brand'             => 'afri-money',   // demo brand; change as needed
      'order_id'          => $order_id,
      'order_amount'      => $order_amt,
      'order_currency'    => $CURRENCY,
      'order_description' => $order_desc,
      'identifier'        => $IDENTIFIER,
      'payer_ip'          => $payer_ip,
      'return_url'        => $RETURN_URL,
      'payer_phone'       => $payer_phone,
      'hash'              => $hash,
    ];

    $checkoutDebug = [
      'endpoint'   => $PAYMENT_URL,
      'client_key' => $CLIENT_KEY,
      'order_id'   => $order_id,
      'form'       => $form,
      'hash_src'   => $hash_src_dbg,
      'hash'       => $hash,
    ];

    // send request (application/x-www-form-urlencoded not required; here we send multipart via POSTFIELDS)
    $ch = curl_init($PAYMENT_URL);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => $form,
      CURLOPT_USERPWD        => $API_USER . ':' . $API_PASS,
      CURLOPT_TIMEOUT        => 60,
    ]);
    $raw = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_errno($ch) ? curl_error($ch) : '';
    curl_close($ch);

    $checkoutDebug['http_code'] = (int)($info['http_code'] ?? 0);
    if ($err) $checkoutDebug['curl_error'] = $err;

    $checkoutResp['bodyRaw'] = (string)$raw;
    $json = json_decode($checkoutResp['bodyRaw'], true);
    if (json_last_error() === JSON_ERROR_NONE) {
      $checkoutResp['json'] = $json;
      // clear cart if success? keep cart; up to you. Do not auto-clear.
    }
  } else {
    // validation errors: keep them for display
    $checkoutDebug['errors'] = $errors;
  }
}

/* ============ view selection ============ */
$view = $_GET['view'] ?? 'catalog';

/* ============ totals helper ============ */
function cart_total($cart, $products){
  $sum = 0.0;
  foreach ($cart as $pid => $qty) {
    if (isset($products[$pid])) {
      $sum += (float)$products[$pid]['price'] * (int)$qty;
    }
  }
  return number_format($sum, 2, '.', '');
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Demo Shop — APM Checkout</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--accent:#2b7cff}
body{background:var(--bg);color:var(--text);font:14px/1.45 ui-monospace,Menlo,Consolas,monospace;margin:0}
.wrap{max-width:1100px;margin:0 auto;padding:22px}
.header{display:flex;align-items:center;justify-content:space-between}
.logo{font-weight:700}
.nav a{color:var(--muted);text-decoration:none;margin-left:12px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
.btn{display:inline-block;padding:8px 12px;border-radius:8px;background:var(--accent);color:#fff;text-decoration:none}
.product{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px dashed rgba(255,255,255,0.03)}
.qty{font-size:13px;color:var(--muted)}
small{color:var(--muted)}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
.input{padding:8px;border-radius:8px;border:1px solid #2a2f3a;background:#11131a;color:var(--text)}
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <div class="logo">Demo Shop — APM Integration</div>
    <div class="nav">
      <a href="?view=catalog" class="navlink">Catalog</a>
      <a href="?view=cart" class="navlink">Cart (<?=array_sum($_SESSION['cart'] ?? [])?>)</a>
      <a href="?view=checkout" class="navlink">Checkout</a>
    </div>
  </div>

  <?php if ($view === 'catalog'): ?>
    <div class="panel">
      <div class="h">Products</div>
      <?php foreach ($PRODUCTS as $id => $p): ?>
        <div class="product">
          <div>
            <div><strong><?=h($p['title'])?></strong></div>
            <div><small>Price: <?=h($p['price'])?> <?=h($CURRENCY)?></small></div>
          </div>
          <div>
            <form method="post" style="display:inline">
              <input type="hidden" name="product_id" value="<?=h($id)?>">
              <button class="btn" name="add_to_cart" type="submit">Add to cart</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php elseif ($view === 'cart'): ?>
    <div class="panel">
      <div class="h">Your cart</div>
      <?php if (empty($_SESSION['cart'])): ?>
        <div>Your cart is empty. <a href="?view=catalog">Browse products</a></div>
      <?php else: ?>
        <?php foreach ($_SESSION['cart'] as $pid => $qty): if (!isset($PRODUCTS[$pid])) continue; $p = $PRODUCTS[$pid]; ?>
          <div class="product">
            <div>
              <div><strong><?=h($p['title'])?></strong> <span class="qty">x<?=h($qty)?></span></div>
              <div><small>Unit: <?=h($p['price'])?> <?=h($CURRENCY)?> — Subtotal: <?=h(number_format($p['price'] * $qty, 2, '.', ''))?> <?=h($CURRENCY)?></small></div>
            </div>
            <div>
              <form method="post" style="display:inline">
                <input type="hidden" name="product_id" value="<?=h($pid)?>">
                <button class="btn" name="remove_from_cart" type="submit">Remove</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
        <div style="margin-top:10px"><strong>Total: <?=h(cart_total($_SESSION['cart'], $PRODUCTS))?> <?=h($CURRENCY)?></strong></div>
        <div style="margin-top:12px"><a class="btn" href="?view=checkout">Proceed to checkout</a></div>
      <?php endif; ?>
    </div>

  <?php elseif ($view === 'checkout'): ?>
    <div class="panel">
      <div class="h">Checkout</div>
      <?php if (empty($_SESSION['cart'])): ?>
        <div>Your cart is empty. <a href="?view=catalog">Go shopping</a></div>
      <?php else: 
        $total = cart_total($_SESSION['cart'], $PRODUCTS);
        ?>
        <form method="post">
          <div style="margin:8px 0;">
            <label>Phone (payer_phone):</label><br>
            <input class="input" type="text" name="phone" value="<?=h($_POST['phone'] ?? '')?>" placeholder="23233310905">
          </div>
          <div style="margin:8px 0;">
            <label>Amount (auto-filled):</label><br>
            <input class="input" type="text" name="amount" value="<?=h($total)?>" readonly>
          </div>
          <div style="margin-top:12px;">
            <button class="btn" name="checkout" type="submit">Send SALE (simulate payment)</button>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <?php if (!empty($checkoutDebug)): ?>
      <div class="panel">
        <div class="h">SALE result</div>
        <div><span class="kv">Endpoint:</span> <?=h($checkoutDebug['endpoint'] ?? '')?></div>
        <div><span class="kv">Order ID:</span> <?=h($checkoutDebug['order_id'] ?? '')?></div>
        <div><span class="kv">HTTP:</span> <?=h($checkoutDebug['http_code'] ?? '')?></div>
        <?php if (!empty($checkoutDebug['curl_error'])): ?><div class="kv">cURL error:</div><pre><?=h($checkoutDebug['curl_error'])?></pre><?php endif; ?>

        <div style="margin-top:10px"><strong>Sent form-data:</strong></div>
        <pre><?=pretty($checkoutDebug['form'] ?? [])?></pre>

        <div style="margin-top:10px"><strong>Response body:</strong></div>
        <pre><?=pretty($checkoutResp['bodyRaw'] ?? '')?></pre>

        <?php if (is_array($checkoutResp['json'] ?? null)): ?>
          <div style="margin-top:10px"><strong>Parsed JSON:</strong></div>
          <pre><?=pretty($checkoutResp['json'])?></pre>
          <?php if (!empty($checkoutResp['json']['trans_id'])): ?>
            <div style="margin-top:10px">
              <a class="btn" href="status_once.php?trans_id=<?=urlencode($checkoutResp['json']['trans_id'])?>" target="_blank">Check status once (trans_id)</a>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($checkoutDebug['errors'])): ?>
          <div style="margin-top:10px;color:#ff6b6b"><strong>Errors:</strong>
            <ul>
              <?php foreach($checkoutDebug['errors'] as $e): ?><li><?=h($e)?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

      </div>
    <?php endif; ?>

  <?php endif; ?>

  <div style="margin-top:18px">
    <small>Demo shop wrapper — for video recording only. No real product logic.</small>
  </div>

</div>
</body>
</html>
