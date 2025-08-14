<?php
/**
 * Universal S2S APM SALE tester
 * - Iterates brands sequentially
 * - Sends x-www-form-urlencoded to https://api.leogcltd.com/post-va
 * - Pretty logs per brand
 * - Handles REDIRECT (GET/POST), SUCCESS, DECLINED
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

/* ================== BRAND SET ==================
 * Expand this list as needed. Keys:
 *  - brand: APM method code
 *  - idType: 'email' | 'phone' | 'custom' — how to fill identifier
 *  - identifier: value (optional, overrides default)
 *  - parameters: extra parameters[...] required by brand
 *  - payer: optional payer_* fields if brand requires them
 */
$BRANDS = [
    // 1) Test connector — should always work in TEST
    [
        'brand' => 'vcard',
        'idType' => 'email',
        'identifier' => 'success@gmail.com', // success / fail for testing
        'parameters' => [], // none
    ],
    // 2) UPI (sample from doc: sz-in-upi requires parameters[upiAddress])
    [
        'brand' => 'sz-in-upi',
        'idType' => 'email',
        'identifier' => 'success@gmail.com',
        'parameters' => [
            'upiAddress' => 'address@upi',
        ],
        'payer' => [
            'payer_first_name' => 'John',
            'payer_last_name'  => 'Doe',
            'payer_email'      => 'doe@example.com',
        ],
    ],
    // 3) Tabby (BNPL) — requires category / buyer_registered_since / buyer_loyalty_level
    [
        'brand' => 'tabby',
        'idType' => 'custom',
        'identifier' => '0987654321-abcd',
        'parameters' => [
            'category'              => 'Clothes',
            'buyer_registered_since'=> '2019-08-24T14:15:22',
            'buyer_loyalty_level'   => '0',
        ],
        'payer' => [
            'payer_first_name' => 'John',
            'payer_last_name'  => 'Doe',
            'payer_address'    => 'BigStreet',
            'payer_country'    => 'US',
            'payer_state'      => 'CA',
            'payer_city'       => 'City',
            'payer_zip'        => '123456',
            'payer_email'      => 'doe@example.com',
            'payer_phone'      => '0987654321',
        ],
    ],
    // 4) Tamara (BNPL) — requires product fields & amounts
    [
        'brand' => 'tamara',
        'idType' => 'custom',
        'identifier' => '0987654321-abcd',
        'parameters' => [
            'shipping_amount'     => '1.01',
            'tax_amount'          => '1.01',
            'product_reference_id'=> 'item125430',
            'product_type'        => 'Clothes',
            'product_sku'         => 'ABC-12345-S-BL',
            'product_amount'      => '998.17',
        ],
        'payer' => [
            'payer_first_name' => 'John',
            'payer_last_name'  => 'Doe',
            'payer_address'    => 'BigStreet',
            'payer_country'    => 'US',
            'payer_state'      => 'CA',
            'payer_city'       => 'City',
            'payer_zip'        => '123456',
            'payer_email'      => 'doe@example.com',
            'payer_phone'      => '0987654321',
        ],
    ],
    // 5) Fawry — phone number must be sent in identifier
    [
        'brand' => 'fawry',
        'idType' => 'phone',
        'identifier' => '+201234567890',
        'parameters' => [],
        'payer' => [
            'payer_first_name' => 'Omar',
            'payer_last_name'  => 'Ali',
            'payer_email'      => 'omar@example.com',
        ],
    ],
    // 6) SEPA — typical IBAN holder (example)
    [
        'brand' => 'sepa',
        'idType' => 'email',
        'identifier' => 'success@gmail.com',
        'parameters' => [
            'iban'                 => 'DE89370400440532013000',
            'account_holder_name'  => 'John Doe',
        ],
    ],
    // 7) Apple Pay — (token not provided here; left as placeholder)
    // To really test applepay via S2S APM, you'd need parameters[paymentToken]
    [
        'brand' => 'applepay',
        'idType' => 'email',
        'identifier' => 'success@gmail.com',
        'parameters' => [
            // 'paymentToken' => '... ApplePay paymentData token ...'
        ],
    ],
];

/* ================== PAGE STYLES ================== */
?>
<!doctype html>
<html><head><meta charset="utf-8">
<title>S2S APM — Multi-brand tester</title>
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
</style>
</head><body>
<h1>🧪 S2S APM — Multi-brand tester</h1>
<?php

/* ================== RUN SEQUENTIALLY ================== */
foreach ($BRANDS as $i => $cfg) {
    $brand      = $cfg['brand'];
    $idType     = $cfg['idType'] ?? 'email';
    $identifier = $cfg['identifier'] ?? ($idType === 'phone' ? '+10000000000' : 'success@gmail.com'); // default for demo
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

    echo '</div>'; // .brand
}

?>
</body></html>
