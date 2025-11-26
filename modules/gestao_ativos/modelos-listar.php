<?php
// public/modules/gestao_ativos/modelos-listar.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
proteger_pagina();

// CSRF
if (empty($_SESSION['csrf_modelo_list'])) $_SESSION['csrf_modelo_list'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_modelo_list'];

// Conexão
$dbc = $conn ?? null;
if (!$dbc) { die('Sem conexão com o banco.'); }

// Descobre se existe coluna "ativo" em moz_modelo
function table_has_ativo($dbc) {
  $rs = $dbc->query("SHOW COLUMNS FROM moz_modelo LIKE 'ativo'");
  return $rs && $rs->num_rows > 0;
}
$hasAtivo = table_has_ativo($dbc);

// Parâmetros
$q      = trim($_GET['q'] ?? '');
$marca  = $_GET['marca'] ?? '';
$marca  = ($marca === '' ? null : (int)$marca);
$status = $_GET['status'] ?? 'ativos'; // ativos|inativos|todos (só se $hasAtivo)
if (!$hasAtivo) $status = 'todos';
$limit  = max(5, min(100, (int)($_GET['limit'] ?? 20)));
$page   = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $limit;

// Ações
$acao = $_GET['acao'] ?? null;

if ($acao === 'toggle' && $hasAtivo) {
  if (!hash_equals($csrf, $_GET['csrf'] ?? '')) die('CSRF inválido.');
  $id = (int)($_GET['id'] ?? 0);
  if ($id > 0) {
    $s = $dbc->prepare("SELECT ativo FROM moz_modelo WHERE id=?");
    $s->bind_param('i', $id);
    $s->execute();
    $s->bind_result($ativo);
    $found = $s->fetch();
    $s->close();

    if ($found) {
      $novo = $ativo ? 0 : 1;
      $u = $dbc->prepare("UPDATE moz_modelo SET ativo=? WHERE id=?");
      $u->bind_param('ii', $novo, $id);
      $u->execute();
      $ok = $u->affected_rows >= 0;
      $u->close();
      $_SESSION['flash_ok'] = $ok ? ($novo ? 'Modelo ativado.' : 'Modelo desativado.') : 'Nenhuma alteração.';
    }
  }
  header('Location: '.$_SERVER['PHP_SELF'].'?'.http_build_query(['q'=>$q,'marca'=>$marca,'status'=>$status,'limit'=>$limit,'p'=>$page]));
  exit;
}

if ($acao === 'delete') {
  if (!hash_equals($csrf, $_GET['csrf'] ?? '')) die('CSRF inválido.');
  $id = (int)($_GET['id'] ?? 0);
  if ($id > 0) {
    // Impede excluir se houver ativos usando este modelo
    $chk = $dbc->prepare("SELECT COUNT(*) FROM moz_ativo WHERE modelo_id = ?");
    $chk->bind_param('i', $id);
    $chk->execute();
    $chk->bind_result($emUso);
    $chk->fetch();
    $chk->close();

    if ($emUso > 0) {
      $_SESSION['flash_err'] = 'Não é possível excluir: há ativos vinculados a este modelo.';
    } else {
      $stmt = $dbc->prepare("DELETE FROM moz_modelo WHERE id = ?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $ok = $stmt->affected_rows > 0;
      $stmt->close();
      $_SESSION['flash_ok'] = $ok ? 'Modelo removido com sucesso.' : 'Nada removido.';
    }
  }
  header('Location: '.$_SERVER['PHP_SELF'].'?'.http_build_query(['q'=>$q,'marca'=>$marca,'status'=>$status,'limit'=>$limit,'p'=>$page]));
  exit;
}

// WHERE dinâmico
$where = [];
$params = [];
$types = '';

if ($q !== '') { $where[] = "m.nome LIKE ?"; $params[] = '%'.$q.'%'; $types .= 's'; }
if ($marca !== null) { $where[] = "m.marca_id = ?"; $params[] = $marca; $types .= 'i'; }
if ($hasAtivo) {
  if ($status === 'ativos')     { $where[] = "m.ativo = 1"; }
  elseif ($status === 'inativos'){ $where[] = "m.ativo = 0"; }
}
$wsql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// Totais
$sqlCount = "SELECT COUNT(*) FROM moz_modelo m $wsql";
$stmtCnt = $dbc->prepare($sqlCount);
if ($types) $stmtCnt->bind_param($types, ...$params);
$stmtCnt->execute();
$stmtCnt->bind_result($total);
$stmtCnt->fetch();
$stmtCnt->close();

$pages = max(1, (int)ceil($total / $limit));

// Dados
$cols = "m.id, m.nome, m.marca_id, ma.nome AS marca_nome".($hasAtivo ? ", m.ativo" : "");
$sql = "
  SELECT $cols
  FROM moz_modelo m
  JOIN moz_marca ma ON ma.id = m.marca_id
  $wsql
  ORDER BY ma.nome, m.nome
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

// Marcas para filtro/select
$marcas = [];
$rpp = $dbc->query("SELECT id, nome FROM moz_marca ORDER BY nome");
while ($r = $rpp->fetch_assoc()) $marcas[] = $r;

// Includes visuais
include_once ROOT_PATH . 'system/includes/head.php';
include_once ROOT_PATH . 'system/includes/navbar.php';
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <h3 class="page-header">Modelos</h3>

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
            <input type="text" class="form-control" id="q" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="nome do modelo...">
          </div>
          <div class="form-group" style="margin-right:10px">
            <label for="marca">Marca</label>
            <select class="form-control" id="marca" name="marca">
              <option value="">— todas —</option>
              <?php foreach ($marcas as $m): ?>
                <option value="<?= (int)$m['id'] ?>" <?= ($marca===(int)$m['id'])?'selected':'' ?>>
                  <?= htmlspecialchars($m['nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if ($hasAtivo): ?>
          <div class="form-group" style="margin-right:10px">
            <label for="status">Status</label>
            <select class="form-control" id="status" name="status">
              <option value="ativos"   <?= $status==='ativos'?'selected':'' ?>>Ativos</option>
              <option value="inativos" <?= $status==='inativos'?'selected':'' ?>>Inativos</option>
              <option value="todos"    <?= $status==='todos'?'selected':'' ?>>Todos</option>
            </select>
          </div>
          <?php endif; ?>
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
          <a class="btn btn-success pull-right" href="<?= BASE_URL ?>/modules/gestao_ativos/modelos-form.php">+ Novo modelo</a>
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
                <th>Modelo</th>
                <th>Marca</th>
                <?php if ($hasAtivo): ?><th style="width:110px">Status</th><?php endif; ?>
                <th style="width:150px">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="<?= $hasAtivo?5:4 ?>" class="text-center text-muted">Nenhum registro.</td></tr>
              <?php else: foreach ($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <td><?= htmlspecialchars($r['nome']) ?></td>
                  <td><?= htmlspecialchars($r['marca_nome']) ?></td>
                  <?php if ($hasAtivo): ?>
                  <td>
                    <?php if ((int)$r['ativo'] === 1): ?>
                      <span class="label label-success">Ativo</span>
                    <?php else: ?>
                      <span class="label label-default">Inativo</span>
                    <?php endif; ?>
                  </td>
                  <?php endif; ?>
                  <td>
                    <?php if ($hasAtivo): ?>
                    <a title="<?= !empty($r['ativo']) ? 'Desativar' : 'Ativar' ?>"
                       href="<?= $_SERVER['PHP_SELF'].'?'.http_build_query([
                          'acao'=>'toggle','id'=>$r['id'],'q'=>$q,'marca'=>$marca,'status'=>$status,'limit'=>$limit,'p'=>$page,'csrf'=>$csrf
                        ]) ?>">
                      <i class="fa <?= !empty($r['ativo']) ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                    </a>
                    &nbsp;&nbsp;
                    <?php endif; ?>
                    <a title="Editar" href="<?= BASE_URL ?>/modules/gestao_ativos/modelos-form.php?id=<?= (int)$r['id'] ?>">
                      <i class="fa fa-pencil"></i>
                    </a>
                    &nbsp;&nbsp;
                    <a title="Excluir"
                       href="<?= $_SERVER['PHP_SELF'].'?'.http_build_query([
                          'acao'=>'delete','id'=>$r['id'],'q'=>$q,'marca'=>$marca,'status'=>$status,'limit'=>$limit,'p'=>$page,'csrf'=>$csrf
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
              $mk = function($p) use ($q,$marca,$status,$limit){
                return $_SERVER['PHP_SELF'].'?'.http_build_query(['q'=>$q,'marca'=>$marca,'status'=>$status,'limit'=>$limit,'p'=>$p]);
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
