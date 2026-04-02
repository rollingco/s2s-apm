<?php
/**
 * index_demo.php
 *
 * Demo portal for:
 * - Card Hosted Checkout
 * - AquaNow / AWCC Hosted Checkout
 * - AfriMoney APM SALE
 * - Orange Money APM SALE
 * - MTN MoMo APM SALE
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

/* =========================================================
 * CONFIG
 * ========================================================= */
const CHECKOUT_HOST   = 'https://pay.leogcltd.com';
const SESSION_URL     = CHECKOUT_HOST . '/api/v1/session';
const APM_URL         = 'https://api.leogcltd.com/post-va';

const MERCHANT_KEY    = '5f306e12-0ff2-11f1-bac9-0a9a38974658';
const MERCHANT_PASS   = '976d5c5d5eacbab78288b12bb15178baS';

const DEFAULT_SUCCESS_URL = 'https://sandbox.pp.ua/success';
const DEFAULT_CANCEL_URL  = 'https://sandbox.pp.ua/cancel';
const DEFAULT_RETURN_URL  = 'https://sandbox.pp.ua/return';

/* =========================================================
 * HELPERS
 * ========================================================= */
function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function posted(string $key, string $default = ''): string {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

function pretty_json($data): string {
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function http_json(string $url, $payload, bool $asForm = false, int $timeout = 60): array {
    $ch = curl_init($url);

    if ($asForm) {
        $sentBody = $payload;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => $timeout,
        ]);
    } else {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $sentBody = $payload;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_TIMEOUT        => $timeout,
        ]);
    }

    $start    = microtime(true);
    $body     = curl_exec($ch);
    $error    = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode((string)$body, true);

    return [
        'ok'       => ($error === '' && $httpCode >= 200 && $httpCode < 300),
        'httpCode' => $httpCode,
        'error'    => $error,
        'raw'      => (string)$body,
        'json'     => is_array($decoded) ? $decoded : null,
        'sent'     => $sentBody,
        'url'      => $url,
        'duration' => number_format(microtime(true) - $start, 3, '.', ''),
    ];
}

function hosted_hash_source(string $number, string $amount, string $currency, string $description, string $password): string {
    return strtoupper($number . $amount . $currency . $description . $password);
}

function hosted_hash(string $number, string $amount, string $currency, string $description, string $password): array {
    $source = hosted_hash_source($number, $amount, $currency, $description, $password);
    $md5hex = md5($source);
    $sha1   = sha1($md5hex);

    return [
        'source' => $source,
        'md5'    => $md5hex,
        'hash'   => $sha1,
    ];
}

function apm_hash_source(string $identifier, string $orderId, string $amount, string $currency, string $secret): string {
    return $identifier . $orderId . $amount . $currency . $secret;
}

function apm_hash(string $identifier, string $orderId, string $amount, string $currency, string $secret): array {
    $source   = apm_hash_source($identifier, $orderId, $amount, $currency, $secret);
    $reversed = strrev($source);
    $upper    = strtoupper($reversed);
    $hash     = md5($upper);

    return [
        'source'   => $source,
        'reversed' => $reversed,
        'upper'    => $upper,
        'hash'     => $hash,
    ];
}

function find_redirect_url(?array $json): string {
    if (!$json) return '';

    $candidates = [
        $json['redirect_url'] ?? null,
        $json['url'] ?? null,
        $json['payment_url'] ?? null,
        $json['checkout_url'] ?? null,
        $json['data']['redirect_url'] ?? null,
        $json['data']['url'] ?? null,
        $json['data']['payment_url'] ?? null,
        $json['data']['checkout_url'] ?? null,
    ];

    foreach ($candidates as $u) {
        if (!empty($u) && is_string($u)) {
            return $u;
        }
    }

    return '';
}

/* =========================================================
 * DEFAULT FORM VALUES
 * ========================================================= */
$flow = posted('flow', 'card_hosted');

$orderId          = posted('order_id', 'order-' . time());
$orderAmount      = posted('order_amount', '0.19');
$orderCurrency    = posted('order_currency', 'USD');
$orderDescription = posted('order_description', 'Important gift');

$successUrl       = posted('success_url', DEFAULT_SUCCESS_URL);
$cancelUrl        = posted('cancel_url', DEFAULT_CANCEL_URL);
$returnUrl        = posted('return_url', DEFAULT_RETURN_URL);

$customerName     = posted('customer_name', 'John Doe');
$customerEmail    = posted('customer_email', 'test@gmail.com');
$billingCountry   = posted('billing_country', 'US');
$billingState     = posted('billing_state', 'CA');
$billingCity      = posted('billing_city', 'Los Angeles');
$billingAddress   = posted('billing_address', 'Moor Building 35274');
$billingZip       = posted('billing_zip', '123456');
$billingPhone     = posted('billing_phone', '347771112233');

$awccNetworkType  = posted('awcc_network_type', 'eth');
$awccBech32       = posted('awcc_bech32', '0');
$awccCryptoType   = posted('awcc_crypto_type', 'USDT');
$awccAccountId    = posted('awcc_account_id', 'CA1001390C');

/* APM minimal fields */
$apmClientKey     = posted('apm_client_key', MERCHANT_KEY);
$apmSecret        = MERCHANT_PASS; // hidden from UI
$apmBrand         = posted('apm_brand', 'afri-money');
$apmIdentifier    = posted('apm_identifier', '111');
$apmPhone         = posted('apm_phone', '23233310905');
$apmReturnUrl     = posted('apm_return_url', 'https://google.com');
$apmCurrency      = posted('apm_currency', 'SLE');
$apmAmount        = posted('apm_amount', '0.99');

/* =========================================================
 * EXECUTION
 * ========================================================= */
$responseData = null;
$debug = [];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($flow === 'card_hosted') {
            $hashData = hosted_hash($orderId, $orderAmount, $orderCurrency, $orderDescription, MERCHANT_PASS);

            $payload = [
                'merchant_key'   => MERCHANT_KEY,
                'operation'      => 'purchase',
                'methods'        => ['card'],
                'session_expiry' => 60,
                'order' => [
                    'number'      => $orderId,
                    'amount'      => $orderAmount,
                    'currency'    => $orderCurrency,
                    'description' => $orderDescription,
                ],
                'success_url' => $successUrl,
                'cancel_url'  => $cancelUrl,
                'hash'        => $hashData['hash'],
            ];

            $responseData = http_json(SESSION_URL, $payload, false);
            $debug = [
                'type' => 'Hosted Checkout / Card',
                'hash' => $hashData,
            ];
        }

        elseif ($flow === 'awcc_hosted') {
            $hashData = hosted_hash($orderId, $orderAmount, $orderCurrency, $orderDescription, MERCHANT_PASS);

            $payload = [
                'merchant_key' => MERCHANT_KEY,
                'operation'    => 'purchase',
                'methods'      => ['awcc'],
                'parameters'   => [
                    'awcc' => [
                        'network_type' => $awccNetworkType,
                        'bech32'       => ($awccBech32 === '1'),
                        'crypto_type'  => $awccCryptoType,
                    ]
                ],
                'order' => [
                    'number'      => $orderId,
                    'amount'      => $orderAmount,
                    'currency'    => $orderCurrency,
                    'description' => $orderDescription,
                ],
                'cancel_url'  => $cancelUrl,
                'success_url' => $successUrl,
                'customer'    => [
                    'name'  => $customerName,
                    'email' => $customerEmail,
                ],
                'billing_address' => [
                    'country' => $billingCountry,
                    'state'   => $billingState,
                    'city'    => $billingCity,
                    'address' => $billingAddress,
                    'zip'     => $billingZip,
                    'phone'   => $billingPhone,
                ],
                'accountId' => $awccAccountId,
                'hash'      => $hashData['hash'],
            ];

            $responseData = http_json(SESSION_URL, $payload, false);
            $debug = [
                'type' => 'Hosted Checkout / AquaNow (AWCC)',
                'hash' => $hashData,
            ];
        }

        elseif (in_array($flow, ['afri_apm', 'orange_apm', 'mtn_apm'], true)) {
            $brandByFlow = [
                'afri_apm'   => 'afri-money',
                'orange_apm' => 'orange-money',
                'mtn_apm'    => 'mtn-momo',
            ];

            $brand = $brandByFlow[$flow];

            $payerPhone = preg_replace('/\s+/', '', $apmPhone);
            $payerPhone = ltrim($payerPhone, '+');

            $rawAmt = preg_replace('/[^0-9.]/', '', $apmAmount);
            $orderAmt = number_format((float)$rawAmt, 2, '.', '');

            if ($payerPhone === '') {
                throw new Exception('Phone is required.');
            }
            if (!is_numeric($orderAmt) || (float)$orderAmt <= 0) {
                throw new Exception('Amount must be a positive number.');
            }

            $orderIdApm   = 'ORDER_' . time();
            $orderDescApm = 'APM payment';
            $payerIp      = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            $hashData = apm_hash($apmIdentifier, $orderIdApm, $orderAmt, $apmCurrency, $apmSecret);

            $payload = [
                'action'            => 'SALE',
                'client_key'        => $apmClientKey,
                'brand'             => $brand,
                'order_id'          => $orderIdApm,
                'order_amount'      => $orderAmt,
                'order_currency'    => $apmCurrency,
                'order_description' => $orderDescApm,
                'identifier'        => $apmIdentifier,
                'payer_ip'          => $payerIp,
                'return_url'        => $apmReturnUrl,
                'payer_phone'       => $payerPhone,
                'hash'              => $hashData['hash'],
            ];

            $responseData = http_json(APM_URL, $payload, true);
            $debug = [
                'type' => 'APM SALE / ' . $brand,
                'hash' => $hashData,
            ];
        }

        else {
            $errorMessage = 'Unknown flow selected.';
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$redirectUrl = $responseData ? find_redirect_url($responseData['json']) : '';

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>LeoGC Demo Portal</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        background: #f4f7fb;
        color: #1f2937;
    }
    .wrap {
        max-width: 1200px;
        margin: 24px auto;
        padding: 0 16px 40px;
    }
    .card {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 18px rgba(0,0,0,0.08);
        margin-bottom: 18px;
    }
    h1, h2, h3 {
        margin-top: 0;
    }
    .grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }
    .grid-3 {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }
    .field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    label {
        font-size: 14px;
        font-weight: 600;
    }
    input, select, textarea {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 14px;
        background: #fff;
    }
    .btn {
        border: 0;
        background: #2563eb;
        color: #fff;
        padding: 12px 18px;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
    }
    .btn:hover {
        background: #1d4ed8;
    }
    .muted {
        color: #64748b;
        font-size: 14px;
    }
    .ok {
        color: #166534;
        font-weight: 700;
    }
    .err {
        color: #b91c1c;
        font-weight: 700;
    }
    pre {
        background: #0f172a;
        color: #e2e8f0;
        padding: 14px;
        border-radius: 10px;
        overflow: auto;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .section-title {
        margin: 8px 0 14px;
        padding-bottom: 8px;
        border-bottom: 1px solid #e5e7eb;
    }
    .note {
        padding: 10px 12px;
        background: #eff6ff;
        border-left: 4px solid #2563eb;
        border-radius: 8px;
        font-size: 14px;
    }
    .row-actions {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }
    a.link-btn {
        display: inline-block;
        padding: 10px 14px;
        background: #059669;
        color: #fff;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 700;
    }
    @media (max-width: 900px) {
        .grid, .grid-3 {
            grid-template-columns: 1fr;
        }
    }
</style>
</head>
<body>
<div class="wrap">

    <div class="card">
        <h1>LeoGC Demo Portal</h1>
        <div class="note">
            One page for demo flows: Card Hosted Checkout, AquaNow/AWCC, AfriMoney, Orange Money, MTN MoMo.
        </div>
    </div>

    <form method="post" class="card">
        <h2 class="section-title">Flow Selection</h2>

        <div class="grid">
            <div class="field">
                <label for="flow">Flow</label>
                <select name="flow" id="flow">
                    <option value="card_hosted" <?= $flow === 'card_hosted' ? 'selected' : '' ?>>Card Hosted Checkout</option>
                    <option value="awcc_hosted" <?= $flow === 'awcc_hosted' ? 'selected' : '' ?>>AquaNow / AWCC Hosted Checkout</option>
                    <option value="afri_apm" <?= $flow === 'afri_apm' ? 'selected' : '' ?>>AfriMoney APM SALE</option>
                    <option value="orange_apm" <?= $flow === 'orange_apm' ? 'selected' : '' ?>>Orange Money APM SALE</option>
                    <option value="mtn_apm" <?= $flow === 'mtn_apm' ? 'selected' : '' ?>>MTN MoMo APM SALE</option>
                </select>
            </div>
        </div>

        <div id="block-hosted-common">
            <h2 class="section-title">Common Order Fields</h2>
            <div class="grid">
                <div class="field">
                    <label for="order_id">Order ID</label>
                    <input type="text" name="order_id" id="order_id" value="<?= h($orderId) ?>">
                </div>
                <div class="field">
                    <label for="order_amount">Amount</label>
                    <input type="text" name="order_amount" id="order_amount" value="<?= h($orderAmount) ?>">
                </div>
                <div class="field">
                    <label for="order_currency">Currency</label>
                    <input type="text" name="order_currency" id="order_currency" value="<?= h($orderCurrency) ?>">
                </div>
                <div class="field">
                    <label for="order_description">Description</label>
                    <input type="text" name="order_description" id="order_description" value="<?= h($orderDescription) ?>">
                </div>
            </div>

            <h2 class="section-title">Hosted URLs</h2>
            <div class="grid">
                <div class="field">
                    <label for="success_url">Success URL</label>
                    <input type="text" name="success_url" id="success_url" value="<?= h($successUrl) ?>">
                </div>
                <div class="field">
                    <label for="cancel_url">Cancel URL</label>
                    <input type="text" name="cancel_url" id="cancel_url" value="<?= h($cancelUrl) ?>">
                </div>
            </div>
        </div>

        <div id="block-awcc">
            <h2 class="section-title">AquaNow / AWCC Fields</h2>
            <div class="grid-3">
                <div class="field">
                    <label for="awcc_network_type">Network Type</label>
                    <input type="text" name="awcc_network_type" id="awcc_network_type" value="<?= h($awccNetworkType) ?>">
                </div>
                <div class="field">
                    <label for="awcc_crypto_type">Crypto Type</label>
                    <input type="text" name="awcc_crypto_type" id="awcc_crypto_type" value="<?= h($awccCryptoType) ?>">
                </div>
                <div class="field">
                    <label for="awcc_account_id">Account ID</label>
                    <input type="text" name="awcc_account_id" id="awcc_account_id" value="<?= h($awccAccountId) ?>">
                </div>
                <div class="field">
                    <label for="awcc_bech32">Bech32</label>
                    <select name="awcc_bech32" id="awcc_bech32">
                        <option value="0" <?= $awccBech32 === '0' ? 'selected' : '' ?>>false</option>
                        <option value="1" <?= $awccBech32 === '1' ? 'selected' : '' ?>>true</option>
                    </select>
                </div>
                <div class="field">
                    <label for="customer_name">Customer Name</label>
                    <input type="text" name="customer_name" id="customer_name" value="<?= h($customerName) ?>">
                </div>
                <div class="field">
                    <label for="customer_email">Customer Email</label>
                    <input type="text" name="customer_email" id="customer_email" value="<?= h($customerEmail) ?>">
                </div>
            </div>

            <div class="grid-3" style="margin-top:14px;">
                <div class="field">
                    <label for="billing_country">Billing Country</label>
                    <input type="text" name="billing_country" id="billing_country" value="<?= h($billingCountry) ?>">
                </div>
                <div class="field">
                    <label for="billing_state">Billing State</label>
                    <input type="text" name="billing_state" id="billing_state" value="<?= h($billingState) ?>">
                </div>
                <div class="field">
                    <label for="billing_city">Billing City</label>
                    <input type="text" name="billing_city" id="billing_city" value="<?= h($billingCity) ?>">
                </div>
                <div class="field">
                    <label for="billing_address">Billing Address</label>
                    <input type="text" name="billing_address" id="billing_address" value="<?= h($billingAddress) ?>">
                </div>
                <div class="field">
                    <label for="billing_zip">Billing ZIP</label>
                    <input type="text" name="billing_zip" id="billing_zip" value="<?= h($billingZip) ?>">
                </div>
                <div class="field">
                    <label for="billing_phone">Billing Phone</label>
                    <input type="text" name="billing_phone" id="billing_phone" value="<?= h($billingPhone) ?>">
                </div>
            </div>
        </div>

        <div id="block-apm">
            <h2 class="section-title">APM Fields</h2>
            <div class="grid-3">
                <div class="field">
                    <label for="apm_client_key">Client Key</label>
                    <input type="text" name="apm_client_key" id="apm_client_key" value="<?= h($apmClientKey) ?>">
                </div>
                <div class="field">
                    <label for="apm_brand">Brand</label>
                    <input type="text" name="apm_brand" id="apm_brand" value="<?= h($apmBrand) ?>" readonly>
                </div>
                <div class="field">
                    <label for="apm_identifier">Identifier</label>
                    <input type="text" name="apm_identifier" id="apm_identifier" value="<?= h($apmIdentifier) ?>">
                </div>

                <div class="field">
                    <label for="apm_phone">Payer Phone</label>
                    <input type="text" name="apm_phone" id="apm_phone" value="<?= h($apmPhone) ?>">
                </div>
                <div class="field">
                    <label for="apm_return_url">Return URL</label>
                    <input type="text" name="apm_return_url" id="apm_return_url" value="<?= h($apmReturnUrl) ?>">
                </div>
                <div class="field">
                    <label for="apm_currency">Currency</label>
                    <input type="text" name="apm_currency" id="apm_currency" value="<?= h($apmCurrency) ?>">
                </div>

                <div class="field">
                    <label for="apm_amount">Amount</label>
                    <input type="text" name="apm_amount" id="apm_amount" value="<?= h($apmAmount) ?>">
                </div>
            </div>
        </div>

        <div style="margin-top:18px;" class="row-actions">
            <button type="submit" class="btn">Run Demo Flow</button>
            <span class="muted">Request, response, hash debug and next-step link will be shown below.</span>
        </div>
    </form>

    <?php if ($errorMessage): ?>
        <div class="card">
            <div class="err">Error: <?= h($errorMessage) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($responseData): ?>
        <div class="card">
            <h2 class="section-title">Execution Result</h2>
            <p>
                <strong>Endpoint:</strong> <?= h($responseData['url']) ?><br>
                <strong>HTTP code:</strong> <?= h((string)$responseData['httpCode']) ?><br>
                <strong>Duration:</strong> <?= h($responseData['duration']) ?> s<br>
                <strong>Status:</strong>
                <?php if ($responseData['ok']): ?>
                    <span class="ok">OK</span>
                <?php else: ?>
                    <span class="err">FAILED</span>
                <?php endif; ?>
            </p>

            <?php if ($responseData['error']): ?>
                <p class="err">cURL error: <?= h($responseData['error']) ?></p>
            <?php endif; ?>

            <?php if ($redirectUrl): ?>
                <div class="row-actions" style="margin-top:12px;">
                    <a class="link-btn" href="<?= h($redirectUrl) ?>" target="_blank" rel="noopener noreferrer">
                        Open Redirect URL
                    </a>
                    <span class="muted"><?= h($redirectUrl) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 class="section-title">Request</h2>
            <pre><?= h(pretty_json($responseData['sent'])) ?></pre>
        </div>

        <div class="card">
            <h2 class="section-title">Raw Response</h2>
            <pre><?= h($responseData['raw']) ?></pre>
        </div>

        <div class="card">
            <h2 class="section-title">Parsed Response JSON</h2>
            <pre><?= h(pretty_json($responseData['json'])) ?></pre>
        </div>

        <div class="card">
            <h2 class="section-title">Hash Debug</h2>
            <p><strong>Type:</strong> <?= h($debug['type'] ?? '') ?></p>

            <?php if (($flow === 'card_hosted' || $flow === 'awcc_hosted') && !empty($debug['hash'])): ?>
                <p><strong>UPPERCASE source string:</strong></p>
                <pre><?= h($debug['hash']['source']) ?></pre>

                <p><strong>MD5 hex:</strong></p>
                <pre><?= h($debug['hash']['md5']) ?></pre>

                <p><strong>SHA1(MD5hex):</strong></p>
                <pre><?= h($debug['hash']['hash']) ?></pre>
            <?php endif; ?>

            <?php if (in_array($flow, ['afri_apm', 'orange_apm', 'mtn_apm'], true) && !empty($debug['hash'])): ?>
                <p><strong>Original source string:</strong></p>
                <pre><?= h($debug['hash']['source']) ?></pre>

                <p><strong>strrev(source):</strong></p>
                <pre><?= h($debug['hash']['reversed']) ?></pre>

                <p><strong>strtoupper(strrev(source)):</strong></p>
                <pre><?= h($debug['hash']['upper']) ?></pre>

                <p><strong>MD5:</strong></p>
                <pre><?= h($debug['hash']['hash']) ?></pre>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<script>
(function () {
    const flow = document.getElementById('flow');
    const blockHostedCommon = document.getElementById('block-hosted-common');
    const blockAwcc = document.getElementById('block-awcc');
    const blockApm = document.getElementById('block-apm');

    const apmBrand = document.getElementById('apm_brand');
    const apmCurrency = document.getElementById('apm_currency');
    const apmPhone = document.getElementById('apm_phone');
    const apmAmount = document.getElementById('apm_amount');
    const apmIdentifier = document.getElementById('apm_identifier');
    const apmReturnUrl = document.getElementById('apm_return_url');

    function updateFlowUI() {
        const value = flow.value;

        const isHosted = (value === 'card_hosted' || value === 'awcc_hosted');
        const isAwcc = (value === 'awcc_hosted');
        const isApm = (value === 'afri_apm' || value === 'orange_apm' || value === 'mtn_apm');

        blockHostedCommon.style.display = isHosted ? 'block' : 'none';
        blockAwcc.style.display = isAwcc ? 'block' : 'none';
        blockApm.style.display = isApm ? 'block' : 'none';

        if (value === 'afri_apm') {
            apmBrand.value = 'afri-money';
            apmCurrency.value = 'SLE';
            apmPhone.value = '23233310905';
            apmAmount.value = '0.99';
            apmIdentifier.value = '111';
            if (!apmReturnUrl.value) apmReturnUrl.value = 'https://google.com';
        }

        if (value === 'orange_apm') {
            apmBrand.value = 'orange-money';
            apmCurrency.value = 'SLE';
            apmPhone.value = '23274221777';
            apmAmount.value = '0.99';
            apmIdentifier.value = '111';
            if (!apmReturnUrl.value) apmReturnUrl.value = 'https://google.com';
        }

        if (value === 'mtn_apm') {
            apmBrand.value = 'mtn-momo';
            apmCurrency.value = 'LRD';
            apmPhone.value = '231881052626';
            apmAmount.value = '10.00';
            apmIdentifier.value = '111';
            if (!apmReturnUrl.value) apmReturnUrl.value = 'https://google.com';
        }
    }

    flow.addEventListener('change', updateFlowUI);
    updateFlowUI();
})();
</script>
</body>
</html>