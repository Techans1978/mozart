<?php
// public/api/hd/forms/list_scripts.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
header('Content-Type: application/json; charset=utf-8');
$dbc = $conn ?? (isset($mysqli) ? $mysqli : null);
if(!$dbc instanceof mysqli){ echo json_encode(['ok'=>false,'error'=>'Sem conexão']); exit; }
$rows=[]; if($rs=$dbc->query("SELECT id,nome,ativo FROM hd_form_script WHERE ativo=1 ORDER BY nome")){ while($r=$rs->fetch_assoc()) $rows[]=$r; }
echo json_encode(['ok'=>true,'data'=>$rows]);
