<?php
require_once __DIR__ . '/../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';

$uid = (int)($_POST['usuario_id'] ?? 0);
$gid = (int)($_POST['grupo_id'] ?? 0);
$primary = isset($_POST['is_primary']) ? 1 : 0;

header('Content-Type: application/json');
if ($uid<=0 || $gid<=0) {
  echo json_encode(['ok'=>false,'mensagem'=>'Dados inválidos']); exit;
}

// Valida hierarquia
$sql = "SELECT 1 FROM grupos_paths 
        WHERE depth>=1 AND (
          (ancestor_id=$gid AND descendant_id IN (SELECT grupo_id FROM usuarios_grupos WHERE usuario_id=$uid))
          OR (descendant_id=$gid AND ancestor_id IN (SELECT grupo_id FROM usuarios_grupos WHERE usuario_id=$uid))
        ) LIMIT 1";
$res = $conn->query($sql);
if ($res && $res->num_rows>0) {
  echo json_encode(['ok'=>false,'mensagem'=>'Usuário já vinculado a grupo pai/filho']); exit;
}

// Insere
$stmt=$conn->prepare("INSERT INTO usuarios_grupos (usuario_id,grupo_id,is_primary) VALUES (?,?,?)");
$stmt->bind_param("iii",$uid,$gid,$primary);
if($stmt->execute()) echo json_encode(['ok'=>true]);
else echo json_encode(['ok'=>false,'mensagem'=>$stmt->error]);
?>