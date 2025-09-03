<?php
// /s2stest/Tola/poll_status.php
header('Content-Type: text/plain; charset=utf-8');

$CHECK_URL  = '/s2stest/Tola/check_status.php'; // локальний роут на вашому домені
$order_id   = $_GET['order_id'] ?? '';
$trans_id   = $_GET['trans_id'] ?? '';

$max_checks = 10;   // ~30 хв
$interval   = 180;  // 3 хв

if (!$order_id && !$trans_id) { echo "pass ?order_id=... or ?trans_id=...\n"; exit; }

$base = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].$CHECK_URL;
$idQ  = $order_id ? ('order_id='.urlencode($order_id)) : ('trans_id='.urlencode($trans_id));

for ($i=1; $i<=$max_checks; $i++) {
  $ts = date('Y-m-d H:i:s');
  echo "[$ts] Attempt $i\n";

  $resp = @file_get_contents($base.'?'.$idQ);
  if ($resp === false) { echo "  request failed\n"; sleep($interval); continue; }

  $json = json_decode($resp, true);
  echo "  HTTP: ".($json['http'] ?? 'n/a')."\n";
  echo "  Raw: ".substr(($json['raw'] ?? ''), 0, 800)."...\n\n";

  $p = $json['parsed'] ?? [];
  if ($p) {
    $result = $p['result'] ?? '';
    $status = $p['status'] ?? '';
    if ($status === 'SUCCESS' || $status === 'DECLINED' || ($result === 'SUCCESS' && $status !== 'PREPARE')) {
      echo "Final:\n".json_encode($p, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
      exit;
    }
  }

  if ($i < $max_checks) sleep($interval);
}
echo "No final status yet. Try later or via dashboard trigger.\n";
