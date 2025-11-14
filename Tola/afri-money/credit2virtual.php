<?php
// Endpoint Akurateco
$endpoint = 'https://api.leogcltd.com/post';

// Твої ключі
$clientKey = 'YOUR_CLIENT_KEY_HERE';
$secretKey = 'YOUR_SECRET_KEY_HERE'; // для підпису (див. Appendix A)

$orderId = 'afrimoney-payout-' . time();

$data = [
    'action'           => 'CREDIT2VIRTUAL',
    'client_key'       => $clientKey,
    // !!! заміни значення brand на ТОЧНУ назву для AfriMoney, яку дала Akurateco
    'brand'            => 'afrimoney-dbm',   // <-- приклад / плейсхолдер
    'order_id'         => $orderId,
    'order_amount'     => '10.00',           // сума виплати
    'order_currency'   => 'SLE',             // валюта, як у вас в MID
    'order_description'=> 'Test AfriMoney payout',
];

// Підпис (приклад — ЗАМІНИ формулу на ту, що в Appendix A)
$signatureString = $data['action']
                 . $data['client_key']
                 . $data['order_id']
                 . $data['order_amount']
                 . $data['order_currency']
                 . $secretKey;

$data['hash'] = md5($signatureString);

// ---- CURL запит ----
$ch = curl_init($endpoint);

curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/x-www-form-urlencoded',
    ],
    CURLOPT_POSTFIELDS     => http_build_query($data),
]);

$responseRaw = curl_exec($ch);

if ($responseRaw === false) {
    die('cURL error: ' . curl_error($ch));
}

curl_close($ch);

// ---- Обробка відповіді ----
$response = json_decode($responseRaw, true);

echo "RAW RESPONSE:\n" . $responseRaw . "\n\n";

if (!is_array($response)) {
    die("Cannot decode JSON response\n");
}

if (!empty($response['status']) && $response['status'] === 'REDIRECT') {
    $redirectUrl = $response['redirect_url'] ?? null;
    echo "Status: {$response['status']}\n";
    echo "Redirect URL: {$redirectUrl}\n";
    echo "Open this URL in browser – the recipient will enter MSISDN there.\n";
} else {
    // інші варіанти відповіді (SUCCESS / DECLINED і т.д.)
    print_r($response);
}
