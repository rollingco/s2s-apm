<?php
// ===== CONFIG (твій MID) =====
$CHECKOUT_HOST = 'https://pay.leogcltd.com';
$MERCHANT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET_KEY    = '54999c284e9f29cf95f090d9a8f3171';

$SUCCESS_URL   = 'https://example.com/success';
$CANCEL_URL    = 'https://example.com/cancel';

// ===== ORDER (зміни за потреби) =====
$orderNumber   = 'order-'.time();
// Спробуємо обидва сценарії: crypto-amount невідомий (USDT) і фіат (USD)
$orderAmount   = '10.00';
$orderCurrencyCrypto = 'USDT';
$orderCurrencyFiat   = 'USD';
$orderDesc     = 'Test payment via awcc';

// ===== AWCC params =====
$networkType = 'eth'; // або 'tron'
$bech32      = false;

// База payload без hash
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

// Кандидати на “рядок для підпису”
$strings = [
    // Док-вариант (з amount)
    'doc_with_amount'        => function($n,$a,$c,$d,$pass,$mk){ return $n.$a.$c.$d.$pass; },
    // Док-вариант (без amount)
    'doc_without_amount'     => function($n,$a,$c,$d,$pass,$mk){ return $n.$c.$d.$pass; },
    // Варіант із merchant_key замість description (бачив у деяких інтеграторах)
    'with_mkey_amount'       => function($n,$a,$c,$d,$pass,$mk){ return $n.$a.$c.$mk.$pass; },
    'with_mkey_no_amount'    => function($n,$a,$c,$d,$pass,$mk){ return $n.$c.$mk.$pass; },
    // Варіант без description (інколи поле виключають)
    'no_desc_amount'         => function($n,$a,$c,$d,$pass,$mk){ return $n.$a.$c.$pass; },
    'no_desc_no_amount'      => function($n,$a,$c,$d,$pass,$mk){ return $n.$c.$pass; },
    // Додаємо success/cancel (рідко, але трапляється)
    'with_urls_amount'       => function($n,$a,$c,$d,$pass,$mk){ global $SUCCESS_URL,$CANCEL_URL; return $n.$a.$c.$d.$SUCCESS_URL.$CANCEL_URL.$pass; },
    'with_urls_no_amount'    => function($n,$a,$c,$d,$pass,$mk){ global $SUCCESS_URL,$CANCEL_URL; return $n.$c.$d.$SUCCESS_URL.$CANCEL_URL.$pass; },
];

// З MD5/SHA1 робимо 4 варіації
$hashers = [
    'sha1(md5(UPPER(s))) [md5 hex]'        => fn($s) => sha1(md5(strtoupper($s))),
    'sha1(UPPER(md5(UPPER(s))))'           => fn($s) => sha1(strtoupper(md5(strtoupper($s)))),
    'sha1(md5(UPPER(s), raw))'             => fn($s) => sha1(md5(strtoupper($s), true)),
    'sha1(md5(s)) [без UPPER]'             => fn($s) => sha1(md5($s)),
];

// Набори тестів: (currency, amountIncluded?)
$tests = [
    ['label'=>'CRYPTO (USDT), NO AMOUNT', 'currency'=>$orderCurrencyCrypto, 'amount'=>null],
    ['label'=>'FIAT (USD), WITH AMOUNT',  'currency'=>$orderCurrencyFiat,   'amount'=>$orderAmount],
];

header('Content-Type: text/plain; charset=utf-8');

foreach ($tests as $t) {
    echo "==== ".$t['label']." ====\n";
    $payload = basePayload($MERCHANT_KEY, $orderNumber, $t['currency'], $orderDesc, $t['amount'], $SUCCESS_URL, $CANCEL_URL, $networkType, $bech32);

    foreach ($strings as $slabel => $makeStr) {
        $src = $makeStr($orderNumber, $t['amount'] ?? '', $t['currency'], $orderDesc, $SECRET_KEY, $MERCHANT_KEY);

        foreach ($hashers as $hlabel => $hasher) {
            $hash = $hasher($src);
            $payload['hash'] = $hash;
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

            echo "\n-- signature candidate: $slabel | hasher: $hlabel\n";
            echo "string_to_sign: ".printable($src)."\n";
            echo "md5(hex, upper on src): ".strtoupper(md5(strtoupper($src)))."\n";
            echo "md5(hex): ".md5($src)."\n";
            echo "md5(raw)->hex(upper on src): ".bin2hex(md5(strtoupper($src), true))."\n";
            echo "hash(final): $hash\n";
            echo "REQUEST: $json\n";

            $res = httpPostJson(rtrim($CHECKOUT_HOST,'/').'/api/v1/session', $payload);
            echo "RESPONSE: HTTP {$res['code']} {$res['body']}\n";

            if ($res['code'] >= 200 && $res['code'] < 300) {
                $data = json_decode($res['body'], true);
                foreach (['checkout_url','redirect_url','payment_url','url'] as $k) {
                    if (!empty($data[$k])) { echo "SUCCESS! Open: {$data[$k]}\n"; }
                }
                exit; // зупиняємось на першому успіху
            }

            // якщо не валідний хеш – пробуємо далі
        }
    }
}

echo "\nFinished all variants. No success.\n";

// ===== helpers =====
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
    return ['code'=>$code, 'body'=>$body];
}

function normalizeAmount($amount): string {
    return number_format((float)$amount, 2, '.', '');
}

function printable($s) {
    // показує нечитаємі символи, якщо раптом десь CRLF/пробіли
    return preg_replace_callback('/[^\x20-\x7E]/u', fn($m) => sprintf('\\x%02X', ord($m[0])), $s);
}
