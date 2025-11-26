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
  // valida existência
  $r = $conn->query("SELECT 1 FROM perfis WHERE id={$id} LIMIT 1");
  if (!$r || $r->num_rows===0) { echo json_encode(['ok'=>0,'mensagem'=>'Perfil não encontrado']); exit; }

  // tenta excluir (triggers vão barrar se tiver filhos ou vínculos)
  $st = $conn->prepare("DELETE FROM perfis WHERE id=? LIMIT 1");
  $st->bind_param('i', $id);
  $st->execute(); $st->close();

  echo json_encode(['ok'=>1,'mensagem'=>'Perfil excluído']);
}catch(mysqli_sql_exception $e){
  // mensagens mais amigáveis
  $msg = $e->getMessage();
  if (strpos($msg, 'subperfis')!==false) $msg = 'Exclua ou mova os subperfis antes.';
  echo json_encode(['ok'=>0,'mensagem'=>'Erro: '.$msg]);
}catch(Throwable $e){
  echo json_encode(['ok'=>0,'mensagem'=>'Erro inesperado: '.$e->getMessage()]);
}
?>