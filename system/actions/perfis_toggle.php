<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

header('Content-Type: application/json; charset=utf-8');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id<=0) { echo json_encode(['ok'=>0,'mensagem'=>'ID inválido']); exit; }

try{
  $r = $conn->query("SELECT ativo FROM perfis WHERE id={$id} LIMIT 1");
  if (!$r || $r->num_rows===0) { echo json_encode(['ok'=>0,'mensagem'=>'Perfil não encontrado']); exit; }
  $atual = (int)$r->fetch_assoc()['ativo'];
  $novo  = $atual?0:1;

  $st = $conn->prepare("UPDATE perfis SET ativo=? WHERE id=?");
  $st->bind_param('ii', $novo, $id);
  $st->execute(); $st->close();

  echo json_encode(['ok'=>1,'mensagem'=>'Status atualizado']);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>0,'mensagem'=>'Erro: '.$e->getMessage()]);
}
?>