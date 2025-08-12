<?php
// HTML header
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>
<style>
body { font-family: monospace; background: #111; color: #eee; padding: 20px; }
.block { border: 1px solid #444; margin-bottom: 20px; padding: 10px; border-radius: 8px; }
.mock { background: #222; border-left: 5px solid #ff00ff; }
.test { background: #222; border-left: 5px solid #ffff00; }
.live { background: #222; border-left: 5px solid #00ff00; }
h2 { margin-top: 0; }
.info { color: #0ff; }
.debug { color: #ff0; }
.error { color: #f66; }
</style></head><body>";

logBlock("MOCKING", "mock", "https://api-sandbox.tolamobile.io/mock/transaction", [
    "type" => "charge",
    "msisdn" => "254000000001",
    "sourcereference" => "TEST-MOCK-123",
    "amount" => 10
]);

logBlock("TEST MODE", "test", "https://api-sandbox.tolamobile.io/v1/transaction", [
    "type" => "charge",
    "msisdn" => "254000000001",
    "sourcereference" => "TEST-ENV-456",
    "amount" => 25
]);

logBlock("LIVE MODE", "live", "https://api.tolamobile.io/v1/transaction", [
    "type" => "charge",
    "msisdn" => "23276123456",
    "sourcereference" => "LIVE-TX-789",
    "amount" => 50
]);

echo "</body></html>";

function logBlock($title, $class, $url, $data) {
    echo "<div class='block $class'>";
    echo "<h2>$title</h2>";
    echo "<div class='info'>[INFO] Sending request to: $url</div>";
    echo "<div class='debug'>[DEBUG] Payload: " . json_encode($data) . "</div>";

    $response = sendRequest($url, $data);

    if ($response['error']) {
        echo "<div class='error'>[ERROR] " . htmlspecialchars($response['error']) . "</div>";
    }
    echo "<div class='info'>[INFO] HTTP Code: " . $response['code'] . "</div>";
    echo "<div class='debug'>[DEBUG] Raw Response: " . htmlspecialchars($response['raw']) . "</div>";
    echo "</div>";
}

function sendRequest($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer YOUR_API_TOKEN'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $result = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_errno($ch) ? curl_error($ch) : '';
    curl_close($ch);
    return [
        'code' => $code,
        'raw' => $result,
        'error' => $error
    ];
}
