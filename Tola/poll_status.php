<?php
// poll_status.php — цикл: перевірка кожні 180 сек, поки не SUCCESS/DECLINED

header('Content-Type: text/plain; charset=utf-8');

/* === CONFIG === */
$CHECK_URL  = 'https://www.zal25.pp.ua/s2stest/Tola/check_status.php';
$order_id   = $_GET['order_id'] ?? '';
$trans_id   = $_GET['trans_id'] ?? '';
$max_checks = 10;          // макс. спроб (~30 хв)
$interval   = 180;         // 3 хвилини

if (!$order_id && !$trans_id) {
  echo "pass ?order_id=... or ?trans_id=...\n"; exit;
}

$idParam = $order_id ? ('order_id='.urlencode($order_id)) : ('trans_id='.urlencode($trans_id));

for ($i=1; $i<=$max_checks; $i++) {
  $ts = date('Y-m-d H:i:s');
  echo "[$ts] Attempt $i\n";

  $resp = file_get_contents($CHECK_URL.'?'.$idParam); // простий варіант виклику нашого check_status.php
  if ($resp === false) { echo "  request failed\n"; sleep($interval); continue; }

  $json = json_decode($resp, true);
  echo "  HTTP: ".($json['http'] ?? 'n/a')."\n";
  echo "  Raw: ".($json['raw'] ?? '')."\n\n";

  $p = $json['parsed'] ?? [];
  if ($p) {
    $result = $p['result'] ?? '';
    $status = $p['status'] ?? '';
    // фінальні варіанти: коригуй під ваші точні значення
    if ($status === 'SUCCESS' || $status === 'DECLINED' || ($result === 'SUCCESS' && $status !== 'PREPARE')) {
      echo "Final:\n".json_encode($p, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
      exit;
    }
  }
  if ($i < $max_checks) sleep($interval);
}
echo "No final status yet. Try later or via dashboard.\n";
