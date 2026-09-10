<?php

/**
 * CredPay via Hosted Checkout
 * Minimal working example
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

/* ===================== CONFIG ===================== */

$CHECKOUT_HOST = 'https://pay.leogcltd.com';
$SESSION_URL   = $CHECKOUT_HOST . '/api/v1/session';

$merchantKey  = '5f306e12-0ff2-11f1-bac9-0a9a38974658';
$merchantPass = '976d5c5d5eacbab78288b12bb15178ba';

/* ===================== INPUTS ===================== */

$orderNumber      = (string)time();
$orderAmount      = '0.10';
$orderCurrency    = 'USD';
$orderDescription = 'Vasyl test ' . time();

$customerName  = 'Vasyl Test';
$customerEmail = 'vasyl.test@example.com';

$successUrl = 'https://www.google.com';

/* ===================== HELPERS ===================== */

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function calc_hash(
    string $number,
    string $amount,
    string $currency,
    string $description,
    string $password
): string {
    $hashInput = strtoupper(
        $number .
        $amount .
        $currency .
        $description .
        $password
    );

    return sha1(md5($hashInput));
}

function build_curl(string $url, array $payload): string
{
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );

    return
        "curl -X POST " . escapeshellarg($url) . " \\\n" .
        "  -H 'Content-Type: application/json' \\\n" .
        "  -H 'Accept: application/json' \\\n" .
        "  -d " . escapeshellarg($json);
}

function http_json(
    string $url,
    array $payload,
    int $timeout = 30
): array {
    $ch = curl_init($url);

    $json = json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
    );

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_TIMEOUT        => $timeout
    ]);

    $responseBody = curl_exec($ch);
    $curlError    = curl_error($ch);
    $httpCode     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        'ok' => (
            $curlError === '' &&
            $httpCode >= 200 &&
            $httpCode < 300
        ),
        'httpCode' => $httpCode,
        'error'    => $curlError,
        'raw'      => $responseBody,
        'json'     => json_decode((string)$responseBody, true)
    ];
}

/* ===================== HASH ===================== */

$hash = calc_hash(
    $orderNumber,
    $orderAmount,
    $orderCurrency,
    $orderDescription,
    $merchantPass
);

/* ===================== REQUEST ===================== */

$request = [
    'merchant_key' => $merchantKey,
    'operation'    => 'purchase',

    'order' => [
        'number'      => $orderNumber,
        'amount'      => $orderAmount,
        'currency'    => $orderCurrency,
        'description' => $orderDescription
    ],

    'customer' => [
        'name'  => $customerName,
        'email' => $customerEmail
    ],

    'success_url' => $successUrl,
    'hash'        => $hash
];

/* ===================== CURL DEBUG ===================== */

$curlCommand = build_curl(
    $SESSION_URL,
    $request
);

/* ===================== SEND REQUEST ===================== */

$response = http_json(
    $SESSION_URL,
    $request
);

/* ===================== FIND REDIRECT URL ===================== */

$redirectUrl = '';

if (is_array($response['json'])) {
    $redirectUrl =
        $response['json']['redirect_url']
        ?? $response['json']['url']
        ?? $response['json']['data']['redirect_url']
        ?? $response['json']['data']['url']
        ?? '';
}

/* ===================== OUTPUT ===================== */

echo '<h2>CredPay Hosted Checkout Test</h2>';

echo '<h3>Request</h3>';
echo '<pre>' .
    h(
        json_encode(
            $request,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        )
    ) .
    '</pre>';

echo '<h3>cURL</h3>';
echo '<pre>' . h($curlCommand) . '</pre>';

echo '<h3>Response</h3>';
echo '<div><b>HTTP:</b> ' . h($response['httpCode']) . '</div>';

if ($response['error']) {
    echo "<div style='color:red'>";
    echo '<b>cURL error:</b> ' . h($response['error']);
    echo '</div>';
}

echo '<pre>' . h($response['raw']) . '</pre>';

/* ===================== NEXT STEP ===================== */

echo '<h3>Next step</h3>';

if ($redirectUrl) {
    echo '<p>';
    echo '<b>Redirect URL:</b><br>';
    echo "<a href='" .
        h($redirectUrl) .
        "' target='_blank'>" .
        h($redirectUrl) .
        '</a>';
    echo '</p>';
} else {
    echo "<p style='color:red'>";
    echo '<b>No redirect URL found.</b>';
    echo '</p>';
}

/* ===================== HASH DEBUG ===================== */

$hashInput = strtoupper(
    $orderNumber .
    $orderAmount .
    $orderCurrency .
    $orderDescription .
    $merchantPass
);

echo '<hr>';
echo '<h3>Hash debug</h3>';
echo '<div><b>Hash input:</b></div>';
echo '<pre>' . h($hashInput) . '</pre>';

echo '<div>';
echo '<b>MD5:</b> ' . h(md5($hashInput));
echo '</div>';

echo '<div>';
echo '<b>SHA1:</b> ' . h($hash);
echo '</div>';
