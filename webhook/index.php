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

function receivedHash(array $data): string
{
    return strtoupper(trim((string)($data['hash'] ?? '')));
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

function getFirstAvailable(array $data, array $keys): string
{
    foreach ($keys as $key) {
        if (isset($data[$key]) && (string)$data[$key] !== '') {
            return (string)$data[$key];
        }
    }

    return '';
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

/*
|--------------------------------------------------------------------------
| HASH VALIDATORS
|--------------------------------------------------------------------------
| The callback may belong to different flows. In some cases the payload
| itself is not enough to reliably identify the flow, so we calculate all
| supported formulas and accept the one that matches the received hash.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| CHECKOUT / COMMON CALLBACK HASH
|--------------------------------------------------------------------------
| Formula from callback docs:
| hash = SHA1(MD5(UPPERCASE(id + order_number + order_amount +
|                           order_currency + order_description + PASSWORD)))
| Result is uppercase.
|--------------------------------------------------------------------------
*/
function validateCheckoutCallbackHash(array $data, string $merchantPass): array
{
    $id = getFirstAvailable($data, ['id', 'payment_public_id', 'payment_id']);
    $orderNumber = getFirstAvailable($data, ['order_number', 'order_id', 'number']);
    $amount = getFirstAvailable($data, ['order_amount', 'amount']);
    $currency = getFirstAvailable($data, ['order_currency', 'currency']);
    $description = getFirstAvailable($data, ['order_description', 'description']);

    $source = $id . $orderNumber . $amount . $currency . $description . $merchantPass;
    $expected = strtoupper(sha1(md5(strtoupper($source))));

    return checkHashResult(
        'checkout_callback',
        'sha1(md5(strtoupper(id . order_number . order_amount . order_currency . order_description . PASSWORD)))',
        $source,
        $expected,
        $data,
        ['id/payment_public_id/payment_id', 'order_number/order_id/number', 'order_amount/amount', 'order_currency/currency', 'order_description/description']
    );
}

/*
|--------------------------------------------------------------------------
| CREDIT2VIRTUAL / WITHDRAWAL CALLBACK HASH
|--------------------------------------------------------------------------
| Formula:
| md5(strtoupper(strrev(trans_id . order_id . status)) . PASSWORD)
| Result is uppercase.
|--------------------------------------------------------------------------
*/
function validateWithdrawalCallbackHash(array $data, string $merchantPass): array
{
    $transId = getFirstAvailable($data, ['trans_id', 'id']);
    $orderId = getFirstAvailable($data, ['order_id', 'order_number']);
    $status = getFirstAvailable($data, ['status']);

    $source = $transId . $orderId . $status;
    $expected = strtoupper(md5(strtoupper(strrev($source)) . $merchantPass));

    return checkHashResult(
        'credit2virtual_withdrawal_callback',
        'md5(strtoupper(strrev(trans_id . order_id . status)) . PASSWORD)',
        $source,
        $expected,
        $data,
        ['trans_id/id', 'order_id/order_number', 'status']
    );
}

/*
|--------------------------------------------------------------------------
| S2S CARD HASH
|--------------------------------------------------------------------------
| Formula from hash formulas doc:
| md5(strtoupper(strrev(email) . PASSWORD . trans_id .
|                 strrev(first6 + last4)))
| Result is uppercase.
|--------------------------------------------------------------------------
*/
function validateS2sCardHash(array $data, string $merchantPass): array
{
    $email = getFirstAvailable($data, ['email', 'customer_email', 'payer_email']);
    $transId = getFirstAvailable($data, ['trans_id', 'id']);

    $cardRaw = getFirstAvailable($data, ['card_number', 'card_num', 'card']);
    $cardDigits = preg_replace('/\D+/', '', $cardRaw) ?: '';

    $first6 = substr($cardDigits, 0, 6);
    $last4 = substr($cardDigits, -4);

    $source = strrev($email) . $merchantPass . $transId . strrev($first6 . $last4);
    $expected = strtoupper(md5(strtoupper($source)));

    return checkHashResult(
        's2s_card',
        'md5(strtoupper(strrev(email) . PASSWORD . trans_id . strrev(first6 . last4)))',
        $source,
        $expected,
        $data,
        ['email/customer_email/payer_email', 'trans_id/id', 'card_number/card_num/card']
    );
}

/*
|--------------------------------------------------------------------------
| S2S APM HASH
|--------------------------------------------------------------------------
| Formula from hash formulas doc:
| 1. remove hash
| 2. reverse all values recursively
| 3. sort params by key
| 4. implode values
| 5. md5(strtoupper(converted_params . PASSWORD))
| Result is uppercase.
|--------------------------------------------------------------------------
*/
function validateS2sApmHash(array $data, string $merchantPass): array
{
    $params = $data;
    unset($params['hash']);

    reverseRecursiveValues($params);
    recursiveKsort($params);

    $converted = implodeRecursiveValues($params);
    $source = $converted . $merchantPass;
    $expected = strtoupper(md5(strtoupper($source)));

    return checkHashResult(
        's2s_apm',
        'md5(strtoupper(convert(reversed_sorted_params) . PASSWORD))',
        $source,
        $expected,
        $data,
        ['all_request_params_except_hash']
    );
}

function validateAllCallbackHashes(array $data, string $merchantPass): array
{
    $checks = [
        validateCheckoutCallbackHash($data, $merchantPass),
        validateWithdrawalCallbackHash($data, $merchantPass),
        validateS2sCardHash($data, $merchantPass),
        validateS2sApmHash($data, $merchantPass),
    ];

    $matched = null;

    foreach ($checks as $check) {
        if (!empty($check['valid'])) {
            $matched = $check;
            break;
        }
    }

    return [
        'valid'    => $matched !== null,
        'matched'  => $matched,
        'received' => receivedHash($data),
        'all'      => $checks,
    ];
}

function detectMeaning(array $data, array $hashResult = []): string
{
    $action = strtoupper((string)($data['action'] ?? ''));
    $result = strtoupper((string)($data['result'] ?? ''));
    $status = strtoupper((string)($data['status'] ?? ''));
    $type = strtolower((string)($data['type'] ?? ''));
    $orderStatus = strtoupper((string)($data['order_status'] ?? ''));

    $flow = $hashResult['matched']['flow'] ?? 'unknown_flow';
    $hashState = !empty($hashResult['valid']) ? 'HASH VALID' : 'HASH INVALID';

    if ($action === 'CREDIT2VIRTUAL') {
        if ($status === 'SETTLED' || $result === 'SUCCESS') {
            return $hashState . ' | ' . $flow . ' | CREDIT2VIRTUAL / withdrawal callback: SUCCESS';
        }

        if ($status === 'DECLINED' || $result === 'DECLINED') {
            return $hashState . ' | ' . $flow . ' | CREDIT2VIRTUAL / withdrawal callback: DECLINED';
        }

        if ($status === 'PREPARE' || $result === 'UNDEFINED') {
            return $hashState . ' | ' . $flow . ' | CREDIT2VIRTUAL / withdrawal callback: PREPARE / UNDEFINED';
        }

        return $hashState . ' | ' . $flow . ' | CREDIT2VIRTUAL / withdrawal callback: Status = ' . $status . ', Result = ' . $result;
    }

    if ($type !== '') {
        if ($type === 'sale' && strtolower($status) === 'success') {
            return $hashState . ' | ' . $flow . ' | SALE: SUCCESS / likely final successful payment';
        }

        if (($type === 'redirect' || $type === '3ds') && strtolower($status) === 'success') {
            return $hashState . ' | ' . $flow . ' | ' . strtoupper($type) . ': SUCCESS / intermediate step, not final payment result';
        }

        if (strtolower($status) === 'fail') {
            return $hashState . ' | ' . $flow . ' | ' . strtoupper($type) . ': FAILED';
        }

        return $hashState . ' | ' . $flow . ' | Type: ' . $type . ', Status: ' . $status . ', Order status: ' . $orderStatus;
    }

    if ($status !== '') {
        return $hashState . ' | ' . $flow . ' | Status: ' . $status;
    }

    if ($result !== '') {
        return $hashState . ' | ' . $flow . ' | Result: ' . $result;
    }

    return $hashState . ' | ' . $flow . ' | Unknown state';
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
$hashResult = validateAllCallbackHashes($data, $merchantPass);
$meaning = detectMeaning($data, $hashResult);

/*
|--------------------------------------------------------------------------
| LOG
|--------------------------------------------------------------------------
*/
$timestamp = date('Y-m-d_H-i-s');
$file = $logDir . "/cb_{$timestamp}_" . bin2hex(random_bytes(3)) . ".log";

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
$log[] = 'MATCHED FLOW: ' . ($hashResult['matched']['flow'] ?? 'NONE');
$log[] = 'MATCHED FORMULA: ' . ($hashResult['matched']['formula'] ?? 'NONE');

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
| Important: always respond quickly with HTTP 200 OK, even if hash is invalid.
| Invalid hash should be handled internally by logging / ignoring order update.
|--------------------------------------------------------------------------
*/
http_response_code(200);
echo $responseText;
