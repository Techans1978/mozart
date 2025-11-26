<?php
// pages/niveis_delete.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/config/db.php';

$mysqli = $mysqli ?? ($conn ?? null);
if (!$mysqli) { echo json_encode(['ok'=>false,'msg'=>'Sem conexão']); exit; }

$id = (int)($_POST['id'] ?? 0);
if(!$id){ echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }

$st = $mysqli->prepare("DELETE FROM acl_levels WHERE id=?");
$st->bind_param('i',$id);
$ok = $st->execute();

echo json_encode(['ok'=>$ok?true:false,'msg'=>$ok?'':'Falha ao excluir']);
?>