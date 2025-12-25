<?php
/**
 * S2S CREDIT2VIRTUAL — MPESA (brand=mpesa) payout by order_id → minimal logs
 *
 * DOCS:
 * - endpoint: https://api.leogcltd.com/post
 * - brand: mpesa
 * - required extra params:
 *     parameters[CommandID] = SalaryPayment | BusinessPayment | PromotionPayment
 *     parameters[PartyB]    = receiver MSISDN in format 254XXXXXXXXX (no +)
 *
 * CREDIT2VIRTUAL HASH (working one):
 *   md5( strtoupper( strrev( order_id . amount . currency ) ) . SECRET )
 */

header('Content-Type: text/html; charset=utf-8');

/* ===================== CONFIG ===================== */
$PAYMENT_URL = 'https://api.leogcltd.com/post';

/* креденції (як у тебе в mpesa_sale_s2s.php) */
$CLIENT_KEY = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET     = '554999c284e9f29cf95f090d9a8f3171';

$CHANNEL_ID  = '';
$STATUS_HELPER_URL = 'status_credit2virtual.php';

/* Prefill from GET */
$DEFAULTS = [
  'order_id'   => isset($_GET['order_id']) ? (string)$_GET['order_id'] : ('mpesa-c2v-' . time()),
  'amount'     => isset($_GET['amount'])   ? (string)$_GET['amount']   : '100.00',
  'currency'   => isset($_GET['currency']) ? (string)$_GET['currency'] : 'KES',
  'brand'      => isset($_GET['brand'])    ? (string)$_GET['brand']    : 'mpesa',
  'desc'       => isset($_GET['desc'])     ? (string)$_GET['desc']     : 'Product',
  'command_id' => isset($_GET['CommandID'])? (string)$_GET['CommandID']: 'SalaryPayment',
  'party_b'    => isset($_GET['PartyB'])   ? (string)$_GET['PartyB']   : '254700000000',
];

/* ===================== Helpers ===================== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function pretty($v){
  if (is_string($v)) {
    $d = json_decode($v,true);
    if (json_last_error()===JSON_ERROR_NONE) $v=$d; else return h($v);
  }
  return h(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}

/** CREDIT2VIRTUAL hash:
 * md5( strtoupper( strrev( order_id . amount . currency ) ) . SECRET )
 */
function build_credit2virtual_hash($order_id, $amount, $currency, $secret, &$srcOut = null){
  $inner = $order_id . $amount . $currency;
  $src   = strtoupper(strrev($inner)) . $secret;
  if ($srcOut !== null) $srcOut = $src;
  return md5($src);
}

/** Normalize amount to XX.XX */
function normalize_amount_2dec(string $raw): string {
  $s = preg_replace('/[^0-9.]/', '', $raw);
  if ($s === '') return '';
  if (substr_count($s, '.') > 1) {
    $parts = explode('.', $s);
    $s = array_shift($parts) . '.' . implode('', $parts);
  }
  if (strpos($s, '.') === false) return $s . '.00';
  list($int, $dec) = array_pad(explode('.', $s, 2), 2, '');
  if ($dec === '')        return $int . '.00';
  if (strlen($dec) === 1) return $int . '.' . $dec . '0';
  return $int . '.' . substr($dec, 0, 2);
}

/** PartyB: digits only, no plus */
function normalize_partyb(string $raw): string {
  $s = preg_replace('/\s+/', '', $raw);
  $s = ltrim($s, '+');
  $s = preg_replace('/\D+/', '', $s);
  return $s;
}

/* ===================== Read form ===================== */
$method    = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$submitted = ($method === 'POST');

if ($submitted) {
  $order_id_in = trim((string)($_POST['order_id'] ?? ''));
  $amount_in   = (string)($_POST['amount'] ?? '');
  $currency    = strtoupper(trim((string)($_POST['currency'] ?? '')));
  $brand       = trim((string)($_POST['brand'] ?? ''));
  $desc        = trim((string)($_POST['desc'] ?? ''));

  $command_id  = trim((string)($_POST['command_id'] ?? ''));
  $party_b     = normalize_partyb((string)($_POST['party_b'] ?? ''));

  $amount = trim($amount_in) === '' ? '' : normalize_amount_2dec($amount_in);

  $errors = [];
  if ($order_id_in === '') $errors[] = 'order_id is required.';
  if ($amount === '' || !preg_match('/^\d+\.\d+$/', $amount)) {
    $errors[] = 'Amount wrong format. Use e.g. 1.00, 10.50, 100.00';
  }
  if ($currency === '') $errors[] = 'Currency is required.';
  if ($brand === '') $brand = 'mpesa';

  // docs-required for brand=mpesa
  $allowedCmd = ['SalaryPayment','BusinessPayment','PromotionPayment'];
  if ($command_id === '') $errors[] = 'CommandID is required for brand=mpesa.';
  if ($command_id !== '' && !in_array($command_id, $allowedCmd, true)) {
    $errors[] = 'CommandID must be one of: SalaryPayment, BusinessPayment, PromotionPayment.';
  }
  if ($party_b === '') $errors[] = 'PartyB is required for brand=mpesa.';
  if ($party_b !== '' && !preg_match('/^\d{9,15}$/', $party_b)) {
    $errors[] = 'PartyB looks wrong. Use digits only, e.g. 2547XXXXXXXX (no +).';
  }

  if ($errors) {
    render_page([
      'errors'  => $errors,
      'prefill' => [
        'order_id'   => $order_id_in,
        'amount'     => $amount_in,
        'currency'   => $currency ?: $DEFAULTS['currency'],
        'brand'      => $brand,
        'desc'       => $desc ?: $DEFAULTS['desc'],
        'command_id' => $command_id ?: $DEFAULTS['command_id'],
        'party_b'    => $party_b ?: $DEFAULTS['party_b'],
      ],
      'debug'    => [],
      'response' => [],
    ]);
    exit;
  }
} else {
  $order_id_in = $DEFAULTS['order_id'];
  $amount      = $DEFAULTS['amount'];
  $amount_in   = $amount;
  $currency    = $DEFAULTS['currency'];
  $brand       = $DEFAULTS['brand'];
  $desc        = $DEFAULTS['desc'];
  $command_id  = $DEFAULTS['command_id'];
  $party_b     = $DEFAULTS['party_b'];
}

/* ===================== Send CREDIT2VIRTUAL ===================== */
$debug = [];
$responseBlocks = ['bodyRaw' => '', 'json' => null];

if ($submitted) {
  $hash_src_dbg = '';
  $hash = build_credit2virtual_hash($order_id_in, $amount, $currency, $SECRET, $hash_src_dbg);

  $form = [
    'action'            => 'CREDIT2VIRTUAL',
    'client_key'        => $CLIENT_KEY,
    'brand'             => $brand,

    'order_id'          => $order_id_in,
    'order_amount'      => $amount,
    'order_currency'    => $currency,
    'order_description' => $desc,

    // Docs-required:
    'parameters[CommandID]' => $command_id,
    'parameters[PartyB]'    => $party_b,

    'hash' => $hash,
  ];

  if ($CHANNEL_ID !== '') {
    $form['channel_id'] = $CHANNEL_ID;
  }

  $debug = [
    'endpoint'    => $PAYMENT_URL,
    'client_key'  => $CLIENT_KEY,
    'form'        => $form,
    'hash_src'    => $hash_src_dbg,
    'hash'        => $hash,
  ];

  $ch = curl_init($PAYMENT_URL);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $form,
    CURLOPT_TIMEOUT        => 60,
  ]);

  $start = microtime(true);
  $raw   = curl_exec($ch);
  $info  = curl_getinfo($ch);
  $err   = curl_errno($ch) ? curl_error($ch) : '';
  curl_close($ch);

  $dur = number_format(microtime(true) - $start, 3, '.', '');

  $debug['duration_sec'] = $dur;
  $debug['http_code']    = (int)($info['http_code'] ?? 0);
  if ($err) $debug['curl_error'] = $err;

  $responseBlocks['bodyRaw'] = (string)$raw;
  $json = json_decode($responseBlocks['bodyRaw'], true);
  if (json_last_error() === JSON_ERROR_NONE) {
    $responseBlocks['json'] = $json;
  }
}

/* ===================== Render ===================== */
render_page([
  'errors'   => [],
  'prefill'  => [
    'order_id'   => $order_id_in,
    'amount'     => isset($amount_in) ? $amount_in : $amount,
    'currency'   => $currency,
    'brand'      => $brand,
    'desc'       => $desc,
    'command_id' => $command_id,
    'party_b'    => $party_b,
  ],
  'debug'    => $debug,
  'response' => $responseBlocks,
]);

/* ---------------------- View ---------------------- */
function render_page($ctx){
  global $STATUS_HELPER_URL;

  $errors = $ctx['errors'] ?? [];
  $prefill= $ctx['prefill'] ?? [
    'order_id'=>'','amount'=>'','currency'=>'KES','brand'=>'mpesa','desc'=>'Product',
    'command_id'=>'SalaryPayment','party_b'=>'254700000000'
  ];
  $debug  = $ctx['debug'] ?? [];
  $resp   = $ctx['response'] ?? [];

  $self = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
  ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>CREDIT2VIRTUAL — MPESA (brand=mpesa)</title>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--err:#ff6b6b}
html,body{background:var(--bg);color:var(--text);margin:0;font:14px/1.45 ui-monospace,Menlo,Consolas,monospace}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.h{font-weight:700;margin:10px 0 6px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:var(--muted)}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#2b7cff;color:#fff;text-decoration:none;cursor:pointer;border:none}
.btn:hover{opacity:.9}
.error{color:var(--err);margin:6px 0}
input[type=text], select{padding:8px 10px;border-radius:8px;border:1px solid #2a2f3a;background:#11131a;color:#e6e6e6;width:520px;max-width:100%}
label{display:inline-block;min-width:200px}
.small{font-size:12px;color:var(--muted)}
</style>
</head>
<body>
<div class="wrap">

  <div class="panel">
    <div class="h">💸 Create CREDIT2VIRTUAL payout — MPESA</div>

    <form action="<?=h($self)?>" method="post">
      <?php if ($errors): foreach ($errors as $e): ?>
        <div class="error">❌ <?=h($e)?></div>
      <?php endforeach; endif; ?>

      <div style="margin:8px 0;">
        <label>order_id:</label>
        <input type="text" name="order_id" value="<?=h($prefill['order_id'])?>" placeholder="ORDER12345">
      </div>

      <div style="margin:8px 0;">
        <label>amount:</label>
        <input type="text" name="amount" value="<?=h($prefill['amount'])?>" placeholder="100.00">
        <div class="small">Will be normalized to XX.XX</div>
      </div>

      <div style="margin:8px 0;">
        <label>currency:</label>
        <input type="text" name="currency" value="<?=h($prefill['currency'])?>" placeholder="KES / UGX ...">
      </div>

      <div style="margin:8px 0;">
        <label>brand:</label>
        <input type="text" name="brand" value="<?=h($prefill['brand'])?>" placeholder="mpesa">
      </div>

      <div style="margin:8px 0;">
        <label>order_description:</label>
        <input type="text" name="desc" value="<?=h($prefill['desc'])?>" placeholder="Product">
      </div>

      <div style="margin:8px 0;">
        <label>parameters[CommandID]:</label>
        <select name="command_id">
          <?php
            $opts = ['SalaryPayment','BusinessPayment','PromotionPayment'];
            foreach ($opts as $o) {
              $sel = ($prefill['command_id'] === $o) ? 'selected' : '';
              echo '<option value="'.h($o).'" '.$sel.'>'.h($o).'</option>';
            }
          ?>
        </select>
      </div>

      <div style="margin:8px 0;">
        <label>parameters[PartyB]:</label>
        <input type="text" name="party_b" value="<?=h($prefill['party_b'])?>" placeholder="2547XXXXXXXX">
        <div class="small">Receiver MSISDN, digits only, no +</div>
      </div>

      <div style="margin-top:12px;">
        <button class="btn" type="submit">Send CREDIT2VIRTUAL</button>
      </div>
    </form>
  </div>

  <?php if (!empty($debug)): ?>
  <div class="panel">
    <div class="h">🟢 CREDIT2VIRTUAL sent</div>
    <div><span class="kv">Endpoint:</span> <?=h($debug['endpoint'] ?? '')?></div>
    <div><span class="kv">Client key:</span> <?=h($debug['client_key'] ?? '')?></div>
    <div>
      <span class="kv">HTTP:</span> <?=h($debug['http_code'] ?? '')?>
      <span class="kv" style="margin-left:12px;">Duration:</span> <?=h($debug['duration_sec'] ?? '')?>s
    </div>
    <?php if (!empty($debug['curl_error'])): ?>
      <div class="error">cURL: <?=h($debug['curl_error'])?></div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="h">🧮 CREDIT2VIRTUAL hash</div>
    <div class="kv">md5( strtoupper( strrev( order_id . amount . currency ) ) . SECRET )</div>
    <div class="kv">Source string (debug):</div>
    <pre><?=h($debug['hash_src'] ?? '')?></pre>
    <div class="kv">Hash:</div>
    <pre><?=h($debug['hash'] ?? '')?></pre>
  </div>

  <div class="panel">
    <div class="h">➡ Sent form-data</div>
    <pre><?=pretty($debug['form'] ?? [])?></pre>
  </div>

  <div class="panel">
    <div class="h">⬅ Response body</div>
    <pre><?=pretty($resp['bodyRaw'] ?? '')?></pre>

    <?php if (is_array($resp['json'] ?? null)): ?>
      <div class="h">Parsed</div>
      <pre><?=pretty($resp['json'])?></pre>

      <?php if (!empty($resp['json']['trans_id'])): ?>
        <div class="h" style="margin-top:16px;">🕒 Check transaction status (GET_TRANS_STATUS)</div>
        <a class="btn" target="_blank" href="<?=h($STATUS_HELPER_URL)?>?trans_id=<?=h($resp['json']['trans_id'])?>">
          Open status helper for this trans_id
        </a>
      <?php endif; ?>

    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
<?php
}
