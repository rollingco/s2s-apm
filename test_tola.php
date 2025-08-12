<?php

// ===== 1. MOCKING =====
// Fake endpoint for simulating a transaction without any backend processing
$mockUrl = "https://api-sandbox.tolamobile.io/mock/transaction";
$mockData = [
    "type"           => "charge",
    "msisdn"         => "254000000001", // test number
    "sourcereference"=> "TEST-MOCK-123",
    "amount"         => 10
];
echo "=== Sending MOCK request ===\n";
print_r($mockData);
$response = sendRequest($mockUrl, $mockData);
echo "=== MOCK response ===\n";
print_r($response);
echo "\n-----------------------------\n";

// ===== 2. TEST MODE =====
// Sandbox environment for validating request structure and logic
// No interaction with Orange Money or Africell
$testUrl = "https://api-sandbox.tolamobile.io/v1/transaction";
$testData = [
    "type"           => "charge",
    "msisdn"         => "254000000001",
    "sourcereference"=> "TEST-ENV-456",
    "amount"         => 25
];
echo "=== Sending TEST MODE request ===\n";
print_r($testData);
$response = sendRequest($testUrl, $testData);
echo "=== TEST MODE response ===\n";
print_r($response);
echo "\n-----------------------------\n";

// ===== 3. LIVE MODE =====
// Production environment — real transactions with Orange Money / Africell
$liveUrl = "https://api.tolamobile.io/v1/transaction";
$liveData = [
    "type"           => "charge",
    "msisdn"         => "23276123456", // real phone number
    "sourcereference"=> "LIVE-TX-789",
    "amount"         => 50
];
echo "=== Sending LIVE MODE request ===\n";
print_r($liveData);
$response = sendRequest($liveUrl, $liveData);
echo "=== LIVE MODE response ===\n";
print_r($response);
echo "\n-----------------------------\n";

/**
 * Sends an HTTP POST request to the given URL with JSON payload
 * @param string $url
 * @param array $data
 * @return array
 */
function sendRequest($url, $data) {
    echo "[INFO] Preparing cURL request to: $url\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer YOUR_API_TOKEN' // Replace with your actual token
    ]);
    $jsonPayload = json_encode($data);
    echo "[DEBUG] Payload: $jsonPayload\n";
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "[INFO] HTTP Response Code: $httpCode\n";
    if (curl_errno($ch)) {
        echo "[ERROR] cURL error: " . curl_error($ch) . "\n";
    }
    curl_close($ch);
    echo "[DEBUG] Raw Response: $result\n";
    return json_decode($result, true);
}

