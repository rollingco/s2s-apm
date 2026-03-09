<?php
/**
 * Tokenization + Payment by card_token (single file) — HASH FIXED
 *
 * Step 1: SALE with req_token=Y (tokenization)
 *   hash = md5(strtoupper(strrev(email) . PASSWORD . strrev(first6+last4)))
 *
 * Step 2: SALE with card_token (payment)
 *   hash = md5(strtoupper(strrev(email) . PASSWORD . strrev(card_token)))
 */

// ========================= CONFIG =========================
$endpoint    = 'https://api.leogcltd.com/post';

$merchantKey = '8af2433a-1269-11f1-9540-2a588e8348b1';
$secret      = '71d7e2e8a5bca26c7cc63776fc36078d'; // PASSWORD from docs

// Card (TEST ONLY)
$cardNumber  = '4111111111111111';
$expMonth    = '01';
$expYear     = '2038';
$cvv         = '123';

// Payer
$payerEmail  = 'talasef354@mekuron.com';

// 3DS
$termUrl3ds  = 'https://afripie.com/payment-methods/3ds';
$termTarget  = '_self';

// ========================= HELPERS =========================
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function pretty_json($data): string {
  if (is_string($data)) {
    $decoded = json_decode($data, true);
    if (is_array($decoded)) {
      return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    return $data;
  }
  return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function mask_pan(string $pan): string {
  $len = strlen($pan);
  if ($len < 10) return str_repeat('*', $len);
  return substr($pan, 0, 6) . str_repeat('*', $len - 10) . substr($pan, -4);
}

function mask_sensitive(array $arr): array {
  $out = $arr;

  if (isset($out['card_number'])) $out['card_number'] = mask_pan((string)$out['card_number']);
  if (isset($out['card_cvv2']))   $out['card_cvv2']   = '***';

  if (isset($out['hash']) && is_string($out['hash']) && strlen($out['hash']) > 16) {
    $out['hash'] = substr($out['hash'], 0, 10) . '…' . substr($out['hash'], -10);
  }

  if (isset($out['card_token']) && is_string($out['card_token']) && strlen($out['card_token']) > 16) {
    $out['card_token'] = substr($out['card_token'], 0, 8) . '…' . substr($out['card_token'], -6);
  }

  return $out;
}

function build_3ds_form(string $url, array $params): string {
  $inputs = '';
  foreach ($params as $k => $v) {
    $inputs .= '<input type="hidden" name="'.h($k).'" value="'.h($v).'">';
  }

  return '
    <form method="post" action="'.h($url).'" class="form3ds">
      '.$inputs.'
      <button type="submit" class="btn">Continue 3DS (POST)</button>
      <div class="hint">This will POST redirect_params to ACS (PaReq + TermUrl, etc.).</div>
    </form>
  ';
}

function do_post_form(string $endpoint, array $fields): array {
  $formBody = http_build_query($fields);

  $outHeaders = [
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json',
    'User-Agent: Token-Then-Pay-Emulator/1.1',
    'Content-Length: ' . strlen($formBody),
  ];

  $start = microtime(true);

  $ch = curl_init($endpoint);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $formBody,
    CURLOPT_HTTPHEADER     => $outHeaders,
    CURLOPT_TIMEOUT        => 60,
  ]);

  $rawResponse = curl_exec($ch);
  $curlErr     = curl_error($ch);
  $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  $ms = (int)round((microtime(true) - $start) * 1000);

  $responseArr = null;
  if (!$curlErr && is_string($rawResponse)) {
    $decoded = json_decode($rawResponse, true);
    if (is_array($decoded)) $responseArr = $decoded;
  }

  $do3ds = false;
  $redirectUrl = '';
  $redirectMethod = '';
  $redirectParams = [];

  if (is_array($responseArr)) {
    $redirectUrl    = (string)($responseArr['redirect_url'] ?? '');
    $redirectMethod = (string)($responseArr['redirect_method'] ?? '');
    $redirectParams = (array) ($responseArr['redirect_params'] ?? []);

    $do3ds = (
      (($responseArr['result'] ?? '') === 'REDIRECT') &&
      (strtoupper($redirectMethod) === 'POST') &&
      $redirectUrl !== '' &&
      !empty($redirectParams)
    );
  }

  return [
    'http_code' => $httpCode,
    'ms' => $ms,
    'curl_error' => $curlErr,
    'headers_out' => $outHeaders,
    'body_out' => $formBody,
    'raw' => $rawResponse,
    'json' => $responseArr,
    'do3ds' => $do3ds,
    'redirect_url' => $redirectUrl,
    'redirect_method' => $redirectMethod,
    'redirect_params' => $redirectParams,
  ];
}

// ========================= STEP 1 =========================
$first6 = substr($cardNumber, 0, 6);
$last4  = substr($cardNumber, -4);

$hashSource1 = strrev($payerEmail) . $secret . strrev($first6 . $last4);
$hashStep1   = md5(strtoupper($hashSource1));

$orderIdToken = 'o5aIiu6fCdFogLzdyfbxN';

$reqStep1 = [
  'action'            => 'SALE',
  'client_key'        => $merchantKey,
  'order_id'          => $orderIdToken,
  'order_amount'      => '0.00',
  'order_currency'    => 'USD',
  'order_description' => 'Afripie Payment Method Addition',

  'card_number'       => $cardNumber,
  'card_exp_month'    => $expMonth,
  'card_exp_year'     => $expYear,
  'card_cvv2'         => $cvv,

  'payer_first_name'  => 'Test',
  'payer_last_name'   => 'Alasef',
  'payer_address'     => '3 Third Ave',
  'payer_country'     => 'US',
  'payer_city'        => 'Melrose',
  'payer_zip'         => '01533',
  'payer_email'       => $payerEmail,
  'payer_phone'       => '6035484534',
  'payer_ip'          => '163.252.215.185',

  'term_url_3ds'      => $termUrl3ds,
  'term_url_target'   => $termTarget,

  'auth'              => 'Y',
  'req_token'         => 'Y',

  'hash'              => $hashStep1,
];

$resStep1 = do_post_form($endpoint, $reqStep1);

$cardToken = '';
if (is_array($resStep1['json'])) {
  $cardToken = (string)($resStep1['json']['card_token'] ?? '');
}
?>