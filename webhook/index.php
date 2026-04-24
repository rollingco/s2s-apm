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
            $name = str_replace(
                ' ',
                '-',
                ucwords(strtolower(str_replace('_', ' ', substr($key, 5))))
            );

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

/*
|--------------------------------------------------------------------------
| CREDIT2VIRTUAL / WITHDRAWAL CALLBACK HASH
|--------------------------------------------------------------------------
| Formula:
| md5(strtoupper(strrev(trans_id . order_id . status)) . PASSWORD)
|--------------------------------------------------------------------------
*/
function buildWithdrawalCallbackHashSource(array $data): string
{
    return
        (string)($data['trans_id'] ?? '') .
        (string)($data['order_id'] ?? '') .
        (string)($data['status'] ?? '');
}

function validateWithdrawalCallbackHash(array $data, string $merchantPass): array
{
    $source = buildWithdrawalCallbackHashSource($data);

    $expected = strtoupper(md5(
        strtoupper(strrev($source)) . $merchantPass
    ));

    $received = strtoupper(trim((string)($data['hash'] ?? '')));

    return [
        'formula'  => 'md5(strtoupper(strrev(trans_id . order_id . status)) . PASSWORD)',
        'source'   => $source,
        'received' => $received,
        'expected' => $expected,
        'valid'    => $received !== '' && hash_equals($expected, $received),
    ];
}

function detectMeaning(array $data): string
{
    $action = strtoupper((string)($data['action'] ?? ''));
    $result = strtoupper((string)($data['result'] ?? ''));
    $status = strtoupper((string)($data['status'] ?? ''));

    if ($action === 'CREDIT2VIRTUAL') {
        if ($status === 'SETTLED' || $result === 'SUCCESS') {
            return 'CREDIT2VIRTUAL / withdrawal callback: SUCCESS';
        }

        if ($status === 'DECLINED' || $result === 'DECLINED') {
            return 'CREDIT2VIRTUAL / withdrawal callback: DECLINED';
        }

        if ($status === 'PREPARE' || $result === 'UNDEFINED') {
            return 'CREDIT2VIRTUAL / withdrawal callback: PREPARE / UNDEFINED';
        }

        return 'CREDIT2VIRTUAL / withdrawal callback: Status = ' . $status . ', Result = ' . $result;
    }

    if ($status !== '') {
        return 'Status: ' . $status;
    }

    if ($result !== '') {
        return 'Result: ' . $result;
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
$hashCheck = validateWithdrawalCallbackHash($data, $merchantPass);
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
$log[] = "METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? '');
$log[] = "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? '');

$log[] = "--------------------------------";
$log[] = "HEADERS:";
$log[] = print_r($headers, true);

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
| RESPONSE
|--------------------------------------------------------------------------
*/
http_response_code(200);
echo $responseText;