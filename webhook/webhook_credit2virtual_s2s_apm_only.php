<?php

declare(strict_types=1);

date_default_timezone_set('Europe/Kyiv');

/*
|--------------------------------------------------------------------------
| AKURATECO CALLBACK CATCHER
|--------------------------------------------------------------------------
| Purpose:
| - receive callback/webhook from payment platform
| - log headers, raw body, parsed data
| - validate only two hash formulas:
|   1) CREDIT2VIRTUAL / withdrawal callback
|   2) SALE / S2S APM
|
| IMPORTANT:
| This file is intended mainly for debugging / investigation.
| Do not expose real merchant password in public repositories or browser output.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| SETTINGS
|--------------------------------------------------------------------------
*/
//$merchantKey  = 'PUT_MERCHANT_KEY_HERE';
//$merchantPass = 'PUT_MERCHANT_PASSWORD_HERE';
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

function receivedHash(array $data): string
{
    return strtoupper(trim((string)($data['hash'] ?? '')));
}

function getFirstAvailable(array $data, array $keys): string
{
    foreach ($keys as $key) {
        if (isset($data[$key]) && (string)$data[$key] !== '') {
            return (string)$data[$key];
        }
    }

    return '';
}

function checkHashResult(
    string $flow,
    string $formula,
    string $source,
    string $expected,
    array $data,
    array $usedFields = []
): array {
    $received = receivedHash($data);

    return [
        'flow'        => $flow,
        'formula'     => $formula,
        'used_fields' => $usedFields,
        'source'      => $source,
        'received'    => $received,
        'expected'    => $expected,
        'valid'       => $received !== '' && hash_equals($expected, $received),
    ];
}

function recursiveKsort(array &$array): void
{
    foreach ($array as &$value) {
        if (is_array($value)) {
            recursiveKsort($value);
        }
    }

    ksort($array);
}

function reverseRecursiveValues(array &$params): void
{
    foreach ($params as &$value) {
        if (is_array($value)) {
            reverseRecursiveValues($value);
        } else {
            $value = strrev((string)$value);
        }
    }
}

function implodeRecursiveValues(array $params): string
{
    $result = '';

    foreach ($params as $value) {
        if (is_array($value)) {
            $result .= implodeRecursiveValues($value);
        } else {
            $result .= (string)$value;
        }
    }

    return $result;
}

function parseRequestBody(string $rawBody): array
{
    $data = $_POST;

    if (!empty($data)) {
        return $data;
    }

    if ($rawBody === '') {
        return [];
    }

    $json = json_decode($rawBody, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        return $json;
    }

    parse_str($rawBody, $parsed);
    if (is_array($parsed)) {
        return $parsed;
    }

    return [];
}

/*
|--------------------------------------------------------------------------
| HASH VALIDATORS
|--------------------------------------------------------------------------
| Only two flows are checked:
| - CREDIT2VIRTUAL / withdrawal callback
| - SALE / S2S APM
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| CREDIT2VIRTUAL / WITHDRAWAL CALLBACK HASH
|--------------------------------------------------------------------------
| Formula:
| md5(strtoupper(strrev(trans_id . order_id . status)) . PASSWORD)
| Result is uppercase.
|--------------------------------------------------------------------------
*/
function validateCredit2VirtualHash(array $data, string $merchantPass): array
{
    $transId = getFirstAvailable($data, ['trans_id', 'id']);
    $orderId = getFirstAvailable($data, ['order_id', 'order_number']);
    $status = getFirstAvailable($data, ['status']);

    $source = $transId . $orderId . $status;
    $expected = strtoupper(md5(strtoupper(strrev($source)) . $merchantPass));

    return checkHashResult(
        'credit2virtual',
        'md5(strtoupper(strrev(trans_id . order_id . status)) . PASSWORD)',
        $source,
        $expected,
        $data,
        ['trans_id/id', 'order_id/order_number', 'status']
    );
}

/*
|--------------------------------------------------------------------------
| SALE / S2S APM HASH
|--------------------------------------------------------------------------
| Formula:
| 1. remove hash from params
| 2. reverse all values recursively
| 3. sort params by key recursively
| 4. implode all values recursively
| 5. md5(strtoupper(converted_params . PASSWORD))
| Result is uppercase.
|--------------------------------------------------------------------------
*/
function validateSaleS2sApmHash(array $data, string $merchantPass): array
{
    $params = $data;
    unset($params['hash']);

    reverseRecursiveValues($params);
    recursiveKsort($params);

    $converted = implodeRecursiveValues($params);
    $source = $converted . $merchantPass;
    $expected = strtoupper(md5(strtoupper($source)));

    return checkHashResult(
        'sale_s2s_apm',
        'md5(strtoupper(convert(reversed_sorted_params) . PASSWORD))',
        $source,
        $expected,
        $data,
        ['all_request_params_except_hash']
    );
}

function validateAllowedHashes(array $data, string $merchantPass): array
{
    $checks = [
        validateCredit2VirtualHash($data, $merchantPass),
        validateSaleS2sApmHash($data, $merchantPass),
    ];

    $matched = [];

    foreach ($checks as $check) {
        if (!empty($check['valid'])) {
            $matched[] = $check;
        }
    }

    return [
        'valid'    => count($matched) > 0,
        'matched'  => $matched,
        'received' => receivedHash($data),
        'all'      => $checks,
    ];
}

function detectBasicMeaning(array $data, array $hashResult = []): string
{
    $action = strtoupper((string)($data['action'] ?? ''));
    $type = strtolower((string)($data['type'] ?? ''));
    $status = strtoupper((string)($data['status'] ?? ''));
    $result = strtoupper((string)($data['result'] ?? ''));

    $matchedFlows = array_map(
        static fn(array $item): string => (string)($item['flow'] ?? ''),
        $hashResult['matched'] ?? []
    );

    $flow = !empty($matchedFlows) ? implode(', ', $matchedFlows) : 'unknown_flow';
    $hashState = !empty($hashResult['valid']) ? 'HASH VALID' : 'HASH INVALID';

    if ($action === 'CREDIT2VIRTUAL') {
        return $hashState . ' | ' . $flow . ' | CREDIT2VIRTUAL | Status: ' . $status . ' | Result: ' . $result;
    }

    if ($type === 'sale') {
        return $hashState . ' | ' . $flow . ' | SALE / S2S APM | Status: ' . $status . ' | Result: ' . $result;
    }

    return $hashState . ' | ' . $flow . ' | Type: ' . $type . ' | Action: ' . $action . ' | Status: ' . $status . ' | Result: ' . $result;
}

/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/
$rawBody = file_get_contents('php://input') ?: '';
$data = parseRequestBody($rawBody);

$headers = getAllHeadersSafe();
$hashResult = validateAllowedHashes($data, $merchantPass);
$meaning = detectBasicMeaning($data, $hashResult);

/*
|--------------------------------------------------------------------------
| LOG
|--------------------------------------------------------------------------
*/
$timestamp = date('Y-m-d_H-i-s');
$file = $logDir . "/cb_{$timestamp}_" . bin2hex(random_bytes(3)) . '.log';

$matchedFlows = array_map(
    static fn(array $item): string => (string)($item['flow'] ?? ''),
    $hashResult['matched'] ?? []
);

$matchedFormulas = array_map(
    static fn(array $item): string => (string)($item['formula'] ?? ''),
    $hashResult['matched'] ?? []
);

$log = [];
$log[] = 'TIME: ' . date('Y-m-d H:i:s');
$log[] = 'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '');
$log[] = 'METHOD: ' . ($_SERVER['REQUEST_METHOD'] ?? '');
$log[] = 'REQUEST_URI: ' . ($_SERVER['REQUEST_URI'] ?? '');

$log[] = '--------------------------------';
$log[] = 'HEADERS:';
$log[] = print_r($headers, true);

$log[] = '--------------------------------';
$log[] = 'RAW:';
$log[] = $rawBody;

$log[] = '--------------------------------';
$log[] = 'DATA:';
$log[] = print_r($data, true);

$log[] = '--------------------------------';
$log[] = 'HASH VALIDATION SUMMARY:';
$log[] = 'RECEIVED HASH: ' . ($hashResult['received'] ?? '');
$log[] = 'HASH VALID: ' . (!empty($hashResult['valid']) ? 'YES' : 'NO');
$log[] = 'MATCHED FLOW(S): ' . (!empty($matchedFlows) ? implode(', ', $matchedFlows) : 'NONE');
$log[] = 'MATCHED FORMULA(S): ' . (!empty($matchedFormulas) ? implode(' | ', $matchedFormulas) : 'NONE');

$log[] = '--------------------------------';
$log[] = 'HASH VALIDATION DETAILS:';
$log[] = print_r($hashResult, true);

$log[] = '--------------------------------';
$log[] = 'MEANING:';
$log[] = $meaning;

$log[] = '================================';

file_put_contents($file, implode("\n", $log));

/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
| Important: respond quickly with HTTP 200 OK.
| If hash is invalid, do not update order status in production logic.
| For this debugger, we only log and return OK.
|--------------------------------------------------------------------------
*/
http_response_code(200);
echo $responseText;
