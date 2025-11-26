<?php
// Dev: ver erros facilmente (remova em produção se preferir)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

// Se sua autenticação usa outra chave de sessão, ajuste aqui ou remova este bloco.
if (!isset($_SESSION)) { session_start(); }
// if (empty($_SESSION['usuario_id'])) {
//   header('Content-Type: application/json; charset=utf-8');
//   http_response_code(401);
//   echo json_encode(['data'=>[], 'erro'=>'Sessão expirada']);
//   exit;
// }

// 1) Traz todos os grupos (id, nome, parent_id, ativo, codigo) — simples e confiável
$sql = "SELECT id, nome, parent_id, ativo, codigo FROM grupos ORDER BY nome ASC";
$res = $conn->query($sql);

$rows = [];
$byId = [];
while ($r = $res->fetch_assoc()) {
  $r['id']        = (int)$r['id'];
  $r['ativo']     = (int)$r['ativo'];
  $r['parent_id'] = isset($r['parent_id']) ? (int)$r['parent_id'] : null;
  $rows[] = $r;
  $byId[$r['id']] = $r; // para lookup rápido
}

// 2) Monta breadcrumb e nível caminhando pelos pais
function buildPathAndLevel(array $node, array $byId): array {
  $names = [];
  $visited = [];
  $cur = $node;
  $level = 0;

  // sobe até a raiz; protege contra ciclos
  while ($cur) {
    if (isset($visited[$cur['id']])) { // ciclo defensivo
      $names[] = '[ciclo? id='.$cur['id'].']';
      break;
    }
    $visited[$cur['id']] = true;

    array_unshift($names, $cur['nome']); // prefixa nome
    $level = count($names) - 1; // raiz = 0

    $pid = $cur['parent_id'] ?? null;
    $cur = ($pid && isset($byId[$pid])) ? $byId[$pid] : null;
  }

  return [implode(' -> ', $names), $level];
}

// 3) Constrói saída para DataTables
$data = [];
foreach ($rows as $g) {
  list($caminho, $nivel) = buildPathAndLevel($g, $byId);
  $data[] = [
    'id'      => $g['id'],
    'nome'    => $g['nome'],
    'caminho' => $caminho,
    'nivel'   => $nivel,
    'ativo'   => $g['ativo'],
    'codigo'  => $g['codigo'] ?? null,
  ];
}

// Ordena por nível e depois caminho (igual ao front)
usort($data, function($a,$b){
  if ($a['nivel'] === $b['nivel']) return strcasecmp($a['caminho'], $b['caminho']);
  return $a['nivel'] <=> $b['nivel'];
});

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['data'=>$data]);

?>