<?php
/**
 * poll_status.php — циклічна перевірка фінального статусу
 * Використання:
 *   poll_status.php?order_id=ORDER_1756891107
 * або
 *   poll_status.php?trans_id=6c8656ce-87d2-11f0-ae91-7aa0fce6d172
 */

header('Content-Type: text/plain; charset=utf-8');

/* ===== CONFIG ===== */
$PAYMENT_URL = 'https://api.leogcltd.com/post-va';
$API_USER    = 'leogc';
$API_PASS    = 'ORuIO57N6KJyeJ';

$CLIENT_KEY  = 'a9375384-26f2-11f0-877d-022c42254708';
$CLIENT_KEY   = 'a9375190-26f2-11f0-be42-022c42254708'; 
$SECRET      = '554999c284e9f29cf95f090d9a8f3171';

// у доках може бути інша назва дії: STATUS / CHECK / QUERY — підставити правильно
$ACTION      = 'STATUS';

/* ===== INPUT ===== */
$order_id = $_GET['order_id'] ?? '';
$trans_id = $_GET['trans_id'] ?? '';

if (!$order_id && !$trans_id) {
    echo "❌ Pass ?order_id=... or ?trans_id=...\n";
    exit;
}

/* ===== побудова payload ===== */
$payload = [
    'action'     => $ACTION,
    'client_key' => $CLIENT_KEY,
];
if ($order_id) $payload['order_id'] = $order_id;
if ($trans_id) $payload['trans_id'] = $trans_id;

// якщо потрібен hash для STATUS — реалізуй функцію за докою
// $payload['hash'] = build_status_hash(...);

/* ===== функція 1 перевірки ===== */
function check_status($url, $user, $pass, $payload) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_USERPWD        => $user . ':' . $pass,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $raw  = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_errno($ch) ? curl_error($ch) : '';
    curl_close($ch);

    return [$http, $raw, $err, json_decode($raw, true)];
}

/* ===== POLLING LOOP ===== */
$max_checks = 20;   // максимум ~1 година при інтервалі 3 хв
$interval   = 180;  // 3 хв у секундах

for ($i=1; $i<=$max_checks; $i++) {
    $ts = date('Y-m-d H:i:s');
    echo "[$ts] Attempt $i\n";

    [$code, $raw, $err, $data] = check_status($PAYMENT_URL, $API_USER, $API_PASS, $payload);

    echo "  HTTP: $code\n";
    if ($err) echo "  cURL error: $err\n";
    echo "  Raw: $raw\n\n";

    if (is_array($data)) {
        $result = $data['result'] ?? '';
        $status = $data['status'] ?? '';

        if ($status === 'SUCCESS' || $status === 'DECLINED' ||
            ($result === 'SUCCESS' && $status !== 'PREPARE')) {

            echo "✅ Final status received:\n";
            echo json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
            exit;
        }
    }

    if ($i < $max_checks) {
        echo "⏳ Waiting $interval sec before next check...\n\n";
        sleep($interval);
    }
}

echo "⚠️ No final status after $max_checks attempts. Try dashboard trigger or check later.\n";
exit; 