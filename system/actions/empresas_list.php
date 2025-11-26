<?php
// Mostrar erros (dev)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
// Autenticação e conexão
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . 'system//config/connect.php';

$dbc = isset($conn_glpi) ? $conn_glpi : (isset($conn) ? $conn : null);
if (!$dbc) { http_response_code(500); die(json_encode(['erro'=>'Sem conexão'])); }
@$dbc->set_charset('utf8mb4');

$sql = "SELECT id, nome_empresarial, cnpj, endereco_cidade, endereco_uf, matriz_filial, ativo
        FROM empresas
        ORDER BY id DESC";
$res = $dbc->query($sql);

$data = [];
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $data[] = $row;
  }
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['data'=>$data], JSON_UNESCAPED_UNICODE);
?>