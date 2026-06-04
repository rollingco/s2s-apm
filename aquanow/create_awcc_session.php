<?php

$CHECKOUT_HOST = 'https://pay.leogcltd.com';
$MERCHANT_KEY  = '5f306e12-0ff2-11f1-bac9-0a9a38974658';
$MERCHANT_PASS = '976d5c5d5eacbab78288b12bb15178ba';

$SUCCESS_URL   = 'https://example.com/success';
$CANCEL_URL    = 'https://example.com/cancel';

$cryptoNetworks = [
    'USDT' => ['eth', 'trx', 'bsc', 'polygon'],
    'USDC' => ['eth', 'polygon', 'sol'],
    'TRX' => ['trx'],
    'AMP' => ['eth'],
    'WLD' => ['eth'],
    'FDUSD' => ['eth', 'bsc'],
    'AVAX' => ['avax'],
    'TON' => ['ton'],
    'LTC' => ['ltc'],
    'HBAR' => ['hbar'],
    'S' => ['sonic'],
    'KNC' => ['eth'],
    'ETC' => ['etc'],
    'FIL' => ['fil'],
    'ANKR' => ['eth'],
    'DYDX' => ['eth'],
    'MASK' => ['eth'],
    'ZRX' => ['eth'],
    'SUSHI' => ['eth'],
    'PAXG' => ['eth'],
    'POL' => ['polygon'],
    'MATIC' => ['polygon'],
    'BNB' => ['bsc'],
    'BAND' => ['eth'],
    'SUI' => ['sui'],
    'COMP' => ['eth'],
    'ETH' => ['eth'],
    'CRV' => ['eth'],
    'BNT' => ['eth'],
    'BAL' => ['eth'],
    'SAND' => ['eth'],
    'CHR' => ['eth'],
    'LUNC' => ['lunc'],
    'BAT' => ['eth'],
    'DAI' => ['eth'],
    'DOGE' => ['doge'],
    'ONDO' => ['eth'],
    'AAVE' => ['eth'],
    'COTI' => ['eth'],
    'XRP' => ['xrp'],
    'UMA' => ['eth'],
    'ADA' => ['ada'],
    'UNI' => ['eth'],
    'CTSI' => ['eth'],
    'SKL' => ['eth'],
    'ENS' => ['eth'],
    'MKR' => ['eth'],
    'OMG' => ['eth'],
    'CHZ' => ['eth'],
    'XLM' => ['xlm'],
    '1INCH' => ['eth'],
    'CELO' => ['celo'],
    'DOT' => ['dot'],
    'ARB' => ['arb'],
    'LINK' => ['eth'],
    'SNX' => ['eth'],
    'FLOKI' => ['eth'],
    'LRC' => ['eth'],
    'QNT' => ['eth'],
    'REN' => ['eth'],
    'SHIB' => ['eth'],
    'RNDR' => ['eth'],
    'MANA' => ['eth'],
    'PEPE' => ['eth'],
    'STORJ' => ['eth'],
    'BCH' => ['bch'],
    'RENDER' => ['sol'],
    'EOS' => ['eos'],
    'ATOM' => ['atom'],
    'TRUMP' => ['sol'],
    'BTC' => ['btc'],
    'SKY' => ['eth'],
    'FTM' => ['ftm'],
    'AXS' => ['eth'],
    'LDO' => ['eth'],
    'WLFI' => ['eth'],
    'GALA' => ['eth'],
    'GRT' => ['eth'],
    'XTZ' => ['xtz'],
    'APE' => ['eth'],
    'ALGO' => ['algo'],
    'BONK' => ['sol'],
    'STX' => ['stx'],
    'YFI' => ['eth'],
    'SOL' => ['sol'],
    'NEAR' => ['near'],
    'WIF' => ['sol'],
];

$selectedCrypto = $_POST['crypto_type'] ?? 'USDT';

if (!array_key_exists($selectedCrypto, $cryptoNetworks)) {
    $selectedCrypto = 'USDT';
}

$availableNetworks = $cryptoNetworks[$selectedCrypto];

$selectedNetwork = $_POST['network_type'] ?? $availableNetworks[0];

if (!in_array($selectedNetwork, $availableNetworks, true)) {
    $selectedNetwork = $availableNetworks[0];
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
            <?php foreach ($cryptoNetworks as $crypto => $networks): ?>
                <option value="<?= htmlspecialchars($crypto, ENT_QUOTES) ?>"
                    <?= $crypto === $selectedCrypto ? 'selected' : '' ?>>
                    <?= htmlspecialchars($crypto, ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field" id="network_block">
        <label for="network_type">Network type:</label><br>
        <select name="network_type" id="network_type"></select>
    </div>

    <button type="submit">Create Checkout Session</button>
</form>

<script>
    const cryptoNetworks = <?= json_encode($cryptoNetworks, JSON_UNESCAPED_SLASHES) ?>;
    const selectedCrypto = <?= json_encode($selectedCrypto) ?>;
    const selectedNetwork = <?= json_encode($selectedNetwork) ?>;

    const cryptoSelect = document.getElementById('crypto_type');
    const networkSelect = document.getElementById('network_type');
    const networkBlock = document.getElementById('network_block');

    function updateNetworkOptions() {
        const crypto = cryptoSelect.value;
        const networks = cryptoNetworks[crypto] || [];

        networkSelect.innerHTML = '';

        networks.forEach(function(network) {
            const option = document.createElement('option');
            option.value = network;
            option.textContent = network;

            if (crypto === selectedCrypto && network === selectedNetwork) {
                option.selected = true;
            }

            networkSelect.appendChild(option);
        });

        if (networks.length <= 1) {
            networkBlock.style.display = 'none';
        } else {
            networkBlock.style.display = 'block';
        }
    }

    cryptoSelect.addEventListener('change', updateNetworkOptions);
    updateNetworkOptions();
</script>

<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$orderNumber   = 'order-' . time();
$orderAmount   = '0.01';
$orderCurrency = 'USD';
$orderDesc     = 'Important gift';

$payload = [
    "merchant_key" => $MERCHANT_KEY,
    "operation"    => "purchase",
    "methods"      => ["awcc"],
    "parameters"   => [
        "awcc" => [
            "network_type" => $selectedNetwork,
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

echo "<h4>Selected network_type</h4>";
echo "<pre>" . htmlspecialchars($selectedNetwork, ENT_QUOTES) . "</pre>";

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