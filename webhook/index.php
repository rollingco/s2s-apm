<?php

date_default_timezone_set('Europe/Kyiv');

// ===== SETTINGS =====
$logDir = __DIR__ . '/webhook_logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

$timestamp = date('Y-m-d_H-i-s');
$uniq = bin2hex(random_bytes(4));
$logFile = $logDir . "/webhook_{$timestamp}_{$uniq}.log";

// ===== HELPER =====
function getAllHeadersSafe(): array
{
    if (function_exists('getallheaders')) {
        return getallheaders();
    }

    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (strpos($name, 'HTTP_') === 0) {
            $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$headerName] = $value;
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

function safeJsonDecode(string $raw)
{
    $decoded = json_decode($raw, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
}

function formatArray($data): string
{
    return print_r($data, true);
}

// ===== INPUT DATA =====
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$uri = $_SERVER['REQUEST_URI'] ?? '';
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$headers = getAllHeadersSafe();
$rawBody = file_get_contents('php://input');
$jsonBody = safeJsonDecode($rawBody);

// ===== PREPARE REPORT =====
$report = [];
$report[] = "================ WEBHOOK RECEIVED ================";
$report[] = "Time: " . date('Y-m-d H:i:s');
$report[] = "Method: " . $method;
$report[] = "URI: " . $uri;
$report[] = "Query string: " . $queryString;
$report[] = "Remote IP: " . $remoteAddr;
$report[] = "User-Agent: " . $userAgent;
$report[] = "Content-Type: " . $contentType;
$report[] = "";

$report[] = "---------------- HEADERS ----------------";
$report[] = formatArray($headers);
$report[] = "";

$report[] = "---------------- GET ----------------";
$report[] = formatArray($_GET);
$report[] = "";

$report[] = "---------------- POST ----------------";
$report[] = formatArray($_POST);
$report[] = "";

$report[] = "---------------- FILES ----------------";
$report[] = formatArray($_FILES);
$report[] = "";

$report[] = "---------------- RAW BODY ----------------";
$report[] = $rawBody !== '' ? $rawBody : '[empty]';
$report[] = "";

$report[] = "---------------- JSON BODY ----------------";
$report[] = $jsonBody !== null ? formatArray($jsonBody) : '[not valid JSON or empty]';
$report[] = "";

$report[] = "---------------- SERVER ----------------";
$report[] = formatArray($_SERVER);
$report[] = "";

$report[] = "==================================================";
$reportText = implode("\n", $report);

// ===== SAVE LOG =====
file_put_contents($logFile, $reportText);

// ===== RESPONSE =====
http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');

echo "Webhook received successfully\n";
echo "Saved to: " . basename($logFile) . "\n\n";
echo $reportText;