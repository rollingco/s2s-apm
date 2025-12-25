<?php
/**
 * S2S CREDIT2VIRTUAL — MPESA-EXPRESS payout by order_id → minimal logs
 *
 * Беремо “скелет” (UI + логи + render_page) з credit2virtual.php,
 * а під MPESA-EXPRESS беремо:
 *  - endpoint / креденції / brand / формулу hash — з mpesa_sale_s2s.php
 *
 * ENDPOINT:
 *  - https://api.leogcltd.com/post-va
 *
 * BRAND:
 *  - mpesa-express
 *
 * HASH (AfriMoney-style як у mpesa_sale_s2s.php):
 *  md5( strtoupper( strrev( identifier + order_id + amount + currency + SECRET ) ) )
 *
 * MSISDN:
 *  - відправляємо як parameters[msisdn] (як у credit2virtual.php)
 *  - додатково дублюємо msisdn та payer_phone (щоб уникнути mismatch на стороні процесингу)
 */

header('Content-Type: text/html; charset=utf-8');

/* ===================== CONFIG ===================== */
$PAYMENT_URL = 'https://api.leogcltd.com/post-va';

/* Креденції беремо з mpesa_sale_s2s.php */
$CLIENT_KEY = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET     = '554999c284e9f29cf95f090d9a8f3171';

/**
 * channel_id — якщо потрібно, вкажи тут.
 * Якщо не потрібно — залиш порожнім рядком.
 */
$CHANNEL_ID  = '';

/**
 * Хелпер для перевірки статусу за trans_id (GET_TRANS_STATUS).
 * Відкривається у новій вкладці з параметром ?trans_id=...
 */
$STATUS_HELPER_URL = 'status_credit2virtual.php';

/* Prefill from GET (автономний режим) */
$DEFAULTS = [
  'order_id' => isset($_GET['order_id']) ? (string)$_GET['order_id'] : ('mpesa-c2v-' . time()),
  'amount'   => isset($_GET['amount'])   ? (string)$_GET['amount']   : '10.00',
  'currency' => isset($_GET['currency']) ? (string)$_GET['currency'] : 'KES',
  'brand'    => isset($_GET['brand'])    ? (string)$_GET['brand']    : 'mpesa-express',
  'identifier'=> isset($_GET['identifier']) ? (string)$_GET['identifier'] : '111',
  'desc'     => isset($_GET['desc'])     ? (string)$_GET['desc']     : 'MPESA-EXPRESS payout test',
  'phone'    => isset($_GET['phone'])    ? (string)$_GET['phone']    : '254700000000',
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

/** Нормалізація суми до формату XX.XX */
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

/**
 * MPESA (AfriMoney-style) hash:
 * md5( strtoupper( strrev( identifier + order_id + amount + currency + SECRET ) ) )
 */
function build_mpesa_hash($identifier, $order_id, $amount, $currency, $secret, &$srcOut = null){
  $src = $identifier . $order_id . $amount . $currency . $secret;
  if ($srcOut !== null) $srcOut = $src; // показуємо "до reverse"
  return md5(strtoupper(strrev($src)));
}

/* ===================== Read form ===================== */
$method    = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$submitted = ($method === 'POST');

if ($submitted) {
  $order_id_in = trim((string)($_POST['order_id'] ?? ''));
  $amount_in   = (string)($_POST['amount'] ?? '');
  $currency    = strtoupper(trim((string)($_POST['currency'] ?? '')));
  $brand       = trim((string)($_POST['brand'] ?? ''));
  $identifier  = trim((string)($_POST['identifier'] ?? ''));
  $desc        = trim((string)($_POST['desc'] ?? ''));
  $phone       = preg_replace('/\s+/', '', (string)($_POST['phone'] ?? ''));
  $phone       = ltrim($phone, '+');
  $email       = trim((string)($_POST['email'] ?? ''));

  $amount = trim($amount_in) === '' ? '' : normalize_amount_2dec($amount_in);

  $errors = [];
  if ($order_id_in === '') $errors[] = 'order_id is required.';
  if ($identifier === '')  $errors[] = 'identifier is required.';
  if ($amount === '' || !preg_match('/^\d+\.\d+$/', $amount)) {
    $errors[] = 'Amount wrong format. Use e.g. 1.00, 10.50, 100.00';
  }
  if ($currency === '') $errors[] = 'Currency is required.';
  if ($phone === '')    $errors[] = 'Phone (MSISDN) is required.';
  if ($brand === '')    $brand = 'mpesa-express';

  // Email не обовʼязковий, але якщо ввели — перевіряємо формат
  if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email format looks wrong.';
  }

  if ($errors) {
    render_page([
      'errors'  => $errors,
      'prefill' => [
        'order_id'   => $order_id_in,
        'amount'     => $amount_in,
        'currency'   => $currency ?: $DEFAULTS['currency'],
        'brand'      => $brand,
        'identifier' => $identifier ?: $DEFAULTS['identifier'],
        'desc'       => $desc,
        'phone'      => $phone,
        'email'      => $email,
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
  $identifier  = $DEFAULTS['identifier'];
  $desc        = $DEFAULTS['desc'];
  $phone       = $DEFAULTS['phone'];
  $email       = $DEFAULTS['email'];
}

/*  ===================== Send CREDIT2VIRTUAL ===================== */
$debug = [];
$responseBlocks = ['bodyRaw' => '', 'json' => null];

if ($submitted) {
  $hash_src_dbg = '';
  $hash = build_mpesa_hash($identifier, $order_id_in, $amount, $currency, $SECRET, $hash_src_dbg);

  $form = [
    'action'            => 'CREDIT2VIRTUAL',
    'client_key'        => $CLIENT_KEY,
    'brand'             => $brand,

    'order_id'          => $order_id_in,
    'order_amount'      => $amount,
    'order_currency'    => $currency,
    'order_description' => $desc,

    // MPESA-стиль: identifier використовується у hash
    'identifier'        => $identifier,

    // MSISDN як у credit2virtual.php + дублювання як у SALE-скелеті
    'parameters[msisdn]' => $phone,
    'msisdn'             => $phone,
    'payer_phone'        => $phone,
  ];

  // channel_id додаємо тільки якщо заповнений у конфігу
  if ($CHANNEL_ID !== '') {
    $form['channel_id'] = $CHANNEL_ID;
  }

  // Email додаємо тільки якщо щось ввели
  if ($email !== '') {
    $form['parameters[email]'] = $email;
  }

  // Hash обовʼязково вкінці
  $form['hash'] = $hash;

  $debug = [
    'endpoint'    => $PAYMENT_URL,
    'client_key'  => $CLIENT_KEY,
    'form'        => $form,
    'hash_src'    => $hash_src_dbg, // це src ДО reverse (як у mpesa_sale_s2s.php)
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
    'identifier' => $identifier,
    'desc'       => $desc,
    'phone'      => $phone,
    'email'      => $email,
  ],
  'debug'    => $debug,
  'response' => $responseBlocks,
]);

/* ---------------------- View ---------------------- */
function render_page($ctx){
  global $STATUS_HELPER_URL;

  $errors = $ctx['errors'] ?? [];
  $prefill= $ctx['prefill'] ?? ['order_id'=>'','amount'=>'','currency'=>'KES','brand'=>'mpesa-express','identifier'=>'111','desc'=>'','phone'=>'','email'=>''];
  $debug  = $ctx['debug'] ?? [];
  $resp   = $ctx['response'] ?? [];

  $self = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
  ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>CREDIT2VIRTUAL — MPESA-EXPRESS payout</title>
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
input[type=text]{padding:8px 10px;border-radius:8px;border:1px solid #2a2f3a;background:#11131a;color:#e6e6e6;width:520px;max-width:100%}
label{display:inline-block;min-width:180px}
.small{font-size:12px;color:var(--muted)}
</style>
</head>
<body>
<div class="wrap">

  <div class="panel">
    <div class="h">💸 Create CREDIT2VIRTUAL payout (MPESA-EXPRESS)</div>
    <form action="<?=h($self)?>" method="post">
      <?php if ($errors): foreach ($errors as $e): ?>
        <div class="error">❌ <?=h($e)?></div>
      <?php endforeach; endif; ?>

      <div style="margin:8px 0;">
        <label>order_id:</label>
        <input type="text" name="order_id" value="<?=h($prefill['order_id'])?>" placeholder="e.g. mpesa-c2v-123456">
      </div>

      <div style="margin:8px 0;">
        <label>amount:</label>
        <input type="text" name="amount" value="<?=h($prefill['amount'])?>" placeholder="1.00, 10.50, 100.00">
        <div class="small">Will be normalized to XX.XX (1 → 1.00, 1.5 → 1.50).</div>
      </div>

      <div style="margin:8px 0;">
        <label>currency:</label>
        <input type="text" name="currency" value="<?=h($prefill['currency'])?>" placeholder="KES">
      </div>

      <div style="margin:8px 0;">
        <label>brand:</label>
        <input type="text" name="brand" value="<?=h($prefill['brand'])?>" placeholder="mpesa-express">
        <div class="small">MPESA-EXPRESS brand (default: mpesa-express).</div>
      </div>

      <div style="margin:8px 0;">
        <label>identifier:</label>
        <input type="text" name="identifier" value="<?=h($prefill['identifier'])?>" placeholder="111">
        <div class="small">Used in hash formula (same as mpesa_sale_s2s.php).</div>
      </div>

      <div style="margin:8px 0;">
        <label>description:</label>
        <input type="text" name="desc" value="<?=h($prefill['desc'])?>" placeholder="MPESA-EXPRESS payout test">
      </div>

      <div style="margin:8px 0;">
        <label>phone (MSISDN):</label>
        <input type="text" name="phone" value="<?=h($prefill['phone'])?>" placeholder="254700000000">
        <div class="small">Sent as parameters[msisdn] + msisdn + payer_phone.</div>
      </div>

      <div style="margin:8px 0;">
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
    <div class="h">🧮 MPESA hash</div>
    <div class="kv">md5( strtoupper( strrev( identifier + order_id + amount + currency + SECRET ) ) )</div>
    <div class="kv">Source string (debug, before reverse):</div>
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

        // Якщо статус REDIRECT — показуємо або форму POST, або просто URL
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
          <div class="small">Click to send POST request to the redirect URL with required parameters.</div>
          <form action="<?=h($redirectUrl)?>" method="post" target="_blank" style="margin-top:10px;">
            <?php foreach ($redirectParams as $name => $value): ?>
              <input type="hidden" name="<?=h($name)?>" value="<?=h($value)?>">
            <?php endforeach; ?>
            <button type="submit" class="btn">Open payout page (POST)</button>
          </form>
          <?php
        } elseif (!empty($parsed['status']) && $parsed['status'] === 'REDIRECT') {
          echo '<div class="h">🔗 Redirect URL</div><pre>' . h($parsed['redirect_url'] ?? '') . "</pre>";
        }

        // Кнопка для GET_TRANS_STATUS, якщо у відповіді є trans_id
        if (!empty($parsed['trans_id'])) {
          ?>
          <div class="h" style="margin-top:16px;">🕒 Check transaction status (GET_TRANS_STATUS)</div>
          <a class="btn" target="_blank" href="<?=h($STATUS_HELPER_URL)?>?trans_id=<?=h($parsed['trans_id'])?>">
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
