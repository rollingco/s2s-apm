<?php
// ==== CONFIG ====
$WEBHOOK_SECRET = '54999c284e9f29cf95f090d9a8f3171';

// Логування вхідних callback
$raw = file_get_contents('php://input');
$headers = function_exists('getallheaders') ? getallheaders() : [];
file_put_contents(__DIR__.'/aquanow_webhook.log',
    "---- ".date('c')." ----\n".
    "Headers: ".json_encode($headers, JSON_PRETTY_PRINT)."\n".
    "Body: ".$raw."\n\n",
    FILE_APPEND
);

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo 'invalid json';
    exit;
}

// Перевірка хеша з доки:
// SHA1( MD5( strtoupper(payment_public_id + order.number + order.amount + order.currency + order.description + merchant.pass) ) )
$receivedHash = $data['hash'] ?? '';
$paymentPublicId = $data['payment_public_id'] ?? '';
$orderNumber     = $data['order']['number'] ?? '';
$orderAmount     = $data['order']['amount'] ?? null;
$orderCurrency   = $data['order']['currency'] ?? '';
$orderDesc       = $data['order']['description'] ?? '';

$calcHash = buildCallbackHash($paymentPublicId, $orderNumber, $orderAmount, $orderCurrency, $orderDesc, $WEBHOOK_SECRET);

if (!hash_equals($calcHash, $receivedHash)) {
    http_response_code(400);
    echo 'bad signature';
    exit;
}

// Тут оновлюємо статус замовлення
$orderStatus = $data['status'] ?? ($data['transaction']['status'] ?? 'unknown');
// TODO: updateOrderStatus($orderNumber, $orderStatus, $data);

// Повертаємо OK
http_response_code(200);
echo 'ok';

// ==== helpers ====
function buildCallbackHash(string $paymentPublicId, string $orderNumber, ?string $amount, string $currency, string $description, string $secret): string {
    $parts = [$paymentPublicId, $orderNumber];
    if ($amount !== null && $amount !== '') {
        $parts[] = $amount;
    }
    $parts[] = $currency;
    $parts[] = $description;
    $parts[] = $secret;

    $toMd5 = strtoupper(implode('', $parts));
    return sha1(md5($toMd5));
}
