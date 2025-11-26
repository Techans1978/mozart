<?php
// Mostrar erros (dev)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
// Autenticação e conexão
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . 'system//config/connect.php';

header('Content-Type: application/json; charset=utf-8');

$dbc = isset($conn_glpi) ? $conn_glpi : (isset($conn) ? $conn : null);
if (!$dbc) { http_response_code(500); echo json_encode(['mensagem'=>'Sem conexão']); exit; }
@$dbc->set_charset('utf8mb4');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) { echo json_encode(['mensagem'=>'ID inválido']); exit; }

// Se preferir "soft delete", troque por UPDATE ativo=0
$stmt = $dbc->prepare("DELETE FROM empresas WHERE id=?");
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$msg = $ok ? 'Empresa excluída.' : ('Erro: '.$stmt->error);
$stmt->close();

echo json_encode(['mensagem'=>$msg, 'ok'=>$ok]);
?>