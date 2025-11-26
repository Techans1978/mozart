<?php
// modules/bpm/bpm_designer.php
// Mozart BPM — Modeler com Properties + Element Templates (CDN + fallback local)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  echo json_encode(['error'=>'PDO $pdo não encontrado']);
  exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$ativos = isset($_GET['ativos']) ? (int)$_GET['ativos'] : -1;

$sql = "
SELECT
  c.id, c.nome, c.codigo, c.ativo, c.sort_order, c.parent_id,
  GROUP_CONCAT(a.nome ORDER BY p.depth SEPARATOR ' / ') AS full_path,
  MAX(p.depth) AS depth
FROM bpm_categorias c
JOIN bpm_categorias_paths p ON p.descendant_id = c.id
JOIN bpm_categorias a       ON a.id = p.ancestor_id
WHERE 1=1
";
$params = [];
if ($q!=='') { $sql.=" AND (c.nome LIKE :q OR c.codigo LIKE :q) "; $params[':q']="%{$q}%"; }
if ($ativos===0 || $ativos===1){ $sql.=" AND c.ativo=:at "; $params[':at']=$ativos; }
$sql .= "
GROUP BY c.id, c.nome, c.codigo, c.ativo, c.sort_order, c.parent_id
ORDER BY full_path, c.sort_order, c.id
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['data'=>$rows], JSON_UNESCAPED_UNICODE);
