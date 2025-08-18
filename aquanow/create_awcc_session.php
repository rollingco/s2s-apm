<?php
/**
 * Create Checkout session (AWCC, crypto w/o amount) — PHP
 * - Payload: order has NO "amount"
 * - Signature: SHA1( MD5( UPPER(order.number + order.currency + order.description + merchant.pass) ) )
 * - Verbose debug block prints the exact string_to_sign and hashes to match Postman Console.
 */

// ===== CONFIG (your MID) =====
$CHECKOUT_HOST = 'https://pay.leogcltd.com';
$MERCHANT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$MERCHANT_PASS = '554999c284e9f29cf95f090d9a8f3171'; // your latest value

$SUCCESS_URL   = 'https://example.com/success';
$CANCEL_URL    = 'https://example.com/cancel';

// ===== Order test data (CRYPTO) =====
// For AWCC crypto, use a crypto currency (e.g., USDT) and DO NOT send amount.
$orderNumber   = 'order-'.time();
$orderCurrency = 'USDT';               // crypto currency (uppercase)
$orderDesc     = 'Test AWCC without amount';

// ===== AWCC specific (Aquanow) =====
$networkType = 'eth';                  // 'eth' or 'tron'
$bech32      = false;                  // irrelevant for USDT, kept for completeness

// ===== Build payload (NO amount) =====
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
        // 'amount'    => not sent for crypto flow w/o amount
        'currency'    => $orderCurrency,  // MUST be uppercase
        'description' => $orderDesc,
    ],
    'cancel_url'  => $CANCEL_URL,
    'success_url' => $SUCCESS_URL,
];

// ===== Hash (NO amount in the string_to_sign) =====
$payload['hash'] = buildSessionHash_AWCC_NoAmount(
    $payload['order']['number'],
    $payload['order']['currency'],
    $payload['order']['description'],
    $MERCHANT_PASS,
    $debug // will be filled for printing
);

// ===== Send request =====
$endpoint = rtrim($CHECKOUT_HOST, '/').'/api/v1/session';
$res = httpPostJson($endpoint, $payload);

// ===== Output (verbose) =====
header('Content-Type: text/plain; charset=utf-8');

echo "POST $endpoint\n\n";

// Debug block — compare with Postman Console if needed
echo "=== HASH DEBUG (AWCC, w/o amount) ==================================\n";
echo "string_to_sign:   {$debug['string_to_sign']}\n";
echo "uppercased:       {$debug['upper']}\n";
echo "md5(upper):       {$debug['md5_hex']}\n";
echo "sha1(md5_hex):    {$payload['hash']}\n";
echo "=====================================================================\n\n";

echo "Request:\n".json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n\n";
echo "HTTP {$res['code']}\n{$res['body']}\n";

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
 * AWCC crypto (no amount) signature:
 *   string_to_sign = order.number + order.currency + order.description + merchant.pass
 *   upper = strtoupper(string_to_sign)
 *   md5_hex = md5(upper)
 *   sha1_hex = sha1(md5_hex)
 */
function buildSessionHash_AWCC_NoAmount(
    string $orderNumber,
    string $orderCurrency,
    string $orderDescription,
    string $merchantPass,
    ?array &$debug = null
): string {
    $stringToSign = $orderNumber . $orderCurrency . $orderDescription . $merchantPass;
    $upper        = strtoupper($stringToSign);
    $md5Hex       = md5($upper);
    $sha1Hex      = sha1($md5Hex);

    $debug = [
        'string_to_sign' => $stringToSign,
        'upper'          => $upper,
        'md5_hex'        => $md5Hex,
        'sha1_hex'       => $sha1Hex,
    ];
    return $sha1Hex;
}
