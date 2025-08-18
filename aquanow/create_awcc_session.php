<?php
/**
 * Create Checkout session (AWCC) — PHP
 * - Currency is UPPERCASE (fixes "order.currency: This value is not valid")
 * - Hash matches Postman pre-request script exactly:
 *   SHA1( MD5( UPPER(order_number + order_amount + order_currency + order_description + merchant_pass) ) )
 */

// ===== CONFIG (your MID) =====
$CHECKOUT_HOST = 'https://pay.leogcltd.com';
$MERCHANT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$MERCHANT_PASS = '54999c284e9f29cf95f090d9a8f3171'; // same as merch1_pass_post in Postman

$SUCCESS_URL   = 'https://example.com/success';
$CANCEL_URL    = 'https://example.com/cancel';

// ===== Order test data =====
// If you test fiat flow, keep amount and use a FIAT currency like USD.
// If you test crypto-without-amount, remove 'amount' from payload and adjust hash accordingly.
$orderNumber   = 'order-1234';
$orderAmount   = '0.19';       // keep as string to match Postman example
$orderCurrency = 'USD';        // MUST be uppercase (this fixes your last error)
$orderDesc     = 'Important gift';

// ===== AWCC specific (Aquanow) =====
$networkType = 'eth';          // 'eth' or 'tron'
$bech32      = false;          // irrelevant for USDT, kept for completeness

// ===== Build payload =====
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
        'amount'      => $orderAmount,     // remove this if you test crypto-without-amount
        'currency'    => $orderCurrency,   // MUST be uppercase
        'description' => $orderDesc,
    ],
    'cancel_url'  => $CANCEL_URL,
    'success_url' => $SUCCESS_URL,
];

// ===== Hash EXACTLY like your Postman script =====
$payload['hash'] = buildSessionHash_PostmanExact(
    $payload['order']['number'],
    $payload['order']['amount'],
    $payload['order']['currency'],
    $payload['order']['description'],
    $MERCHANT_PASS
);

// ===== Send request =====
$endpoint = rtrim($CHECKOUT_HOST, '/').'/api/v1/session';
$res = httpPostJson($endpoint, $payload);

header('Content-Type: text/plain; charset=utf-8');
echo "POST $endpoint\n";
echo "Request:\n".json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n\n";
echo "HTTP {$res['code']}\n{$res['body']}\n";

// Try to extract a checkout URL for convenience
$data = json_decode($res['body'], true);
if (is_array($data)) {
    foreach (['checkout_url','redirect_url','payment_url','url'] as $k) {
        if (!empty($data[$k])) { echo "\nOpen in browser: {$data[$k]}\n"; break; }
    }
}

// ================= helpers =================
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

/**
 * Matches the Postman Pre-request Script exactly:
 * var to_md5 = order_number + order_amount + order_currency + order_description + merchant_pass;
 * var hash   = CryptoJS.SHA1(CryptoJS.MD5(to_md5.toUpperCase()).toString());
 * var result = CryptoJS.enc.Hex.stringify(hash);
 */
function buildSessionHash_PostmanExact(
    string $orderNumber,
    string $orderAmount,
    string $orderCurrency,
    string $orderDescription,
    string $merchantPass
): string {
    $toMd5Upper = strtoupper($orderNumber . $orderAmount . $orderCurrency . $orderDescription . $merchantPass);
    return sha1(md5($toMd5Upper)); // md5 hex → sha1(hex) => lowercase hex (same as CryptoJS)
}
