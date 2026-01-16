<?php
// Mostrar erros de mysqli como exceção (útil para ver a causa real em ambiente de dev)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

function redirect_msg($ok, $msg, $id=null){
  $qs = ['ok'=>$ok?1:0,'msg'=>$msg];
  if ($id) $qs['id'] = (int)$id;
  $qs = http_build_query($qs);
  header("Location: ".BASE_URL."/pages/grupos_form.php?{$qs}");
  exit;
}
function table_exists(mysqli $conn, string $table): bool {
  $t = $conn->real_escape_string($table);
  $r = $conn->query("SHOW TABLES LIKE '{$t}'");
  return ($r && $r->num_rows > 0);
}

try {
  // --------- Entrada ---------
  $nome      = trim($_POST['nome']    ?? '');
  $codigo    = trim($_POST['codigo']  ?? '');
  $parent_id = isset($_POST['parent_id']) && $_POST['parent_id']!=='' ? (int)$_POST['parent_id'] : null;
  $ativo     = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1;
  $id        = isset($_POST['id']) && $_POST['id']!=='' ? (int)$_POST['id'] : null; // se usar o mesmo para update

  if ($nome === '') redirect_msg(false, 'Nome é obrigatório.', $id);
  if ($id && $parent_id && $id === $parent_id) redirect_msg(false, 'Grupo não pode ser pai de si mesmo.');

  // (Opcional) valida se o parent_id existe (quando informado)
  if ($parent_id !== null) {
    $chk = $conn->prepare("SELECT 1 FROM grupos WHERE id = ? LIMIT 1");
    $chk->bind_param('i', $parent_id);
    $chk->execute();
    $res = $chk->get_result();
    $chk->close();
    if (!$res || $res->num_rows === 0) {
      redirect_msg(false, 'Grupo pai informado não existe.');
    }
  }

  // Evita ciclos se houver closure table (grupos_paths)
  if ($parent_id && $id && table_exists($conn,'grupos_paths')) {
    // se o novo pai for descendente do próprio nó, gera ciclo
    $sql = "SELECT 1 FROM grupos_paths WHERE ancestor_id = ? AND descendant_id = ? LIMIT 1";
    $stc = $conn->prepare($sql);
    $stc->bind_param('ii', $id, $parent_id);
    $stc->execute();
    $stc->store_result();
    if ($stc->num_rows > 0) {
      $stc->close();
      redirect_msg(false, 'Ciclo detectado: o novo pai é descendente do próprio grupo.');
    }
    $stc->close();
  }

  // ---------- INSERT ou UPDATE (apenas colunas existentes) ----------
  if ($id) {
    // UPDATE: nome, codigo, parent_id, ativo
    $stmt = $conn->prepare("UPDATE grupos SET nome = ?, codigo = ?, parent_id = ?, ativo = ? WHERE id = ?");
    // tipos: s s i i i
    $pid = $parent_id; // pode ser null
    $stmt->bind_param('ssiii', $nome, $codigo, $pid, $ativo, $id);
    $stmt->execute();
    $stmt->close();
    $msg = 'Grupo atualizado com sucesso!';
  } else {
    // INSERT: nome, codigo, parent_id, ativo
    $stmt = $conn->prepare("INSERT INTO grupos (nome, codigo, parent_id, ativo) VALUES (?, ?, ?, ?)");
    // tipos: s s i i
    $pid = $parent_id; // pode ser null
    $stmt->bind_param('ssii', $nome, $codigo, $pid, $ativo);
    $stmt->execute();
    $stmt->close();
    $msg = 'Grupo criado com sucesso!';
  }

  // Nada de path_cache/depth aqui: sua estrutura usa closure table + triggers

  redirect_msg(true, $msg, $id);

} catch (mysqli_sql_exception $e) {
  // Em dev, mostre o erro detalhado; em prod, troque por log + mensagem genérica
  redirect_msg(false, 'Erro no banco: '.$e->getMessage());
} catch (Throwable $e) {
  redirect_msg(false, 'Erro inesperado: '.$e->getMessage());
}
?>