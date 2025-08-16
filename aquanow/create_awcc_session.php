<?php
// ==== CONFIG (ваші дані) ====
$CHECKOUT_HOST = 'https://pay.leogcltd.com';
$MERCHANT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET_KEY    = '54999c284e9f29cf95f090d9a8f3171';

$SUCCESS_URL   = 'https://example.com/success';
$CANCEL_URL    = 'https://example.com/cancel';

// awcc options
$networkType = 'eth';      // 'eth' або 'tron'
$bech32      = false;      // true/false

// ==== PAYLOAD ====
$orderNumber   = 'order-'.time();
$orderAmount   = '10.00';
$orderCurrency = 'USD';
$orderDesc     = 'Test payment via awcc';

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
        'amount'      => $orderAmount,
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
        'phone'   => '347777112233',
    ],
];

// ==== HASH ====
$payload['hash'] = buildAuthHash($orderNumber, $orderAmount, $orderCurrency, $orderDesc, $SECRET_KEY);

// ==== HTTP ====
$response = httpPostJson(rtrim($CHECKOUT_HOST,'/').'/api/v1/session', $payload);
echo "HTTP {$response['code']}\n{$response['body']}\n";

$data = json_decode($response['body'], true);
if (is_array($data)) {
    foreach (['checkout_url','redirect_url','payment_url','url'] as $k) {
        if (!empty($data[$k])) {
            echo "Open in browser: {$data[$k]}\n";
            break;
        }
    }
}

// ===== helpers =====
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
    if ($err) { $body = "cURL error: $err"; }
    return ['code'=>$code, 'body'=>$body];
}

// Формула з документації:
// SHA1( MD5( strtoupper(order.number + order.amount + order.currency + order.description + merchant.pass) ) )
function buildAuthHash(string $orderNumber, ?string $amount, string $currency, string $description, string $secret): string {
    $parts = [$orderNumber];
    if ($amount !== null && $amount !== '') {
        $parts[] = $amount;
    }
    $parts[] = $currency;
    $parts[] = $description;
    $parts[] = $secret;

    $toMd5 = strtoupper(implode('', $parts));
    return sha1(md5($toMd5));
}
