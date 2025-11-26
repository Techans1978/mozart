<?php
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id<=0){ echo json_encode(['ok'=>false,'mensagem'=>'ID inválido']); exit; }

try{
  // impede excluir se tiver filhos diretos
  $hasChild = $conn->prepare("SELECT 1 FROM papeis WHERE parent_id=? LIMIT 1");
  $hasChild->bind_param('i',$id); $hasChild->execute(); $hasChild->store_result();
  if ($hasChild->num_rows>0){ $hasChild->close(); echo json_encode(['ok'=>false,'mensagem'=>'Remova/mova os subpapéis antes de excluir.']); exit; }
  $hasChild->close();

  $st = $conn->prepare("DELETE FROM papeis WHERE id=?");
  $st->bind_param('i',$id); $st->execute(); $st->close();
  echo json_encode(['ok'=>true,'mensagem'=>'Papel excluído com sucesso.']);
} catch (Throwable $e){
  echo json_encode(['ok'=>false,'mensagem'=>$e->getMessage()]);
}
?>