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
// HASH
//
// Checkout hash:
// sha1(md5(strtoupper(
//     merchant_key +
//     order_number +
//     amount +
//     currency +
//     description +
//     password
// )))
// ============================================================

$hashString =
    $merchantKey .
    $orderNumber .
    $orderAmount .
    $orderCurrency .
    $orderDescription .
    $password;

$hash = sha1(
    md5(
        strtoupper($hashString)
    )
);

// ============================================================
// REQUEST
// ============================================================

$request = [
    'merchant_key' => $merchantKey,

    'operation' => 'purchase',

    // We explicitly tell Checkout to show Google Pay
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

curl_close($ch);

// ============================================================
// OUTPUT
// ============================================================

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Google Pay Checkout Test</title>

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
    </style>
</head>

<body>

<h1>Google Pay Hosted Checkout Test</h1>

<h2>Request</h2>

<pre><?= htmlspecialchars($json) ?></pre>

<h2>HTTP status</h2>

<pre><?= htmlspecialchars((string)$httpCode) ?></pre>

<?php if ($curlError): ?>

    <h2>cURL error</h2>

    <pre class="error"><?= htmlspecialchars($curlError) ?></pre>

<?php else: ?>

    <h2>Response</h2>

    <pre><?= htmlspecialchars($response) ?></pre>

    <?php

    $responseData = json_decode($response, true);

    if (!empty($responseData['redirect_url'])):

        $redirectUrl = $responseData['redirect_url'];

    ?>

        <h2>Checkout</h2>

        <p>
            Session was created successfully.
        </p>

        <a
            class="button"
            href="<?= htmlspecialchars($redirectUrl) ?>"
            target="_blank"
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