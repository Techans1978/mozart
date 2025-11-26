<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3).'/config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();
$uid = (int)($_SESSION['user_id'] ?? 0);

$items = [];
$exists = function($t) use ($conn){
  $t = $conn->real_escape_string($t);
  $rs = $conn->query("SHOW TABLES LIKE '$t'");
  return $rs && $rs->num_rows > 0;
};

if ($uid>0 && $exists('bpm_task') && $exists('bpm_instance') && $exists('bpm_process_version') && $exists('bpm_process')) {
  // Tarefas direcionadas ao usuário (assignee) ou abertas ao grupo (candidate_group) do usuário (se tiver mapeamento)
  // Aqui mantemos simples: pegue tarefas com assignee = uid OU sem assignee (pool).
  $sql = "
    SELECT t.id, t.instance_id, t.node_id, t.name, t.type,
           t.assignee_user_id, t.status, t.due_at, t.created_at,
           p.name AS process_name
    FROM bpm_task t
    JOIN bpm_instance i ON i.id = t.instance_id
    JOIN bpm_process_version pv ON pv.id = i.version_id
    JOIN bpm_process p ON p.id = pv.process_id
    WHERE t.status IN ('ready','claimed','in_progress','error')
      AND (t.assignee_user_id IS NULL OR t.assignee_user_id = $uid)
    ORDER BY t.due_at IS NULL, t.due_at ASC, t.created_at ASC
    LIMIT 200";
  if ($rs = $conn->query($sql)) {
    while($r=$rs->fetch_assoc()) $items[] = $r;
  }
}

echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
