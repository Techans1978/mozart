<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

function redirect_msg($ok, $msg){
  $qs = http_build_query(['ok'=>$ok?1:0,'msg'=>$msg]);
  header("Location: ".BASE_URL."/pages/perfis_form.php?{$qs}");
  exit;
}

$nome      = trim($_POST['nome']    ?? '');
$codigo    = trim($_POST['codigo']  ?? '');
$parent_id = isset($_POST['parent_id']) && $_POST['parent_id']!=='' ? (int)$_POST['parent_id'] : null;
$ativo     = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1;
$id        = isset($_POST['id']) && $_POST['id']!=='' ? (int)$_POST['id'] : null;

try {
  if ($nome === '') redirect_msg(false, 'Nome é obrigatório.');
  if ($id && $parent_id && $id === $parent_id) redirect_msg(false, 'Perfil não pode ser pai de si mesmo.');

  // valida existência do pai (se informado)
  if ($parent_id !== null) {
    $chk = $conn->prepare("SELECT 1 FROM perfis WHERE id = ? LIMIT 1");
    $chk->bind_param('i', $parent_id);
    $chk->execute(); $r=$chk->get_result(); $chk->close();
    if (!$r || $r->num_rows===0) redirect_msg(false,'Perfil pai informado não existe.');
  }

  if ($id) {
    $stmt = $conn->prepare("UPDATE perfis SET nome=?, codigo=?, parent_id=?, ativo=? WHERE id=?");
    $pid = $parent_id;
    $stmt->bind_param('ssiii', $nome, $codigo, $pid, $ativo, $id);
    $stmt->execute(); $stmt->close();
    redirect_msg(true, 'Perfil atualizado com sucesso!');
  } else {
    $stmt = $conn->prepare("INSERT INTO perfis (nome, codigo, parent_id, ativo) VALUES (?,?,?,?)");
    $pid = $parent_id;
    $stmt->bind_param('ssii', $nome, $codigo, $pid, $ativo);
    $stmt->execute(); $stmt->close();
    redirect_msg(true, 'Perfil criado com sucesso!');
  }

} catch (mysqli_sql_exception $e) {
  redirect_msg(false, 'Erro no banco: '.$e->getMessage());
} catch (Throwable $e) {
  redirect_msg(false, 'Erro inesperado: '.$e->getMessage());
}
?>