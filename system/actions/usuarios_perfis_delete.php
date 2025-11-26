<?php
require_once __DIR__ . '/../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';

$id = (int)($_POST['id'] ?? 0);
header('Content-Type: application/json');
if($id<=0){ echo json_encode(['ok'=>false,'mensagem'=>'ID inválido']); exit; }
$conn->query("DELETE FROM usuarios_perfis WHERE id=$id");
echo json_encode(['ok'=>true]);
?>