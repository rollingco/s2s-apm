<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

/*
  Demo Pet Shop — wraps the existing SALE flow
  - Catalog (10 items with images) → Cart → Checkout (SALE) → link to status_once.php
  - No headers logging, only essential debug
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
$BRAND       = 'afri-money'; // change if needed

/* ============ PRODUCTS (10 items) ============ */
$PRODUCTS = [
  1 => ['title' => 'Premium Dog Food 2kg',        'price' => '12.50', 'img' => 'https://images.unsplash.com/photo-1534361960057-19889db9621e?w=800&q=80'],
  2 => ['title' => 'Cat Crunchies 1.5kg',         'price' => '9.40',  'img' => 'https://images.unsplash.com/photo-1619983081563-430f63602796?w=800&q=80'],
  3 => ['title' => 'Puppy Starter Pack 1kg',      'price' => '7.25',  'img' => 'https://images.unsplash.com/photo-1558944351-c97e1330b9b6?w=800&q=80'],
  4 => ['title' => 'Senior Dog Mix 2kg',          'price' => '11.90', 'img' => 'https://images.unsplash.com/photo-1517849845537-4d257902454a?w=800&q=80'],
  5 => ['title' => 'Kitten Growth Mix 1kg',       'price' => '6.80',  'img' => 'https://images.unsplash.com/photo-1574158622682-e40e69881006?w=800&q=80'],
  6 => ['title' => 'Grain-Free Dog Jerky 500g',   'price' => '8.60',  'img' => 'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?w=800&q=80'],
  7 => ['title' => 'Cat Tuna Treats 400g',        'price' => '5.90',  'img' => 'https://images.unsplash.com/photo-1596854407944-bf87f6fdd49e?w=800&q=80'],
  8 => ['title' => 'Dog Biscuits 900g',           'price' => '10.20', 'img' => 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=800&q=80'],
  9 => ['title' => 'Dental Chews (M) 10pcs',      'price' => '4.75',  'img' => 'https://images.unsplash.com/photo-1547483238-2cbf1aabfb54?w=800&q=80'],
 10 => ['title' => 'Cat Chicken Bites 500g',      'price' => '7.99',  'img' => 'https://images.unsplash.com/photo-1596495578065-8c15fce736d7?w=800&q=80'],
];

/* ============ helpers ============ */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function pretty($v){ if (is_string($v)) { $d=json_decode($v,true); if(json_last_error()===JSON_ERROR_NONE) $v=$d; else return h($v);} return h(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)); }
function build_sale_hash($identifier, $order_id, $amount, $currency, $secret, &$srcOut=null){
  $src = $identifier . $order_id . $amount . $currency . $secret;
  if ($srcOut !== null) $srcOut = $src;
  return md5(strtoupper(strrev($src)));
}
function cart_total($cart, $products){
  $sum = 0.0;
  foreach ($cart as $pid => $qty) if (isset($products[$pid])) $sum += (float)$products[$pid]['price'] * (int)$qty;
  return number_format($sum, 2, '.', '');
}

/* ============ CART actions ============ */
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
  $pid = (int)($_POST['product_id'] ?? 0);
  if ($pid && isset($PRODUCTS[$pid])) $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + 1;
  header('Location: ' . $_SERVER['PHP_SELF']); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_cart'])) {
  $pid = (int)($_POST['product_id'] ?? 0);
  if ($pid && isset($_SESSION['cart'][$pid])) unset($_SESSION['cart'][$pid]);
  header('Location: ' . $_SERVER['PHP_SELF'] . '?view=cart'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cart'])) {
  $_SESSION['cart'] = []; header('Location: ' . $_SERVER['PHP_SELF'] . '?view=cart'); exit;
}

/* ============ Checkout (SALE) ============ */
$checkoutDebug = [];
$checkoutResp = ['bodyRaw' => '', 'json' => null];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
  $payer_phone = ltrim(preg_replace('/\s+/', '', $_POST['phone'] ?? ''), '+');
  $rawAmt      = preg_replace('/[^0-9.]/', '', $_POST['amount'] ?? '0');
  $order_amt   = number_format((float)$rawAmt, 2, '.', '');

  $errors = [];
  if ($payer_phone === '') $errors[] = 'Phone is required.';
  if (!is_numeric($order_amt) || (float)$order_amt <= 0) $errors[] = 'Amount must be a positive number.';

  if (empty($errors)) {
    $order_id   = 'ORDER_' . time();
    $order_desc = 'Purchase from PetGoods Market';
    $payer_ip   = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $hash_src_dbg = '';
    $hash = build_sale_hash($IDENTIFIER, $order_id, $order_amt, $CURRENCY, $SECRET, $hash_src_dbg);

    $form = [
      'action'            => 'SALE',
      'client_key'        => $CLIENT_KEY,
      'brand'             => $BRAND,
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

    $ch = curl_init($PAYMENT_URL);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => $form, // multipart/form-data
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
    if (json_last_error() === JSON_ERROR_NONE) $checkoutResp['json'] = $json;
  } else {
    $checkoutDebug['errors'] = $errors;
  }
}

/* ============ view ============ */
$view = $_GET['view'] ?? 'catalog';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>PetGoods Market — APM Checkout Demo</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--accent:#2b7cff}
*{box-sizing:border-box}
body{background:var(--bg);color:var(--text);font:14px/1.5 ui-monospace,Menlo,Consolas,monospace;margin:0}
.wrap{max-width:1200px;margin:0 auto;padding:22px}
.header{display:flex;align-items:center;justify-content:space-between;padding:14px 0}
.brand{display:flex;align-items:center;gap:10px}
.logo{width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg,#3b82f6,#06b6d4)}
.brand h1{font-size:16px;margin:0}
.nav a{color:#fff;text-decoration:none;margin-left:14px;opacity:.7}
.nav a:hover{opacity:1}
.hero{background:linear-gradient(135deg,#1b1e2a,#12141c);border:1px solid #262b36;border-radius:16px;padding:18px 20px;display:flex;align-items:center;justify-content:space-between;gap:14px}
.hero h2{margin:0 0 6px 0;font-size:18px}
.hero p{margin:0;color:var(--muted)}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:16px;margin:16px 0}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
@media(max-width:1000px){.grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.grid{grid-template-columns:1fr}}
.card{background:#11131a;border:1px solid #232635;border-radius:12px;overflow:hidden;display:flex;flex-direction:column}
.card img{width:100%;height:160px;object-fit:cover}
.card .body{padding:12px;flex:1;display:flex;flex-direction:column}
.card h3{margin:0 0 8px 0;font-size:14px}
.price{color:#c7d2fe;font-weight:700;margin-top:auto}
.actions{display:flex;justify-content:space-between;align-items:center;margin-top:10px}
.btn{display:inline-block;padding:8px 12px;border-radius:8px;background:var(--accent);color:#fff;text-decoration:none;border:0;cursor:pointer}
.btn.sec{background:#2f3543}
.small{color:var(--muted);font-size:12px}
.line{display:flex;justify-content:space-between;align-items:center;border-bottom:1px dashed rgba(255,255,255,.06);padding:8px 0}
.pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
.input{padding:8px;border-radius:8px;border:1px solid #2a2f3a;background:#11131a;color:var(--text);width:260px}
</style>
</head>
<body>
<div class="wrap">

  <!-- Header / Shop-like top bar -->
  <div class="header">
    <div class="brand">
      <div class="logo"></div>
      <h1>PetGoods Market</h1>
    </div>
    <div class="nav">
      <a href="?view=catalog">Catalog</a>
      <a href="?view=cart">Cart (<?=array_sum($_SESSION['cart'] ?? [])?>)</a>
      <a href="?view=checkout">Checkout</a>
    </div>
  </div>

  <!-- Hero banner -->
  <div class="hero">
    <div>
      <h2>Healthy food for happy pets</h2>
      <p>Choose items, add to cart, and complete a demo checkout using APM (SALE + STATUS).</p>
    </div>
    <a class="btn" href="?view=catalog">Shop now</a>
  </div>

  <?php if ($view === 'catalog'): ?>
    <div class="panel">
      <div class="grid">
        <?php foreach ($PRODUCTS as $id => $p): ?>
          <div class="card">
            <img src="<?=h($p['img'])?>" alt="<?=h($p['title'])?>">
            <div class="body">
              <h3><?=h($p['title'])?></h3>
              <div class="price"><?=h($p['price'])?> <?=h($CURRENCY)?></div>
              <div class="actions">
                <form method="post">
                  <input type="hidden" name="product_id" value="<?=h($id)?>">
                  <button class="btn" name="add_to_cart" type="submit">Add to cart</button>
                </form>
                <span class="small">In stock</span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  <?php elseif ($view === 'cart'): ?>
    <div class="panel">
      <h3 style="margin:0 0 12px 0">Your cart</h3>
      <?php if (empty($_SESSION['cart'])): ?>
        <div class="small">Cart is empty. <a href="?view=catalog">Browse products</a></div>
      <?php else: ?>
        <?php foreach ($_SESSION['cart'] as $pid => $qty): if (!isset($PRODUCTS[$pid])) continue; $p = $PRODUCTS[$pid]; ?>
          <div class="line">
            <div>
              <strong><?=h($p['title'])?></strong>
              <span class="small"> × <?=h($qty)?></span>
            </div>
            <div>
              <span class="small"><?=h($p['price'])?> <?=h($CURRENCY)?></span>
              <form method="post" style="display:inline;margin-left:8px">
                <input type="hidden" name="product_id" value="<?=h($pid)?>">
                <button class="btn sec" name="remove_from_cart" type="submit">Remove</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
        <div class="line" style="border-bottom:0;margin-top:8px">
          <strong>Total:</strong>
          <strong><?=h(cart_total($_SESSION['cart'], $PRODUCTS))?> <?=h($CURRENCY)?></strong>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px">
          <a class="btn" href="?view=checkout">Proceed to checkout</a>
          <form method="post"><button class="btn sec" name="clear_cart" type="submit">Clear cart</button></form>
        </div>
      <?php endif; ?>
    </div>

  <?php elseif ($view === 'checkout'): ?>
    <div class="panel">
      <h3 style="margin:0 0 12px 0">Checkout</h3>
      <?php if (empty($_SESSION['cart'])): ?>
        <div class="small">Cart is empty. <a href="?view=catalog">Go shopping</a></div>
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
            <button class="btn" name="checkout" type="submit">Send SALE</button>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <?php if (!empty($checkoutDebug)): ?>
      <div class="panel">
        <h3 style="margin:0 0 10px 0">SALE result</h3>
        <div class="small">Endpoint: <?=h($checkoutDebug['endpoint'] ?? '')?></div>
        <div class="small">Order ID: <?=h($checkoutDebug['order_id'] ?? '')?></div>
        <div class="small">HTTP: <?=h($checkoutDebug['http_code'] ?? '')?></div>
        <?php if (!empty($checkoutDebug['curl_error'])): ?><div class="small" style="color:#ff6b6b">cURL: <?=h($checkoutDebug['curl_error'])?></div><?php endif; ?>

        <div style="margin-top:10px"><strong>Sent form-data</strong></div>
        <div class="pre"><?=pretty($checkoutDebug['form'] ?? [])?></div>

        <div style="margin-top:10px"><strong>Response body</strong></div>
        <div class="pre"><?=pretty($checkoutResp['bodyRaw'] ?? '')?></div>

        <?php if (is_array($checkoutResp['json'] ?? null)): ?>
          <div style="margin-top:10px"><strong>Parsed JSON</strong></div>
          <div class="pre"><?=pretty($checkoutResp['json'])?></div>
          <?php if (!empty($checkoutResp['json']['trans_id'])): ?>
            <div style="margin-top:10px">
              <a class="btn" href="status_once.php?trans_id=<?=urlencode($checkoutResp['json']['trans_id'])?>" target="_blank">
                Check status once (trans_id)
              </a>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($checkoutDebug['errors'])): ?>
          <div style="margin-top:10px;color:#ff6b6b"><strong>Errors:</strong>
            <ul><?php foreach($checkoutDebug['errors'] as $e): ?><li><?=h($e)?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>

  <div style="margin:10px 0">
    <span class="small">Demo UI for recording the real APM flow (SALE → STATUS). Images sourced from Unsplash.</span>
  </div>

</div>
</body>
</html>
<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

/*
  Demo Pet Shop — wraps the existing SALE flow
  - Catalog (10 items with images) → Cart → Checkout (SALE) → link to status_once.php
  - No headers logging, only essential debug
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
$BRAND       = 'afri-money'; // change if needed

/* ============ PRODUCTS (10 items) ============ */
$PRODUCTS = [
  1 => ['title' => 'Premium Dog Food 2kg',        'price' => '12.50', 'img' => 'https://images.unsplash.com/photo-1534361960057-19889db9621e?w=800&q=80'],
  2 => ['title' => 'Cat Crunchies 1.5kg',         'price' => '9.40',  'img' => 'https://images.unsplash.com/photo-1619983081563-430f63602796?w=800&q=80'],
  3 => ['title' => 'Puppy Starter Pack 1kg',      'price' => '7.25',  'img' => 'https://images.unsplash.com/photo-1558944351-c97e1330b9b6?w=800&q=80'],
  4 => ['title' => 'Senior Dog Mix 2kg',          'price' => '11.90', 'img' => 'https://images.unsplash.com/photo-1517849845537-4d257902454a?w=800&q=80'],
  5 => ['title' => 'Kitten Growth Mix 1kg',       'price' => '6.80',  'img' => 'https://images.unsplash.com/photo-1574158622682-e40e69881006?w=800&q=80'],
  6 => ['title' => 'Grain-Free Dog Jerky 500g',   'price' => '8.60',  'img' => 'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?w=800&q=80'],
  7 => ['title' => 'Cat Tuna Treats 400g',        'price' => '5.90',  'img' => 'https://images.unsplash.com/photo-1596854407944-bf87f6fdd49e?w=800&q=80'],
  8 => ['title' => 'Dog Biscuits 900g',           'price' => '10.20', 'img' => 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=800&q=80'],
  9 => ['title' => 'Dental Chews (M) 10pcs',      'price' => '4.75',  'img' => 'https://images.unsplash.com/photo-1547483238-2cbf1aabfb54?w=800&q=80'],
 10 => ['title' => 'Cat Chicken Bites 500g',      'price' => '7.99',  'img' => 'https://images.unsplash.com/photo-1596495578065-8c15fce736d7?w=800&q=80'],
];

/* ============ helpers ============ */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function pretty($v){ if (is_string($v)) { $d=json_decode($v,true); if(json_last_error()===JSON_ERROR_NONE) $v=$d; else return h($v);} return h(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)); }
function build_sale_hash($identifier, $order_id, $amount, $currency, $secret, &$srcOut=null){
  $src = $identifier . $order_id . $amount . $currency . $secret;
  if ($srcOut !== null) $srcOut = $src;
  return md5(strtoupper(strrev($src)));
}
function cart_total($cart, $products){
  $sum = 0.0;
  foreach ($cart as $pid => $qty) if (isset($products[$pid])) $sum += (float)$products[$pid]['price'] * (int)$qty;
  return number_format($sum, 2, '.', '');
}

/* ============ CART actions ============ */
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
  $pid = (int)($_POST['product_id'] ?? 0);
  if ($pid && isset($PRODUCTS[$pid])) $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + 1;
  header('Location: ' . $_SERVER['PHP_SELF']); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_cart'])) {
  $pid = (int)($_POST['product_id'] ?? 0);
  if ($pid && isset($_SESSION['cart'][$pid])) unset($_SESSION['cart'][$pid]);
  header('Location: ' . $_SERVER['PHP_SELF'] . '?view=cart'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cart'])) {
  $_SESSION['cart'] = []; header('Location: ' . $_SERVER['PHP_SELF'] . '?view=cart'); exit;
}

/* ============ Checkout (SALE) ============ */
$checkoutDebug = [];
$checkoutResp = ['bodyRaw' => '', 'json' => null];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
  $payer_phone = ltrim(preg_replace('/\s+/', '', $_POST['phone'] ?? ''), '+');
  $rawAmt      = preg_replace('/[^0-9.]/', '', $_POST['amount'] ?? '0');
  $order_amt   = number_format((float)$rawAmt, 2, '.', '');

  $errors = [];
  if ($payer_phone === '') $errors[] = 'Phone is required.';
  if (!is_numeric($order_amt) || (float)$order_amt <= 0) $errors[] = 'Amount must be a positive number.';

  if (empty($errors)) {
    $order_id   = 'ORDER_' . time();
    $order_desc = 'Purchase from PetGoods Market';
    $payer_ip   = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $hash_src_dbg = '';
    $hash = build_sale_hash($IDENTIFIER, $order_id, $order_amt, $CURRENCY, $SECRET, $hash_src_dbg);

    $form = [
      'action'            => 'SALE',
      'client_key'        => $CLIENT_KEY,
      'brand'             => $BRAND,
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

    $ch = curl_init($PAYMENT_URL);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => $form, // multipart/form-data
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
    if (json_last_error() === JSON_ERROR_NONE) $checkoutResp['json'] = $json;
  } else {
    $checkoutDebug['errors'] = $errors;
  }
}

/* ============ view ============ */
$view = $_GET['view'] ?? 'catalog';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>PetGoods Market — APM Checkout Demo</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--accent:#2b7cff}
*{box-sizing:border-box}
body{background:var(--bg);color:var(--text);font:14px/1.5 ui-monospace,Menlo,Consolas,monospace;margin:0}
.wrap{max-width:1200px;margin:0 auto;padding:22px}
.header{display:flex;align-items:center;justify-content:space-between;padding:14px 0}
.brand{display:flex;align-items:center;gap:10px}
.logo{width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg,#3b82f6,#06b6d4)}
.brand h1{font-size:16px;margin:0}
.nav a{color:#fff;text-decoration:none;margin-left:14px;opacity:.7}
.nav a:hover{opacity:1}
.hero{background:linear-gradient(135deg,#1b1e2a,#12141c);border:1px solid #262b36;border-radius:16px;padding:18px 20px;display:flex;align-items:center;justify-content:space-between;gap:14px}
.hero h2{margin:0 0 6px 0;font-size:18px}
.hero p{margin:0;color:var(--muted)}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:16px;margin:16px 0}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
@media(max-width:1000px){.grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.grid{grid-template-columns:1fr}}
.card{background:#11131a;border:1px solid #232635;border-radius:12px;overflow:hidden;display:flex;flex-direction:column}
.card img{width:100%;height:160px;object-fit:cover}
.card .body{padding:12px;flex:1;display:flex;flex-direction:column}
.card h3{margin:0 0 8px 0;font-size:14px}
.price{color:#c7d2fe;font-weight:700;margin-top:auto}
.actions{display:flex;justify-content:space-between;align-items:center;margin-top:10px}
.btn{display:inline-block;padding:8px 12px;border-radius:8px;background:var(--accent);color:#fff;text-decoration:none;border:0;cursor:pointer}
.btn.sec{background:#2f3543}
.small{color:var(--muted);font-size:12px}
.line{display:flex;justify-content:space-between;align-items:center;border-bottom:1px dashed rgba(255,255,255,.06);padding:8px 0}
.pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
.input{padding:8px;border-radius:8px;border:1px solid #2a2f3a;background:#11131a;color:var(--text);width:260px}
</style>
</head>
<body>
<div class="wrap">

  <!-- Header / Shop-like top bar -->
  <div class="header">
    <div class="brand">
      <div class="logo"></div>
      <h1>PetGoods Market</h1>
    </div>
    <div class="nav">
      <a href="?view=catalog">Catalog</a>
      <a href="?view=cart">Cart (<?=array_sum($_SESSION['cart'] ?? [])?>)</a>
      <a href="?view=checkout">Checkout</a>
    </div>
  </div>

  <!-- Hero banner -->
  <div class="hero">
    <div>
      <h2>Healthy food for happy pets</h2>
      <p>Choose items, add to cart, and complete a demo checkout using APM (SALE + STATUS).</p>
    </div>
    <a class="btn" href="?view=catalog">Shop now</a>
  </div>

  <?php if ($view === 'catalog'): ?>
    <div class="panel">
      <div class="grid">
        <?php foreach ($PRODUCTS as $id => $p): ?>
          <div class="card">
            <img src="<?=h($p['img'])?>" alt="<?=h($p['title'])?>">
            <div class="body">
              <h3><?=h($p['title'])?></h3>
              <div class="price"><?=h($p['price'])?> <?=h($CURRENCY)?></div>
              <div class="actions">
                <form method="post">
                  <input type="hidden" name="product_id" value="<?=h($id)?>">
                  <button class="btn" name="add_to_cart" type="submit">Add to cart</button>
                </form>
                <span class="small">In stock</span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  <?php elseif ($view === 'cart'): ?>
    <div class="panel">
      <h3 style="margin:0 0 12px 0">Your cart</h3>
      <?php if (empty($_SESSION['cart'])): ?>
        <div class="small">Cart is empty. <a href="?view=catalog">Browse products</a></div>
      <?php else: ?>
        <?php foreach ($_SESSION['cart'] as $pid => $qty): if (!isset($PRODUCTS[$pid])) continue; $p = $PRODUCTS[$pid]; ?>
          <div class="line">
            <div>
              <strong><?=h($p['title'])?></strong>
              <span class="small"> × <?=h($qty)?></span>
            </div>
            <div>
              <span class="small"><?=h($p['price'])?> <?=h($CURRENCY)?></span>
              <form method="post" style="display:inline;margin-left:8px">
                <input type="hidden" name="product_id" value="<?=h($pid)?>">
                <button class="btn sec" name="remove_from_cart" type="submit">Remove</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
        <div class="line" style="border-bottom:0;margin-top:8px">
          <strong>Total:</strong>
          <strong><?=h(cart_total($_SESSION['cart'], $PRODUCTS))?> <?=h($CURRENCY)?></strong>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px">
          <a class="btn" href="?view=checkout">Proceed to checkout</a>
          <form method="post"><button class="btn sec" name="clear_cart" type="submit">Clear cart</button></form>
        </div>
      <?php endif; ?>
    </div>

  <?php elseif ($view === 'checkout'): ?>
    <div class="panel">
      <h3 style="margin:0 0 12px 0">Checkout</h3>
      <?php if (empty($_SESSION['cart'])): ?>
        <div class="small">Cart is empty. <a href="?view=catalog">Go shopping</a></div>
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
            <button class="btn" name="checkout" type="submit">Send SALE</button>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <?php if (!empty($checkoutDebug)): ?>
      <div class="panel">
        <h3 style="margin:0 0 10px 0">SALE result</h3>
        <div class="small">Endpoint: <?=h($checkoutDebug['endpoint'] ?? '')?></div>
        <div class="small">Order ID: <?=h($checkoutDebug['order_id'] ?? '')?></div>
        <div class="small">HTTP: <?=h($checkoutDebug['http_code'] ?? '')?></div>
        <?php if (!empty($checkoutDebug['curl_error'])): ?><div class="small" style="color:#ff6b6b">cURL: <?=h($checkoutDebug['curl_error'])?></div><?php endif; ?>

        <div style="margin-top:10px"><strong>Sent form-data</strong></div>
        <div class="pre"><?=pretty($checkoutDebug['form'] ?? [])?></div>

        <div style="margin-top:10px"><strong>Response body</strong></div>
        <div class="pre"><?=pretty($checkoutResp['bodyRaw'] ?? '')?></div>

        <?php if (is_array($checkoutResp['json'] ?? null)): ?>
          <div style="margin-top:10px"><strong>Parsed JSON</strong></div>
          <div class="pre"><?=pretty($checkoutResp['json'])?></div>
          <?php if (!empty($checkoutResp['json']['trans_id'])): ?>
            <div style="margin-top:10px">
              <a class="btn" href="status_once.php?trans_id=<?=urlencode($checkoutResp['json']['trans_id'])?>" target="_blank">
                Check status once (trans_id)
              </a>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($checkoutDebug['errors'])): ?>
          <div style="margin-top:10px;color:#ff6b6b"><strong>Errors:</strong>
            <ul><?php foreach($checkoutDebug['errors'] as $e): ?><li><?=h($e)?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>

  <div style="margin:10px 0">
    <span class="small">Demo UI for recording the real APM flow (SALE → STATUS). Images sourced from Unsplash.</span>
  </div>

</div>
</body>
</html>
