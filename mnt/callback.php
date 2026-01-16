<?php
// Simple MoMo callback receiver
// Logs request headers + body to a file

$logFile = __DIR__ . '/momo_callback.log';

$headers = [];
foreach (getallheaders() as $k => $v) {
    $headers[$k] = $v;
}

$body = file_get_contents('php://input');

$entry = [
    'time'    => date('c'),
    'method'  => $_SERVER['REQUEST_METHOD'] ?? '',
    'uri'     => $_SERVER['REQUEST_URI'] ?? '',
    'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
    'headers' => $headers,
    'body'    => $body,
];

file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);

http_response_code(200);
echo "OK";
