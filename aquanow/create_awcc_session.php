<?php
/**
 * AWCC (Aquanow) Checkout — hash diagnostics
 * - Tries multiple "string to sign" variants and hashing pipelines
 * - Prints clean, separated logs for each attempt
 * - Stops on first successful response (2xx) and shows checkout URL
 *
 * Output: text/plain (human-readable)
 */

// ========= CONFIG (your MID) =========
$CHECKOUT_HOST = 'https://pay.leogcltd.com';
$MERCHANT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET_KEY    = '54999c284e9f29cf95f090d9a8f3171';

$SUCCESS_URL   = 'https://example.com/success';
$CANCEL_URL    = 'https://example.com/cancel';

// ========= ORDER (edit if needed) =========
$orderNumber         = 'order-'.time();
// We'll test both scenarios: crypto w/o amount (USDT) and fiat with amount (USD)
$orderAmount         = '10.00';
$orderCurrencyCrypto = 'USDT';
$orderCurrencyFiat   = 'USD';
$orderDesc           = 'Test payment via awcc';

// ========= AWCC parameters =========
$networkType = 'eth'; // or 'tron'
$bech32      = false;

header('Content-Type: text/plain; charset=utf-8');

// ---------- Helpers to build payload ----------
function normalizeAmount($amount): string {
    return number_format((float)$amount, 2, '.', '');
}

function basePayload($merchantKey, $number, $currency, $desc, $amountOrNull, $success, $cancel, $networkType, $bech32) {
    $order = [
        'number'      => $number,
        'currency'    => $currency,
        'description' => $desc,
    ];
    if ($amountOrNull !== null && $amountOrNull !== '') {
        $order['amount'] = normalizeAmount($amountOrNull);
    }
    return [
        'merchant_key' => $merchantKey,
        'operation'    => 'purchase',
        'methods'      => ['awcc'],
        'parameters'   => [
            'awcc' => [
                'network_type' => $networkType,
                'bech32'       => $bech32 ? 'true' : 'false',
            ],
        ],
        'order' => $order,
        'cancel_url'  => $cancel,
        'success_url' => $success,
        'customer' => ['name' => 'John Doe', 'email' => 'test@example.com'],
        'billing_address' => [
            'country' => 'US','state' => 'CA','city' => 'Los Angeles',
            'address' => 'Moor Building 35274','zip' => '123456','phone' => '347777112233',
        ],
    ];
}

function httpPostJson(string $url, array $data): array {
    $json = json_encode($data, JSON_UNESCAPED_SLASHES);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) { $body = "cURL error: $err"; }
    return ['code'=>$code, 'body'=>$body, 'json'=>$json];
}

/** Make non-printable characters visible (e.g., stray line breaks) */
function printable($s) {
    return preg_replace_callback(
        '/[^\x20-\x7E]/u',
        fn($m) => sprintf('\\x%02X', ord($m[0])),
        $s
    );
}

/** Pretty section separator */
function sep($title) {
    $line = str_repeat('=', 80);
    echo "\n$line\n$title\n$line\n";
}

/** Attempt separator */
function attempt_header($label) {
    $line = str_repeat('-', 80);
    echo "\n$line\n$label\n$line\n";
}

/** Key/value pretty print */
function kv($k, $v) {
    echo str_pad($k.':', 24, ' ', STR_PAD_RIGHT).$v."\n";
}

// ---------- Candidate “string to sign” builders ----------
// Note: we pass ALL inputs; builders decide what to include.
$strings = [
    // From docs: with amount
    'DOC: with amount' =>
        fn($n,$a,$c,$d,$pass,$mk) => $n.$a.$c.$d.$pass,
    // From docs: crypto case (no amount)
    'DOC: without amount' =>
        fn($n,$a,$c,$d,$pass,$mk) => $n.$c.$d.$pass,

    // Alternative: merchant_key instead of description
    'ALT: with merchant_key (amount)' =>
        fn($n,$a,$c,$d,$pass,$mk) => $n.$a.$c.$mk.$pass,
    'ALT: with merchant_key (no amount)' =>
        fn($n,$a,$c,$d,$pass,$mk) => $n.$c.$mk.$pass,

    // Alternative: drop description
    'ALT: no description (amount)' =>
        fn($n,$a,$c,$d,$pass,$mk) => $n.$a.$c.$pass,
    'ALT: no description (no amount)' =>
        fn($n,$a,$c,$d,$pass,$mk) => $n.$c.$pass,

    // Rare: include return URLs
    'ALT: include URLs (amount)' =>
        function($n,$a,$c,$d,$pass,$mk) {
            global $SUCCESS_URL,$CANCEL_URL;
            return $n.$a.$c.$d.$SUCCESS_URL.$CANCEL_URL.$pass;
        },
    'ALT: include URLs (no amount)' =>
        function($n,$a,$c,$d,$pass,$mk) {
            global $SUCCESS_URL,$CANCEL_URL;
            return $n.$c.$d.$SUCCESS_URL.$CANCEL_URL.$pass;
        },
];

// ---------- Hash pipelines ----------
// We vary uppercasing and MD5 output (hex vs raw) before SHA1.
$hashers = [
    'sha1( md5( UPPER(s) ) )       [md5 hex]'  => fn($s) => sha1(md5(strtoupper($s))),
    'sha1( UPPER( md5( UPPER(s) ) ) )'         => fn($s) => sha1(strtoupper(md5(strtoupper($s)))),
    'sha1( md5( UPPER(s), raw ) )'             => fn($s) => sha1(md5(strtoupper($s), true)),
    'sha1( md5( s ) )               [no upper]' => fn($s) => sha1(md5($s)),
];

// ---------- Test sets ----------
// 1) Crypto (USDT) -> NO amount in payload and signature
// 2) Fiat (USD)    -> WITH amount in payload and signature
$tests = [
    ['label'=>'CRYPTO (USDT) — NO AMOUNT', 'currency'=>$orderCurrencyCrypto, 'amount'=>null],
    ['label'=>'FIAT (USD) — WITH AMOUNT',  'currency'=>$orderCurrencyFiat,   'amount'=>$orderAmount],
];

// ====================================================================================
// RUN
// ====================================================================================
sep('AWCC HASH DIAGNOSTICS — START');

foreach ($tests as $tIndex => $t) {
    sep("TEST SET #".($tIndex+1).": {$t['label']}");

    $payload = basePayload(
        $MERCHANT_KEY, $orderNumber, $t['currency'], $orderDesc,
        $t['amount'], $SUCCESS_URL, $CANCEL_URL, $networkType, $bech32
    );

    $attempt = 0;
    foreach ($strings as $sigLabel => $makeStr) {
        $src = $makeStr($orderNumber, $t['amount'] ?? '', $t['currency'], $orderDesc, $SECRET_KEY, $MERCHANT_KEY);

        foreach ($hashers as $hashLabel => $hasher) {
            $attempt++;
            attempt_header("ATTEMPT #$attempt");

            // --- Signature candidate block (highlighted) ---
            echo "-- SIGNATURE CANDIDATE ----------------------------------------------------\n";
            kv('Candidate label', $sigLabel);
            kv('Hasher',          $hashLabel);
            kv('string_to_sign',  printable($src));
            kv('md5(UPPER(src))', md5(strtoupper($src)));
            kv('md5(src)',        md5($src));
            kv('md5(UPPER)->hex', bin2hex(md5(strtoupper($src), true)));

            // Build final hash and request
            $hash = $hasher($src);
            $payload['hash'] = $hash;
            $url = rtrim($CHECKOUT_HOST,'/').'/api/v1/session';
       

            echo "\n-- REQUEST ----------------------------------------------------------------\n";
            kv('POST', $url);
            echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n";

            $res = httpPostJson($url, $payload);

            echo "\n-- RESPONSE ---------------------------------------------------------------\n";
            kv('HTTP code', (string)$res['code']);
            echo (string)$res['body']."\n";

            // Stop on first success and show checkout URL
            if ($res['code'] >= 200 && $res['code'] < 300) {
                $data = json_decode($res['body'], true);
                $open = null;
                foreach (['checkout_url','redirect_url','payment_url','url'] as $k) {
                    if (!empty($data[$k])) { $open = $data[$k]; break; }
                }
                echo "\n**************************************************************************\n";
                echo "SUCCESS! OPEN THIS URL: ".$open."\n";
                echo "**************************************************************************\n";
                exit;
            }
        }
    }
}

sep('AWCC HASH DIAGNOSTICS — FINISHED (no successful attempt)');
// End of file
