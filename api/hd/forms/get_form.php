<?php
// public/api/hd/forms/get_form.php?id=
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
header('Content-Type: application/json; charset=utf-8');
$id = (int)($_GET['id'] ?? 0);
if(!$id){ echo json_encode(['ok'=>false,'error'=>'id obrigatório']); exit; }
$dbc = $conn ?? (isset($mysqli) ? $mysqli : null);
if(!$dbc instanceof mysqli){ echo json_encode(['ok'=>false,'error'=>'Sem conexão']); exit; }
$res = $dbc->query("SELECT id,nome,schema_json FROM hd_formulario WHERE id=$id");
$row = $res? $res->fetch_assoc() : null;
if(!$row){ echo json_encode(['ok'=>false,'error'=>'Form não encontrado']); exit; }
echo json_encode(['ok'=>true,'data'=>['id'=>$row['id'],'nome'=>$row['nome'],'schema'=>json_decode($row['schema_json'], true)]]);
