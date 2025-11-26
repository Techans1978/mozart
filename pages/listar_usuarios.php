<?php
// pages/listar_usuarios.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php'; // $conn (mysqli)

if (session_status() === PHP_SESSION_NONE) session_start();

$EDIT_PAGE = BASE_URL . '/pages/cadastrar_usuario.php'; // destino do botão Editar

// ============== helpers ==============
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function only_digits($s){ return preg_replace('/\D+/', '', (string)$s); }
function flash($key, $msg=null){
  if ($msg===null){ $m=$_SESSION['flash'][$key]??null; unset($_SESSION['flash'][$key]); return $m; }
  $_SESSION['flash'][$key]=$msg;
}
function csrf_token(){
  if (empty($_SESSION['csrf_user_list'])) $_SESSION['csrf_user_list'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf_user_list'];
}
function check_csrf($t){
  return isset($_SESSION['csrf_user_list']) && hash_equals($_SESSION['csrf_user_list'], (string)$t);
}
function redirect_self(){
  $url = strtok($_SERVER['REQUEST_URI'], '?');
  if (!empty($_GET)) $url .= '?'.http_build_query($_GET);
  header("Location: $url", true, 303);
  exit;
}

// ============== Ações (POST) ==============
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $acao = $_POST['acao'] ?? '';
  $id   = (int)($_POST['id'] ?? 0);
  $csrf = $_POST['csrf'] ?? '';

  if (!check_csrf($csrf)) { flash('erro', 'Token CSRF inválido. Recarregue a página.'); redirect_self(); }
  if ($id<=0)             { flash('erro', 'ID inválido.'); redirect_self(); }

  if ($acao==='inativar') {
    $st = $conn->prepare("UPDATE usuarios SET ativo=0 WHERE id=?");
    if ($st){ $st->bind_param('i',$id); $ok=$st->execute(); $st->close(); }
    if (!empty($ok)) flash('ok','Usuário inativado.'); else flash('erro','Falha ao inativar.');
    redirect_self();
  }

  if ($acao==='reativar') {
    $st = $conn->prepare("UPDATE usuarios SET ativo=1 WHERE id=?");
    if ($st){ $st->bind_param('i',$id); $ok=$st->execute(); $st->close(); }
    if (!empty($ok)) flash('ok','Usuário reativado.'); else flash('erro','Falha ao reativar.');
    redirect_self();
  }

  if ($acao==='excluir') {
    // tenta excluir; se houver FKs (ex.: usuarios_grupos), o MySQL pode barrar
    $st = $conn->prepare("DELETE FROM usuarios WHERE id=?");
    if ($st){
      $st->bind_param('i',$id);
      $ok = $st->execute();
      $err = $conn->errno;
      $st->close();
      if (!empty($ok)) flash('ok','Usuário excluído com sucesso.');
      else {
        // 1451: cannot delete or update a parent row: a foreign key constraint fails
        if ($err==1451) flash('erro','Não foi possível excluir: existem vínculos (grupos/perfis/papéis, etc.). Inative em vez de excluir.');
        else flash('erro','Falha ao excluir.');
      }
    } else {
      flash('erro','Erro ao preparar exclusão: '.$conn->error);
    }
    redirect_self();
  }

  flash('erro','Ação inválida.');
  redirect_self();
}

// ============== Filtros (GET) ==============
$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'ativos'; // todos | ativos | inativos
$nivel  = $_GET['nivel']  ?? '';       // filtro opcional por nível de acesso

$where = [];
$params = [];
$types  = '';

if ($q!=='') {
  // busca por username, nome, cpf/cnpj, email, telefone, cargo
  $where[] = "(u.username LIKE CONCAT('%', ?, '%')
               OR u.nome_completo LIKE CONCAT('%', ?, '%')
               OR u.cpf = ?
               OR u.email LIKE CONCAT('%', ?, '%')
               OR u.telefone LIKE CONCAT('%', ?, '%')
               OR u.cargo LIKE CONCAT('%', ?, '%'))";
  $params[]=$q; $params[]=$q; $params[]=only_digits($q);
  $params[]=$q; $params[]=only_digits($q); $params[]=$q;
  $types .= 'ssssss';
}
if ($status==='ativos')   $where[]="u.ativo=1";
if ($status==='inativos') $where[]="u.ativo=0";
if ($nivel!=='') {
  $where[]="u.nivel_acesso = ?";
  $params[]=$nivel; $types.='s';
}

$sql = "SELECT u.id, u.username, u.nome_completo, u.cpf, u.cargo,
               u.email, u.telefone, u.nivel_acesso, u.ativo, u.data_cadastro
        FROM usuarios u";
if ($where) $sql .= " WHERE ".implode(' AND ',$where);
$sql .= " ORDER BY u.data_cadastro DESC, u.nome_completo ASC LIMIT 1000";

// executa
$users = [];
if ($types) {
  $st = $conn->prepare($sql);
  if ($st) {
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) $users[]=$r;
    $st->close();
  } else {
    flash('erro','Erro ao preparar consulta: '.$conn->error);
  }
} else {
  $res = $conn->query($sql);
  if ($res) { while ($r=$res->fetch_assoc()) $users[]=$r; }
  else { flash('erro','Erro ao consultar: '.$conn->error); }
}

// includes padrão
include_once ROOT_PATH . '/system/includes/head.php';
include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<style>
.table-actions .btn { margin-right: 6px; }
.badge-pill { border-radius:999px; padding:4px 10px; font-size:12px; }
.badge-ativo { background:#e6ffed; color:#177245; border:1px solid #c1f2ce; }
.badge-inativo { background:#fff3cd; color:#8a6d3b; border:1px solid #ffe69c; }
</style>

<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

    <div class="row">
      <div class="col-lg-12">
        <h2>Usuários</h2>

        <?php if ($m = flash('ok')): ?>
          <div class="alert alert-success"><?= h($m) ?></div>
        <?php endif; ?>
        <?php if ($m = flash('erro')): ?>
          <div class="alert alert-danger"><?= h($m) ?></div>
        <?php endif; ?>

        <form class="form-inline" method="get" style="margin-bottom:12px">
          <div class="form-group">
            <input type="text" class="form-control" name="q"
                   placeholder="Buscar por username, nome, CPF/CNPJ, e-mail..."
                   value="<?= h($q) ?>" style="min-width:320px">
          </div>
          <div class="form-group" style="margin-left:8px">
            <select class="form-control" name="status">
              <option value="todos"   <?= $status==='todos'?'selected':'' ?>>Todos</option>
              <option value="ativos"  <?= $status==='ativos'?'selected':'' ?>>Ativos</option>
              <option value="inativos"<?= $status==='inativos'?'selected':'' ?>>Inativos</option>
            </select>
          </div>
          <div class="form-group" style="margin-left:8px">
            <select class="form-control" name="nivel">
              <option value="" <?= $nivel===''?'selected':'' ?>>Todos os Níveis</option>
              <option value="bigboss" <?= $nivel==='bigboss'?'selected':'' ?>>Big Boss</option>
              <option value="admin"   <?= $nivel==='admin'?'selected':'' ?>>Administrador</option>
              <option value="gerente" <?= $nivel==='gerente'?'selected':'' ?>>Gerente</option>
              <option value="usuario" <?= $nivel==='usuario'?'selected':'' ?>>Usuário</option>
            </select>
          </div>
          <button class="btn btn-primary" type="submit" style="margin-left:8px">Filtrar</button>
          <a class="btn btn-default" href="<?= h(strtok($_SERVER['REQUEST_URI'],'?')) ?>" style="margin-left:4px">Limpar</a>
          <a class="btn btn-success" href="<?= h($EDIT_PAGE) ?>" style="margin-left:8px">➕ Novo Usuário</a>
        </form>

        <div class="table-responsive">
          <table class="table table-striped table-hover">
            <thead>
              <tr>
                <th style="width:64px">ID</th>
                <th>Username</th>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Telefone</th>
                <th>Nível</th>
                <th>Status</th>
                <th style="width:240px">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$users): ?>
                <tr><td colspan="8" class="text-muted">Nenhum usuário encontrado.</td></tr>
              <?php else: foreach ($users as $u): ?>
                <tr>
                  <td><?= (int)$u['id'] ?></td>
                  <td><?= h($u['username']) ?></td>
                  <td><?= h($u['nome_completo']) ?></td>
                  <td><?= h($u['email']) ?></td>
                  <td><?= h($u['telefone']) ?></td>
                  <td><?= h($u['nivel_acesso']) ?></td>
                  <td>
                    <?php if ((int)$u['ativo']===1): ?>
                      <span class="badge badge-pill badge-ativo">Ativo</span>
                    <?php else: ?>
                      <span class="badge badge-pill badge-inativo">Inativo</span>
                    <?php endif; ?>
                  </td>
                  <td class="table-actions">
                    <!-- Editar -->
                    <a class="btn btn-xs btn-primary"
                       href="<?= h($EDIT_PAGE) . '?usuario_id=' . (int)$u['id'] ?>">
                      Editar
                    </a>

                    <!-- Inativar/Reativar -->
                    <?php if ((int)$u['ativo']===1): ?>
                      <form method="post" style="display:inline" onsubmit="return confirmInativar(<?= (int)$u['id'] ?>)">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="acao" value="inativar">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <button class="btn btn-xs btn-warning" type="submit">Inativar</button>
                      </form>
                    <?php else: ?>
                      <form method="post" style="display:inline" onsubmit="return confirmReativar(<?= (int)$u['id'] ?>)">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="acao" value="reativar">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <button class="btn btn-xs btn-success" type="submit">Reativar</button>
                      </form>
                    <?php endif; ?>

                    <!-- Excluir -->
                    <form method="post" style="display:inline" onsubmit="return confirmExcluir(<?= (int)$u['id'] ?>)">
                      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="acao" value="excluir">
                      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <button class="btn btn-xs btn-danger" type="submit">Excluir</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <p class="text-muted">
          <small>Exibindo até 1000 registros. Use os filtros para refinar.</small><br>
          <small>Ordenado por data de cadastro mais recente.</small>
        </p>
      </div>
    </div>
  </div>
</div>

<script>
function confirmExcluir(id){
  return confirm('Tem certeza que deseja EXCLUIR definitivamente o usuário #' + id + ' ? Esta ação não poderá ser desfeita.');
}
function confirmInativar(id){
  return confirm('Inativar o usuário #' + id + ' ? Ele perderá o acesso se estiver ativo.');
}
function confirmReativar(id){
  return confirm('Reativar o usuário #' + id + ' ? O acesso será restabelecido conforme permissões.');
}
</script>

<?php include_once ROOT_PATH . '/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>
