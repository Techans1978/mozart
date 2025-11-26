<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';

function redirect_msg($ok, $msg, $id=null){
  $qs = http_build_query(['ok'=>$ok?1:0,'msg'=>$msg] + ($id?['id'=>$id]:[]));
  header("Location: ".BASE_URL."/pages/papeis_form.php?{$qs}");
  exit;
}

$nome      = trim($_POST['nome']    ?? '');
$codigoRaw = trim($_POST['codigo']  ?? '');
$codigo    = ($codigoRaw==='') ? null : $codigoRaw;
$parent_id = isset($_POST['parent_id']) && $_POST['parent_id']!=='' ? (int)$_POST['parent_id'] : null;
$ativo     = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1;
$id        = isset($_POST['id']) && $_POST['id']!=='' ? (int)$_POST['id'] : null;

try {
  if ($nome==='') redirect_msg(false,'Nome é obrigatório.', $id);
  if ($id && $parent_id && $id===$parent_id) redirect_msg(false,'Papel não pode ser pai de si mesmo.', $id);

  if ($parent_id!==null){
    $chk = $conn->prepare("SELECT 1 FROM papeis WHERE id=? LIMIT 1");
    $chk->bind_param('i',$parent_id); $chk->execute();
    $rs = $chk->get_result(); $chk->close();
    if (!$rs || $rs->num_rows===0) redirect_msg(false,'Papel pai informado não existe.', $id);
  }

  if ($parent_id && $id){
    $stc = $conn->prepare("SELECT 1 FROM papeis_paths WHERE ancestor_id=? AND descendant_id=? LIMIT 1");
    $stc->bind_param('ii',$id,$parent_id); $stc->execute(); $stc->store_result();
    if ($stc->num_rows>0){ $stc->close(); redirect_msg(false,'Ciclo detectado: o novo pai é descendente do próprio papel.', $id); }
    $stc->close();
  }

  if ($id){
    $stmt = $conn->prepare("UPDATE papeis SET nome=?, codigo=?, parent_id=?, ativo=? WHERE id=?");
    $stmt->bind_param('ssiii', $nome, $codigo, $parent_id, $ativo, $id);
    $stmt->execute(); $stmt->close();
    redirect_msg(true,'Papel atualizado com sucesso!', $id);
  } else {
    $stmt = $conn->prepare("INSERT INTO papeis (nome, codigo, parent_id, ativo) VALUES (?,?,?,?)");
    $stmt->bind_param('ssii', $nome, $codigo, $parent_id, $ativo);
    $stmt->execute();
    $newId = $stmt->insert_id; $stmt->close();
    redirect_msg(true,'Papel criado com sucesso!', $newId);
  }

} catch (mysqli_sql_exception $e){
  redirect_msg(false,'Erro no banco: '.$e->getMessage(), $id);
} catch (Throwable $e){
  redirect_msg(false,'Erro inesperado: '.$e->getMessage(), $id);
}
?>