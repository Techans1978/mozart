<?php
require_once __DIR__ . '/../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';

$uid = (int)($_GET['usuario_id'] ?? 0);
$out = ['data'=>[]];
if ($uid > 0) {
  $sql = "SELECT up.id, up.is_primary, p.nome, COALESCE(p.path_cache,p.nome) AS perfil_label
          FROM usuarios_perfis up
          JOIN perfis p ON p.id = up.perfil_id
          WHERE up.usuario_id = $uid
          ORDER BY p.path_cache";
  $res = $conn->query($sql);
  while($r=$res->fetch_assoc()) $out['data'][]=$r;
}
header('Content-Type: application/json');
echo json_encode($out);
?>