<?php
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id<=0){ echo json_encode(['ok'=>false,'mensagem'=>'ID inválido']); exit; }

try{
  $r = $conn->query("SELECT ativo FROM papeis WHERE id={$id}")->fetch_assoc();
  if (!$r){ echo json_encode(['ok'=>false,'mensagem'=>'Papel não encontrado']); exit; }
  $novo = ((int)$r['ativo']===1) ? 0 : 1;
  $st = $conn->prepare("UPDATE papeis SET ativo=? WHERE id=?");
  $st->bind_param('ii',$novo,$id); $st->execute(); $st->close();
  echo json_encode(['ok'=>true,'mensagem'=>'Status atualizado']);
} catch (Throwable $e){
  echo json_encode(['ok'=>false,'mensagem'=>$e->getMessage()]);
}
?>