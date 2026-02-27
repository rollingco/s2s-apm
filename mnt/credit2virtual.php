<?php
/**
 * S2S CREDIT2VIRTUAL — MTN MoMo payout by order_id → minimal logs
 *
 * - endpoint: https://api.leogcltd.com/post
 * - brand: mnt-momo
 * - signature: md5( strtoupper( strrev( order_id . amount . currency ) ) . SECRET )
 * - amount normalized to 2 decimals (1 -> 1.00, 1.5 -> 1.50)
 * - phone (MSISDN) sent as parameters[msisdn]
 * - optional: parameters[email]
 */

header('Content-Type: text/html; charset=utf-8');

/* ===================== CONFIG ===================== */
$PAYMENT_URL = 'https://api.leogcltd.com/post';

$CLIENT_KEY  = 'bd059f56-e01a-11f0-835c-42fb5ea66c1c';
$SECRET      = '24907c0221dd485b8cd6ae936a9c3c01';

/**
 * channel_id — якщо його потрібно передавати, вкажи тут значення.
 * Якщо не потрібно — залиш порожнім рядком.
 */
$CHANNEL_ID  = ''; // наприклад: '12345' або UUID, якщо дасть Тола

/**
 * Хелпер для перевірки статусу за trans_id (GET_TRANS_STATUS).
 * Він буде відкриватися у новій вкладці з параметром ?trans_id=...
 */
$STATUS_HELPER_URL = 'status_credit2virtual.php';

/* Prefill from GET (автономний режим) */
$DEFAULTS = [
  'order_id' => isset($_GET['order_id']) ? (string)$_GET['order_id'] : ('mtnmomo-' . time()),
  'amount'   => isset($_GET['amount'])   ? (string)$_GET['amount']   : '10.00',
  'currency' => isset($_GET['currency']) ? (string)$_GET['currency'] : 'EUR',
  'brand'    => isset($_GET['brand'])    ? (string)$_GET['brand']    : 'mnt-momo',
  'desc'     => isset($_GET['desc'])     ? (string)$_GET['desc']     : 'MTN MoMo payout test',
  'phone'    => isset($_GET['phone'])    ? (string)$_GET['phone']    : '23280855053',
  'email'    => isset($_GET['email'])    ? (string)$_GET['email']    : 'success@gmail.com',
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

/**
 * CREDIT2VIRTUAL hash:
 * md5( strtoupper( strrev( order_id . amount . currency ) ) . SECRET )
 */
function build_credit2virtual_hash($order_id, $amount, $currency, $secret, &$srcOut = null){
  $inner = $order_id . $amount . $currency;
  $src   = strtoupper(strrev($inner)) . $secret;
  if ($srcOut !== null) $srcOut = $src;
  return md5($src);
}

/** Amount normalization to XX.XX */
function normalize_amount_2dec(string $raw): string {
  $s = preg_replace('/[^0-9.]/', '', $raw);
  if ($s === '') return '';

  if (substr_count($s, '.') > 1) {
    $parts = explode('.', $s);
    $s = array_shift($parts) . '.' . implode('', $parts);
  }

  if (strpos($s, '.') === false) {
    return $s . '.00';
  }
  list($int, $dec) = array_pad(explode('.', $s, 2), 2, '');
  if ($dec === '')        return $int . '.00';
  if (strlen($dec) === 1) return $int . '.' . $dec . '0';
  return $int . '.' . $dec;
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
  $phone       = trim((string)($_POST['phone'] ?? ''));
  $email       = trim((string)($_POST['email'] ?? ''));

  $amount = trim($amount_in) === '' ? '' : normalize_amount_2dec($amount_in);

  $errors = [];
  if ($order_id_in === '') $errors[] = 'order_id is required.';
  if ($amount === '' || !preg_match('/^\d+\.\d+$/', $amount)) {
    $errors[] = 'Amount wrong format. Use e.g. 1.00, 10.50, 100.00';
  }
  if ($currency === '') $errors[] = 'Currency is required.';
  if ($phone === '') $errors[] = 'Phone (MSISDN) is required.';
  if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email format looks wrong.';
  }

  if ($brand === '') $brand = 'mnt-momo';

  if ($errors) {
    render_page([
      'errors'  => $errors,
      'prefill' => [
        'order_id' => $order_id_in,
        'amount'   => $amount_in,
        'currency' => $currency ?: $DEFAULTS['currency'],
        'brand'    => $brand,
        'desc'     => $desc,
        'phone'    => $phone,
        'email'    => $email,
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
  $phone       = $DEFAULTS['phone'];
  $email       = $DEFAULTS['email'];
}

/*  ===================== Send CREDIT2VIRTUAL ===================== */
$debug = [];
$responseBlocks = ['bodyRaw' => '', 'json' => null];

if ($submitted) {
  $hash_src_dbg = '';
  $hash = build_credit2virtual_hash($order_id_in, $amount, $currency, $SECRET, $hash_src_dbg);

  $form = [
    'action'            => 'CREDIT2VIRTUAL',
    'client_key'        => $CLIENT_KEY,
    'order_id'          => $order_id_in,
    'order_amount'      => $amount,
    'order_currency'    => $currency,
    'order_description' => $desc,
    'brand'             => $brand,
    'parameters[msisdn]' => $phone,
  ];

  if ($CHANNEL_ID !== '') {
    $form['channel_id'] = $CHANNEL_ID;
  }

  if ($email !== '') {
    $form['parameters[email]'] = $email;
  }

  $form['hash'] = $hash;

  $debug = [
    'endpoint'   => $PAYMENT_URL,
    'client_key' => $CLIENT_KEY,
    'form'       => $form,
    'hash_src'   => $hash_src_dbg,
    'hash'       => $hash,
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
    'order_id' => $order_id_in,
    'amount'   => isset($amount_in)?$amount_in:$amount,
    'currency' => $currency,
    'brand'    => $brand,
    'desc'     => $desc,
    'phone'    => $phone,
    'email'    => $email,
  ],
  'debug'    => $debug,
  'response' => $responseBlocks,
]);

/* ---------------------- View ---------------------- */
function render_page($ctx){
  global $STATUS_HELPER_URL;

  $errors = $ctx['errors'] ?? [];
  $prefill= $ctx['prefill'] ?? ['order_id'=>'','amount'=>'','currency'=>'EUR','brand'=>'mnt-momo','desc'=>'','phone'=>'','email'=>''];
  $debug  = $ctx['debug'] ?? [];
  $resp   = $ctx['response'] ?? [];

  $self = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
  ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>CREDIT2VIRTUAL — MTN MoMo payout</title>
<style>
:root{--bg:#0f1115;--panel:#171923;--b:#2a2f3a;--text:#e6e6e6;--muted:#9aa4af;--err:#ff6b6b}
html,body{background:var(--bg);color:var(--text);margin:0;font:14px/1.45 ui-monospace,Menlo,Consolas,monospace}
.wrap{padding:22px;max-width:1100px;margin:0 auto}
.h{font-weight:700;margin:10px 0 6px}
.panel{background:var(--panel);border:1px solid var(--b);border-radius:12px;padding:14px 16px;margin:14px 0}
.kv{color:var(--muted)}
pre{background:#11131a;padding:12px;border-radius:10px;border:1px solid #232635;white-space:pre-wrap}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#2b7cff;color:#fff;text-decoration:none;cursor:pointer}
.error{color:var(--err);margin:6px 0}
input[type=text]{padding:8px 10px;border-radius:8px;border:1px solid #2a2f3a;background:#11131a;color:#e6e6e6}
label{display:inline-block;min-width:150px}
.small{font-size:12px;color:var(--muted)}
</style>
</head>
<body>
<div class="wrap">

  <div class="panel">
    <div class="h">💸 Create CREDIT2VIRTUAL payout (MTN MoMo)</div>
    <form action="<?=h($self)?>" method="post">
      <?php if ($errors): foreach ($errors as $e): ?>
        <div class="error">❌ <?=h($e)?></div>
      <?php endforeach; endif; ?>

      <div style="margin:8px 0;">
        <label>order_id:</label>
        <input type="text" name="order_id" value="<?=h($prefill['order_id'])?>" placeholder="e.g. mtnmomo-123456">
      </div>

      <div style="margin:8px 0%;">
        <label>amount:</label>
        <input type="text" name="amount" value="<?=h($prefill['amount'])?>" placeholder="1.00, 10.50, 100.00">
        <div class="small">Will be normalized to XX.XX (1 → 1.00, 1.5 → 1.50).</div>
      </div>

      <div style="margin:8px 0%;">
        <label>currency:</label>
        <input type="text" name="currency" value="<?=h($prefill['currency'])?>" placeholder="EUR">
      </div>

      <div style="margin:8px 0%;">
        <label>brand:</label>
        <input type="text" name="brand" value="<?=h($prefill['brand'])?>" placeholder="mnt-momo">
        <div class="small">MoMo payout brand (default: mnt-momo).</div>
      </div>

      <div style="margin:8px 0%;">
        <label>description:</label>
        <input type="text" name="desc" value="<?=h($prefill['desc'])?>" placeholder="MTN MoMo payout test">
      </div>

      <div style="margin:8px 0%;">
        <label>phone (MSISDN):</label>
        <input type="text" name="phone" value="<?=h($prefill['phone'])?>" placeholder="+2327XXXXXXX">
        <div class="small">Will be sent as parameters[msisdn] (required).</div>
      </div>

      <div style="margin:8px 0%;">
        <label>email (optional):</label>
        <input type="text" name="email" value="<?=h($prefill['email'])?>" placeholder="success@gmail.com">
        <div class="small">If filled, will be sent as parameters[email].</div>
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
      <?php
        $parsed = $resp['json'];

        if (
          !empty($parsed['status']) &&
          $parsed['status'] === 'REDIRECT' &&
          !empty($parsed['redirect_method']) &&
          strtoupper($parsed['redirect_method']) === 'POST' &&
          !empty($parsed['redirect_url'])
        ) {
          $redirectUrl    = $parsed['redirect_url'];
          $redirectParams = isset($parsed['redirect_params']) && is_array($parsed['redirect_params'])
            ? $parsed['redirect_params']
            : [];
          ?>
          <div class="h">🔗 Redirect form (POST)</div>
          <div class="small">
            Click the button below to send a POST request to the payout page with the required parameters.
          </div>
          <form action="<?=h($redirectUrl)?>" method="post" target="_blank" style="margin-top:10px;">
            <?php foreach ($redirectParams as $name => $value): ?>
              <input type="hidden" name="<?=h($name)?>" value="<?=h($value)?>">
            <?php endforeach; ?>
            <button type="submit" class="btn">Open payout page (POST)</button>
          </form>
          <?php
        } elseif (!empty($parsed['status']) && $parsed['status'] === 'REDIRECT') {
          echo '<div class="h">🔗 Redirect URL</div><pre>' . h($parsed['redirect_url'] ?? '') . "</pre>";
          echo '<div class="small">Open this URL in browser — recipient will enter MSISDN there.</div>';
        }

        if (!empty($parsed['trans_id'])) {
          ?>
          <div class="h" style="margin-top:16px;">🕒 Check transaction status (GET_TRANS_STATUS)</div>
          <a
            class="btn"
            target="_blank"
            href="<?=h($STATUS_HELPER_URL)?>?trans_id=<?=h($parsed['trans_id'])?>">
            Open status helper for this trans_id
          </a>
          <?php
        }
      ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
<?php
}