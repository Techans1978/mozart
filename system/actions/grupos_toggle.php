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

$dbc->begin_transaction();
try {
  $stmt = $dbc->prepare("SELECT ativo FROM grupos WHERE id=? FOR UPDATE");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$r) throw new Exception('Grupo não encontrado.');

  $novo = $r['ativo'] ? 0 : 1;
  $stmt = $dbc->prepare("UPDATE grupos SET ativo=? WHERE id=?");
  $stmt->bind_param('ii', $novo, $id);
  if (!$stmt->execute()) throw new Exception($stmt->error);
  $stmt->close();

  $dbc->commit();
  echo json_encode(['mensagem'=> ($novo? 'Ativado' : 'Inativado') . ' com sucesso.']);
} catch (Exception $e) {
  $dbc->rollback();
  http_response_code(500);
  echo json_encode(['mensagem'=>'Falha: '.$e->getMessage()]);
}
?>