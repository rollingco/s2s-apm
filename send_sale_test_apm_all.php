<?php
/**
 * Universal S2S APM SALE tester (multi-brand)
 * - Iterates over ALL brands from the documentation
 * - Sends x-www-form-urlencoded to https://api.leogcltd.com/post-va
 * - Pretty logs per brand (HTML)
 * - Handles SUCCESS / DECLINED / REDIRECT (GET/POST)
 * - Allows brand-specific overrides: identifier, payer_*, parameters[...]
 *
 * Notes:
 * - For vcard (TEST connector): use identifier success@gmail.com (SUCCESS) or fail@gmail.com (DECLINED)
 * - Some brands require additional parameters per Appendix B; fill them in overrides below
 * - Apple Pay via S2S APM requires parameters[paymentToken] generated on your side (left as placeholder)
 */

header('Content-Type: text/html; charset=utf-8');

/* ================== GLOBAL CONFIG ================== */
$CLIENT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$PASSWORD    = '554999c284e9f29cf95f090d9a8f3171';
$PAYMENT_URL = 'https://api.leogcltd.com/post-va'; // same endpoint for all brands
$BASE_RETURN = 'https://zal25.pp.ua/s2stest/callback.php'; // your return URL
$DEFAULT_IP  = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$AMOUNT      = '1.99';
$CURRENCY    = 'USD';
$DESC        = 'APM test payment';

/**
 * SALE signature (Appendix A):
 * md5(strtoupper(strrev(identifier + order_id + order_amount + order_currency + PASSWORD)))
 */
function sale_hash($identifier, $order_id, $amount, $currency, $password) {
    $src = $identifier . $order_id . $amount . $currency . $password;
    return md5(strtoupper(strrev($src)));
}

/* ================== FULL BRAND LIST (from doc) ================== */
$ALL_BRANDS = [
    // A
    'airtel','allpay','applepay','araka','astropay','axxi-cash','axxi-pin','a2a_transfer',
    // B
    'beeline','billplz','bitolo','bpwallet','cardpaymentz','citizen','cnfmo','crypto-btg','dcp','dl','dlocal','doku-hpp','dpbanktransfer',
    // F
    'fairpay','fawry','feexpaycard',
    // G
    'gigadat','googlepay',
    // H
    'hayvn','hayvn-wdwl','helio','help2pay',
    // I
    'ideal_crdz','instant-bills-pay','ipasspay',
    // J
    'jvz',
    // K
    'kashahpp',
    // M
    'm2p-debit','m2p-withdrawal','mcpayhpp','mcpayment','mercury','moov-money','moov-togo','mpesa','mtn-mobile-money',
    // N
    'naps','netbanking-upi','next-level-finance','nimbbl','noda','nv-apm',
    // O
    'om-wallet','one-collection',
    // P
    'pagsmile','panapay-netbanking','panapay-upi','papara','payablhpp','payftr-in','payhere','paymentrush','payneteasyhpp','payok-payout','payok-promptpay','payok-upi','paypal','paythrough-upi','paytota','pix','pr-cash','pr-creditcard','pr-cryptocurrency','pr-online','ptbs','ptn-email','ptn-inapp','ptn-sms',
    'pyk-bkmexpress','pyk-dana','pyk-linkaja','pyk-momo','pyk-nequipush','pyk-ovo','pyk-paparawallet','pyk-payout','pyk-pix','pyk-promptpay','pyk-shopeepay','pyk-truemoney','pyk-upi','pyk-viettelpay','pyk-zalopay',
    // S
    'sepa','sofortuber','stcpay','stripe-js','swifipay-deposit','sz-in-imps','sz-in-paytm','sz-in-upi','sz-jp-p2p','sz-kr-p2p','sz-my-ob','sz-th-ob','sz-th-qr','sz-vn-ob','sz-vn-p2p',
    // T
    'tabby','tamara','togocom','trustgate',
    // U
    'unipayment',
    // V
    'vcard','vouchstar','vpayapp_upi',
    // W
    'webpaygate','winnerpay',
    // X
    'xprowirelatam-ted','xprowirelatam-cash','xprowirelatam-bank-transfer','xprowirelatam-bank-slip','xprowirelatam-picpay','xprowirelatam-pix','xswitfly',
    // Y
    'yapily','yo-uganda-limited',
    // Z
    'zeropay',
];

/* ================== BRAND-SPECIFIC OVERRIDES ==================
 * For brands that require special identifier format, payer fields, or parameters[...]
 * Add/modify as needed per Appendix B.
 */
$BRAND_OVERRIDES = [
    // TEST connector — success/fail via identifier
    'vcard' => [
        'idType' => 'email',
        'identifier' => 'success@gmail.com',
        'parameters' => [],
    ],

    // UPI (example from doc: requires parameters[upiAddress])
    'sz-in-upi' => [
        'idType' => 'email',
        'identifier' => 'success@gmail.com',
        'parameters' => [ 'upiAddress' => 'address@upi' ],
        'payer' => [ 'payer_first_name' => 'John', 'payer_last_name' => 'Doe', 'payer_email' => 'doe@example.com' ],
    ],

    // Tabby (BNPL)
    'tabby' => [
        'idType' => 'custom',
        'identifier' => '0987654321-abcd',
        'parameters' => [
            'category' => 'Clothes',
            'buyer_registered_since' => '2019-08-24T14:15:22',
            'buyer_loyalty_level' => '0',
        ],
        'payer' => [
            'payer_first_name' => 'John', 'payer_last_name' => 'Doe', 'payer_address' => 'BigStreet',
            'payer_country' => 'US', 'payer_state' => 'CA', 'payer_city' => 'City', 'payer_zip' => '123456',
            'payer_email' => 'doe@example.com', 'payer_phone' => '0987654321',
        ],
    ],

    // Tamara (BNPL)
    'tamara' => [
        'idType' => 'custom',
        'identifier' => '0987654321-abcd',
        'parameters' => [
            'shipping_amount' => '1.01', 'tax_amount' => '1.01',
            'product_reference_id' => 'item125430', 'product_type' => 'Clothes',
            'product_sku' => 'ABC-12345-S-BL', 'product_amount' => '998.17',
        ],
        'payer' => [
            'payer_first_name' => 'John', 'payer_last_name' => 'Doe', 'payer_address' => 'BigStreet',
            'payer_country' => 'US', 'payer_state' => 'CA', 'payer_city' => 'City', 'payer_zip' => '123456',
            'payer_email' => 'doe@example.com', 'payer_phone' => '0987654321',
        ],
    ],

    // Fawry — phone number in identifier (not parameters)
    'fawry' => [
        'idType' => 'phone',
        'identifier' => '+201234567890',
        'parameters' => [],
        'payer' => [ 'payer_first_name' => 'Omar', 'payer_last_name' => 'Ali', 'payer_email' => 'omar@example.com' ],
    ],

    // SEPA (example IBAN/holder)
    'sepa' => [
        'idType' => 'email',
        'identifier' => 'success@gmail.com',
        'parameters' => [ 'iban' => 'DE89370400440532013000', 'account_holder_name' => 'John Doe' ],
    ],

    // Apple Pay — requires parameters[paymentToken] from your Apple Pay integration
    'applepay' => [
        'idType' => 'email',
        'identifier' => 'success@gmail.com',
        'parameters' => [
            // 'paymentToken' => '... ApplePay paymentData token ...'
        ],
    ],

    // payneteasyhpp — requires full payer details (doc remark)
    'payneteasyhpp' => [
        'idType' => 'email',
        'identifier' => 'success@gmail.com',
        'parameters' => [],
        'payer' => [
            'payer_first_name' => 'John', 'payer_last_name' => 'Doe', 'payer_address' => 'BigStreet',
            'payer_city' => 'City', 'payer_state' => 'CA', 'payer_zip' => '123456',
            'payer_country' => 'US', 'payer_phone' => '+1234567890', 'payer_email' => 'doe@example.com',
        ],
    ],
];

/* ================== PAGE STYLES ================== */
?>
<!doctype html>
<html><head><meta charset="utf-8">
<title>S2S APM — Multi-brand tester (full)</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#0f172a;color:#e2e8f0;margin:0;padding:20px}
h1{font-weight:700;margin:0 0 16px}
.brand{border:1px solid #334155;border-radius:16px;margin:16px 0;padding:16px;background:#0b1220}
.brand h2{margin:0 0 12px;font-size:18px}
.kv{display:grid;grid-template-columns:220px 1fr;gap:8px;margin:12px 0}
.kv b{color:#a5b4fc}
pre{background:#020617;border-radius:12px;padding:12px;overflow:auto;border:1px solid #1e293b}
.badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;border:1px solid #475569}
.badge.ok{color:#10b981;border-color:#065f46}
.badge.err{color:#ef4444;border-color:#7f1d1d}
.badge.redir{color:#f59e0b;border-color:#7c2d12}
a{color:#93c5fd}
hr.sep{border:none;border-top:1px dashed #334155;margin:12px 0}
.note{font-size:12px;color:#94a3b8}
</style>
</head><body>
<h1>🧪 S2S APM — Multi-brand tester (full)</h1>
<p class="note">Endpoint: <?=htmlspecialchars($PAYMENT_URL)?> · Amount: <?=$AMOUNT?> <?=$CURRENCY?> · Return URL: <?=htmlspecialchars($BASE_RETURN)?></p>
<?php

/* ================== BUILD FINAL RUN LIST ================== */
$RUN = [];
foreach ($ALL_BRANDS as $b) {
    $ov = $BRAND_OVERRIDES[$b] ?? [];
    $RUN[] = array_merge([
        'brand' => $b,
        'idType' => 'email',
        'identifier' => 'success@gmail.com', // default identifier (override per brand if needed)
        'parameters' => [],
        'payer' => [],
    ], $ov);
}

/* ================== RUN SEQUENTIALLY ================== */
foreach ($RUN as $i => $cfg) {
    $brand      = $cfg['brand'];
    $idType     = $cfg['idType'] ?? 'email';
    $identifier = $cfg['identifier'] ?? ($idType === 'phone' ? '+10000000000' : 'success@gmail.com');
    $parameters = $cfg['parameters'] ?? [];
    $payer      = $cfg['payer'] ?? [];

    $order_id   = strtoupper($brand) . '_' . time() . '_' . ($i+1);
    $amount     = $AMOUNT;
    $currency   = $CURRENCY;
    $hash       = sale_hash($identifier, $order_id, $amount, $currency, $PASSWORD);

    // Build payload
    $payload = array_merge([
        'action'            => 'SALE',
        'client_key'        => $CLIENT_KEY,
        'brand'             => $brand,
        'order_id'          => $order_id,
        'order_amount'      => $amount,
        'order_currency'    => $currency,
        'order_description' => $DESC,
        'identifier'        => $identifier,
        'payer_ip'          => $DEFAULT_IP,
        'return_url'        => $BASE_RETURN,
        'hash'              => $hash,
    ], $payer);

    // Flatten parameters[...] as parameters[key]
    foreach ($parameters as $k => $v) {
        $payload["parameters[$k]"] = $v;
    }

    $postFields = http_build_query($payload);

    $ch = curl_init($PAYMENT_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($postFields),
        ],
        CURLOPT_TIMEOUT        => 60,
    ]);
    $response   = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $result = is_array($data) ? ($data['result'] ?? '') : '';

    echo '<div class="brand">';
    echo '<h2>[' . ($i+1) . '] Brand: <span class="badge">' . htmlspecialchars($brand) . '</span></h2>';

    echo '<div class="kv"><b>Identifier</b><div>' . htmlspecialchars($identifier) . ' <span class="badge">'.htmlspecialchars($idType).'</span></div></div>';
    echo '<div class="kv"><b>Order</b><div>' . htmlspecialchars($order_id) . ' — ' . htmlspecialchars($amount . ' ' . $currency) . '</div></div>';

    echo '<div class="kv"><b>Payload</b><div><pre>'.htmlspecialchars(print_r($payload, true)).'</pre></div></div>';
    echo '<div class="kv"><b>Raw Response</b><div><pre>'.htmlspecialchars($response).'</pre></div></div>';

    if ($curl_error) {
        echo '<div class="kv"><b>cURL</b><div><span class="badge err">CURL ERROR</span><pre>'.htmlspecialchars($curl_error).'</pre></div></div>';
    } else {
        echo '<div class="kv"><b>Parsed</b><div><pre>'.htmlspecialchars(print_r($data, true)).'</pre></div></div>';

        if ($result === 'SUCCESS') {
            echo '<div class="kv"><b>Result</b><div><span class="badge ok">SUCCESS</span></div></div>';
        } elseif ($result === 'DECLINED') {
            echo '<div class="kv"><b>Result</b><div><span class="badge err">DECLINED</span></div></div>';
        } elseif ($result === 'REDIRECT') {
            $url    = $data['redirect_url']    ?? '';
            $method = strtoupper($data['redirect_method'] ?? 'GET');
            $params = $data['redirect_params'] ?? [];
            echo '<div class="kv"><b>Result</b><div><span class="badge redir">REDIRECT</span> ' . htmlspecialchars($method) . '</div></div>';
            echo '<div class="kv"><b>Redirect URL</b><div>' . htmlspecialchars($url) . '</div></div>';
            echo '<div class="kv"><b>Redirect params</b><div><pre>'.htmlspecialchars(print_r($params, true)).'</pre></div></div>';

            if ($url) {
                if ($method === 'POST') {
                    // auto-submit POST
                    echo "<form id='post_$i' method='POST' action='".htmlspecialchars($url, ENT_QUOTES)."'>";
                    foreach ($params as $k => $v) {
                        $v = is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE);
                        echo "<input type='hidden' name='".htmlspecialchars($k, ENT_QUOTES)."' value='".htmlspecialchars($v, ENT_QUOTES)."'>";
                    }
                    echo "</form>";
                    echo "<script>document.getElementById('post_$i').submit();</script>";
                } else {
                    // GET: append params as query string
                    $qs = http_build_query($params);
                    $join = (strpos($url, '?') === false) ? '?' : '&';
                    $full = $url . ($qs ? $join . $qs : '');
                    echo "<script>window.open(" . json_encode($full) . ", '_blank');</script>";
                    echo "<div class='kv'><b>Open</b><div><a target='_blank' href='".htmlspecialchars($full, ENT_QUOTES)."'>Open redirect (GET)</a></div></div>";
                }
            }
        } else {
            echo '<div class="kv"><b>Result</b><div><span class="badge">UNKNOWN</span></div></div>';
        }
    }

    echo '<hr class="sep">';
    echo '</div>'; // .brand
}

?>
</body></html>
