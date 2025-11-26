<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3).'/config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();
$uid = (int)($_SESSION['user_id'] ?? 0);

$id = (int)($_POST['id'] ?? 0);
if ($id<=0 || $uid<=0) { echo json_encode(['ok'=>false,'error'=>'Parâmetros inválidos']); exit; }

$conn->begin_transaction();
try {
  // conclui se o usuário é o assignee (ou se estiver aberto — para simplificar no MVP)
  $q = "UPDATE bpm_task SET status='completed', finished_at=NOW()
        WHERE id=$id AND (assignee_user_id=$uid OR assignee_user_id IS NULL)";
  $conn->query($q);
  if ($conn->affected_rows<=0) { throw new Exception('Você não pode concluir esta tarefa.'); }

  // histórico
  $conn->query("INSERT INTO bpm_event_log(instance_id,event_type,event_time,actor_user_id,data_json)
                SELECT instance_id,'TASK_COMPLETED',NOW(),$uid,JSON_OBJECT('taskId',$id)
                FROM bpm_task WHERE id=$id");

  $conn->commit();
  echo json_encode(['ok'=>true]);
} catch(Exception $e){
  $conn->rollback();
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
