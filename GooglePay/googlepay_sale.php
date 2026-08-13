<?php

// ============================================================
// CONFIG
// ============================================================

$endpoint = 'https://pay.leogcltd.com/api/v1/session';

$merchantKey = '5f306e12-0ff2-11f1-bac9-0a9a38974658';
$password    = '976d5c5d5eacbab78288b12bb15178ba';

// ============================================================
// ORDER
// ============================================================

$orderNumber      = 'GPAY-' . time();
$orderAmount      = '10.00';
$orderCurrency    = 'EUR';
$orderDescription = 'Google Pay test';

// ============================================================
// CUSTOMER
// ============================================================

$customerName  = 'Test User';
$customerEmail = 'test@example.com';

// ============================================================
// RETURN URLS
// ============================================================

$successUrl = 'https://example.com/success.php';
$cancelUrl  = 'https://example.com/cancel.php';
$errorUrl   = 'https://example.com/error.php';

// ============================================================
// HELPERS
// ============================================================

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function calc_hash(
    string $number,
    string $amount,
    string $currency,
    string $description,
    string $password
): string {
    $input = strtoupper(
        $number .
        $amount .
        $currency .
        $description .
        $password
    );

    return sha1(md5($input));
}

// ============================================================
// HASH
//
// IMPORTANT:
// merchant_key is NOT included in the hash.
//
// Formula:
// sha1(md5(strtoupper(
//     order_number +
//     amount +
//     currency +
//     description +
//     password
// )))
// ============================================================

$hashInput = strtoupper(
    $orderNumber .
    $orderAmount .
    $orderCurrency .
    $orderDescription .
    $password
);

$hash = calc_hash(
    $orderNumber,
    $orderAmount,
    $orderCurrency,
    $orderDescription,
    $password
);

// ============================================================
// REQUEST
// ============================================================

$request = [
    'merchant_key' => $merchantKey,
    'operation'    => 'purchase',

    'methods' => [
        'num-googlepay'
    ],

    'order' => [
        'number'      => $orderNumber,
        'amount'      => $orderAmount,
        'currency'    => $orderCurrency,
        'description' => $orderDescription,
    ],

    'customer' => [
        'name'  => $customerName,
        'email' => $customerEmail,
    ],

    'billing_address' => [
        'country' => 'DE',
        'state'   => 'Berlin',
        'city'    => 'Berlin',
        'address' => 'Test Street 1',
        'zip'     => '10115',
        'phone'   => '+491234567890',
    ],

    'success_url' => $successUrl,
    'cancel_url'  => $cancelUrl,
    'error_url'   => $errorUrl,

    'hash' => $hash,
];

// ============================================================
// CURL
// ============================================================

$json = json_encode(
    $request,
    JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
);

$ch = curl_init($endpoint);

curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],

    CURLOPT_POSTFIELDS => $json,

    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

// PHP 8.5: curl_close() is deprecated and has no effect,
// so it is intentionally not used here.

// ============================================================
// PARSE RESPONSE
// ============================================================

$responseData = json_decode((string)$response, true);
$redirectUrl = '';

if (is_array($responseData) && !empty($responseData['redirect_url'])) {
    $redirectUrl = (string)$responseData['redirect_url'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Google Pay Hosted Checkout Test</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        pre {
            background: #f4f4f4;
            padding: 20px;
            overflow-x: auto;
            border-radius: 8px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .button {
            display: inline-block;
            padding: 14px 25px;
            background: #000;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 18px;
        }

        .error {
            color: #b00020;
        }

        .ok {
            color: #087a2f;
        }

        code {
            background: #f4f4f4;
            padding: 2px 5px;
            border-radius: 4px;
        }
    </style>
</head>

<body>

<h1>Google Pay Hosted Checkout Test</h1>

<h2>Hash input</h2>

<pre><?= h($hashInput) ?></pre>

<h2>Calculated hash</h2>

<pre><?= h($hash) ?></pre>

<h2>Request</h2>

<pre><?= h($json) ?></pre>

<h2>HTTP status</h2>

<pre><?= h($httpCode) ?></pre>

<?php if ($curlError): ?>

    <h2>cURL error</h2>
    <pre class="error"><?= h($curlError) ?></pre>

<?php else: ?>

    <h2>Response</h2>
    <pre><?= h($response) ?></pre>

    <?php if ($redirectUrl !== ''): ?>

        <h2>Checkout</h2>

        <p class="ok">
            Session was created successfully.
        </p>

        <a
            class="button"
            href="<?= h($redirectUrl) ?>"
            target="_blank"
            rel="noopener noreferrer"
        >
            Open Hosted Checkout
        </a>

    <?php else: ?>

        <p class="error">
            redirect_url was not returned.
        </p>

    <?php endif; ?>

<?php endif; ?>

</body>
</html>

