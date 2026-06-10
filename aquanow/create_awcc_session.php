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

$usdtNetworks = [
    'ETH'  => 'eth',
    'TRON' => 'tron',
];

$selectedCrypto = $_POST['crypto_type'] ?? 'USDT';

if (!in_array($selectedCrypto, $cryptoList, true)) {
    $selectedCrypto = 'USDT';
}

$selectedNetwork = $_POST['network_type'] ?? 'ETH';

if (!array_key_exists($selectedNetwork, $usdtNetworks)) {
    $selectedNetwork = 'ETH';
}

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AquaNow Crypto Checkout Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 30px;
        }

        select, button {
            padding: 8px;
            margin-top: 6px;
            min-width: 220px;
        }

        pre {
            background: #f4f4f4;
            padding: 12px;
            overflow-x: auto;
        }

        .field {
            margin-bottom: 16px;
        }
    </style>
</head>
<body>

<h2>AquaNow Crypto Checkout Test</h2>

<form method="post">
    <div class="field">
        <label for="crypto_type">Cryptocurrency:</label><br>
        <select name="crypto_type" id="crypto_type">
            <?php foreach ($cryptoList as $crypto): ?>
                <option value="<?= htmlspecialchars($crypto, ENT_QUOTES) ?>"
                    <?= $crypto === $selectedCrypto ? 'selected' : '' ?>>
                    <?= htmlspecialchars($crypto, ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field" id="network_block">
        <label for="network_type">USDT network type:</label><br>
        <select name="network_type" id="network_type">
            <?php foreach ($usdtNetworks as $label => $value): ?>
                <option value="<?= htmlspecialchars($label, ENT_QUOTES) ?>"
                    <?= $label === $selectedNetwork ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label, ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit">Create Checkout Session</button>
</form>

<script>
    const cryptoSelect = document.getElementById('crypto_type');
    const networkBlock = document.getElementById('network_block');

    function toggleNetworkBlock() {
        networkBlock.style.display = cryptoSelect.value === 'USDT' ? 'block' : 'none';
    }

    cryptoSelect.addEventListener('change', toggleNetworkBlock);
    toggleNetworkBlock();
</script>

<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$orderNumber   = 'order-' . time();
$orderAmount   = '1.00';
$orderCurrency = 'USD';

if ($selectedCrypto === 'USDT') {
    $orderDesc = sprintf(
        'Test %s (%s)',
        $selectedCrypto,
        $selectedNetwork
    );
} else {
    $orderDesc = sprintf(
        'Test %s',
        $selectedCrypto
    );
}

$awccParameters = [
    "crypto_type" => $selectedCrypto
];

if ($selectedCrypto === 'USDT') {
    $awccParameters["network_type"] = $usdtNetworks[$selectedNetwork];
    $awccParameters["bech32"] = false;
}

$payload = [
    "merchant_key" => $MERCHANT_KEY,
    "operation"    => "purchase",
    "methods"      => ["awcc"],
    "parameters"   => [
        "awcc" => $awccParameters
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

$toMd5Upper = strtoupper(
    $payload['order']['number'] .
    $payload['order']['amount'] .
    $payload['order']['currency'] .
    $payload['order']['description'] .
    $MERCHANT_PASS
);

$payload['hash'] = sha1(md5($toMd5Upper));

$endpoint = rtrim($CHECKOUT_HOST, '/') . '/api/v1/session';
$res = httpPostJson($endpoint, $payload);

echo "<hr>";
echo "<h3>POST {$endpoint}</h3>";

echo "<h4>Selected crypto</h4>";
echo "<pre>" . htmlspecialchars($selectedCrypto, ENT_QUOTES) . "</pre>";

if ($selectedCrypto === 'USDT') {
    echo "<h4>Selected network_type</h4>";
    echo "<pre>" . htmlspecialchars($usdtNetworks[$selectedNetwork], ENT_QUOTES) . "</pre>";
}

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

?>

</body>
</html>