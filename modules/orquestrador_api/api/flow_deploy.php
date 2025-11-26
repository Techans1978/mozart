<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once __DIR__ . '/../includes/MozartFlowRunner.php';
$db = $conn ?? $mysqli ?? null; if(!$db){ http_response_code(500); echo json_encode(['message'=>'no db']); exit; }

$env = $_POST['env'] ?? 'dev';
$spec = $_POST['spec_json'] ?? '';

try {
  // cria flow e versão
  $db->query("INSERT INTO moz_flow (nome,categoria,descr) VALUES ('deploy','deploy','Deploy via editor')");
  $flowId = (int)$db->insert_id;
  $stmt = $db->prepare("INSERT INTO moz_flow_version (flow_id, version, spec_json, status) VALUES (?,?,?,'active')");
  $v = date('Ymd.His');
  $stmt->bind_param('iss',$flowId,$v,$spec); $stmt->execute(); $fvId = (int)$stmt->insert_id; $stmt->close();

  // marca deploy env
  $stmt = $db->prepare("INSERT INTO moz_flow_deploy (flow_version_id, env, is_active) VALUES (?,?,1) ON DUPLICATE KEY UPDATE is_active=VALUES(is_active)");
  $stmt->bind_param('is',$fvId,$env); $stmt->execute(); $stmt->close();

  echo json_encode(['message'=>'Deployed', 'flow_version_id'=>$fvId]);
} catch (Throwable $e){
  http_response_code(500);
  echo json_encode(['message'=>'error: '.$e->getMessage()]);
}
