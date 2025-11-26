<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/connect.php';
$db = $conn ?? $mysqli ?? null; if(!$db){ echo json_encode(['message'=>'no db']); exit; }
$connector_id = (int)($_POST['connector_id'] ?? 0);
$cv_id = (int)($_POST['cv_id'] ?? 0);
$nome = $_POST['nome'] ?? '';
$display_name = $_POST['display_name'] ?? null;
$image_url = $_POST['image_url'] ?? null;
$categoria = $_POST['categoria'] ?? null;
$descr = $_POST['descr'] ?? null;
$version = $_POST['version'] ?? '1.0.0';
$status = $_POST['status'] ?? 'draft';
$method = $_POST['method'] ?? 'GET';
$base_url = $_POST['base_url'] ?? '';
$path_tpl = $_POST['path_tpl'] ?? '';
$headers_json = $_POST['headers_json'] ?? '{}';
$policy_json = $_POST['policy_json'] ?? '{}';
try{
  if ($connector_id>0){
    $s=$db->prepare("UPDATE api_connector SET nome=?, display_name=?, image_url=?, categoria=?, descr=? WHERE id=?");
    $s->bind_param('sssssi',$nome,$display_name,$image_url,$categoria,$descr,$connector_id); $s->execute(); $s->close();
  } else {
    $s=$db->prepare("INSERT INTO api_connector (nome, display_name, image_url, categoria, descr) VALUES (?,?,?,?,?)");
    $s->bind_param('sssss',$nome,$display_name,$image_url,$categoria,$descr); $s->execute(); $connector_id=(int)$s->insert_id; $s->close();
  }
  if ($cv_id>0){
    $s=$db->prepare("UPDATE api_connector_version SET connector_id=?, version=?, method=?, base_url=?, path_tpl=?, headers_json=?, policy_json=?, status=? WHERE id=?");
    $s->bind_param('isssssss',$connector_id,$version,$method,$base_url,$path_tpl,$headers_json,$policy_json,$status,$cv_id); $s->execute(); $s->close();
  } else {
    $s=$db->prepare("INSERT INTO api_connector_version (connector_id, version, method, base_url, path_tpl, headers_json, policy_json, status) VALUES (?,?,?,?,?,?,?,?)");
    $s->bind_param('isssssss',$connector_id,$version,$method,$base_url,$path_tpl,$headers_json,$policy_json,$status); $s->execute(); $cv_id=(int)$s->insert_id; $s->close();
  }
  echo json_encode(['message'=>'Salvo','redirect'=>'connectors-builder.php?id='.$connector_id.'&cv='.$cv_id]);
} catch(Throwable $e){ http_response_code(500); echo json_encode(['message'=>'Erro: '.$e->getMessage()]); }
