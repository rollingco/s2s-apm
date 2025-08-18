<?php
/**
 * Create Checkout session (AWCC) — PHP
 * Hash exactly like Postman pre-request script:
 *   SHA1( MD5( UPPER(order_number + order_amount + order_currency + order_description + merchant_pass) ) )
 * Includes verbose debug to compare with Postman Console.
 */

// ===== CONFIG (your MID) =====
$CHECKOUT_HOST = 'https://pay.leogcltd.com';
$MERCHANT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$MERCHANT_PASS = '54999c284e9f29cf95f090d9a8f3171'; // must match merch1_pass_post in Postman

$SUCCESS_URL   = 'https://example.com/success';
$CANCEL_URL    = 'https://example.com/cancel';

// ===== Order test data =====
$orderNumber   = 'order-1234';
$orderAmount   = '0.19';   // keep string; format must match what you use in Postman
$orderCurrency = 'USD';    // MUST be uppercase for API validation
$orderDesc     = 'Important gift';

// If you test crypto-without-amount, set false here (amount omitted from payload & hash)
const USE_AMOUNT = true;

// ===== AWCC specific (Aquanow) =====
$networkType = 'eth';      // 'eth' or 'tron'
$bech32      = false;

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
        'currency'    => $orderCurrency,
        'description' => $orderDesc,
    ],
    'cancel_url'  => $CANCEL_URL,
    'success_url' => $SUCCESS_URL,
];

if (USE_AMOUNT) {
    $payload['order']['amount'] = $orderAmount;
}

// ===== Hash EXACTLY like your Postman script =====
$payload['hash'] = buildSessionHash_PostmanExact(
    $payload['order']['number'],
    USE_AMOUNT ? $payload['order']['amount'] : '',
    $payload['order']['currency'],
    $payload['order']['description'],
    $MERCHANT_PASS,
    $debug // <- will be filled with intermediate values
);

// ===== Send request =====
$endpoint = rtrim($CHECKOUT_HOST, '/').'/api/v1/session';
$res = httpPostJson($endpoint, $payload);

// ===== Output (verbose) =====
header('Content-Type: text/plain; charset=utf-8');

echo "POST $endpoint\n\n";

// --- Debug block: must match Postman Console ---
echo "=== HASH DEBUG (compare with Postman Console) ======================\n";
echo "string_to_sign:   {$debug['string_to_sign']}\n";
echo "uppercased:       {$debug['upper']}\n";
echo "md5(upper):       {$debug['md5_hex']}\n";
echo "sha1(md5_hex):    {$payload['hash']}\n";
echo "====================================================================\n\n";

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
 * Matches the Postman Pre-request Script exactly and returns debug info.
 * JS reference you showed:
 *   var to_md5 = order_number + order_amount + order_currency + order_description + merchant_pass;
 *   var hash   = CryptoJS.SHA1(CryptoJS.MD5(to_md5.toUpperCase()).toString());
 *   var result = CryptoJS.enc.Hex.stringify(hash);
 */
function buildSessionHash_PostmanExact(
    string $orderNumber,
    string $orderAmount,        // pass '' if amount not used
    string $orderCurrency,
    string $orderDescription,
    string $merchantPass,
    ?array &$debug = null
): string {
    // IMPORTANT: build concatenation exactly as Postman does
    $stringToSign = $orderNumber . $orderAmount . $orderCurrency . $orderDescription . $merchantPass;
    $upper = strtoupper($stringToSign);
    $md5Hex = md5($upper);      // CryptoJS.MD5(...).toString() -> hex
    $sha1Hex = sha1($md5Hex);   // CryptoJS.SHA1(md5Hex).toString() -> hex

    $debug = [
        'string_to_sign' => $stringToSign,
        'upper'          => $upper,
        'md5_hex'        => $md5Hex,
        'sha1_hex'       => $sha1Hex,
    ];
    return $sha1Hex;
}
