<?php
/**
 * MoMo Sandbox: Create API User + Generate API Key (PHP) with detailed logging
 * - Logs request/response headers and bodies
 * - Fixes HTTP 411 by sending {} body for /apikey and setting Content-Length
 */

$subscriptionKeyCollections = '3ffd6cae71514869886e03164962c7fd';
$callbackHost = 'zal25.pp.ua';           // host only
$targetEnv   = 'sandbox';
$logFile     = __DIR__ . '/momo_debug.log';

$apiUser = uuid_v4(); // API User must be UUID v4

log_line($logFile, "=== START " . date('c') . " ===");
log_line($logFile, "Target Env: {$targetEnv}");
log_line($logFile, "Callback Host: {$callbackHost}");
log_line($logFile, "API User (UUID): {$apiUser}");
log_line($logFile, "");

// 1) Create API User
$createUrl = "https://sandbox.momodeveloper.mtn.com/v1_0/apiuser";
$createBody = json_encode(["providerCallbackHost" => $callbackHost], JSON_UNESCAPED_SLASHES);

$createHeaders = [
    "X-Reference-Id: {$apiUser}",
    "Ocp-Apim-Subscription-Key: {$subscriptionKeyCollections}",
    "Content-Type: application/json",
    "Content-Length: " . strlen($createBody),
];

$create = http_request('POST', $createUrl, $createHeaders, $createBody, $logFile, "CREATE_API_USER");

if ($create['http_code'] < 200 || $create['http_code'] >= 300) {
    log_line($logFile, "ERROR creating API user.");
    exit("ERROR creating API user. HTTP {$create['http_code']}\n{$create['body']}\n");
}

// 2) Generate API Key
$keyUrl = "https://sandbox.momodeveloper.mtn.com/v1_0/apiuser/{$apiUser}/apikey";

// IMPORTANT: send {} to avoid HTTP 411 from some proxies that require Content-Length
$keyBody = "{}";

$keyHeaders = [
    "Ocp-Apim-Subscription-Key: {$subscriptionKeyCollections}",
    "Content-Type: application/json",
    "Content-Length: " . strlen($keyBody),
];

$key = http_request('POST', $keyUrl, $keyHeaders, $keyBody, $logFile, "GENERATE_API_KEY");

if ($key['http_code'] < 200 || $key['http_code'] >= 300) {
    log_line($logFile, "ERROR generating API key.");
    exit("ERROR generating API key. HTTP {$key['http_code']}\n{$key['body']}\n");
}

$data = json_decode($key['body'], true);
$apiKey = $data['apiKey'] ?? null;

if (!$apiKey) {
    log_line($logFile, "ERROR: apiKey not found in response JSON.");
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

log_line($logFile, "SUCCESS. Saved momo_credentials.json");
log_line($logFile, "=== END " . date('c') . " ===\n");

echo "SUCCESS ✅\n";
echo "API User: {$apiUser}\n";
echo "API Key : {$apiKey}\n";
echo "Saved to: momo_credentials.json\n";
echo "Debug log: momo_debug.log\n";


// -------------------- Helpers --------------------

function http_request(string $method, string $url, array $headers, ?string $body, string $logFile, string $tag): array
{
    log_line($logFile, "----- {$tag} -----");
    log_line($logFile, "REQUEST {$method} {$url}");
    log_line($logFile, "Request headers:\n" . implode("\n", $headers));
    log_line($logFile, "Request body:\n" . ($body ?? '[null]'));

    $ch = curl_init($url);

    $respHeaders = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 20,

        // Capture response headers
        CURLOPT_HEADERFUNCTION => function ($curl, $headerLine) use (&$respHeaders) {
            $len = strlen($headerLine);
            $headerLine = trim($headerLine);
            if ($headerLine !== '') $respHeaders[] = $headerLine;
            return $len;
        },
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $respBody = curl_exec($ch);

    $info = curl_getinfo($ch);
    $httpCode = $info['http_code'] ?? 0;

    if ($respBody === false) {
        $err = curl_error($ch);
        curl_close($ch);

        log_line($logFile, "cURL ERROR: {$err}");
        log_line($logFile, "----- END {$tag} -----\n");

        return [
            'http_code' => 0,
            'body'      => '',
            'error'     => $err,
            'resp_headers' => $respHeaders,
            'curl_info' => $info,
        ];
    }

    curl_close($ch);

    log_line($logFile, "RESPONSE HTTP: {$httpCode}");
    log_line($logFile, "Response headers:\n" . implode("\n", $respHeaders));
    log_line($logFile, "Response body:\n" . $respBody);
    log_line($logFile, "cURL info:\n" . json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    log_line($logFile, "----- END {$tag} -----\n");

    return [
        'http_code' => $httpCode,
        'body'      => $respBody,
        'error'     => null,
        'resp_headers' => $respHeaders,
        'curl_info' => $info,
    ];
}

function log_line(string $file, string $text): void
{
    file_put_contents($file, $text . "\n", FILE_APPEND);
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
