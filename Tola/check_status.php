<?php
// /s2stest/Tola/check_status.php
header('Content-Type: application/json; charset=utf-8');

$PAYMENT_URL = 'https://api.leogcltd.com/post-va';
$API_USER    = 'leogc';
$API_PASS    = 'ORuIO57N6KJyeJ';

$CLIENT_KEY  = 'a9375384-26f2-11f0-877d-022c42254708';
$CLIENT_KEY   = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET      = '554999c284e9f29cf95f090d9a8f3171';

$order_id = $_GET['order_id'] ?? '';
$trans_id = $_GET['trans_id'] ?? '';
$action   = $_GET['action']   ?? 'STATUS'; // підлаштуй назву під доку, якщо інша

if (!$order_id && !$trans_id) {
  http_response_code(400);
  echo json_encode(['error'=>'pass ?order_id=... or ?trans_id=...'], JSON_PRETTY_PRINT);
  exit;
}

// Якщо потрібен hash для STATUS — встав тут реальну формулу з доків.
// function build_status_hash($id,$client_key,$secret){ return md5(strtoupper(strrev($id.$client_key.$secret))); }

$payload = [
  'action'     => $action,
  'client_key' => $CLIENT_KEY,
];
if ($order_id) $payload['order_id'] = $order_id;
if ($trans_id) $payload['trans_id'] = $trans_id;
// $payload['hash'] = build_status_hash($order_id ?: $trans_id, $CLIENT_KEY, $SECRET);

$ch = curl_init($PAYMENT_URL);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => http_build_query($payload),
  CURLOPT_USERPWD        => $API_USER . ':' . $API_PASS,
  CURLOPT_TIMEOUT        => 30,
]);
$raw  = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_errno($ch) ? curl_error($ch) : '';
curl_close($ch);

echo json_encode([
  'http'   => $http,
  'error'  => $err ?: null,
  'sent'   => $payload,
  'parsed' => json_decode($raw, true),
  'raw'    => $raw,
], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
