<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3).'/config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
proteger_pagina();

$id = (int)($_GET['id'] ?? 0);
$out = ['ok'=>true,'doneIds'=>[]];

// Caminho percorrido: derivar de eventos + tarefas concluídas.
// Suporta os dois formatos de log que propusemos: bpm_event_log.data_json com "bpmnId" ou tasks com node_id.
$exists = function($t) use ($conn){
  $t = $conn->real_escape_string($t);
  $rs = $conn->query("SHOW TABLES LIKE '$t'");
  return $rs && $rs->num_rows > 0;
};

if ($id>0 && $exists('bpm_event_log')) {
  $q = "SELECT data_json FROM bpm_event_log WHERE instance_id=$id AND event_type IN ('TASK_CREATED','TASK_COMPLETED','GATEWAY_TAKEN','SERVICE_TASK_COMPLETED')";
  if ($rs = $conn->query($q)) {
    while($r=$rs->fetch_assoc()){
      $d = json_decode($r['data_json'] ?? 'null', true);
      if (is_array($d)) {
        foreach (['bpmnId','nodeId','node_id'] as $k) {
          if (!empty($d[$k])) $out['doneIds'][] = $d[$k];
        }
      }
    }
  }
}
if ($id>0 && $exists('bpm_task')) {
  $q = "SELECT node_id FROM bpm_task WHERE instance_id=$id AND status IN ('completed','error')";
  if ($rs = $conn->query($q)) {
    while($r=$rs->fetch_assoc()){ if (!empty($r['node_id'])) $out['doneIds'][] = $r['node_id']; }
  }
}

$out['doneIds'] = array_values(array_unique($out['doneIds']));
echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
