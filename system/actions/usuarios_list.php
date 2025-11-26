<?php
// JSON de usuários para DataTables
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . 'system/config/connect.php';

header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT id, nome_completo, email, ativo FROM usuarios ORDER BY nome_completo ASC";
$res = $conn->query($sql);

$data = [];
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $data[] = [
      'id'            => (int)$r['id'],
      'nome_completo' => $r['nome_completo'],
      'email'         => $r['email'],
      'ativo'         => (int)$r['ativo'],
    ];
  }
}

echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE);
?>