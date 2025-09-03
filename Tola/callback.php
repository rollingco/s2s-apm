<?php
// s2stest/callback.php
header('Content-Type: text/plain; charset=utf-8');

$raw = file_get_contents('php://input');
$ts  = date('Y-m-d H:i:s');

// якщо приходить form-data/x-www-form-urlencoded:
$payload = $_POST;

// якщо приходить JSON:
if (!$payload && $raw) {
    $try = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $payload = $try;
    }
}

// лог
file_put_contents(__DIR__.'/tola_callback.log',
    "[$ts]\nRAW: $raw\nPARSED: ".print_r($payload, true)."\n---\n",
    FILE_APPEND
);

// TODO: (опц.) перевірка підпису з доків, наприклад:
// $expected = build_callback_hash(...);
// if (($payload['hash'] ?? '') !== $expected) { http_response_code(400); exit('BAD HASH'); }

// оновлення вашої БД
$orderId = $payload['order_id'] ?? null;
$transId = $payload['trans_id'] ?? null;
$status  = $payload['status']  ?? null; // SUCCESS | DECLINED

// TODO: update orders set status=?, updated_at=now() where order_id=?

echo 'OK';
