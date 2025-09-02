<?php
/**
 * S2S APM SALE demo (updated for MID with Basic Auth + SLE)
 * - Same connector URL for test & live (as per AM comment)
 * - MID auth: API Username / API Password -> HTTP Basic
 * - Currency: SLE (per your MID)
 * - Verbose HTML logging
 *
 * Quick test:
 *   send_sale_apm.php?brand=applepay
 *   send_sale_apm.php?brand=googlepay
 *   send_sale_apm.php?brand=paypal
 *   send_sale_apm.php?brand=vcard
 */

header('Content-Type: text/html; charset=utf-8');

// ======== CONFIG (fill with your values) ========
$CLIENT_KEY   = 'a9375384-26f2-11f0-877d-022c42254708';
$PASSWORD_MD5 = '554999c284e9f29cf95f090d9a8f3171'; // as before (Appendix A secret)
$PAYMENT_URL  = 'https://api.leogcltd.com/post-va'; // same for test & live per manager

// MID credentials (from your screenshots)
$API_USER     = 'leogc';
$API_PASS     = 'ORuIO57N6KJyeJ'; // ← якщо змінять — онови тут

// Allowed brands for quick switch via query (?brand=...)
$ALLOWED_BRANDS = ['applepay','googlepay','paypal','vcard']; // розширюй за потреби

// ======== RUNTIME (can be overridden via query) ========
$brand       = strtolower($_GET['brand'] ?? 'vcard'); // швидке перемикання
if (!in_array($brand, $ALLOWED_BRANDS, true)) {
    // не зупиняємо виконання — просто попереджаємо
    $brand_warning = "Brand '$brand' is not in allowed list: [" . implode(', ', $ALLOWED_BRANDS) . "]";
}

$identifier  = $_GET['identifier'] ?? 'success@gmail.com'; // success|fail
$order_ccy   = $_GET['ccy']        ?? 'SLE';                // ← SLE згідно з MID
$order_amt   = $_GET['amt']        ?? '1.99';
$return_url  = $_GET['return']     ?? 'https://zal25.pp.ua/s2stest/callback.php';

// order
$order_id     = 'ORDER_' . time();
$description  = 'APM test payment';
$payer_ip     = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// ======== Helpers ========
function build_sale_hash($identifier, $order_id, $amount, $currency, $passwordMd5) {
    // Appendix A (залишив, як у тебе було): md5(strtoupper(strrev(identifier + order_id + amount + currency + PASSWORD)))
    $src = $identifier . $order_id . $amount . $currency . $passwordMd5;
    return md5(strtoupper(strrev($src)));
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function pretty($v){
    if (is_string($v)) {
        $d = json_decode($v, true);
        if (json_last_error() === JSON_ERROR_NONE) return pretty($d);
        return h($v);
    }
    return h(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}

// ======== Build payload ========
$hash = build_sale_hash($identifier, $order_id, $order_amt, $order_ccy, $PASSWORD_MD5);
$payload = [
    'action'            => 'SALE',
    'client_key'        => $CLIENT_KEY,
    'brand'             => $brand,
    'order_id'          => $order_id,
    'order_amount'      => $order_amt,
    'order_currency'    => $order_ccy,
    'order_description' => $description,
    'identifier'        => $identifier,
    'payer_ip'          => $payer_ip,
    'return_url'        => $return_url,
    'hash'              => $hash,
];

// form-encoded body
$postFields = http_build_query($payload);

// ======== Send (with Basic Auth) ========
$ch = curl_init($PAYMENT_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postFields,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/x-www-form-urlencoded',
        'Content-Length: ' . strlen($postFields),
    ],
    CURLOPT_USERPWD        => $API_USER . ':' . $API_PASS,  // ← ключовий момент: MID = user/pass
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_HEADER         => true, // щоб побачити headers
]);

$start = microtime(true);
$raw    = curl_exec($ch);
$info   = curl_getinfo($ch);
$errno  = curl_errno($ch);
$error  = $errno ? curl_error($ch) : '';
curl_close($ch);
$duration = number_format(microtime(true) - $start, 3, '.', '');

// split headers/body
$respHeaders = '';
$respBody    = '';
if ($raw !== false && isset($info['header_size'])) {
    $respHeaders = substr($raw, 0, $info['header_size']);
    $respBody    = substr($raw, $info['header_size']);
}

$data = json_decode($respBody, true);

// ======== HTML log ========
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>S2S APM SALE (Tola via Akurateco) – Verbose</title>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--ok:#2ecc71;--warn:#f1c40f;--err:#ff6b6b;--info:#00d1d1}
html,body{background:var(--bg);color:var(--text);margin:0;font:14px/1.45 ui-monospace,Menlo,Consolas,monospace}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.h{font-weight:700;margin:10px 0 6px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:var(--muted)}
.tag{display:inline-block;padding:2px 8px;border-radius:8px;font-size:12px;margin-right:6px}
.t-ok{background:rgba(46,204,113,.12);color:var(--ok)}
.t-err{background:rgba(255,107,107,.12);color:var(--err)}
.t-info{background:rgba(0,209,209,.12);color:var(--info)}
pre{background:#11131a;padding:12px;border-radius:10px;overflow:auto;border:1px solid #232635;white-space:pre-wrap}
a{color:#8ab4ff;text-decoration:none}
</style>
</head>
<body>
<div class="wrap">
  <div class="h">🟢 S2S APM SALE demo (with Basic Auth)</div>

  <div class="panel">
    <div><span class="kv">Endpoint:</span> <?=h($PAYMENT_URL)?></div>
    <div><span class="kv">Auth (MID):</span> <?=h($API_USER)?> : ******</div>
    <div><span class="kv">Order ID:</span> <?=h($order_id)?> &nbsp; <span class="kv">Duration:</span> <?=h($duration)?>s</div>
    <?php if (!empty($brand_warning)): ?>
      <div class="tag t-err"><?=h($brand_warning)?></div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="h">➡ Sent payload (x-www-form-urlencoded)</div>
    <pre><?=pretty($payload)?></pre>
  </div>

  <div class="panel">
    <div class="h">⬅ Raw response headers</div>
    <pre><?=h(trim($respHeaders))?></pre>
  </div>

  <div class="panel">
    <div class="h">⬅ Raw response body</div>
    <pre><?=pretty($respBody)?></pre>
  </div>

<?php
// ======== Result handling ========
$http = $info['http_code'] ?? 0;
$badge = ($http >= 200 && $http < 300) ? 't-ok' : 't-err';
echo '<div class="panel"><span class="tag '.$badge.'">HTTP '.$http.'</span>';

// JSON ok?
if (!is_array($data)) {
    echo '<div class="tag t-err">Invalid/empty JSON body</div>';
    if ($error) echo '<div class="tag t-err">cURL: '.h($error).'</div>';
    echo '</div></div></body></html>'; exit;
}

$result = $data['result'] ?? '';
echo '<div><span class="kv">result:</span> <b>'.h($result).'</b></div>';

if ($result === 'SUCCESS') {
    echo '<div class="tag t-ok">Payment SUCCESS</div>';
} elseif ($result === 'DECLINED') {
    echo '<div class="tag t-err">DECLINED</div>';
} elseif ($result === 'ERROR') {
    $ec = $data['error_code']    ?? '';
    $em = $data['error_message'] ?? '';
    echo '<div class="tag t-err">ERROR '.$ec.'</div>';
    echo '<div>'.h($em).'</div>';

    // targeted hints
    if ((string)$ec === '204006') {
        echo '<div style="margin-top:8px">';
        echo '<div class="h">Hints for 204006 (Payment system/brand not supported)</div>';
        echo '<ul>';
        echo '<li>Make sure this Merchant has <b>S2S APM protocol mapping</b> enabled.</li>';
        echo '<li>Ensure the selected <b>brand</b> ('.h($brand).') is <b>enabled for this MID</b> (Tola connector).</li>';
        echo '<li>Try one of enabled demo brands: <code>applepay</code>, <code>googlepay</code>, <code>paypal</code>, <code>vcard</code> via <code>?brand=...</code>.</li>';
        echo '<li>Currency for this MID appears to be <b>SLE</b>; using a different currency may be rejected.</li>';
        echo '</ul>';
        echo '</div>';
    }
} elseif ($result === 'REDIRECT') {
    $url    = $data['redirect_url']    ?? '';
    $method = strtoupper($data['redirect_method'] ?? 'GET');
    $params = $data['redirect_params'] ?? [];
    echo '<div class="tag t-info">REDIRECT '.$method.'</div>';
    echo '<div><span class="kv">URL:</span> '.h($url).'</div>';
    echo '<div class="h">Params</div><pre>'.pretty($params).'</pre>';

    if (!$url) {
        echo '<div class="tag t-err">Missing redirect_url</div>';
    } else {
        if ($method === 'POST') {
            echo "<form id='redir' method='POST' action='".h($url)."'>";
            foreach ($params as $k => $v) {
                $v = is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE);
                echo "<input type='hidden' name='".h($k)."' value='".h($v)."'>";
            }
            echo "</form><script>document.getElementById('redir').submit();</script>";
        } else {
            $qs   = http_build_query($params);
            $join = (strpos($url, '?') === false) ? '?' : '&';
            $full = $url . ($qs ? $join . $qs : '');
            echo "<script>location.href = ".json_encode($full).";</script>";
            echo "<noscript><a href='".h($full)."'>Proceed</a></noscript>";
        }
    }
} else {
    echo '<div class="tag t-info">Unexpected result</div>';
}
echo '</div>'; // panel
?>

  <div class="panel">
    <div class="h">Quick tips</div>
    <ul>
      <li>Switch brand via URL, e.g. <code>?brand=applepay</code>.</li>
      <li>If you still get 204006: ask AM to enable the brand for this MID (Tola connector) and verify Merchant→Protocols contains S2S APM mapping.</li>
      <li>Keep currency as <b>SLE</b> for this MID, unless AM adds more.</li>
    </ul>
  </div>
</div>
</body>
</html>
