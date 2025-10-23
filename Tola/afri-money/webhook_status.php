<?php
// webhook_status.php
// Приймає POST від платіжного шлюзу, перевіряє підпис, логує, оновлює статус замовлення, відповідає 200 OK.

// === CONFIG ===
header('Content-Type: text/plain; charset=utf-8');
$SECRET      = '554999c284e9f29cf95f090d9a8f3171'; // той самий, що у вас в SALE (або окремий "Webhook secret", якщо такий видає шлюз)
$ALLOW_IPS   = []; // Можна вказати список IP шлюзу, якщо відомі. Напр.: ['1.2.3.4','5.6.7.8']
$LOG_FILE    = __DIR__ . '/logs/webhook_' . date('Ymd') . '.log';
$STATE_DIR   = __DIR__ . '/state'; // для простої ідемпотентності через файли
if (!is_dir(dirname($LOG_FILE))) @mkdir(dirname($LOG_FILE), 0775, true);
if (!is_dir($STATE_DIR)) @mkdir($STATE_DIR, 0775, true);

// === Helpers ===
function log_line($msg) {
  global $LOG_FILE;
  @file_put_contents($LOG_FILE, '['.date('Y-m-d H:i:s')."] $msg\n", FILE_APPEND);
}
function json_input() {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return [$raw, $data];
}
function ok_exit($text='OK') {
  http_response_code(200);
  echo $text;
  exit;
}
function bad_exit($code=400, $text='Bad Request') {
  http_response_code($code);
  echo $text;
  exit;
}

// === Optional: обмеження за IP ===
if (!empty($ALLOW_IPS)) {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  if (!in_array($ip, $ALLOW_IPS, true)) {
    log_line("Blocked IP $ip");
    bad_exit(403, 'Forbidden');
  }
}

// === Читаємо тіло + заголовки ===
[$raw, $json] = json_input();
$hdrs = function_exists('getallheaders') ? getallheaders() : [];
log_line("HEADERS: ".json_encode($hdrs, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
log_line("RAW: ".$raw);

// Перевіряємо JSON
if (!is_array($json)) {
  bad_exit(400, 'Invalid JSON');
}

// === Витягуємо ключові поля ===
$event     = $json['event']     ?? '';
$trans_id  = $json['trans_id']  ?? '';
$order_id  = $json['order_id']  ?? '';
$status    = strtoupper($json['status'] ?? '');
$amount    = $json['amount']    ?? '';
$currency  = strtoupper($json['currency'] ?? '');
$signature = $json['signature'] ?? ($hdrs['X-Signature'] ?? '');

// === Перевірка підпису ===
// ВАРІАНТ A (рекомендовано): HMAC-SHA256 від сирого тіла
$expectedA = base64_encode(hash_hmac('sha256', $raw, $SECRET, true));
// ВАРІАНТ B (якщо шлюз вимагає вашу "MD5(strrev...)" логіку для webhook):
// Наприклад: md5(strtoupper(strrev(identifier + trans_id + status + amount + currency + SECRET)))
$identifier = $json['identifier'] ?? '';
$hash_src   = $identifier.$trans_id.$status.$amount.$currency.$SECRET;
$expectedB  = md5(strtoupper(strrev($hash_src)));

// Вибір перевірки (підлаштувати під специфікацію вашого шлюзу):
$valid = false;
if (!empty($signature)) {
  if (hash_equals($expectedA, $signature)) { $valid = true; $used = 'HMAC'; }
  elseif (hash_equals($expectedB, $signature)) { $valid = true; $used = 'MD5_REV_UPPER'; }
}
if (!$valid) {
  log_line("Signature invalid. Got=".var_export($signature,true)." expA=$expectedA expB=$expectedB src=$hash_src");
  bad_exit(400, 'Invalid signature');
}
log_line("Signature OK (method=$used) for trans_id=$trans_id");

// === Ідемпотентність: не обробляти одну й ту ж транзакцію/подію двічі ===
if ($trans_id === '') {
  bad_exit(400, 'Missing trans_id');
}
$flag = $STATE_DIR.'/done_'.$trans_id.'_'.$status.'.flag';
if (file_exists($flag)) {
  log_line("Duplicate event ignored: $trans_id $status");
  ok_exit('OK (duplicate)');
}
@file_put_contents($flag, date('c'));

// === TODO: Оновлення замовлення/статусу в вашій системі ===
// Тут:
// - знаходимо замовлення по order_id або по trans_id
// - перевіряємо суму/валюту/бренд/identifier, щоб уникнути "склеювання"
// - якщо $status === 'SUCCESS' -> зараховуємо платіж, оновлюємо статус (наприклад, "paid")
// - якщо 'DECLINE' або 'EXPIRED' -> позначаємо як failed/canceled
// - ведемо бізнес-логи/метрики
try {
  // Приклад (заглушка):
  // update_order_status($order_id, map_gateway_status($status));
  log_line("Handled: order_id=$order_id trans_id=$trans_id status=$status amount=$amount $currency");
} catch (Throwable $e) {
  log_line("Handle error: ".$e->getMessage());
  // Можна видалити флаг, щоб дозволити повторну обробку при ретраї:
  @unlink($flag);
  bad_exit(500, 'Processing error');
}

// === ВАЖЛИВО: швидко і чітко відповісти 200 ===
ok_exit('OK');
