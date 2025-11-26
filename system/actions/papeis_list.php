<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';

$sql = "
SELECT p.id, p.nome, p.ativo,
       COALESCE(MAX(pp.depth),0) AS depth,
       (
         SELECT GROUP_CONCAT(a.nome ORDER BY pp2.depth ASC SEPARATOR ' -> ')
         FROM papeis_paths pp2
         JOIN papeis a ON a.id = pp2.ancestor_id
         WHERE pp2.descendant_id = p.id
       ) AS path
FROM papeis p
LEFT JOIN papeis_paths pp ON pp.descendant_id = p.id
GROUP BY p.id, p.nome, p.ativo
ORDER BY depth ASC, path ASC, p.nome ASC";
$res = $conn->query($sql);

$out = [];
while($r = $res->fetch_assoc()){
  $out[] = [
    'id'    => (int)$r['id'],
    'nome'  => $r['nome'],
    'path'  => $r['path'],
    'depth' => (int)$r['depth'],
    'ativo' => (int)$r['ativo'],
  ];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
?>