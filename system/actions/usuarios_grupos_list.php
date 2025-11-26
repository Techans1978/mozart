<?php
require_once __DIR__ . '/../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';

$uid = (int)($_GET['usuario_id'] ?? 0);
$out = ['data'=>[]];
if ($uid > 0) {
  $sql = "SELECT ug.id, ug.is_primary, g.nome, COALESCE(g.path_cache,g.nome) AS grupo_label
          FROM usuarios_grupos ug
          JOIN grupos g ON g.id = ug.grupo_id
          WHERE ug.usuario_id = $uid
          ORDER BY g.path_cache";
  $res = $conn->query($sql);
  while($r=$res->fetch_assoc()) $out['data'][]=$r;
}
header('Content-Type: application/json');
echo json_encode($out);
?>