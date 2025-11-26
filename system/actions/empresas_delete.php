<?php
// empresas_delete.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

header('Content-Type: application/json; charset=utf-8');

$dbc = isset($conn_glpi) ? $conn_glpi : (isset($conn) ? $conn : null);
if (!$dbc) { http_response_code(500); echo json_encode(['ok'=>false,'mensagem'=>'Sem conexão']); exit; }
@$dbc->set_charset('utf8mb4');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) { echo json_encode(['ok'=>false,'mensagem'=>'ID inválido']); exit; }

// Verifica vínculos
$deps = 0;
if ($stc = $dbc->prepare("SELECT COUNT(*) AS c FROM moz_deposito WHERE empresa_id = ?")) {
  $stc->bind_param('i', $id);
  $stc->execute();
  $deps = (int)($stc->get_result()->fetch_assoc()['c'] ?? 0);
  $stc->close();
}

if ($deps > 0) {
  echo json_encode([
    'ok' => false,
    'mensagem' => "Não é possível excluir: a empresa possui $deps vínculo(s) (ex.: depósitos). Use 'Inativar' para retirá-la do uso."
  ]);
  exit;
}

// Sem vínculos -> excluir
$stmt = $dbc->prepare("DELETE FROM empresas WHERE id=?");
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$msg = $ok ? 'Empresa excluída.' : ('Falha ao excluir: '.$stmt->error);
$stmt->close();

echo json_encode(['ok'=>$ok,'mensagem'=>$msg]);
