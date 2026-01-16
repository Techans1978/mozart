<?php
// Mostrar erros (dev)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
// Autenticação e conexão
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . 'system//config/connect.php';

header('Content-Type: application/json; charset=utf-8');

$dbc = isset($conn_glpi) ? $conn_glpi : (isset($conn) ? $conn : null);
if (!$dbc) { http_response_code(500); echo json_encode(['mensagem'=>'Sem conexão']); exit; }
@$dbc->set_charset('utf8mb4');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) { echo json_encode(['mensagem'=>'ID inválido']); exit; }

// Bloqueia exclusão se houver filhos
$stmt = $dbc->prepare("SELECT COUNT(*) AS q FROM grupos WHERE parent_id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$q = (int)$stmt->get_result()->fetch_assoc()['q'];
$stmt->close();

if ($q > 0) {
  // Se preferir "reparent" dos filhos para raiz, descomente abaixo e remova o return
  // $stmt = $dbc->prepare("UPDATE grupos SET parent_id=NULL WHERE parent_id=?");
  // $stmt->bind_param('i', $id);
  // $stmt->execute(); $stmt->close();
  echo json_encode(['ok'=>false,'mensagem'=>'Não é possível excluir: existem subgrupos vinculados.']); exit;
}

$stmt = $dbc->prepare("DELETE FROM grupos WHERE id=?");
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$msg = $ok ? 'Grupo excluído.' : ('Erro: '.$stmt->error);
$stmt->close();

echo json_encode(['ok'=>$ok,'mensagem'=>$msg]);

?>