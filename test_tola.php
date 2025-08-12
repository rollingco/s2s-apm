<?php
/**
 * Tola Wallet – MOCKING via Stoplight
 * Uses the Stoplight mock endpoint you shared to simulate API calls.
 * Verbose logging is printed to STDOUT (works in CLI and browser).
 */

// ========= CONFIG =========
$MOCK_ENDPOINT = "https://stoplight.io/mocks/tolamobile/api-docs/39881367/transaction";
// Stoplight example uses Basic auth with a dummy token. Replace if they give you a real one.
$AUTH_SCHEME   = "Basic";
$AUTH_TOKEN    = "123"; // <- change if needed
$HEADERS_EXTRA = [
    "Accept: application/json",
    "Content-Type: application/json",
];

// Example payload from your curl (disbursement). You can switch to "charge" if you need.
$payload = [
    "msisdn"         => "254000000001",
    "type"           => "disbursement",   // or "charge"
    "channel"        => "KENYA.SAFARICOM",
    "currency"       => "KES",
    "amount"         => 100,
    "sourcereference"=> "8FD2KuZNJnBPLKmz"
];

// ========= RUN =========
logLine("=== MOCKING with Stoplight ===", "INFO");
logLine("Endpoint: $MOCK_ENDPOINT", "INFO");
logLine("Auth: $AUTH_SCHEME " . maskToken($AUTH_TOKEN), "INFO");

$response = sendJsonPost($MOCK_ENDPOINT, $payload, $AUTH_SCHEME, $AUTH_TOKEN, $HEADERS_EXTRA);

logLine("HTTP Code: {$response['http_code']}", "INFO");
logLine("Duration: {$response['duration_sec']} sec", "INFO");

if ($response['curl_error']) {
    logLine("cURL Error: {$response['curl_error']}", "ERROR");
}

// Pretty-print raw response
$pretty = tryPrettyJson($response['raw']);
logLine("Raw Response:\n" . $pretty, "DEBUG");

exit(0);

// ========= FUNCTIONS =========

/**
 * Send JSON POST with verbose timing + headers
 */
function sendJsonPost(string $url, array $data, string $authScheme, string $token, array $extraHeaders = []): array {
    $ch = curl_init($url);

    $headers = $extraHeaders;
    if (!empty($authScheme) && !empty($token)) {
        $headers[] = "Authorization: $authScheme $token";
    }

    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    logLine("Payload:\n" . tryPrettyJson($json), "DEBUG");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_HEADER         => false,
        CURLOPT_TIMEOUT        => 60,
    ]);

    $start = microtime(true);
    $raw   = curl_exec($ch);
    $info  = curl_getinfo($ch);
    $errNo = curl_errno($ch);
    $err   = $errNo ? curl_error($ch) : "";
    curl_close($ch);
    $end   = microtime(true);

    return [
        "raw"         => $raw,
        "http_code"   => $info['http_code'] ?? 0,
        "duration_sec"=> number_format($end - $start, 3, '.', ''),
        "curl_error"  => $err,
    ];
}

/**
 * Pretty-print JSON if possible, otherwise return the original string
 */
function tryPrettyJson($maybeJson): string {
    if (is_array($maybeJson)) {
        return json_encode($maybeJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if (is_string($maybeJson)) {
        $decoded = json_decode($maybeJson, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $maybeJson;
    }
    return print_r($maybeJson, true);
}

/**
 * Simple timestamped logger
 */
function logLine(string $msg, string $level = "INFO"): void {
    $ts = date("Y-m-d H:i:s");
    // Colorize in browser/CLI (basic ANSI for CLI; HTML-safe output still readable)
    $prefix = "[$ts] [$level] ";
    echo $prefix . $msg . PHP_EOL;
}

/**
 * Mask token for logs
 */
function maskToken(string $token): string {
    if (strlen($token) <= 4) return str_repeat("*", strlen($token));
    return substr($token, 0, 2) . str_repeat("*", max(0, strlen($token) - 4)) . substr($token, -2);
}
