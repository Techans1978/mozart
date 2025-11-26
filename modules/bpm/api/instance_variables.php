<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3) . '/config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
proteger_pagina();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mask = (int)($_GET['mask_secrets'] ?? 1);

$out = ['ok'=>false,'vars'=>[]];

$exists = function($t) use ($conn){
  $t = $conn->real_escape_string($t);
  $rs = $conn->query("SHOW TABLES LIKE '$t'");
  return $rs && $rs->num_rows > 0;
};

if ($id>0 && $exists('bpm_variable')) {
  $q = "SELECT id, `key`, `type`, value_json, scope, updated_at FROM bpm_variable WHERE instance_id=$id ORDER BY updated_at DESC";
  if ($rs = $conn->query($q)) {
    $out['ok'] = true;
    while($r=$rs->fetch_assoc()){
      $val = json_decode($r['value_json'] ?? 'null', true);
      $r['value'] = $val;
      unset($r['value_json']);
      if ($mask && is_string($val) && strlen($val)>0 && preg_match('/secret|senha|token/i', $r['key'])) {
        $r['value'] = '••••••';
      }
      $out['vars'][] = $r;
    }
  }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
