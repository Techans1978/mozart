<?php
// public/modules/gestao_ativos/fabricantes-distribuidores-listar.php
ini_set('display_errors',1); ini_set('startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

if (empty($_SESSION['csrf_fabdist_list'])) $_SESSION['csrf_fabdist_list']=bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_fabdist_list'];

$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

function table_has_col($dbc,$table,$col){
  $rs = $dbc->query("SHOW COLUMNS FROM {$table} LIKE '{$dbc->real_escape_string($col)}'");
  return $rs && $rs->num_rows>0;
}
$marcaHasAtivo = table_has_col($dbc,'moz_marca','ativo');
$fornHasAtivo  = table_has_col($dbc,'moz_fornecedor','ativo');

$tab     = $_GET['tab'] ?? 'fabricantes';           // fabricantes | distribuidores
$tab     = in_array($tab,['fabricantes','distribuidores']) ? $tab : 'fabricantes';
$q       = trim($_GET['q'] ?? '');
$status  = $_GET['status'] ?? 'ativos';             // ativos|inativos|todos (só se tiver coluna ativo)
$limit   = max(5, min(100,(int)($_GET['limit']??20)));
$page    = max(1,(int)($_GET['p']??1));
$offset  = ($page-1)*$limit;

$acao = $_GET['acao'] ?? null;
$id   = (int)($_GET['id'] ?? 0);

$hasAtivo = ($tab==='fabricantes') ? $marcaHasAtivo : $fornHasAtivo;
$table    = ($tab==='fabricantes') ? 'moz_marca' : 'moz_fornecedor';

// AÇÕES: toggle/excluir
if ($acao==='toggle' && $hasAtivo) {
  if (!hash_equals($csrf, $_GET['csrf'] ?? '')) die('CSRF inválido.');
  if ($id>0){
    $q1=$dbc->prepare("SELECT ativo FROM {$table} WHERE id=?");
    $q1->bind_param('i',$id); $q1->execute(); $q1->bind_result($a); $found=$q1->fetch(); $q1->close();
    if ($found){
      $novo = $a ? 0 : 1;
      $u=$dbc->prepare("UPDATE {$table} SET ativo=? WHERE id=?");
      $u->bind_param('ii',$novo,$id); $u->execute(); $u->close();
      $_SESSION['flash_ok'] = $novo? 'Ativado.' : 'Desativado.';
    }
  }
  header('Location: '.$_SERVER['PHP_SELF'].'?'.http_build_query(['tab'=>$tab,'q'=>$q,'status'=>$status,'limit'=>$limit,'p'=>$page])); exit;
}

if ($acao==='delete') {
  if (!hash_equals($csrf, $_GET['csrf'] ?? '')) die('CSRF inválido.');
  if ($id>0){
    // Regras simples de integridade:
    if ($tab==='fabricantes'){
      // não deixar excluir se marca usada em moz_ativo ou moz_modelo
      $emUso=0;
      $chk=$dbc->prepare("SELECT COUNT(*) FROM moz_ativo WHERE marca_id=?");
      $chk->bind_param('i',$id); $chk->execute(); $chk->bind_result($c1); $chk->fetch(); $chk->close(); $emUso += (int)$c1;
      $chk=$dbc->prepare("SELECT COUNT(*) FROM moz_modelo WHERE marca_id=?");
      $chk->bind_param('i',$id); $chk->execute(); $chk->bind_result($c2); $chk->fetch(); $chk->close(); $emUso += (int)$c2;
      if ($emUso>0){ $_SESSION['flash_err']='Não é possível excluir: há ativos/modelos vinculados.'; }
      else {
        $d=$dbc->prepare("DELETE FROM moz_marca WHERE id=?");
        $d->bind_param('i',$id); $d->execute(); $ok=$d->affected_rows>0; $d->close();
        $_SESSION['flash_ok'] = $ok? 'Fabricante removido.' : 'Nada removido.';
      }
    } else {
      // distribuidores: não excluir se houver ativos apontando fornecedor_id
      $chk=$dbc->prepare("SELECT COUNT(*) FROM moz_ativo WHERE fornecedor_id=?");
      $chk->bind_param('i',$id); $chk->execute(); $chk->bind_result($emUso); $chk->fetch(); $chk->close();
      if ($emUso>0){ $_SESSION['flash_err']='Não é possível excluir: há ativos vinculados a este distribuidor.'; }
      else {
        $d=$dbc->prepare("DELETE FROM moz_fornecedor WHERE id=?");
        $d->bind_param('i',$id); $d->execute(); $ok=$d->affected_rows>0; $d->close();
        $_SESSION['flash_ok'] = $ok? 'Distribuidor removido.' : 'Nada removido.';
      }
    }
  }
  header('Location: '.$_SERVER['PHP_SELF'].'?'.http_build_query(['tab'=>$tab,'q'=>$q,'status'=>$status,'limit'=>$limit,'p'=>$page])); exit;
}

// WHERE dinâmico
$where=[]; $params=[]; $types='';
if ($q!==''){
  if ($tab==='fabricantes'){ $where[]="nome LIKE ?"; $params[]='%'.$q.'%'; $types.='s'; }
  else { // distribuidores pode buscar em múltiplos campos
    $where[]="(nome LIKE ? OR cnpj LIKE ? OR email LIKE ? OR telefone LIKE ?)";
    array_push($params, '%'.$q.'%','%'.$q.'%','%'.$q.'%','%'.$q.'%');
    $types.='ssss';
  }
}
if ($hasAtivo){
  if ($status==='ativos')   $where[]="ativo=1";
  if ($status==='inativos') $where[]="ativo=0";
}
$wsql = $where? ('WHERE '.implode(' AND ',$where)) : '';

// Total + Dados
$sqlCount = "SELECT COUNT(*) FROM {$table} {$wsql}";
$st=$dbc->prepare($sqlCount);
if ($types) $st->bind_param($types, ...$params);
$st->execute(); $st->bind_result($total); $st->fetch(); $st->close();
$pages=max(1,(int)ceil($total/$limit));

$cols = ($tab==='fabricantes')
  ? ("id, nome".($hasAtivo?", ativo":""))
  : ("id, nome, cnpj, telefone, email".($hasAtivo?", ativo":""));

$sql = "SELECT $cols FROM {$table} {$wsql} ORDER BY nome LIMIT ? OFFSET ?";
$types2=$types.'ii'; $params2=$params; $params2[]=$limit; $params2[]=$offset;

$st=$dbc->prepare($sql);
if ($types2!=='ii') $st->bind_param($types2, ...$params2);
else $st->bind_param('ii',$limit,$offset);
$st->execute(); $res=$st->get_result(); $rows=$res->fetch_all(MYSQLI_ASSOC); $st->close();

// includes visuais
include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<div id="page-wrapper"><div class="container-fluid">
  <h3 class="page-header">Fabricantes & Distribuidores</h3>

  <?php if(!empty($_SESSION['flash_ok'])): ?><div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_ok']) ?></div><?php unset($_SESSION['flash_ok']); endif; ?>
  <?php if(!empty($_SESSION['flash_err'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_err']) ?></div><?php unset($_SESSION['flash_err']); endif; ?>

  <ul class="nav nav-tabs" style="margin-bottom:15px">
    <li class="<?= $tab==='fabricantes'?'active':'' ?>"><a href="<?= $_SERVER['PHP_SELF'].'?'.http_build_query(['tab'=>'fabricantes','q'=>$q,'status'=>$status,'limit'=>$limit]) ?>">Fabricantes</a></li>
    <li class="<?= $tab==='distribuidores'?'active':'' ?>"><a href="<?= $_SERVER['PHP_SELF'].'?'.http_build_query(['tab'=>'distribuidores','q'=>$q,'status'=>$status,'limit'=>$limit]) ?>">Distribuidores</a></li>
    <a class="btn btn-success pull-right" href="<?= BASE_URL ?>/modules/gestao_ativos/fabricantes-distribuidores-cadastro.php?tab=<?= $tab ?>" style="margin-left:8px">+ Novo</a>
  </ul>

  <div class="panel panel-default">
    <div class="panel-heading">Filtros</div>
    <div class="panel-body">
      <form method="get" class="form-inline">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <div class="form-group" style="margin-right:10px">
          <label for="q">Busca</label>
          <input class="form-control" id="q" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="nome, cnpj, email..." />
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
            <?php foreach([10,20,50,100] as $L): ?>
              <option value="<?= $L ?>" <?= $L===$limit?'selected':'' ?>><?= $L ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary">Aplicar</button>
        <a class="btn btn-default" href="<?= $_SERVER['PHP_SELF'].'?tab='.$tab ?>">Limpar</a>
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
              <?php if ($tab==='distribuidores'): ?>
                <th style="width:150px">CNPJ</th>
                <th style="width:150px">Telefone</th>
                <th>Email</th>
              <?php endif; ?>
              <?php if ($hasAtivo): ?><th style="width:110px">Status</th><?php endif; ?>
              <th style="width:150px">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php if(!$rows): ?>
              <tr><td colspan="<?= ($tab==='distribuidores' ? ( $hasAtivo?6:5 ) : ( $hasAtivo?4:3 )) ?>" class="text-center text-muted">Nenhum registro.</td></tr>
            <?php else: foreach($rows as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= htmlspecialchars($r['nome']) ?></td>
                <?php if ($tab==='distribuidores'): ?>
                  <td><?= htmlspecialchars($r['cnpj'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['telefone'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
                <?php endif; ?>
                <?php if ($hasAtivo): ?>
                  <td><?= !empty($r['ativo']) ? '<span class="label label-success">Ativo</span>' : '<span class="label label-default">Inativo</span>' ?></td>
                <?php endif; ?>
                <td>
                  <?php if ($hasAtivo): ?>
                    <a title="<?= !empty($r['ativo'])?'Desativar':'Ativar' ?>" href="<?= $_SERVER['PHP_SELF'].'?'.http_build_query(['tab'=>$tab,'acao'=>'toggle','id'=>$r['id'],'q'=>$q,'status'=>$status,'limit'=>$limit,'p'=>$page,'csrf'=>$csrf]) ?>">
                      <i class="fa <?= !empty($r['ativo'])? 'fa-toggle-on':'fa-toggle-off' ?>"></i>
                    </a>&nbsp;&nbsp;
                  <?php endif; ?>
                  <a title="Editar" href="<?= BASE_URL ?>/modules/gestao_ativos/fabricantes-distribuidores-cadastro.php?tab=<?= $tab ?>&id=<?= (int)$r['id'] ?>"><i class="fa fa-pencil"></i></a>&nbsp;&nbsp;
                  <a title="Excluir" href="<?= $_SERVER['PHP_SELF'].'?'.http_build_query(['tab'=>$tab,'acao'=>'delete','id'=>$r['id'],'q'=>$q,'status'=>$status,'limit'=>$limit,'p'=>$page,'csrf'=>$csrf]) ?>" onclick="return confirm('Confirma a exclusão?');"><i class="fa fa-trash text-danger"></i></a>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($pages>1): ?>
      <nav aria-label="Paginação"><ul class="pagination">
        <?php $mk=function($p)use($tab,$q,$status,$limit){ return $_SERVER['PHP_SELF'].'?'.http_build_query(['tab'=>$tab,'q'=>$q,'status'=>$status,'limit'=>$limit,'p'=>$p]); }; ?>
        <li class="<?= $page<=1?'disabled':'' ?>"><a href="<?= $mk(max(1,$page-1)) ?>">&laquo;</a></li>
        <?php for($i=1;$i<=$pages;$i++): ?>
          <li class="<?= $i===$page?'active':'' ?>"><a href="<?= $mk($i) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
        <li class="<?= $page>=$pages?'disabled':'' ?>"><a href="<?= $mk(min($pages,$page+1)) ?>">&raquo;</a></li>
      </ul></nav>
      <?php endif; ?>
    </div>
  </div>
</div></div>

<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
