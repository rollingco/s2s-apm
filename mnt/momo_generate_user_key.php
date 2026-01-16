<?php
/**
 * MoMo Sandbox: Create API User + Generate API Key (PHP)
 * Result is saved into momo_credentials.json
 */

$subscriptionKeyCollections = '3ffd6cae71514869886e03164962c7fd';
$callbackHost = 'zal25.pp.ua'; // host only
$targetEnv = 'sandbox';

$apiUser = uuid_v4(); // This ISccc the API User

// 1) Create API User
$createUrl = "https://sandbox.momodeveloper.mtn.com/v1_0/apiuser";
$createHeaders = [
    "X-Reference-Id: {$apiUser}",
    "Ocp-Apim-Subscription-Key: {$subscriptionKeyCollections}",
    "Content-Type: application/json",
];
$createBody = json_encode(["providerCallbackHost" => $callbackHost], JSON_UNESCAPED_SLASHES);

$create = http_request('POST', $createUrl, $createHeaders, $createBody);
if ($create['http_code'] < 200 || $create['http_code'] >= 300) {
    exit("ERROR creating API user. HTTP {$create['http_code']}\n{$create['body']}\n");
}

// 2) Generate API Key
$keyUrl = "https://sandbox.momodeveloper.mtn.com/v1_0/apiuser/{$apiUser}/apikey";
$keyHeaders = [
    "Ocp-Apim-Subscription-Key: {$subscriptionKeyCollections}",
    "Content-Type: application/json",
];

$key = http_request('POST', $keyUrl, $keyHeaders, null);
if ($key['http_code'] < 200 || $key['http_code'] >= 300) {
    exit("ERROR generating API key. HTTP {$key['http_code']}\n{$key['body']}\n");
}

$data = json_decode($key['body'], true);
$apiKey = $data['apiKey'] ?? null;
if (!$apiKey) {
    exit("ERROR: apiKey not found in response.\n{$key['body']}\n");
}

// 3) Save result
$result = [
    'x_target_environment' => $targetEnv,
    'providerCallbackHost' => $callbackHost,
    'apiUser'              => $apiUser,
    'apiKey'               => $apiKey,
    'created_at'           => date('c'),
];

file_put_contents(__DIR__ . '/momo_credentials.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "SUCCESS ✅\n";
echo "API User: {$apiUser}\n";
echo "API Key : {$apiKey}\n";
echo "Saved to: momo_credentials.json\n";


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
        return ['http_code' => 0, 'body' => '', 'error' => $err];
    }

    curl_close($ch);
    return ['http_code' => $httpCode, 'body' => $respBody, 'error' => null];
}

function uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    $hex = bin2hex($data);
    return sprintf('%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}
