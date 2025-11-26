<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

// Carrega todos, depois sobe pais em memória
$sql = "SELECT id, nome, parent_id, ativo, codigo FROM perfis ORDER BY nome ASC";
$res = $conn->query($sql);
$rows = []; $byId = [];
while ($r = $res->fetch_assoc()) {
  $r['id']        = (int)$r['id'];
  $r['ativo']     = (int)$r['ativo'];
  $r['parent_id'] = isset($r['parent_id']) ? (int)$r['parent_id'] : null;
  $rows[] = $r; $byId[$r['id']] = $r;
}

function buildPathLevel($node, $byId) {
  $names = []; $seen = []; $cur = $node; $guard = 0;
  while ($cur && $guard++ < 100) {
    if (isset($seen[$cur['id']])) { $names[]='[ciclo]'; break; }
    $seen[$cur['id']] = true;
    array_unshift($names, $cur['nome']);
    $pid = $cur['parent_id'] ?? null;
    $cur = ($pid && isset($byId[$pid])) ? $byId[$pid] : null;
  }
  $nivel = max(0, count($names)-1);
  return [implode(' -> ', $names), $nivel];
}

$data = [];
foreach ($rows as $p) {
  list($caminho, $nivel) = buildPathLevel($p, $byId);
  $data[] = [
    'id'      => $p['id'],
    'nome'    => $p['nome'],
    'caminho' => $caminho,
    'nivel'   => $nivel,
    'ativo'   => $p['ativo'],
  ];
}
usort($data, function($a,$b){
  if ($a['nivel'] === $b['nivel']) return strcasecmp($a['caminho'], $b['caminho']);
  return $a['nivel'] <=> $b['nivel'];
});

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['data'=>$data]);
?>