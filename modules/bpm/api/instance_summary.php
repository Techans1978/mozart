<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3) . '/config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
proteger_pagina();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$out = ['ok'=>false];

$exists = function($t) use ($conn){
  $t = $conn->real_escape_string($t);
  $rs = $conn->query("SHOW TABLES LIKE '$t'");
  return $rs && $rs->num_rows > 0;
};

if ($id>0 && $exists('bpm_instance')) {
  $sql = "SELECT i.id, i.status, i.started_at, i.ended_at, i.duration_ms,
                 pv.semver, p.name AS process_name
          FROM bpm_instance i
          LEFT JOIN bpm_process_version pv ON pv.id = i.version_id
          LEFT JOIN bpm_process p          ON p.id = pv.process_id
          WHERE i.id = $id";
  if ($rs = $conn->query($sql)) {
    if ($row = $rs->fetch_assoc()) {
      $out['ok'] = true;
      $out['instance'] = $row;
      // Atividades atuais
      if ($exists('bpm_task')) {
        $tasks = [];
        $q2 = "SELECT id, node_id, type, assignee_user_id, status, due_at, priority, created_at
               FROM bpm_task WHERE instance_id=$id AND status IN ('ready','claimed','error') ORDER BY created_at DESC";
        if ($r2 = $conn->query($q2)) { while($t=$r2->fetch_assoc()) $tasks[] = $t; }
        $out['tasks'] = $tasks;
      }
    }
  }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
