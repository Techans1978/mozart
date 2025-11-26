<?php
require_once __DIR__ . '/../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';

$uid = (int)($_POST['usuario_id'] ?? 0);
$pid = (int)($_POST['perfil_id'] ?? 0);
$primary = isset($_POST['is_primary']) ? 1 : 0;

header('Content-Type: application/json');
if ($uid<=0 || $pid<=0) {
  echo json_encode(['ok'=>false,'mensagem'=>'Dados inválidos']); exit;
}

// Valida hierarquia
$sql = "SELECT 1 FROM perfis_paths 
        WHERE depth>=1 AND (
          (ancestor_id=$pid AND descendant_id IN (SELECT perfil_id FROM usuarios_perfis WHERE usuario_id=$uid))
          OR (descendant_id=$pid AND ancestor_id IN (SELECT perfil_id FROM usuarios_perfis WHERE usuario_id=$uid))
        ) LIMIT 1";
$res = $conn->query($sql);
if ($res && $res->num_rows>0) {
  echo json_encode(['ok'=>false,'mensagem'=>'Usuário já vinculado a perfil pai/filho']); exit;
}

// Insere
$stmt=$conn->prepare("INSERT INTO usuarios_perfis (usuario_id,perfil_id,is_primary) VALUES (?,?,?)");
$stmt->bind_param("iii",$uid,$pid,$primary);
if($stmt->execute()) echo json_encode(['ok'=>true]);
else echo json_encode(['ok'=>false,'mensagem'=>$stmt->error]);
?>