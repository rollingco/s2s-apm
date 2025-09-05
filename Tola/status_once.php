<?php
header('Content-Type: text/html; charset=utf-8');

$PAYMENT_URL='https://api.leogcltd.com/post-va';
$API_USER='leogc'; $API_PASS='ORuIO57N6KJyeJ';
$CLIENT_KEY='a9375190-26f2-11f0-be42-022c42254708';
$PASSWORD='554999c284e9f29cf95f090d9a8f3171';
$ACTION='GET_TRANS_STATUS';

$trans_id=trim($_GET['trans_id']??'');
if($trans_id===''){ http_response_code(400); echo 'Pass ?trans_id=...'; exit; }

$hash_src=strtoupper(strrev($trans_id)).$PASSWORD;
$hash=md5($hash_src);

$payload=['action'=>$ACTION,'client_key'=>$CLIENT_KEY,'trans_id'=>$trans_id,'hash'=>$hash];
$body=http_build_query($payload);

$ch=curl_init($PAYMENT_URL);
curl_setopt_array($ch,[
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_POST=>true,
  CURLOPT_POSTFIELDS=>$body,
  CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded','Content-Length: '.strlen($body)],
  CURLOPT_USERPWD=>$API_USER.':'.$API_PASS,
  CURLOPT_HEADER=>true,
  CURLOPT_TIMEOUT=>30,
]);
$start=microtime(true);
$raw=curl_exec($ch);
$info=curl_getinfo($ch);
$err=curl_errno($ch)?curl_error($ch):'';
curl_close($ch);
$dur=number_format(microtime(true)-$start,3,'.','');

$respHeaders=$respBody='';
if($raw!==false && isset($info['header_size'])){
  $respHeaders=substr($raw,0,$info['header_size']);
  $respBody=substr($raw,$info['header_size']);
}else{$respBody=(string)$raw;}
$parsed=json_decode($respBody,true);

function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function pretty($v){ if(is_string($v)){ $d=json_decode($v,true); if(json_last_error()===JSON_ERROR_NONE)$v=$d; else return h($v);} return h(json_encode($v,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8">
<title>GET_TRANS_STATUS</title>
<style>body{background:#111;color:#eee;font:14px monospace}pre{background:#222;padding:10px;border-radius:8px}</style>
</head><body>
<h2>GET_TRANS_STATUS</h2>
<p>Duration: <?=$dur?>s | HTTP: <?= (int)($info['http_code']??0)?></p>
<?php if($err): ?><p>cURL error: <?=h($err)?></p><?php endif; ?>

<h3>Hash calculation</h3>
<p>Source = strtoupper(strrev(trans_id)) + PASSWORD:<br><pre><?=h($hash_src)?></pre></p>
<p>Hash (md5): <b><?=h($hash)?></b></p>

<h3>Payload</h3>
<pre><?=pretty($payload)?></pre>

<h3>Response headers</h3>
<pre><?=h($respHeaders)?></pre>

<h3>Response body</h3>
<pre><?=pretty($respBody)?></pre>
<?php if(is_array($parsed)): ?>
<h4>Parsed JSON</h4>
<pre><?=pretty($parsed)?></pre>
<?php endif; ?>
</body></html>
