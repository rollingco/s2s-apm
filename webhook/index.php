<?php

declare(strict_types=1);

date_default_timezone_set('Europe/Kyiv');

/*
|--------------------------------------------------------------------------
| SETTINGS
|--------------------------------------------------------------------------
*/
$merchantPass = 'YOUR_MERCHANT_PASSWORD_HERE'; // <-- підстав свій merchant.pass / PASSWORD
$logDir = __DIR__ . '/callback_logs';
$responseText = 'OK';

/*
|--------------------------------------------------------------------------
| PREPARE LOG DIR
|--------------------------------------------------------------------------
*/
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function getAllHeadersSafe(): array
{
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        return is_array($headers) ? $headers : [];
    }

    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
            $headers[$name] = $value;
        }
    }

    if (isset($_SERVER['CONTENT_TYPE'])) {
        $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
    }
    if (isset($_SERVER['CONTENT_LENGTH'])) {
        $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
    }

    return $headers;
}

function safeArrayToString($data): string
{
    return print_r($data, true);
}

function buildHashSource(array $data, string $merchantPass): string
{
    return
        ($data['id'] ?? '') .
        ($data['order_number'] ?? '') .
        ($data['order_amount'] ?? '') .
        ($data['order_currency'] ?? '') .
        ($data['order_description'] ?? '') .
        $merchantPass;
}

function validateCallbackHash(array $data, string $merchantPass): array
{
    $source = buildHashSource($data, $merchantPass);

    $md5 = strtoupper(md5($source));
    $sha1 = strtoupper(sha1($source));
    $received = strtoupper(trim((string)($data['hash'] ?? '')));

    return [
        'source'   => $source,
        'received' => $received,
        'md5'      => $md5,
        'sha1'     => $sha1,
        'md5_ok'   => $received !== '' && hash_equals($md5, $received),
        'sha1_ok'  => $received !== '' && hash_equals($sha1, $received),
        'valid'    => $received !== '' && (hash_equals($md5, $received) || hash_equals($sha1, $received)),
    ];
}

function detectFinalMeaning(array $data): string
{
    $type = strtolower(trim((string)($data['type'] ?? '')));
    $status = strtolower(trim((string)($data['status'] ?? '')));
    $orderStatus = strtolower(trim((string)($data['order_status'] ?? '')));

    if ($status === 'success' && $type === 'sale' && in_array($orderStatus, ['settled', 'prepare', 'pending'], true)) {
        return 'Payment callback looks positive for SALE, but final business meaning depends on your flow and order_status.';
    }

    if ($status === 'success' && $type === 'redirect') {
        return 'Redirect step completed. This is NOT final payment success yet.';
    }

    if ($status === 'success' && $type === '3ds') {
        return '3DS step completed. This is NOT final payment success yet.';
    }

    if ($status === 'waiting') {
        return 'Transaction is still being processed.';
    }

    if ($status === 'fail') {
        return 'Transaction failed.';
    }

    if ($status === 'undefined') {
        return 'Transaction result is uncertain and should be checked later.';
    }

    return 'Unknown callback state. Review type, status, and order_status manually.';
}

function maskSensitive(array $data): array
{
    $copy = $data;

    $fieldsToMask = [
        'hash',
        'card_token',
        'customer_email',
        'payee_email',
        'card',
        'payee_card',
    ];

    foreach ($fieldsToMask as $field) {
        if (!isset($copy[$field])) {
            continue;
        }

        $value = (string)$copy[$field];

        if (in_array($field, ['customer_email', 'payee_email'], true)) {
            $parts = explode('@', $value, 2);
            if (count($parts) === 2) {
                $name = $parts[0];
                $domain = $parts[1];
                $copy[$field] = substr($name, 0, 2) . '***@' . $domain;
            } else {
                $copy[$field] = '***';
            }
        } elseif ($field === 'hash') {
            $copy[$field] = substr($value, 0, 6) . '***' . substr($value, -6);
        } elseif ($field === 'card_token') {
            $copy[$field] = substr($value, 0, 6) . '***' . substr($value, -4);
        } else {
            $copy[$field] = $value;
        }
    }

    return $copy;
}

/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$uri = $_SERVER['REQUEST_URI'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$headers = getAllHeadersSafe();
$rawBody = file_get_contents('php://input') ?: '';

/*
|--------------------------------------------------------------------------
| PARSE DATA
|--------------------------------------------------------------------------
| Callback docs show HTTP POST with key=value pairs.
| Usually PHP already fills $_POST for x-www-form-urlencoded.
*/
$data = $_POST;

if (empty($data) && $rawBody !== '') {
    parse_str($rawBody, $parsed);
    if (is_array($parsed) && !empty($parsed)) {
        $data = $parsed;
    }
}

$maskedData = maskSensitive($data);
$hashCheck = validateCallbackHash($data, $merchantPass);
$meaning = detectFinalMeaning($data);

/*
|--------------------------------------------------------------------------
| FILE NAMES
|--------------------------------------------------------------------------
*/
$timestamp = date('Y-m-d_H-i-s');
$uniq = bin2hex(random_bytes(4));
$baseName = "callback_{$timestamp}_{$uniq}";
$txtLogFile = $logDir . '/' . $baseName . '.log';
$jsonLogFile = $logDir . '/' . $baseName . '.json';

/*
|--------------------------------------------------------------------------
| BUILD LOG
|--------------------------------------------------------------------------
*/
$logLines = [];
$logLines[] = "================ CALLBACK RECEIVED ================";
$logLines[] = "Time: " . date('Y-m-d H:i:s');
$logLines[] = "Method: " . $method;
$logLines[] = "URI: " . $uri;
$logLines[] = "Remote IP: " . $ip;
$logLines[] = "Content-Type: " . $contentType;
$logLines[] = "User-Agent: " . $userAgent;
$logLines[] = "";

$logLines[] = "---------------- HEADERS ----------------";
$logLines[] = safeArrayToString($headers);
$logLines[] = "";

$logLines[] = "---------------- RAW BODY ----------------";
$logLines[] = $rawBody !== '' ? $rawBody : '[empty]';
$logLines[] = "";

$logLines[] = "---------------- PARSED POST DATA ----------------";
$logLines[] = safeArrayToString($maskedData);
$logLines[] = "";

$logLines[] = "---------------- IMPORTANT FIELDS ----------------";
$logLines[] = "id: " . ($data['id'] ?? '');
$logLines[] = "order_number: " . ($data['order_number'] ?? '');
$logLines[] = "order_amount: " . ($data['order_amount'] ?? '');
$logLines[] = "order_currency: " . ($data['order_currency'] ?? '');
$logLines[] = "order_status: " . ($data['order_status'] ?? '');
$logLines[] = "type: " . ($data['type'] ?? '');
$logLines[] = "status: " . ($data['status'] ?? '');
$logLines[] = "reason: " . ($data['reason'] ?? '');
$logLines[] = "";

$logLines[] = "---------------- HASH CHECK ----------------";
$logLines[] = "Hash source: " . $hashCheck['source'];
$logLines[] = "Received hash: " . $hashCheck['received'];
$logLines[] = "Expected MD5 : " . $hashCheck['md5'];
$logLines[] = "Expected SHA1: " . $hashCheck['sha1'];
$logLines[] = "MD5 match: " . ($hashCheck['md5_ok'] ? 'YES' : 'NO');
$logLines[] = "SHA1 match: " . ($hashCheck['sha1_ok'] ? 'YES' : 'NO');
$logLines[] = "VALID HASH: " . ($hashCheck['valid'] ? 'YES' : 'NO');
$logLines[] = "";

$logLines[] = "---------------- BUSINESS INTERPRETATION ----------------";
$logLines[] = $meaning;
$logLines[] = "";

$logLines[] = "=========================================================";

$logText = implode("\n", $logLines);

/*
|--------------------------------------------------------------------------
| SAVE LOGS
|--------------------------------------------------------------------------
*/
file_put_contents($txtLogFile, $logText);

$jsonPayload = [
    'received_at' => date('c'),
    'request' => [
        'method' => $method,
        'uri' => $uri,
        'ip' => $ip,
        'content_type' => $contentType,
        'user_agent' => $userAgent,
        'headers' => $headers,
        'raw_body' => $rawBody,
    ],
    'callback' => $maskedData,
    'hash_check' => $hashCheck,
    'meaning' => $meaning,
];

file_put_contents(
    $jsonLogFile,
    json_encode($jsonPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

/*
|--------------------------------------------------------------------------
| FAST RESPONSE
|--------------------------------------------------------------------------
*/
http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo $responseText;

/*
|--------------------------------------------------------------------------
| OPTIONAL DEBUG OUTPUT IN BROWSER
|--------------------------------------------------------------------------
| Закоментуй цей блок, якщо endpoint має бути тільки для callback.
|--------------------------------------------------------------------------
*/
if (php_sapi_name() !== 'cli') {
    echo "\n\n";
    echo $logText;
}