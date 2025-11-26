<?php
// public/modules/gestao_ativos/categorias-listar.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
proteger_pagina();

// CSRF
if (empty($_SESSION['csrf_cat_list'])) $_SESSION['csrf_cat_list'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_cat_list'];

// Conexão
$dbc = $conn ?? null;
if (!$dbc) { die('Sem conexão com o banco.'); }

// Parâmetros
$q      = trim($_GET['q'] ?? '');
$pid    = $_GET['parent'] ?? '';
$pid    = ($pid === '' ? null : (int)$pid);
$status = $_GET['status'] ?? 'ativos'; // ativos|inativos|todos
$limit  = max(5, min(100, (int)($_GET['limit'] ?? 20)));
$page   = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $limit;

// Ações
$acao = $_GET['acao'] ?? null;
if ($acao === 'toggle') {
  if (!hash_equals($csrf, $_GET['csrf'] ?? '')) die('CSRF inválido.');
  $id = (int)($_GET['id'] ?? 0);
  if ($id > 0) {
    // pega status atual
    $s = $dbc->prepare("SELECT ativo FROM moz_cat_ativo WHERE id=?");
    $s->bind_param('i', $id);
    $s->execute();
    $s->bind_result($ativo);
    $found = $s->fetch();
    $s->close();

    if ($found) {
      $novo = $ativo ? 0 : 1;
      $u = $dbc->prepare("UPDATE moz_cat_ativo SET ativo=? WHERE id=?");
      $u->bind_param('ii', $novo, $id);
      $u->execute();
      $ok = $u->affected_rows >= 0;
      $u->close();
      $_SESSION['flash_ok'] = $ok ? ($novo ? 'Categoria ativada.' : 'Categoria desativada.') : 'Nenhuma alteração.';
    }
  }
  header('Location: '.$_SERVER['PHP_SELF'].'?'.http_build_query(['q'=>$q,'parent'=>$pid,'status'=>$status,'limit'=>$limit,'p'=>$page]));
  exit;
}

if ($acao === 'delete') {
  if (!hash_equals($csrf, $_GET['csrf'] ?? '')) die('CSRF inválido.');
  $id = (int)($_GET['id'] ?? 0);
  if ($id > 0) {
    // Impede excluir pai com filhos
    $chk = $dbc->prepare("SELECT COUNT(*) FROM moz_cat_ativo WHERE pai_id = ?");
    $chk->bind_param('i', $id);
    $chk->execute();
    $chk->bind_result($hasChildren);
    $chk->fetch();
    $chk->close();

    if ($hasChildren > 0) {
      $_SESSION["flash_err"] = "Exclua ou mova as subcategorias antes de remover esta categoria.";
    } else {
      $stmt = $dbc->prepare("DELETE FROM moz_cat_ativo WHERE id = ?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $ok = $stmt->affected_rows > 0;
      $stmt->close();
      $_SESSION['flash_ok'] = $ok ? 'Categoria removida com sucesso.' : 'Nada removido.';
    }
  }
  header('Location: '.$_SERVER['PHP_SELF'].'?'.http_build_query(['q'=>$q,'parent'=>$pid,'status'=>$status,'limit'=>$limit,'p'=>$page]));
  exit;
}

// WHERE dinâmico
$where = [];
$params = [];
$types = '';

if ($q !== '') { $where[] = "c.nome LIKE ?"; $params[] = '%'.$q.'%'; $types .= 's'; }
if ($pid !== null) { $where[] = "c.pai_id = ?"; $params[] = $pid; $types .= 'i'; }
if ($status === 'ativos')     { $where[] = "c.ativo = 1"; }
elseif ($status === 'inativos'){ $where[] = "c.ativo = 0"; }
// 'todos' não filtra ativo

$wsql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// Total
$sqlCount = "SELECT COUNT(*) FROM moz_cat_ativo c $wsql";
$stmtCnt = $dbc->prepare($sqlCount);
if ($types) $stmtCnt->bind_param($types, ...$params);
$stmtCnt->execute();
$stmtCnt->bind_result($total);
$stmtCnt->fetch();
$stmtCnt->close();

$pages = max(1, (int)ceil($total / $limit));

// Dados
$sql = "
  SELECT c.id, c.nome, c.pai_id, c.ativo, p.nome AS pai_nome
  FROM moz_cat_ativo c
  LEFT JOIN moz_cat_ativo p ON p.id = c.pai_id
  $wsql
  ORDER BY COALESCE(p.nome, ''), c.nome
  LIMIT ? OFFSET ?
";
$types2 = $types . 'ii';
$params2 = $params; $params2[] = $limit; $params2[] = $offset;

$stmt = $dbc->prepare($sql);
if ($types2 !== 'ii') $stmt->bind_param($types2, ...$params2);
else $stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Pais para filtro
$allParents = [];
$rpp = $dbc->query("SELECT id, nome FROM moz_cat_ativo ORDER BY nome");
while ($r = $rpp->fetch_assoc()) $allParents[] = $r;

// Includes visuais
include_once ROOT_PATH . 'system/includes/head.php';
include_once ROOT_PATH . 'system/includes/navbar.php';
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <h3 class="page-header">Categorias de Ativos</h3>

    <?php if (!empty($_SESSION['flash_ok'])): ?>
      <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_ok']) ?></div>
      <?php unset($_SESSION['flash_ok']); endif; ?>
    <?php if (!empty($_SESSION['flash_err'])): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_err']) ?></div>
      <?php unset($_SESSION['flash_err']); endif; ?>

    <div class="panel panel-default">
      <div class="panel-heading">Filtros</div>
      <div class="panel-body">
        <form class="form-inline" method="get">
          <div class="form-group" style="margin-right:10px">
            <label for="q">Busca</label>
            <input type="text" class="form-control" id="q" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="nome...">
          </div>
          <div class="form-group" style="margin-right:10px">
            <label for="parent">Pai</label>
            <select class="form-control" id="parent" name="parent">
              <option value="">— todos —</option>
              <?php foreach ($allParents as $popt): ?>
                <option value="<?= (int)$popt['id'] ?>" <?= ($pid===(int)$popt['id'])?'selected':'' ?>>
                  <?= htmlspecialchars($popt['nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin-right:10px">
            <label for="status">Status</label>
            <select class="form-control" id="status" name="status">
              <option value="ativos"   <?= $status==='ativos'?'selected':'' ?>>Ativos</option>
              <option value="inativos" <?= $status==='inativos'?'selected':'' ?>>Inativos</option>
              <option value="todos"    <?= $status==='todos'?'selected':'' ?>>Todos</option>
            </select>
          </div>
          <div class="form-group" style="margin-right:10px">
            <label for="limit">Itens/página</label>
            <select class="form-control" id="limit" name="limit">
              <?php foreach ([10,20,50,100] as $L): ?>
                <option value="<?= $L ?>" <?= $L===$limit?'selected':'' ?>><?= $L ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-primary">Aplicar</button>
          <a class="btn btn-default" href="<?= $_SERVER['PHP_SELF'] ?>">Limpar</a>
          <a class="btn btn-success pull-right" href="<?= BASE_URL ?>/modules/gestao_ativos/categorias-form.php">+ Nova categoria</a>
        </form>
      </div>
    </div>

    <div class="panel panel-default">
      <div class="panel-heading">Listagem (<?= (int)$total ?>)</div>
      <div class="panel-body">
        <div class="table-responsive">
          <table class="table table-striped table-bordered">
            <thead>
              <tr>
                <th style="width:80px">ID</th>
                <th>Nome</th>
                <th>Pai</th>
                <th style="width:110px">Status</th>
                <th style="width:150px">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-center text-muted">Nenhum registro.</td></tr>
              <?php else: foreach ($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <td><?= htmlspecialchars($r['nome']) ?></td>
                  <td><?= htmlspecialchars($r['pai_nome'] ?? '—') ?></td>
                  <td>
                    <?php if ((int)$r['ativo'] === 1): ?>
                      <span class="label label-success">Ativo</span>
                    <?php else: ?>
                      <span class="label label-default">Inativo</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <!-- Toggle -->
                    <a title="<?= $r['ativo'] ? 'Desativar' : 'Ativar' ?>"
                       href="<?= $_SERVER['PHP_SELF'].'?'.http_build_query([
                          'acao'=>'toggle','id'=>$r['id'],'q'=>$q,'parent'=>$pid,'status'=>$status,'limit'=>$limit,'p'=>$page,'csrf'=>$csrf
                        ]) ?>">
                      <i class="fa <?= $r['ativo'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                    </a>
                    &nbsp;&nbsp;
                    <!-- Editar -->
                    <a title="Editar" href="<?= BASE_URL ?>/modules/gestao_ativos/categorias-form.php?id=<?= (int)$r['id'] ?>">
                      <i class="fa fa-pencil"></i>
                    </a>
                    &nbsp;&nbsp;
                    <!-- Excluir -->
                    <a title="Excluir"
                       href="<?= $_SERVER['PHP_SELF'].'?'.http_build_query([
                          'acao'=>'delete','id'=>$r['id'],'q'=>$q,'parent'=>$pid,'status'=>$status,'limit'=>$limit,'p'=>$page,'csrf'=>$csrf
                        ]) ?>"
                       onclick="return confirm('Confirma a exclusão?');">
                      <i class="fa fa-trash text-danger"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <?php if ($pages > 1): ?>
          <nav aria-label="Paginação">
            <ul class="pagination">
              <?php
              $mk = function($p) use ($q,$pid,$status,$limit){
                return $_SERVER['PHP_SELF'].'?'.http_build_query(['q'=>$q,'parent'=>$pid,'status'=>$status,'limit'=>$limit,'p'=>$p]);
              };
              ?>
              <li class="<?= $page<=1?'disabled':'' ?>"><a href="<?= $mk(max(1,$page-1)) ?>">&laquo;</a></li>
              <?php for ($i=1;$i<=$pages;$i++): ?>
                <li class="<?= $i===$page?'active':'' ?>"><a href="<?= $mk($i) ?>"><?= $i ?></a></li>
              <?php endfor; ?>
              <li class="<?= $page>=$pages?'disabled':'' ?>"><a href="<?= $mk(min($pages,$page+1)) ?>">&raquo;</a></li>
            </ul>
          </nav>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include_once ROOT_PATH . 'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . 'system/includes/footer.php'; ?>
