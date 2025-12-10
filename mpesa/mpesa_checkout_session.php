<?php
/**
 * MPESA CHECKOUT via /api/v1/session
 *
 * - merchant_key  = your test/live merchant key
 * - merchant_pass = your password from back-office
 */

$checkoutHost   = 'https://api.leogcltd.com';
$sessionUrl     = $checkoutHost . '/api/v1/session';

$merchantKey    = 'a9375384-26f2-11f0-877d-022c42254708';   // твій мерчант-кій
$merchantPass   = '554999c284e9f29cf95f090d9a8f3171';       // твій пароль

$orderNumber    = 'mpesa-checkout-' . time();
$orderAmount    = '10.00';
$orderCurrency  = 'KES';
$orderDescr     = 'Test Mpesa payment';

$successUrl     = 'https://yourdomain.com/success.php';
$cancelUrl      = 'https://yourdomain.com/cancel.php';

// ---------------------
// HASH (auth signature)
// sha1(md5(strtoupper(number.amount.currency.description.PASSWORD)))
// ---------------------

$toMd5   = $orderNumber . $orderAmount . $orderCurrency . $orderDescr . $merchantPass;
$hash    = sha1(md5(strtoupper($toMd5)));

// ---------------------
// Build auth request
// ---------------------

$payload = [
    'merchant_key' => $merchantKey,
    'operation'    => 'purchase',
    'methods'      => ['mpesa'],                 // показати Mpesa на checkout
    'order'        => [
        'number'      => $orderNumber,
        'amount'      => $orderAmount,
        'currency'    => $orderCurrency,
        'description' => $orderDescr,
    ],
    'success_url'  => $successUrl,
    'cancel_url'   => $cancelUrl,
    // customer / billing_address за бажанням
    'hash'         => $hash,
];

$ch = curl_init($sessionUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$error    = curl_error($ch);
curl_close($ch);

if ($error) {
    die('cURL error: ' . $error);
}

$data = json_decode($response, true);

// очікується redirect_url в успішній відповіді
if (!empty($data['redirect_url'])) {
    // лог для дебагу
    file_put_contents(__DIR__ . '/logs/mpesa_checkout_session.log',
        date('c') . " | ORDER {$orderNumber}\n" . $response . "\n\n",
        FILE_APPEND
    );

    header('Location: ' . $data['redirect_url']);
    exit;
}

// Якщо щось пішло не так – покажемо відповідь
header('Content-Type: text/plain; charset=utf-8');
echo "No redirect_url in response\n\n";
echo $response;
