<?php
// msisdn_playground.php
// Handy launcher to quickly run SALE with specific test MSISDNs (via payer_phone only).
// Click buttons to open your existing send_sale_apm.php with ?phone=...

header('Content-Type: text/html; charset=utf-8');

// Map of human-friendly labels to test MSISDNs (Tola-style)
$cases = [
  'Success'             => '254000000000',
  'Insufficient funds'  => '254000000005',
  'MSISDN invalid'      => '254000000013',
  'Rejected (default)'  => '254000000099',
];

// Base URL to your working SALE script
$send_url = 'https://www.zal25.pp.ua/s2stest/Tola/send_sale_apm.php';

// Optional: default common params for SALE (you can tweak if needed)
$common = [
  'brand'  => 'afri-money',
  'id'     => '111',
  'ccy'    => 'SLE',
  'amt'    => '100.00',
  'return' => 'https://google.com',
];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MSISDN Playground</title>
<style>
  body{font:14px/1.5 ui-monospace,Menlo,Consolas,monospace;background:#0f1115;color:#e6e6e6;margin:0}
  .wrap{max-width:900px;margin:0 auto;padding:24px}
  .card{background:#171923;border:1px solid #2a2f3a;border-radius:12px;padding:18px;margin:14px 0}
  a.btn{display:inline-block;margin:8px 8px 0 0;padding:10px 14px;border-radius:10px;text-decoration:none;background:#2b7cff;color:#fff}
  a.btn:hover{opacity:.9}
  .muted{color:#9aa4af}
  code{background:#11131a;border:1px solid #232635;border-radius:6px;padding:2px 6px}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2>MSISDN Playground</h2>
    <div class="muted">Click a case to send SALE with <code>payer_phone</code> set to a specific test number (no <code>msisdn</code> field).</div>
  </div>

  <div class="card">
    <h3>Quick cases</h3>
    <?php foreach ($cases as $label => $msisdn): 
      $q = array_merge($common, ['phone' => $msisdn]);
      $url = $send_url . '?' . http_build_query($q);
    ?>
      <div>
        <a class="btn" target="_blank" href="<?=h($url)?>">
          <?=h($label)?> → <?=h($msisdn)?>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h3>Custom number</h3>
    <form action="<?=h($send_url)?>" method="get" target="_blank">
      <?php foreach ($common as $k => $v): ?>
        <input type="hidden" name="<?=h($k)?>" value="<?=h($v)?>">
      <?php endforeach; ?>
      <label>payer_phone:
        <input type="text" name="phone" value="254000000000" style="margin-left:10px;padding:6px 8px;border-radius:8px;border:1px solid #2a2f3a;background:#11131a;color:#e6e6e6">
      </label>
      <button type="submit" class="btn">Send SALE</button>
    </form>
  </div>
</div>
</body>
</html>
