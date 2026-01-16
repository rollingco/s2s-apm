<?php
/**
 * MoMo Sandbox - Create API User + Generate API Key (PHP)
 *
 * Requirements:
 *  - PHP 7.4+
 *  - cURL enabled
 *
 * Notes:
 *  - X-Reference-Id MUST be a UUID v4 (API User).
 *  - Use your Collections Primary subscription key for these steps.
 */

$subscriptionKeyCollections = 'PUT_YOUR_COLLECTIONS_PRIMARY_SUBSCRIPTION_KEY_HERE';
$callbackHost = 'your-domain.com'; // WITHOUT https:// and WITHOUT path, just host

// You asked "uuid = leogcltd" — MoMo requires UUID v4.
// We'll use "leogcltd" as an internal label only.
$label = 'leogcltd';

// Generate UUID v4 (API User)
$apiUser = uuid_v4();

echo "Label: {$label}\n";
echo "API User (UUID): {$apiUser}\n\n";

// 1) Create API User
$createUrl = "https://sandbox.momodeveloper.mtn.com/v1_0/apiuser";
$createHeaders = [
    "X-Reference-Id: {$apiUser}",
    "Ocp-Apim-Subscription-Key: {$subscriptionKeyCollections}",
    "Content-Type: application/json",
];

$createBody = json_encode([
    "providerCallbackHost" => $callbackHost
], JSON_UNESCAPED_SLASHES);

$createResp = http_request('POST', $createUrl, $createHeaders, $createBody);

echo "CREATE API USER\n";
echo "HTTP: {$createResp['http_code']}\n";
echo "Response: {$createResp['body']}\n\n";

if ($createResp['http_code'] < 200 || $createResp['http_code'] >= 300) {
    exit("Failed to create API user. Check subscription key / headers / callbackHost.\n");
}

// 2) Generate API Key
$keyUrl = "https://sandbox.momodeveloper.mtn.com/v1_0/apiuser/{$apiUser}/apikey";
$keyHeaders = [
    "Ocp-Apim-Subscription-Key: {$subscriptionKeyCollections}",
    "Content-Type: application/json",
];

$keyResp = http_request('POST', $keyUrl, $keyHeaders, null);

echo "GENERATE API KEY\n";
echo "HTTP: {$keyResp['http_code']}\n";
echo "Response: {$keyResp['body']}\n\n";

$data = json_decode($keyResp['body'], true);
$apiKey = $data['apiKey'] ?? null;

if (!$apiKey) {
    exit("Failed to parse apiKey from response.\n");
}

echo "=== RESULT (use in Akurateco connector) ===\n";
echo "API User: {$apiUser}\n";
echo "API Key : {$apiKey}\n";
echo "X-Target-Environment: sandbox\n";


// -------------------- Helpers --------------------

function http_request(string $method, string $url, array $headers, ?string $body): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 20,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $respBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($respBody === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return [
            'http_code' => 0,
            'body'      => '',
            'error'     => $err,
        ];
    }

    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'body'      => $respBody,
        'error'     => null,
    ];
}

function uuid_v4(): string
{
    $data = random_bytes(16);
    // Set version to 0100
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    $hex = bin2hex($data);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}
