<?php
require_once __DIR__ . '/../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';

$id = (int)($_POST['id'] ?? 0);
$val = (int)($_POST['valor'] ?? 0);
header('Content-Type: application/json');
if($id<=0){ echo json_encode(['ok'=>false]); exit; }
$conn->query("UPDATE usuarios_grupos SET is_primary=$val WHERE id=$id");
echo json_encode(['ok'=>true]);
?>