<?php
// ==== CONFIG ====
$CHECKOUT_HOST = 'https://pay.leogcltd.com';
$MERCHANT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET_KEY    = '54999c284e9f29cf95f090d9a8f3171';

$SUCCESS_URL   = 'https://example.com/success';
$CANCEL_URL    = 'https://example.com/cancel';

// awcc options
$networkType = 'eth';      // 'eth' або 'tron'
$bech32      = false;      // true/false

// ==== ORDER DATA ====
$orderNumber   = 'order-'.time();
$orderAmount   = normalizeAmount('10');     // нормалізуємо до 2-х знаків: '10.00'
$orderCurrency = 'USD';
$orderDesc     = 'Test payment via awcc';

// Заготовка payload без hash
$basePayload = [
    'merchant_key' => $MERCHANT_KEY,
    'operation'    => 'purchase',
    'methods'      => ['awcc'],
    'parameters'   => [
        'awcc' => [
            'network_type' => $networkType,
            'bech32'       => $bech32 ? 'true' : 'false',
        ],
    ],
    'order' => [
        'number'      => $orderNumber,
        'amount'      => $orderAmount,
        'currency'    => $orderCurrency,
        'description' => $orderDesc,
    ],
    'cancel_url'  => $CANCEL_URL,
    'success_url' => $SUCCESS_URL,
    'customer' => [
        'name'  => 'John Doe',
        'email' => 'test@example.com',
    ],
    'billing_address' => [
        'country' => 'US',
        'state'   => 'CA',
        'city'    => 'Los Angeles',
        'address' => 'Moor Building 35274',
        'zip'     => '123456',
        'phone'   => '347777112233',
    ],
];

// Варіанти формули. Починаємо з тієї, що в доках найчастіша:
// SHA1( MD5( UPPERCASE(order.number + amount + currency + description + secret) ) )
$hashVariants = [
    'SHA1(MD5(UPPER(CONCAT))) [MD5 hex]'      => function() use ($orderNumber,$orderAmount,$orderCurrency,$orderDesc,$SECRET_KEY) {
        $s = strtoupper($orderNumber.$orderAmount.$orderCurrency.$orderDesc.$SECRET_KEY);
        return sha1(md5($s)); // md5 hex -> sha1(hex)
    },
    'SHA1(UPPER(MD5(UPPER(CONCAT))))'         => function() use ($orderNumber,$orderAmount,$orderCurrency,$orderDesc,$SECRET_KEY) {
        $s = strtoupper($orderNumber.$orderAmount.$orderCurrency.$orderDesc.$SECRET_KEY);
        return sha1(strtoupper(md5($s))); // деякі доки вимагають MD5 hex в uppercase перед sha1
    },
    'SHA1(MD5(UPPER(CONCAT)) raw)'            => function() use ($orderNumber,$orderAmount,$orderCurrency,$orderDesc,$SECRET_KEY) {
        $s = strtoupper($orderNumber.$orderAmount.$orderCurrency.$orderDesc.$SECRET_KEY);
        return sha1(md5($s, true)); // sha1 від raw md5 bytes
    },
    'SHA1(MD5(CONCAT)) [без upper]'           => function() use ($orderNumber,$orderAmount,$orderCurrency,$orderDesc,$SECRET_KEY) {
        $s = $orderNumber.$orderAmount.$orderCurrency.$orderDesc.$SECRET_KEY;
        return sha1(md5($s));
    },
];

// Послідовно пробуємо відправити запит з кожним варіантом
foreach ($hashVariants as $label => $fn) {
    $payload = $basePayload;
    $payload['hash'] = $fn();

    $res = httpPostJson(rtrim($CHECKOUT_HOST,'/').'/api/v1/session', $payload);
    echo "Trying hash scheme: $label\n";
    echo "HTTP {$res['code']}\n{$res['body']}\n\n";

    if ($res['code'] >= 200 && $res['code'] < 300) {
        // Успіх
        $data = json_decode($res['body'], true);
        if (is_array($data)) {
            foreach (['checkout_url','redirect_url','payment_url','url'] as $k) {
                if (!empty($data[$k])) {
                    echo "Open in browser: {$data[$k]}\n";
                    break 2;
                }
            }
        }
        break;
    }

    // Якщо повернули конкретно помилку хеша — йдемо до наступного варіанту
    if (!hashError($res['body'])) {
        // Інша помилка — немає сенсу міняти формулу
        break;
    }
}

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

function hashError(string $body): bool {
    $j = json_decode($body, true);
    if (!is_array($j)) return false;
    if (!empty($j['error_message']) && stripos($j['error_message'], 'hash') !== false) return true;
    if (!empty($j['errors']) && is_array($j['errors'])) {
        foreach ($j['errors'] as $e) {
            if (!empty($e['error_message']) && stripos($e['error_message'], 'hash') !== false) return true;
        }
    }
    return false;
}

function normalizeAmount($amount): string {
    // 2 знаки після крапки, крапка як сепаратор; без пробілів і тисяч
    return number_format((float)$amount, 2, '.', '');
}
