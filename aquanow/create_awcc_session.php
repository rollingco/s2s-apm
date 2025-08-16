<?php
// ==== CONFIG ====
$CHECKOUT_HOST = 'https://pay.leogcltd.com';
$MERCHANT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET_KEY    = '54999c284e9f29cf95f090d9a8f3171';

$SUCCESS_URL   = 'https://example.com/success';
$CANCEL_URL    = 'https://example.com/cancel';

// awcc (USDT) опції
$networkType = 'eth';  // 'eth' або 'tron'
$bech32      = false;  // true/false (не впливає на USDT)

$orderNumber   = 'order-'.time();
$orderCurrency = 'USDT';                 // <— криптовалюта
$orderDesc     = 'Test payment via awcc';

// === payload БЕЗ amount ===
$payload = [
    'merchant_key' => $MERCHANT_KEY,
    'operation'    => 'purchase',
    'methods'      => ['awcc'],
    'parameters'   => [
        'awcc' => [
            'network_type' => $networkType,
            'bech32'       => $bech32 ? 'true' : 'false',
        ],
    ],
    'order' => [
        'number'      => $orderNumber,
        // 'amount'    => (не надсилаємо для crypto з невідомою сумою)
        'currency'    => $orderCurrency,
        'description' => $orderDesc,
    ],
    'cancel_url'  => $CANCEL_URL,
    'success_url' => $SUCCESS_URL,
    'customer' => [
        'name'  => 'John Doe',
        'email' => 'test@example.com',
    ],
    'billing_address' => [
        'country' => 'US',
        'state'   => 'CA',
        'city'    => 'Los Angeles',
        'address' => 'Moor Building 35274',
        'zip'     => '123456',
        'phone'   => '347771112233',
    ],
];

// === HASH без amount ===
// Формула з доки: SHA1(MD5(UPPER(order.number + order.currency + order.description + password)))
$payload['hash'] = buildAuthHashCryptoNoAmount($orderNumber, $orderCurrency, $orderDesc, $SECRET_KEY);

// === HTTP ===
$response = httpPostJson(rtrim($CHECKOUT_HOST,'/').'/api/v1/session', $payload);
echo "HTTP {$response['code']}\n{$response['body']}\n";
if ($response['code'] >= 200 && $response['code'] < 300) {
    $data = json_decode($response['body'], true);
    foreach (['checkout_url','redirect_url','payment_url','url'] as $k) {
        if (!empty($data[$k])) { echo "Open in browser: {$data[$k]}\n"; break; }
    }
}

// ==== helpers ====
function httpPostJson(string $url, array $data): array {
    $json = json_encode($data, JSON_UNESCAPED_SLASHES);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) $body = "cURL error: $err";
    return ['code'=>$code, 'body'=>$body];
}

function buildAuthHashCryptoNoAmount(string $orderNumber, string $currency, string $description, string $secret): string {
    // для crypto без amount: order.number + order.currency + order.description + password
    $toMd5 = strtoupper($orderNumber.$currency.$description.$secret);
    return sha1(md5($toMd5));
}
