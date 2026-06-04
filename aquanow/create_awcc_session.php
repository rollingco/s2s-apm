<?php

$CHECKOUT_HOST = 'https://pay.leogcltd.com';
$MERCHANT_KEY  = '5f306e12-0ff2-11f1-bac9-0a9a38974658';
$MERCHANT_PASS = '976d5c5d5eacbab78288b12bb15178ba';

$SUCCESS_URL   = 'https://example.com/success';
$CANCEL_URL    = 'https://example.com/cancel';

$cryptoList = [
    'USDT','TRX','AMP','USDC','WLD','FDUSD','AVAX','TON','LTC','HBAR','S','KNC','ETC','FIL',
    'ANKR','DYDX','MASK','ZRX','SUSHI','PAXG','POL','MATIC','BNB','BAND','SUI','COMP','ETH',
    'CRV','BNT','BAL','SAND','CHR','LUNC','BAT','DAI','DOGE','ONDO','AAVE','COTI','XRP','UMA',
    'ADA','UNI','CTSI','SKL','ENS','MKR','OMG','CHZ','XLM','1INCH','CELO','DOT','ARB','LINK',
    'SNX','FLOKI','LRC','QNT','REN','SHIB','RNDR','MANA','PEPE','STORJ','BCH','RENDER','EOS',
    'ATOM','TRUMP','BTC','SKY','FTM','AXS','LDO','WLFI','GALA','GRT','XTZ','APE','ALGO','BONK',
    'STX','YFI','SOL','NEAR','WIF'
];

$selectedCrypto = $_POST['crypto_type'] ?? 'USDT';

if (!in_array($selectedCrypto, $cryptoList, true)) {
    $selectedCrypto = 'USDT';
}

header('Content-Type: text/html; charset=utf-8');

echo '<form method="post">';
echo '<label for="crypto_type">Choose cryptocurrency:</label><br>';
echo '<select name="crypto_type" id="crypto_type">';

foreach ($cryptoList as $crypto) {
    $selected = ($crypto === $selectedCrypto) ? 'selected' : '';
    echo '<option value="' . htmlspecialchars($crypto, ENT_QUOTES) . '" ' . $selected . '>' . htmlspecialchars($crypto, ENT_QUOTES) . '</option>';
}

echo '</select><br><br>';
echo '<button type="submit">Create Checkout Session</button>';
echo '</form>';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

// Order
$orderNumber   = 'order-' . time();
$orderAmount   = '0.19';
$orderCurrency = 'USD';
$orderDesc     = 'Important gift';

$payload = [
    "merchant_key" => $MERCHANT_KEY,
    "operation"    => "purchase",
    "methods"      => ["awcc"],
    "parameters"   => [
        "awcc" => [
            "network_type" => "eth",
            "bech32"       => false,
            "crypto_type"  => $selectedCrypto
        ]
    ],
    "order" => [
        "number"      => $orderNumber,
        "amount"      => $orderAmount,
        "currency"    => $orderCurrency,
        "description" => $orderDesc
    ],
    "cancel_url"  => $CANCEL_URL,
    "success_url" => $SUCCESS_URL,
    "customer"    => [
        "name"  => "John Doe",
        "email" => "test@gmail.com"
    ],
    "billing_address" => [
        "country" => "US",
        "state"   => "CA",
        "city"    => "Los Angeles",
        "address" => "Moor Building 35274",
        "zip"     => "123456",
        "phone"   => "347771112233"
    ],
    "accountId" => "CA1001390C"
];

// Signature
$toMd5Upper = strtoupper(
    $payload['order']['number'] .
    $payload['order']['amount'] .
    $payload['order']['currency'] .
    $payload['order']['description'] .
    $MERCHANT_PASS
);

$payload['hash'] = sha1(md5($toMd5Upper));

// Send
$endpoint = rtrim($CHECKOUT_HOST, '/') . '/api/v1/session';
$res = httpPostJson($endpoint, $payload);

// Output
echo "<h3>POST {$endpoint}</h3>";
echo "<h4>Selected crypto</h4>";
echo "<pre>" . htmlspecialchars($selectedCrypto, ENT_QUOTES) . "</pre>";

echo "<h4>Request</h4>";
echo "<pre>" . htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES) . "</pre>";

echo "<h4>Response</h4>";
echo "<pre>HTTP {$res['code']}\n" . htmlspecialchars($res['body'], ENT_QUOTES) . "</pre>";

$data = json_decode($res['body'], true);

if (is_array($data)) {
    foreach (['checkout_url', 'redirect_url', 'payment_url', 'url'] as $k) {
        if (!empty($data[$k])) {
            $url = htmlspecialchars($data[$k], ENT_QUOTES);
            echo "<p><strong>Open in browser:</strong> <a href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\">{$url}</a></p>";
            break;
        }
    }
}

function httpPostJson(string $url, array $data): array {
    $json = json_encode($data, JSON_UNESCAPED_SLASHES);

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);

    curl_close($ch);

    if ($err) {
        $body = "cURL error: $err";
    }

    return [
        'code' => $code,
        'body' => $body
    ];
}