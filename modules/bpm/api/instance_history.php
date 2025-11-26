<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3) . '/config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
proteger_pagina();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$limit  = isset($_GET['limit']) ? max(1,(int)$_GET['limit']) : 100;
$offset = isset($_GET['offset']) ? max(0,(int)$_GET['offset']) : 0;
$types  = isset($_GET['types']) ? trim($_GET['types']) : '';

$out = ['ok'=>false,'items'=>[]];

$exists = function($t) use ($conn){
  $t = $conn->real_escape_string($t);
  $rs = $conn->query("SHOW TABLES LIKE '$t'");
  return $rs && $rs->num_rows > 0;
};

if ($id>0 && $exists('bpm_event_log')) {
  $where = "instance_id=$id";
  if ($types) {
    $safe = array_map(function($s) use ($conn){ return "'".$conn->real_escape_string($s)."'"; }, array_filter(array_map('trim', explode(',', $types))));
    if ($safe) $where .= " AND event_type IN (".implode(',',$safe).")";
  }
  $q = "SELECT id, event_type, event_time, actor_user_id, data_json
        FROM bpm_event_log
        WHERE $where
        ORDER BY event_time DESC
        LIMIT $limit OFFSET $offset";
  if ($rs = $conn->query($q)) {
    $out['ok'] = true;
    while($r=$rs->fetch_assoc()) {
      $r['data'] = json_decode($r['data_json'] ?? 'null', true);
      unset($r['data_json']);
      $out['items'][] = $r;
    }
  }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
