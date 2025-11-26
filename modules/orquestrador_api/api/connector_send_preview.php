<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
$method = $_POST['method'] ?? 'GET';
$url = $_POST['url'] ?? '';
$headers = json_decode($_POST['headers_json'] ?? '{}', true) ?: [];
$params = json_decode($_POST['params_json'] ?? '{}', true) ?: [];
$body_mode = $_POST['body_mode'] ?? 'none';
$body_raw = $_POST['body_raw'] ?? '';
if (!$url) { echo json_encode(['ok'=>false,'error'=>'URL vazia']); exit; }
if (!empty($params)){
  $parsed = parse_url($url);
  $base = $parsed['scheme'].'://'.$parsed['host'].(isset($parsed['port'])?':'.$parsed['port']:'').($parsed['path'] ?? '');
  $current = []; if (!empty($parsed['query'])) parse_str($parsed['query'], $current);
  $query = http_build_query(array_merge($current, $params));
  $url = $base . ($query ? ('?'.$query) : '');
}
$ch = curl_init();
curl_setopt_array($ch, [ CURLOPT_URL=>$url, CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_HEADER=>true, CURLOPT_TIMEOUT=>30 ]);
$h=[]; foreach($headers as $k=>$v){ $h[]=$k.': '.$v; } if ($h) curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
if (in_array($method, ['POST','PUT','PATCH','DELETE'])){ if ($body_mode==='raw') curl_setopt($ch, CURLOPT_POSTFIELDS, $body_raw); }
$resp = curl_exec($ch);
if ($resp === false){ echo json_encode(['ok'=>false,'error'=>curl_error($ch)]); curl_close($ch); exit; }
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($resp, 0, $header_size);
$body = substr($resp, $header_size);
$info = curl_getinfo($ch);
curl_close($ch);
echo json_encode(['ok'=>true,'request'=>['method'=>$method,'url'=>$url,'headers'=>$headers,'params'=>$params,'mode'=>$body_mode],'response'=>['headers'=>$header,'body'=>$body],'info'=>$info], JSON_UNESCAPED_UNICODE);
