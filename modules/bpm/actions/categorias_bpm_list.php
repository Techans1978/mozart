<?php
// bootstrap padrão (arquivos no nível: public/modules/bpm/actions/)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// config.php fica em public/config.php (3 níveis acima)
require_once dirname(__DIR__, 3) . '/config.php';

require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

if (!isset($conn) && isset($mysqli)) { $conn = $mysqli; }
if (!($conn instanceof mysqli)) { die('Conexão MySQLi $conn não encontrada.'); }

function q_all($sql, $types='', $params=[]) {
  global $conn;
  $stmt = $conn->prepare($sql);
  if (!$stmt) { die('Erro prepare: '.$conn->error); }
  if ($types && $params) { $stmt->bind_param($types, *$params); }
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  if ($res) { while ($row = $res->fetch_assoc()) { $rows[] = $row; } }
  $stmt->close();
  return $rows;
}
function q_one($sql, $types='', $params=[]) { $rows = q_all($sql,$types,$params); return $rows ? $rows[0] : null; }
function q_exec($sql, $types='', $params=[]) {
  global $conn;
  $stmt = $conn->prepare($sql);
  if (!$stmt) { die('Erro prepare: '.$conn->error); }
  if ($types && $params) { $stmt->bind_param($types, *$params); }
  $ok = $stmt->execute();
  $err = $stmt->error;
  $aff = $stmt->affected_rows;
  $stmt->close();
  if (!$ok && $err) { throw new Exception($err); }
  return $aff;
}
?>
<?php
header('Content-Type: application/json; charset=utf-8');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$ativos = isset($_GET['ativos']) ? (int)$_GET['ativos'] : -1;

$sql = "SELECT c.id, c.nome, c.codigo, c.ativo, c.sort_order, c.parent_id,
               GROUP_CONCAT(a.nome ORDER BY p.depth SEPARATOR ' / ') AS full_path,
               MAX(p.depth) AS depth
        FROM bpm_categorias c
        JOIN bpm_categorias_paths p ON p.descendant_id=c.id
        JOIN bpm_categorias a ON a.id=p.ancestor_id
        WHERE 1=1";
$types=''; $params=[];
if ($q!==''){ $sql .= " AND (c.nome LIKE ? OR c.codigo LIKE ?)"; $types.='ss'; $like='%{}%'; $like=str_replace("{}", $q, $like); $params=[ $like, $like ]; }
if ($ativos===0 || $ativos===1){ $sql .= " AND c.ativo=?"; $types.='i'; $params[]=$ativos; }
$sql .= " GROUP BY c.id, c.nome, c.codigo, c.ativo, c.sort_order, c.parent_id
          ORDER BY full_path, c.sort_order, c.id";
$rows = q_all($sql,$types,$params);
echo json_encode(['data'=>$rows], JSON_UNESCAPED_UNICODE);
