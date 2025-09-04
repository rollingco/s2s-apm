<?php
/**
 * poll_status_cli.php — long polling via GET_TRANS_STATUS (CLI only)
 * Usage:
 *   php poll_status_cli.php --trans_id=6c8656ce-87d2-11f0-ae91-7aa0fce6d172 [--hash=v2]
 */

if (php_sapi_name() !== 'cli') { fwrite(STDERR,"Run from CLI.\n"); exit(1); }

/* ==== CONFIG ==== */
$PAYMENT_URL = 'https://api.leogcltd.com/post-va';
$API_USER    = 'leogc';
$API_PASS    = 'ORuIO57N6KJyeJ';

$CLIENT_KEY  = 'a9375190-26f2-11f0-be42-022c42254708';
$SECRET      = '554999c284e9f29cf95f090d9a8f3171';

$ACTION      = 'GET_TRANS_STATUS';
$intervalSec = 180;
$maxChecks   = 20;

/* ==== Args ==== */
$trans_id = null; $hashMode = 'v1';
foreach ($argv as $a){
  if (preg_match('/^--trans_id=(.+)$/',$a,$m)) $trans_id = $m[1];
  if (preg_match('/^--hash=(v1|v2)$/',$a,$m)) $hashMode = $m[1];
}
if (!$trans_id){ fwrite(STDERR,"Pass --trans_id=...\n"); exit(1); }

/* ==== Hash fns ==== */
function build_status_hash_v1($trans_id,$client_key,$secret){
  return md5(strtoupper(strrev($trans_id.$client_key.$secret)));
}
function build_status_hash_v2($trans_id,$secret){
  return md5(strtoupper(strrev($trans_id.$secret)));
}
$hash = ($hashMode==='v2')
  ? build_status_hash_v2($trans_id,$SECRET)
  : build_status_hash_v1($trans_id,$CLIENT_KEY,$SECRET);

$payload = [
  'action'     => $ACTION,
  'client_key' => $CLIENT_KEY,
  'trans_id'   => $trans_id,
  'hash'       => $hash,
];

function ask($url,$user,$pass,$payload){
  $ch = curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>http_build_query($payload),
    CURLOPT_USERPWD=>$user.':'.$pass,
    CURLOPT_TIMEOUT=>30,
  ]);
  $raw  = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_errno($ch)?curl_error($ch):'';
  curl_close($ch);
  return [$http,$raw,$err,json_decode($raw,true)];
}

$finalStatuses=['SETTLED','DECLINED','REFUND','VOID'];

for ($i=1;$i<=$maxChecks;$i++){
  $ts=date('Y-m-d H:i:s');
  echo "[$ts] Attempt $i\n";
  [$code,$raw,$err,$data]=ask($PAYMENT_URL,$API_USER,$API_PASS,$payload);
  echo "  HTTP: $code\n";
  if ($err) echo "  cURL: $err\n";
  echo "  Raw: $raw\n\n";

  if (is_array($data)){
    $st=strtoupper((string)($data['status']??''));
    if (in_array($st,$finalStatuses,true)){
      echo "Final:\n".json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
      exit(0);
    }
  }

  if ($i<$maxChecks){ echo "Sleeping {$intervalSec}s...\n\n"; sleep($intervalSec); }
}

echo "No final status within {$maxChecks} checks.\n";
