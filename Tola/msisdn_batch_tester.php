<?php
/**
 * msisdn_batch_tester.php
 * Run a sequence of SALE requests with different payer_phone values (no msisdn field),
 * print immediate responses and build poll links per case.
 */

header('Content-Type: text/html; charset=utf-8');

/* ===== Targets ===== */
$SEND_URL   = 'https://www.zal25.pp.ua/s2stest/Tola/send_sale_apm.php';      // your working SALE page (renders HTML)
$POLL_URL   = 'https://www.zal25.pp.ua/s2stest/Tola/poll_status.php';        // your poller (plain text)

/* ===== Cases (label => phone) ===== */
$cases = [
  'Success'             => '254000000000',
  'Insufficient funds'  => '254000000005',
  'MSISDN invalid'      => '254000000013',
  'Rejected (default)'  => '254000000099',
];

/* ===== Common query params for send_sale_apm.php ===== */
$common = [
  'brand'  => 'afri-money',
  'id'     => '111',
  'ccy'    => 'SLE',
  'amt'    => '100.00',
  'return' => 'https://google.com',
];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }

/* ===== Helper: extract JSON from send_sale_apm HTML page =====
   Your send_sale_apm.php prints JSON body inside <pre> blocks. We'll try to pull the last JSON blob. */
function extract_json_from_html($html){
  // naive approach: find the last {...} block
  if (preg_match_all('/\{(?:[^{}]|(?R))*\}/s', $html, $m) && !empty($m[0])) {
    $last = end($m[0]);
    $decoded = json_decode($last, true);
    if (json_last_error() === JSON_ERROR_NONE) return $decoded;
  }
  return null;
}

/* ===== Run ===== */
$results = [];
foreach ($cases as $label => $phone) {
  $url = $SEND_URL . '?' . http_build_query(array_merge($common, ['phone' => $phone]));

  // fetch HTML response from send_sale_apm.php
  $html = @file_get_contents($url);
  $parsed = $html ? extract_json_from_html($html) : null;

  $order_id = $parsed['order_id'] ?? null;
  $trans_id = $parsed['trans_id'] ?? null;
  $poll_qs  = [];
  if ($order_id) $poll_qs['order_id'] = $order_id;
  if ($trans_id) $poll_qs['trans_id'] = $trans_id;

  $results[] = [
    'label'    => $label,
    'phone'    => $phone,
    'send_url' => $url,
    'parsed'   => $parsed,
    'poll'     => $poll_qs ? ($POLL_URL.'?'.http_build_query($poll_qs)) : null,
  ];

  // tiny delay between calls to be nice
  usleep(300000); // 300 ms
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MSISDN Batch Tester</title>
<style>
  body{font:14px/1.5 ui-monospace,Menlo,Consolas,monospace;background:#0f1115;color:#e6e6e6;margin:0}
  .wrap{max-width:1100px;margin:0 auto;padding:24px}
  .card{background:#171923;border:1px solid #2a2f3a;border-radius:12px;padding:18px;margin:14px 0}
  .muted{color:#9aa4af}
  pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
  a{color:#8ab4ff}
  table{width:100%;border-collapse:collapse}
  th,td{padding:8px;border-bottom:1px solid #2a2f3a;text-align:left;vertical-align:top}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2>MSISDN Batch Tester</h2>
    <div class="muted">Runs SALE for several test numbers (as <code>payer_phone</code>), parses the JSON body, and provides poll links.</div>
  </div>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th>Case</th>
          <th>Phone</th>
          <th>Immediate response (parsed)</th>
          <th>Poll link</th>
          <th>Open SALE</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($results as $r): ?>
          <tr>
            <td><?=h($r['label'])?></td>
            <td><?=h($r['phone'])?></td>
            <td><pre><?=h(json_encode($r['parsed'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre></td>
            <td>
              <?php if ($r['poll']): ?>
                <a target="_blank" href="<?=h($r['poll'])?>">Poll now</a>
              <?php else: ?>
                <span class="muted">n/a</span>
              <?php endif; ?>
            </td>
            <td><a target="_blank" href="<?=h($r['send_url'])?>">Open SALE</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
