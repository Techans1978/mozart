<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3).'/config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
proteger_pagina();

$name = trim($_GET['name'] ?? '');
$status = trim($_GET['status'] ?? '');
$cat = trim($_GET['category'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50; $offset = ($page-1)*$limit;

$items = [];
$exists = function($t) use ($conn){
  $t = $conn->real_escape_string($t);
  $rs = $conn->query("SHOW TABLES LIKE '$t'");
  return $rs && $rs->num_rows > 0;
};

if ($exists('bpm_process_version') && $exists('bpm_process')) {
  $w = [];
  if ($name!=='')   $w[] = "p.name LIKE '%".$conn->real_escape_string($name)."%'";
  if ($status!=='') $w[] = "pv.status = '".$conn->real_escape_string($status)."'";
  if ($cat!=='')    $w[] = "p.category LIKE '%".$conn->real_escape_string($cat)."%'";
  $where = $w ? ('WHERE '.implode(' AND ',$w)) : '';
  $sql = "
    SELECT pv.id, pv.semver, pv.status, p.name, p.description, p.category
    FROM bpm_process_version pv
    JOIN bpm_process p ON p.id = pv.process_id
    $where
    ORDER BY p.name ASC, pv.created_at DESC
    LIMIT $limit OFFSET $offset";
  if ($rs = $conn->query($sql)) {
    while($r = $rs->fetch_assoc()){
      $items[] = [
        'id' => $r['id'],
        'name' => $r['name'],
        'description' => $r['description'],
        'category' => $r['category'],
        'semver' => $r['semver'],
        'status' => $r['status'],
      ];
    }
  }
}

echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
