<?php
/**
 * Simplified CSS version of S2S APM tester
 */
header('Content-Type: text/html; charset=utf-8');
$CLIENT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$PASSWORD    = '554999c284e9f29cf95f090d9a8f3171';
$PAYMENT_URL = 'https://api.leogcltd.com/post-va';
$BASE_RETURN = 'https://zal25.pp.ua/s2stest/callback.php';
$DEFAULT_IP  = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$AMOUNT      = '1.99';
$CURRENCY    = 'USD';
$DESC        = 'APM test payment';
function sale_hash($identifier, $order_id, $amount, $currency, $password) {
    $src = $identifier . $order_id . $amount . $currency . $password;
    return md5(strtoupper(strrev($src)));
}
$ALL_BRANDS = ['vcard','applepay','googlepay','paypal'];
$BRAND_OVERRIDES = [
    'vcard' => ['identifier' => 'success@gmail.com'],
    'applepay' => ['identifier' => 'success@gmail.com'],
    'googlepay' => ['identifier' => 'success@gmail.com'],
    'paypal' => ['identifier' => 'success@gmail.com']
];
?>
<!doctype  html>
<html><head><meta charset="utf-8">
<title>S2S APM — Tester</title>
<style>
body{font-family:Arial, Helvetica, sans-serif;background:#ffffff;color:#111;margin:0;padding:20px}
h1{margin:0 0 16px}
.brand{border:1px solid #ddd;border-radius:8px;margin:16px 0;padding:16px;background:#fff}
.brand h2{margin:0 0 12px;font-size:18px}
.kv{display:grid;grid-template-columns:200px 1fr;gap:6px;margin:10px 0}
.kv b{color:#333}
pre{background:#f6f8fa;border:1px solid #e1e4e8;border-radius:6px;padding:10px;overflow:auto}
.badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:12px;border:1px solid #ccc;background:#eee;color:#333}
.badge.ok{background:#e8f5e9;border-color:#c8e6c9;color:#2e7d32}
.badge.err{background:#ffebee;border-color:#ffcdd2;color:#c62828}
.badge.redir{background:#fff3e0;border-color:#ffe0b2;color:#ef6c00}
a{color:#0366d6}
hr.sep{border:none;border-top:1px dashed #ddd;margin:12px 0}
.note{font-size:12px;color:#666}
</style>
</head><body>
<h1>🧪 S2S APM — Tester</h1>
<?php
foreach ($ALL_BRANDS as $i => $brand) {
    $identifier = $BRAND_OVERRIDES[$brand]['identifier'] ?? 'success@gmail.com';
    $order_id   = strtoupper($brand) . '_' . time() . '_' . ($i+1);
    $hash       = sale_hash($identifier, $order_id, $AMOUNT, $CURRENCY, $PASSWORD);
    $payload = [
        'action' => 'SALE',
        'client_key' => $CLIENT_KEY,
        'brand' => $brand,
        'order_id' => $order_id,
        'order_amount' => $AMOUNT,
        'order_currency' => $CURRENCY,
        'order_description' => $DESC,
        'identifier' => $identifier,
        'payer_ip' => $DEFAULT_IP,
        'return_url' => $BASE_RETURN,
        'hash' => $hash
    ];
    $postFields = http_build_query($payload);
    $ch = curl_init($PAYMENT_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    $result = $data['result'] ?? '';
    echo '<div class="brand">';
    echo '<h2>['.($i+1).'] Brand: <span class="badge">'.$brand.'</span></h2>';
    echo '<b>Payload</b><pre>'.htmlspecialchars(print_r($payload, true)).'</pre>';
    echo '<b>Raw Response</b><pre>'.htmlspecialchars($response).'</pre>';
    if ($curl_error) {
        echo '<span class="badge err">CURL ERROR</span> '.htmlspecialchars($curl_error);
    } else {
        echo '<b>Parsed</b><pre>'.htmlspecialchars(print_r($data, true)).'</pre>';
        if ($result === 'SUCCESS') echo '<span class="badge ok">SUCCESS</span>';
        elseif ($result === 'DECLINED') echo '<span class="badge err">DECLINED</span>';
        elseif ($result === 'REDIRECT') echo '<span class="badge redir">REDIRECT</span>';
        else echo '<span class="badge">UNKNOWN</span>';
    }
    echo '</div>';
}
?>
</body></html>
