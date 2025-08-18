<?php
$CHECKOUT_HOST = 'https://pay.leogcltd.com';
$MERCHANT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$MERCHANT_PASS = '54999c284e9f29cf95f090d9a8f3171';

$SUCCESS_URL   = 'https://example.com/success';
$CANCEL_URL    = 'https://example.com/cancel';

// === your test data (як у Postman прикладі) ===
$orderNumber   = 'order-1234';
$orderAmount   = '0.19';
$orderCurrency = 'usd';            // у Postman теж було 'usd'
$orderDesc     = 'Important gift';

$payload = [
    'merchant_key' => $MERCHANT_KEY,
    'operation'    => 'purchase',
    'methods'      => ['awcc'],     // або 'card' — залежно від кейсу
    'order'        => [
        'number'      => $orderNumber,
        'amount'      => $orderAmount,
        'currency'    => $orderCurrency,
        'description' => $orderDesc,
    ],
    'cancel_url'   => $CANCEL_URL,
    'success_url'  => $SUCCESS_URL,
];

// === hash строго як у твоєму Pre-request Script ===
$payload['hash'] = buildSessionHash_PostmanExact(
    $payload['order']['number'],
    $payload['order']['amount'],
    $payload['order']['currency'],
    $payload['order']['description'],
    $MERCHANT_PASS
);

// --- HTTP ---
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => rtrim($CHECKOUT_HOST, '/').'/api/v1/session',
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
]);
$resBody = curl_exec($ch);
$http    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err     = curl_error($ch);
curl_close($ch);

if ($err) { die("cURL error: $err"); }
echo "HTTP $http\n$resBody\n";

// ================= helpers =================
function buildSessionHash_PostmanExact(
    string $orderNumber,
    string $orderAmount,
    string $orderCurrency,
    string $orderDescription,
    string $merchantPass
): string {
    $toMd5Upper = strtoupper($orderNumber . $orderAmount . $orderCurrency . $orderDescription . $merchantPass);
    return sha1(md5($toMd5Upper));
}
