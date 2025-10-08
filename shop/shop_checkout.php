<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

/*
  PetGoods Market — APM demo with COUNTRY + BRAND selector
  - Country:
      SL  → real SALE (Orange Money / AfriMoney)
      KE  → mock Web Checkout (M-Pesa / Airtel Money)
      NG  → mock Web Checkout (MTN MoMo)
  - For mock flow we redirect to webcheckout.php
*/

/* ================= CONFIG (real SALE for SL only) ================= */
$PAYMENT_URL = 'https://api.leogcltd.com/post-va';
$API_USER    = 'leogc';
$API_PASS    = 'ORuIO57N6KJyeJ';
$CLIENT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET      = '554999c284e9f29cf95f090d9a8f3171';
$IDENTIFIER  = '111';
$RETURN_URL  = 'https://google.com';

/* Country → currency map */
$CURRENCY_BY_COUNTRY = [
  'sl' => 'SLE', // Sierra Leone
  'ke' => 'KES', // Kenya
  'ng' => 'NGN', // Nigeria
];

/* Brand meta (also used for UI) */
$ASSET_URL = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/';
$BRANDS_ALL = [
  // SL (real)
  'orange-money' => [
    'title' => 'Orange Money',
    'logo'  => $ASSET_URL.'Orange_Money-Logo.wine.png',
    'hint'  => 'Sierra Leone • Orange',
  ],
  'afri-money' => [
    'title' => 'AfriMoney',
    'logo'  => $ASSET_URL.'afrimoney.png',
    'hint'  => 'Sierra Leone • AfriCell',
  ],
  // KE (mock)
  'm-pesa' => [
    'title' => 'M-Pesa',
    'logo'  => $ASSET_URL.'mpesa-logo.png',
    'hint'  => 'Kenya • Safaricom',
  ],
  'airtel-money' => [
    'title' => 'Airtel Money',
    'logo'  => '', // поставлю без логотипа, щоб не було 404; дай файл — додам
    'hint'  => 'Kenya • Airtel',
  ],
  // NG (mock)
  'mtn-momo' => [
    'title' => 'MTN MoMo',
    'logo'  => $ASSET_URL.'mtn-logo-new.webp', // твій логотип
    'hint'  => 'Nigeria • MTN',
  ],
];

/* Available brands per country */
$BRANDS_BY_COUNTRY = [
  'sl' => ['orange-money','afri-money'],
  'ke' => ['m-pesa','airtel-money'],
  'ng' => ['mtn-momo'],
];

/* ================= CATALOG IMAGES/PRODUCTS ================= */
$IMAGES = [
  'dog1' => 'https://i.pinimg.com/736x/95/a2/b8/95a2b8ba2415aa48565616632ea0d309.jpg',
  'dog2' => 'https://barberpet.com.ua/ua/wp-content/uploads/sites/2/2021/10/what-to-expect-when-adopting-a-puppy-scaled.jpeg',
  'dog3' => 'https://ds1.skrami.com/products/p845268_5a762bb23b662.jpg',
  'cat1' => 'https://ipress.ua/media/gallery/full/1/4/14_a8db1.jpg',
  'cat2' => 'https://lh3.googleusercontent.com/proxy/LvHe2Wt4f53Xht0iY-l2uQS3vOwLPsy_SI2OhjyS5RHGTljOjeLElJ9V1RbCcC88BeuS9qGQyI3DNuc4oZuoagO6AuEncG1YssFaWcdh',
  'cat3' => 'https://img.depo.ua/745xX/Aug2017/288072.jpg',
  'mix1' => 'https://st2.depositphotos.com/4683977/8607/i/450/depositphotos_86072750-stock-photo-bone-animal-food.jpghttps://st2.depositphotos.com/4683977/8607/i/450/depositphotos_86072750-stock-photo-bone-animal-food.jpghttps://st2.depositphotos.com/4683977/8607/i/450/depositphotos_86072750-stock-photo-bone-animal-food.jpg',
  'mix2' => 'https://st4.depositphotos.com/1833015/30502/i/1600/depositphotos_305028550-stock-photo-dog-in-the-kitchen-with.jpg',
  'mix3' => 'https://st5.depositphotos.com/25936512/62156/i/450/depositphotos_621569014-stock-photo-food-animals-pets-dry-food.jpg',
  'mix4' => 'https://media.istockphoto.com/id/1367839756/uk/%D0%B2%D0%B5%D0%BA%D1%82%D0%BE%D1%80%D0%BD%D1%96-%D0%B7%D0%BE%D0%B1%D1%80%D0%B0%D0%B6%D0%B5%D0%BD%D0%BD%D1%8F/%D0%BA%D0%BE%D1%80%D0%BC-%D0%B4%D0%BB%D1%8F-%D0%BA%D1%96%D1%88%D0%BE%D0%BA-%D1%96-%D1%81%D0%BE%D0%B1%D0%B0%D0%BA-%D0%BC%D1%83%D0%BB%D1%8C%D1%82%D1%8F%D1%88%D0%BD%D1%96-%D0%BA%D0%BE%D0%BD%D1%82%D0%B5%D0%B9%D0%BD%D0%B5%D1%80%D0%B8-%D0%B4%D0%BB%D1%8F-%D0%BA%D0%BE%D1%80%D0%BC%D1%96%D0%B2-%D0%B4%D0%BB%D1%8F-%D0%B4%D0%BE%D0%BC%D0%B0%D1%88%D0%BD%D1%96%D1%85-%D1%82%D0%B2%D0%B0%D1%80%D0%B8%D0%BD-%D0%B0%D0%B1%D0%BE.jpg?s=612x612&w=0&k=20&c=PhjWy1AthQBo_o7gP_ZMMbU_wpD0qPOlQxVwNogBcsw=',
];
$PRODUCTS = [
  1 => ['title' => 'Premium Dog Food 2kg',   'price' => '12.50', 'img' => $IMAGES['dog1']],
  2 => ['title' => 'Cat Crunchies 1.5kg',    'price' => '9.40',  'img' => $IMAGES['cat1']],
  3 => ['title' => 'Puppy Starter Pack 1kg', 'price' => '7.25',  'img' => $IMAGES['dog2']],
  4 => ['title' => 'Senior Dog Mix 2kg',     'price' => '11.90', 'img' => $IMAGES['mix2']],
  5 => ['title' => 'Kitten Growth Mix 1kg',  'price' => '6.80',  'img' => $IMAGES['cat2']],
  6 => ['title' => 'Grain-Free Dog Jerky',   'price' => '8.60',  'img' => $IMAGES['dog3']],
  7 => ['title' => 'Cat Tuna Treats 400g',   'price' => '5.90',  'img' => $IMAGES['cat3']],
  8 => ['title' => 'Dog Biscuits 900g',      'price' => '10.20', 'img' => $IMAGES['mix1']],
  9 => ['title' => 'Dental Chews (M) 10pcs', 'price' => '4.75',  'img' => $IMAGES['mix3']],
 10 => ['title' => 'Cat Chicken Bites 500g', 'price' => '7.99',  'img' => $IMAGES['mix4']],
];

/* ================= helpers ================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function pretty($v){
  if (is_string($v)) { $d=json_decode($v,true); if(json_last_error()===JSON_ERROR_NONE) $v=$d; else return h($v); }
  return h(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}
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

/* ================= session init ================= */
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
if (empty($_SESSION['country']) || !in_array($_SESSION['country'], ['sl','ke','ng'], true)) $_SESSION['country'] = 'sl';
if (empty($_SESSION['brand'])) $_SESSION['brand'] = $BRANDS_BY_COUNTRY[$_SESSION['country']][0];

/* ================= actions ================= */
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
/* Change country */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_country'])) {
  $c = $_POST['country'] ?? 'sl';
  if (in_array($c, ['sl','ke','ng'], true)) {
    $_SESSION['country'] = $c;
    // reset brand to first available for this country
    $_SESSION['brand'] = $BRANDS_BY_COUNTRY[$c][0];
  }
  $back = $_POST['back'] ?? 'cart';
  header('Location: ' . $_SERVER['PHP_SELF'] . '?view=' . urlencode($back)); exit;
}
/* Change brand */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_brand'])) {
  $b = $_POST['brand'] ?? '';
  $country = $_SESSION['country'];
  if (in_array($b, $BRANDS_BY_COUNTRY[$country], true)) $_SESSION['brand'] = $b;
  $back = $_POST['back'] ?? 'cart';
  header('Location: ' . $_SERVER['PHP_SELF'] . '?view=' . urlencode($back)); exit;
}

/* ================= real SALE (SL only) ================= */
$checkoutDebug = [];
$checkoutResp = ['bodyRaw' => '', 'json' => null];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
  $country = $_SESSION['country'];
  $currency = $CURRENCY_BY_COUNTRY[$country] ?? 'SLE';
  $payer_phone = ltrim(preg_replace('/\s+/', '', $_POST['phone'] ?? ''), '+');
  $rawAmt      = preg_replace('/[^0-9.]/', '', $_POST['amount'] ?? '0');
  $order_amt   = number_format((float)$rawAmt, 2, '.', '');
  $selectedBrand = $_SESSION['brand'];

  $errors = [];
  if ($country !== 'sl') $errors[] = 'For KE/NG use Web Checkout (demo).';
  if ($payer_phone === '') $errors[] = 'Phone is required.';
  if (!is_numeric($order_amt) || (float)$order_amt <= 0) $errors[] = 'Amount must be a positive number.';
  if (!in_array($selectedBrand, $BRANDS_BY_COUNTRY[$country], true)) $errors[] = 'Unsupported brand for selected country.';

  if (empty($errors)) {
    $order_id   = 'ORDER_' . time();
    $order_desc = 'Purchase from PetGoods Market';
    $payer_ip   = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $hash_src_dbg = '';
    $hash = build_sale_hash($IDENTIFIER, $order_id, $order_amt, $currency, $SECRET, $hash_src_dbg);

    $form = [
      'action'            => 'SALE',
      'client_key'        => $CLIENT_KEY,
      'brand'             => $selectedBrand,
      'order_id'          => $order_id,
      'order_amount'      => $order_amt,
      'order_currency'    => $currency,
      'order_description' => $order_desc,
      'identifier'        => $IDENTIFIER,
      'payer_ip'          => $payer_ip,
      'return_url'        => $RETURN_URL,
      'payer_phone'       => $payer_phone,
      'hash'              => $hash,
    ];

    // Save order for success page later
    if (!isset($_SESSION['orders'])) $_SESSION['orders'] = [];
    $_SESSION['orders'][$order_id] = [
      'order_id'   => $order_id,
      'cart'       => $_SESSION['cart'],
      'amount'     => $order_amt,
      'currency'   => $currency,
      'phone'      => $payer_phone,
      'brand'      => $selectedBrand,
      'created_at' => time(),
    ];

    $checkoutDebug = [
      'endpoint'   => $PAYMENT_URL,
      'client_key' => $CLIENT_KEY,
      'order_id'   => $order_id,
      'brand'      => $selectedBrand,
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
    if (json_last_error() === JSON_ERROR_NONE) {
      $checkoutResp['json'] = $json;

      if (!empty($json['trans_id'])) {
        $tid = (string)$json['trans_id'];
        $_SESSION['orders'][$order_id]['trans_id'] = $tid;
        if (!isset($_SESSION['orders_by_tid'])) $_SESSION['orders_by_tid'] = [];
        $_SESSION['orders_by_tid'][$tid] = $order_id;
      }
    }
  } else {
    $checkoutDebug['errors'] = $errors;
  }
}

/* ================= view ================= */
$view = $_GET['view'] ?? 'catalog';
$country = $_SESSION['country'];
$currency = $CURRENCY_BY_COUNTRY[$country] ?? 'SLE';
$allowedBrands = $BRANDS_BY_COUNTRY[$country];
$selectedBrand = in_array($_SESSION['brand'], $allowedBrands, true) ? $_SESSION['brand'] : $allowedBrands[0];
$_SESSION['brand'] = $selectedBrand;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>PetGoods Market — APM Demo</title>
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
.small{color:#9aa4af;font-size:12px}
.line{display:flex;justify-content:space-between;align-items:center;border-bottom:1px dashed rgba(255,255,255,.06);padding:8px 0}
.pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
.input{padding:8px;border-radius:8px;border:1px solid #2a2f3a;background:#11131a;color:var(--text);width:260px}
/* payment logos */
.pm-row{display:flex;gap:14px;flex-wrap:wrap;margin-top:8px}
.pm-opt{display:flex;align-items:center;gap:14px;padding:12px 16px;border:1px solid #2a2f3a;border-radius:16px;background:#10131a;cursor:pointer}
.pm-opt.pm-selected{box-shadow:0 0 0 2px #2b7cff55 inset;border-color:#2b7cff}

/* BIG logos everywhere */
.pm-logo{
  height:126px;            /* ~122–128px як просив */
  width:auto;
  max-width:260px;         /* щоб не розлазилось */
  object-fit:contain;
}

/* щоб картки з великими лого виглядали гарно */
.pm-meta{display:flex;flex-direction:column}
.pm-title{font-weight:700}
.pm-hint{font-size:12px;color:#9aa4af;margin-top:2px}

/* на вузьких екранах трохи зменшимо */
@media (max-width:640px){
  .pm-logo{height:88px;max-width:200px}
  .pm-opt{flex-direction:column;align-items:flex-start}
}
</style>
</head>
<body>
<div class="wrap">

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

  <div class="hero">
    <div>
      <h2>Healthy food for happy pets</h2>
      <p>Country-aware payment demo: SL (real) • KE/NG (web checkout demo).</p>
    </div>
    <div class="small">Country: <strong><?=strtoupper($country)?></strong> • Currency: <strong><?=h($currency)?></strong></div>
  </div>

  <?php if ($view === 'catalog'): ?>
    <div class="panel">
      <div class="grid">
        <?php foreach ($PRODUCTS as $id => $p): ?>
          <div class="card">
            <img src="<?=h($p['img'])?>" alt="<?=h($p['title'])?>">
            <div class="body">
              <h3><?=h($p['title'])?></h3>
              <div class="price"><?=h($p['price'])?> <?=h($currency)?></div>
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
            <div><strong><?=h($p['title'])?></strong> <span class="small">× <?=h($qty)?></span></div>
            <div class="small"><?=h($p['price'])?> <?=h($currency)?></div>
          </div>
        <?php endforeach; ?>
        <div class="line" style="border-bottom:0;margin-top:8px"><strong>Total:</strong><strong><?=h(cart_total($_SESSION['cart'],$PRODUCTS))?> <?=h($currency)?></strong></div>

        <!-- Country selector -->
        <form method="post" style="margin-top:14px">
          <input type="hidden" name="set_country" value="1">
          <input type="hidden" name="back" value="cart">
          <div class="small" style="margin-bottom:6px"><strong>Country</strong></div>
          <div class="pm-row">
            <?php foreach (['sl'=>'Sierra Leone','ke'=>'Kenya','ng'=>'Nigeria'] as $code=>$title): ?>
              <label class="pm-opt <?= $country===$code?'pm-selected':'' ?>">
                <input type="radio" name="country" value="<?=$code?>" <?= $country===$code?'checked':'' ?>> <?=$title?>
              </label>
            <?php endforeach; ?>
          </div>
          <div style="margin-top:8px"><button class="btn sec" type="submit">Save country</button></div>
        </form>

        <!-- Brand selector -->
        <div style="margin-top:16px">
          <div style="margin-bottom:6px"><strong>Payment method</strong> <span class="small">(brand)</span></div>
          <form method="post">
            <input type="hidden" name="set_brand" value="1">
            <input type="hidden" name="back" value="cart">
            <div class="pm-row">
              <?php foreach ($allowedBrands as $b):
                $meta = $BRANDS_ALL[$b]; ?>
                <label class="pm-opt <?= $selectedBrand===$b?'pm-selected':'' ?>">
                  <input type="radio" name="brand" value="<?=h($b)?>" <?= $selectedBrand===$b?'checked':'' ?>>
                  <?php if (!empty($meta['logo'])): ?>
                    <img class="pm-logo" src="<?=h($meta['logo'])?>" alt="<?=h($meta['title'])?>">
                  <?php else: ?>
                    <div class="pm-meta">
                      <div class="pm-title"><?=h($meta['title'])?></div>
                      <div class="pm-hint"><?=h($meta['hint'])?></div>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($meta['logo'])): ?>
                    <span class="small"><?=h($meta['hint'])?></span>
                  <?php endif; ?>
                </label>
              <?php endforeach; ?>
            </div>
            <div style="margin-top:10px"><button class="btn" type="submit">Save payment method</button></div>
          </form>
        </div>

        <div style="margin-top:16px;display:flex;gap:8px">
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
        <?php if ($country === 'sl'): ?>
          <!-- REAL SALE (Sierra Leone) -->
          <form method="post">
            <div style="margin:8px 0;">
              <label>Phone (payer_phone):</label><br>
              <input class="input" type="text" name="phone" value="<?=h($_POST['phone'] ?? '')?>" placeholder="23233310905">
            </div>
            <div style="margin:8px 0;">
              <label>Amount:</label><br>
              <input class="input" type="text" name="amount" value="<?=h($total)?>" readonly>
            </div>
            <div class="small" style="margin:6px 0">Brand: <strong><?=h($BRANDS_ALL[$selectedBrand]['title'])?></strong></div>
            <div style="margin-top:12px;">
              <button class="btn" name="checkout" type="submit">Send SALE</button>
            </div>
          </form>
        <?php else: ?>
          <!-- MOCK WEB CHECKOUT (KE/NG) -->
          <form method="post" action="webcheckout.php">
            <input type="hidden" name="country" value="<?=h($country)?>">
            <input type="hidden" name="brand"   value="<?=h($selectedBrand)?>">
            <div style="margin:8px 0;">
              <label>Phone (payer_phone):</label><br>
              <input class="input" type="text" name="phone" value="<?=h($_POST['phone'] ?? '')?>" placeholder="<?= $country==='ke' ? '254700000000' : '234700000000' ?>">
            </div>
            <div style="margin:8px 0;">
              <label>Amount:</label><br>
              <input class="input" type="text" name="amount" value="<?=h($total)?>" readonly>
            </div>
            <div class="small" style="margin:6px 0">
              Method: <strong><?=h($BRANDS_ALL[$selectedBrand]['title'])?></strong> • Country: <strong><?=strtoupper($country)?></strong>
            </div>
            <div style="margin-top:12px;">
              <button class="btn" type="submit">Continue to Web Checkout (demo)</button>
            </div>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php if (!empty($checkoutDebug)): ?>
      <div class="panel">
        <h3 style="margin:0 0 10px 0">SALE result</h3>
        <div class="small">Endpoint: <?=h($checkoutDebug['endpoint'] ?? '')?></div>
        <div class="small">Order ID: <?=h($checkoutDebug['order_id'] ?? '')?></div>
        <div class="small">Brand: <?=h($checkoutDebug['brand'] ?? '')?></div>
        <div class="small">HTTP: <?=h($checkoutDebug['http_code'] ?? '')?></div>
        <?php if (!empty($checkoutDebug['curl_error'])): ?>
          <div class="small" style="color:#ff6b6b">cURL: <?=h($checkoutDebug['curl_error'])?></div>
        <?php endif; ?>

        <div style="margin-top:10px"><strong>Sent form-data</strong></div>
        <div class="pre"><?=pretty($checkoutDebug['form'] ?? [])?></div>

        <div style="margin-top:10px"><strong>Response body</strong></div>
        <div class="pre"><?=pretty($checkoutResp['bodyRaw'] ?? '')?></div>

        <?php if (is_array($checkoutResp['json'] ?? null)): ?>
          <div style="margin-top:10px"><strong>Parsed JSON</strong></div>
          <div class="pre"><?=pretty($checkoutResp['json'])?></div>
          <?php if (!empty($checkoutResp['json']['trans_id'])): ?>
            <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
              <a class="btn" href="status_once.php?trans_id=<?=urlencode($checkoutResp['json']['trans_id'])?>" target="_blank">
                Check status once (trans_id)
              </a>
              <?php
                $oid_link = urlencode($checkoutDebug['order_id']);
                $tid_link = urlencode($checkoutResp['json']['trans_id']);
              ?>
              <a class="btn" href="success.php?order_id=<?=$oid_link?>&trans_id=<?=$tid_link?>" target="_blank">
                Open success page (if approved)
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
    <span class="small">SL uses real SALE; KE/NG use Web Checkout demo (UI only).</span>
  </div>

</div>
</body>
</html>
