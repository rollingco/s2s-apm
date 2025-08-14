<?php
/**
 * S2S APM SALE demo
 * - Builds correct SALE signature (Appendix A)
 * - Sends x-www-form-urlencoded POST
 * - Handles REDIRECT (GET with query params or POST with fields)
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h3>🟢 S2S APM SALE demo</h3>";

/* ================== CONFIG ================== */
$CLIENT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$PASSWORD    = '554999c284e9f29cf95f090d9a8f3171';
$PAYMENT_URL = 'https://api.leogcltd.com/post-va'; // prod/test URL від акаунт-менеджера

// тестові значення з доки
$brand       = 'vcard';                  // тестовий конектор
$identifier  = 'success@gmail.com';      // success@gmail.com | fail@gmail.com
/* ============================================ */

// order
$order_id     = 'ORDER_' . time();
$order_amount = '1.99';
$order_ccy    = 'USD';
$description  = 'APM test payment';
$payer_ip     = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$return_url   = 'https://zal25.pp.ua/s2stest/callback.php';

/**
 * Appendix A: SALE signature
 * md5(strtoupper(strrev(identifier + order_id + order_amount + order_currency + PASSWORD)))
 * (без розділювачів, саме конкатенація значень)
 */
function build_sale_hash($identifier, $order_id, $amount, $currency, $password) {
    $src = $identifier . $order_id . $amount . $currency . $password;
    return md5(strtoupper(strrev($src)));
}
// :contentReference[oaicite:2]{index=2}

$hash = build_sale_hash($identifier, $order_id, $order_amount, $order_ccy, $PASSWORD);

$payload = [
    'action'            => 'SALE',
    'client_key'        => $CLIENT_KEY,
    'brand'             => $brand,
    'order_id'          => $order_id,
    'order_amount'      => $order_amount,
    'order_currency'    => $order_ccy,
    'order_description' => $description,
    'identifier'        => $identifier,
    'payer_ip'          => $payer_ip,
    'return_url'        => $return_url,
    'hash'              => $hash,
];

// Надсилаємо S2S POST як x-www-form-urlencoded
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
$response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);
// :contentReference[oaicite:3]{index=3}

$data = json_decode($response, true);

/* ========== Debug block ========== */
echo "<pre>";
echo "🔹 ORDER_ID: {$order_id}\n\n";
echo "📤 Sent:\n";
print_r($payload);
echo "\n📥 Raw Response:\n{$response}\n";
echo "\n📥 Parsed Response:\n";
print_r($data);
if ($curl_error) {
    echo "\n❌ CURL Error: {$curl_error}\n";
}
echo "</pre>";

if (!is_array($data)) {
    echo '<p style="color:red">❌ Invalid JSON in response.</p>';
    exit;
}

/**
 * Обробка результатів:
 * - SUCCESS/DECLINED — просто показуємо
 * - REDIRECT — виконуємо редирект згідно redirect_method та redirect_params
 *   (для GET з query-параметрами — або через форму, або через JS редирект)
 */
// :contentReference[oaicite:4]{index=4}

$result = $data['result'] ?? '';
if ($result === 'SUCCESS') {
    echo "<p style='color:green'>✅ SUCCESS</p>";
} elseif ($result === 'DECLINED') {
    echo "<p style='color:red'>❌ DECLINED</p>";
} elseif ($result === 'REDIRECT') {
    $url    = $data['redirect_url']    ?? '';
    $method = strtoupper($data['redirect_method'] ?? 'GET');
    $params = $data['redirect_params'] ?? [];

    if (!$url) {
        echo "<p style='color:red'>❌ REDIRECT без redirect_url.</p>";
        exit;
    }

    // Якщо метод POST — автоформа
    if ($method === 'POST') {
        echo "<p>➡ Redirecting via POST…</p>";
        echo "<form id='redir' method='POST' action='".htmlspecialchars($url, ENT_QUOTES)."'>";
        foreach ($params as $k => $v) {
            $v = is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE);
            echo "<input type='hidden' name='".htmlspecialchars($k, ENT_QUOTES)."' value='".htmlspecialchars($v, ENT_QUOTES)."'>";
        }
        echo "</form><script>document.getElementById('redir').submit();</script>";
        exit;
    }

    // Інакше GET:
    // Якщо в redirect_url вже є query-параметри, дока радить або передати їх через інпути GET-форми,
    // або зробити JS-редирект з повним URL з параметрами.
    // Тут — збираємо єдину URL-строку й редиректимо через JS.
    $qs = http_build_query($params);
    $join = (strpos($url, '?') === false) ? '?' : '&';
    $full = $url . ($qs ? $join . $qs : '');

    echo "<p>➡ Redirecting via GET…</p>";
    echo "<script>document.location = " . json_encode($full) . ";</script>";
    echo "<noscript><a href='".htmlspecialchars($full, ENT_QUOTES)."'>Proceed</a></noscript>";
    exit;
} else {
    echo "<p>⚠️ Unexpected result: <b>" . htmlspecialchars($result) . "</b></p>";
}

/* Підказка: callback.php має повертати 'OK' на будь-який валідний POST від платформи */
