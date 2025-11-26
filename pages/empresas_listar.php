<?php
// pages/empresas_listar.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php'; // $conn (mysqli)

$EDIT_PAGE = BASE_URL . '/pages/cadastrar_empresa.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function only_digits($s){ return preg_replace('/\D+/', '', (string)$s); }
function flash($key, $msg = null) {
  if ($msg === null) {
    if (!empty($_SESSION['flash'][$key])) {
      $m = $_SESSION['flash'][$key];
      unset($_SESSION['flash'][$key]);
      return $m;
    }
    return null;
  }
  $_SESSION['flash'][$key] = $msg;
}
function csrf_token() {
  if (empty($_SESSION['csrf_emp'])) $_SESSION['csrf_emp'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf_emp'];
}
function check_csrf($t) {
  return isset($_SESSION['csrf_emp']) && hash_equals($_SESSION['csrf_emp'], (string)$t);
}
function redirect_self() {
  $url = strtok($_SERVER["REQUEST_URI"], '?');
  if (!empty($_GET)) $url .= '?'. http_build_query($_GET);
  header("Location: $url", true, 303);
  exit;
}

/* ========= AÇÕES ========= */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $acao = $_POST['acao'] ?? '';
  $id   = (int)($_POST['id'] ?? 0);
  $csrf = $_POST['csrf'] ?? '';

  if (!check_csrf($csrf)) { flash('erro','Token CSRF inválido.'); redirect_self(); }
  if ($id<=0){ flash('erro','ID inválido.'); redirect_self(); }

  if ($acao==='excluir') {
    // checa vínculos (depósitos, etc.)
    $deps = 0;
    if ($stc = $conn->prepare("SELECT COUNT(*) AS c FROM moz_deposito WHERE empresa_id=?")) {
      $stc->bind_param('i',$id);
      $stc->execute();
      $deps = (int)($stc->get_result()->fetch_assoc()['c'] ?? 0);
      $stc->close();
    }
    if ($deps>0){
      flash('erro',"Não é possível excluir: a empresa possui $deps vínculo(s) (ex.: depósitos). Use 'Inativar' para retirar do uso.");
      redirect_self();
    }

    $st = $conn->prepare("DELETE FROM empresas WHERE id=?");
    if ($st){
      $st->bind_param('i',$id);
      if ($st->execute()) flash('ok','Empresa excluída com sucesso.');
      else flash('erro','Falha ao excluir: '.$conn->error);
      $st->close();
    } else {
      flash('erro','Erro ao preparar exclusão: '.$conn->error);
    }
    redirect_self();
  }

  if ($acao==='inativar') {
    $st = $conn->prepare("UPDATE empresas SET ativo=0 WHERE id=?");
    if ($st){ $st->bind_param('i',$id); $st->execute(); $st->close(); flash('ok','Empresa inativada.'); }
    else { flash('erro','Erro: '.$conn->error); }
    redirect_self();
  }

  if ($acao==='reativar') {
    $st = $conn->prepare("UPDATE empresas SET ativo=1 WHERE id=?");
    if ($st){ $st->bind_param('i',$id); $st->execute(); $st->close(); flash('ok','Empresa reativada.'); }
    else { flash('erro','Erro: '.$conn->error); }
    redirect_self();
  }

  flash('erro','Ação inválida.'); redirect_self();
}

/* ========= FILTROS ========= */
$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'ativos'; // todos|ativos|inativos

$where=[]; $params=[]; $types='';

if ($q!=='') {
  $where[] = "(e.nome_empresarial LIKE CONCAT('%', ?, '%')
            OR e.nome_fantasia LIKE CONCAT('%', ?, '%')
            OR e.apelido LIKE CONCAT('%', ?, '%')
            OR e.nome_interno LIKE CONCAT('%', ?, '%')
            OR e.cnpj = ?
            OR e.endereco_cidade LIKE CONCAT('%', ?, '%')
            OR e.endereco_uf LIKE CONCAT('%', ?, '%'))";
  $params[]=$q; $params[]=$q; $params[]=$q; $params[]=$q;
  $params[] = only_digits($q);
  $params[]=$q; $params[]=strtoupper($q);
  $types   .= 'sssssss';
}
if ($status==='ativos')   $where[] = "e.ativo=1";
if ($status==='inativos') $where[] = "e.ativo=0";

$sql = "SELECT e.id, e.nome_empresarial, e.nome_fantasia, e.apelido, e.cnpj,
               e.endereco_cidade, e.endereco_uf, e.ativo, e.created_at, e.updated_at,
               (SELECT COUNT(*) FROM moz_deposito d WHERE d.empresa_id=e.id) AS deps
        FROM empresas e";
if ($where) $sql .= " WHERE ".implode(' AND ',$where);
$sql .= " ORDER BY e.updated_at DESC, e.nome_empresarial ASC LIMIT 1000";

$rows=[];
if ($types){
  $st=$conn->prepare($sql);
  if($st){ $st->bind_param($types, ...$params); $st->execute(); $r=$st->get_result(); while($a=$r->fetch_assoc()) $rows[]=$a; $st->close(); }
  else { flash('erro','Erro ao preparar consulta: '.$conn->error); }
}else{
  $r=$conn->query($sql);
  if($r){ while($a=$r->fetch_assoc()) $rows[]=$a; } else { flash('erro','Erro ao consultar: '.$conn->error); }
}

include_once ROOT_PATH . '/system/includes/head.php';
include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<style>
.table-actions .btn { margin-right: 6px; }
.badge-pill { border-radius: 999px; padding: 4px 10px; font-size: 12px; }
.badge-ativo { background:#e6ffed; color:#177245; border:1px solid #c1f2ce; }
.badge-inativo { background:#fff3cd; color:#8a6d3b; border:1px solid #ffe69c; }
</style>

<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

    <div class="row">
      <div class="col-lg-12">
        <h2>Empresas</h2>

        <?php if ($m = flash('ok')): ?><div class="alert alert-success"><?= h($m) ?></div><?php endif; ?>
        <?php if ($m = flash('erro')): ?><div class="alert alert-danger"><?= h($m) ?></div><?php endif; ?>

        <form class="form-inline" method="get" style="margin-bottom:12px">
          <div class="form-group">
            <input type="text" class="form-control" name="q" placeholder="Buscar por nome, CNPJ, cidade, apelido..."
                   value="<?= h($q) ?>" style="min-width:320px">
          </div>
          <div class="form-group" style="margin-left:8px">
            <select class="form-control" name="status">
              <option value="todos"   <?= $status==='todos'?'selected':'' ?>>Todos</option>
              <option value="ativos"  <?= $status==='ativos'?'selected':'' ?>>Ativos</option>
              <option value="inativos"<?= $status==='inativos'?'selected':'' ?>>Inativos</option>
            </select>
          </div>
          <button class="btn btn-primary" type="submit" style="margin-left:8px">Filtrar</button>
          <a class="btn btn-default" href="<?= h(strtok($_SERVER['REQUEST_URI'],'?')) ?>" style="margin-left:4px">Limpar</a>
          <a class="btn btn-success" href="<?= h($EDIT_PAGE) ?>" style="margin-left:8px">➕ Nova Empresa</a>
          <a href="<?= BASE_URL ?>/pages/empresas_importar.php" class="btn btn-default" style="margin-left:6px;">
  Importar Lista
</a>
        </form>

        <div class="table-responsive">
          <table class="table table-striped table-hover">
            <thead>
              <tr>
                <th style="width:64px">ID</th>
                <th>Razão Social</th>
                <th>Fantasia</th>
                <th>CNPJ</th>
                <th>Cidade/UF</th>
                <th>Apelido</th>
                <th>Status</th>
                <th style="width:240px">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="8" class="text-muted">Nenhuma empresa encontrada.</td></tr>
              <?php else: foreach ($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <td><?= h($r['nome_empresarial']) ?></td>
                  <td><?= h($r['nome_fantasia']) ?></td>
                  <td><?= h($r['cnpj']) ?></td>
                  <td><?= h($r['endereco_cidade']) ?>/<?= h($r['endereco_uf']) ?></td>
                  <td><?= h($r['apelido'] ?? '') ?></td>
                  <td>
                    <?php if ((int)$r['ativo']===1): ?>
                      <span class="badge badge-pill badge-ativo">Ativo</span>
                    <?php else: ?>
                      <span class="badge badge-pill badge-inativo">Inativo</span>
                    <?php endif; ?>
                  </td>
                  <td class="table-actions">
                    <a class="btn btn-xs btn-primary"
                       href="<?= h($EDIT_PAGE) . '?empresa_id=' . (int)$r['id'] ?>">Editar</a>

                    <?php if ((int)$r['ativo']===1): ?>
                      <form method="post" style="display:inline" onsubmit="return confirm('Inativar empresa #<?= (int)$r['id'] ?>?');">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="acao" value="inativar">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-xs btn-warning" type="submit">Inativar</button>
                      </form>
                    <?php else: ?>
                      <form method="post" style="display:inline" onsubmit="return confirm('Reativar empresa #<?= (int)$r['id'] ?>?');">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="acao" value="reativar">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-xs btn-success" type="submit">Reativar</button>
                      </form>
                    <?php endif; ?>

                    <?php if ((int)$r['deps']===0): ?>
                      <form method="post" style="display:inline" onsubmit="return confirm('Excluir DEFINITIVAMENTE a empresa #<?= (int)$r['id'] ?>?');">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-xs btn-danger" type="submit">Excluir</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <p class="text-muted"><small>Exibindo até 1000 registros. Use a busca para refinar.</small></p>
      </div>
    </div>
  </div>
</div>

<?php include_once ROOT_PATH . '/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>
