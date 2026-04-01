<?php

declare(strict_types=1);

date_default_timezone_set('Europe/Kyiv');

/*
|--------------------------------------------------------------------------
| SETTINGS
|--------------------------------------------------------------------------
*/
$merchantKey  = '5f306e12-0ff2-11f1-bac9-0a9a38974658';
$merchantPass = '976d5c5d5eacbab78288b12bb15178ba';

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
        return getallheaders() ?: [];
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

    return $headers;
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

function validateHash(array $data, string $merchantPass): array
{
    $source = buildHashSource($data, $merchantPass);

    $expectedMd5  = strtoupper(md5($source));
    $expectedSha1 = strtoupper(sha1($source));
    $received     = strtoupper(trim((string)($data['hash'] ?? '')));

    return [
        'source' => $source,
        'received' => $received,
        'md5' => $expectedMd5,
        'sha1' => $expectedSha1,
        'valid' => $received && (
            hash_equals($expectedMd5, $received) ||
            hash_equals($expectedSha1, $received)
        )
    ];
}

function detectMeaning(array $data): string
{
    $type = strtolower($data['type'] ?? '');
    $status = strtolower($data['status'] ?? '');
    $orderStatus = strtolower($data['order_status'] ?? '');

    if ($status === 'success' && $type === 'sale') {
        return 'SALE success (likely payment OK, check order_status)';
    }

    if ($type === 'redirect') {
        return 'Redirect step (not final)';
    }

    if ($type === '3ds') {
        return '3DS step (not final)';
    }

    if ($status === 'waiting') {
        return 'Still processing';
    }

    if ($status === 'fail') {
        return 'FAILED';
    }

    return 'Unknown state';
}

/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/
$rawBody = file_get_contents('php://input') ?: '';
$data = $_POST;

if (empty($data) && $rawBody) {
    parse_str($rawBody, $parsed);
    if (is_array($parsed)) {
        $data = $parsed;
    }
}

$headers = getAllHeadersSafe();
$hashCheck = validateHash($data, $merchantPass);
$meaning = detectMeaning($data);

/*
|--------------------------------------------------------------------------
| LOG
|--------------------------------------------------------------------------
*/
$timestamp = date('Y-m-d_H-i-s');
$file = $logDir . "/cb_{$timestamp}_" . bin2hex(random_bytes(3)) . ".log";

$log = [];
$log[] = "TIME: " . date('Y-m-d H:i:s');
$log[] = "IP: " . ($_SERVER['REMOTE_ADDR'] ?? '');
$log[] = "--------------------------------";
$log[] = "RAW:";
$log[] = $rawBody;
$log[] = "--------------------------------";
$log[] = "DATA:";
$log[] = print_r($data, true);
$log[] = "--------------------------------";
$log[] = "HASH:";
$log[] = print_r($hashCheck, true);
$log[] = "--------------------------------";
$log[] = "MEANING:";
$log[] = $meaning;
$log[] = "================================";

file_put_contents($file, implode("\n", $log));

/*
|--------------------------------------------------------------------------
| RESPONSE (IMPORTANT!)
|--------------------------------------------------------------------------
*/
http_response_code(200);
echo $responseText;