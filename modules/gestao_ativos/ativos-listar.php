<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

/* ======================== Helpers e bootstrap ======================== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

function table_exists(mysqli $db,$t){
  $t = $db->real_escape_string($t);
  $r = $db->query("SHOW TABLES LIKE '$t'");
  return $r && $r->num_rows>0;
}
$hasModelo = table_exists($dbc,'moz_modelo');

/* CSRF p/ ações da listagem */
if (empty($_SESSION['csrf_ativos'])) $_SESSION['csrf_ativos'] = bin2hex(random_bytes(16));
$csrf_list = $_SESSION['csrf_ativos'];

/* Flag campo 'ativo' em moz_ativo (para Ativar/Inativar) */
$hasAtivoFlag = table_exists($dbc,'moz_ativo') && (function(mysqli $db){
  $r=$db->query("SHOW COLUMNS FROM moz_ativo LIKE 'ativo'");
  return $r && $r->num_rows>0;
})($dbc);

/* ======================== Ações (POST) ======================== */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['acao'])) {
  if (!hash_equals($csrf_list, $_POST['csrf'] ?? '')) die('CSRF inválido.');
  $id = (int)($_POST['id'] ?? 0);
  if ($id<=0) { $_SESSION['flash_err']='ID inválido.'; header('Location: '.$_SERVER['PHP_SELF'].'?'.http_build_query($_GET)); exit; }

  try {
    if ($_POST['acao']==='toggle' && $hasAtivoFlag) {
      // alterna 0/1
      $st = $dbc->prepare("UPDATE moz_ativo SET ativo = 1 - ativo WHERE id=? LIMIT 1");
      $st->bind_param('i',$id); $st->execute(); $st->close();
      $_SESSION['flash_ok']='Registro atualizado (ativo/inativo).';

    } elseif ($_POST['acao']==='delete') {
      // exclusão (pode falhar se houver FKs)
      $st=$dbc->prepare("DELETE FROM moz_ativo WHERE id=? LIMIT 1");
      $st->bind_param('i',$id); $st->execute(); $st->close();
      if ($dbc->affected_rows>0) {
        $_SESSION['flash_ok']='Ativo excluído com sucesso.';
      } else {
        $_SESSION['flash_err']='Não foi possível excluir (nenhuma linha afetada).';
      }

    } else {
      $_SESSION['flash_err']='Ação não suportada.';
    }
  } catch (mysqli_sql_exception $e) {
    $_SESSION['flash_err']='Erro ao processar ação: '.$e->getMessage();
  }

  header('Location: '.$_SERVER['PHP_SELF'].'?'.http_build_query($_GET)); exit;
}

/* ======================== Filtros ======================== */
$q       = trim($_GET['q'] ?? '');
$cat     = (int)($_GET['cat_id'] ?? 0);
$marca   = (int)($_GET['marca_id'] ?? 0);
$status  = ($_GET['status_id'] ?? '')!=='' ? (int)$_GET['status_id'] : null;
$ativo   = ($_GET['ativo'] ?? '')!=='' ? (int)$_GET['ativo'] : null;
$limit   = max(10, min(100, (int)($_GET['limit'] ?? 20)));
$page    = max(1,(int)($_GET['p'] ?? 1));
$offset  = ($page-1)*$limit;

/* WHERE dinâmico */
$where=[]; $types=''; $args=[];
if($q!==''){ $where[]="(a.nome LIKE ? OR a.tag_patrimonial LIKE ? OR a.numero_serie LIKE ? OR f.nome LIKE ?)";
             $types.='ssss'; array_push($args,"%$q%","%$q%","%$q%","%$q%"); }
if($cat>0){ $where[]="a.cat_id=?"; $types.='i'; $args[]=$cat; }
if($marca>0){ $where[]="a.marca_id=?"; $types.='i'; $args[]=$marca; }
if($status!==null){ $where[]="a.status_id=?"; $types.='i'; $args[]=$status; }
if($ativo!==null){ $where[]="a.ativo=?"; $types.='i'; $args[]=$ativo; }
$wsql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

/* Total */
$sqlCount="SELECT COUNT(*) FROM moz_ativo a LEFT JOIN moz_fornecedor f ON f.id=a.fornecedor_id $wsql";
$st=$dbc->prepare($sqlCount); if($types) $st->bind_param($types,...$args);
$st->execute(); $st->bind_result($total); $st->fetch(); $st->close();
$pages=max(1,(int)ceil($total/$limit));

/* Dados */
$sql="SELECT a.id,a.nome,a.tag_patrimonial,a.numero_serie,a.status_id,a.ativo,
             c.nome AS categoria, m.nome AS marca, ".($hasModelo?"mo.nome AS modelo,":"'' AS modelo,")."
             d.nome AS local_nome, f.nome AS fornecedor
      FROM moz_ativo a
      LEFT JOIN moz_cat_ativo c ON c.id=a.cat_id
      LEFT JOIN moz_marca m ON m.id=a.marca_id
      ".($hasModelo?"LEFT JOIN moz_modelo mo ON mo.id=a.modelo_id":"")."
      LEFT JOIN moz_deposito d ON d.id=a.local_id
      LEFT JOIN moz_fornecedor f ON f.id=a.fornecedor_id
      $wsql
      ORDER BY a.created_at DESC
      LIMIT ? OFFSET ?";
$types2=$types.'ii'; $args2=$args; array_push($args2,$limit,$offset);
$st=$dbc->prepare($sql); if($types2) $st->bind_param($types2,...$args2);
$st->execute(); $res=$st->get_result(); $rows=$res->fetch_all(MYSQLI_ASSOC); $st->close();

/* Combos */
$cats=[]; $r=$dbc->query("SELECT id,nome FROM moz_cat_ativo WHERE ativo=1 ORDER BY nome"); if($r) while($x=$r->fetch_assoc()) $cats[]=$x;
$marcas=[]; $r=$dbc->query("SELECT id,nome FROM moz_marca WHERE ativo=1 ORDER BY nome"); if($r) while($x=$r->fetch_assoc()) $marcas[]=$x;

/* Layout */
include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header">Ativos</h1></div></div>
  <div class="row"><div class="col-lg-12">

    <div class="panel panel-default">
      <div class="panel-heading">Filtros</div>
      <div class="panel-body">
        <form class="form-inline" method="get">
          <input class="form-control" name="q" value="<?=h($q)?>" placeholder="busca (nome, tag, série, fornecedor)">
          <select class="form-control" name="cat_id">
            <option value="0">— Categoria —</option>
            <?php foreach($cats as $c): ?>
              <option value="<?=$c['id']?>" <?=$cat===$c['id']?'selected':''?>><?=h($c['nome'])?></option>
            <?php endforeach; ?>
          </select>
          <select class="form-control" name="marca_id">
            <option value="0">— Marca —</option>
            <?php foreach($marcas as $m): ?>
              <option value="<?=$m['id']?>" <?=$marca===$m['id']?'selected':''?>><?=h($m['nome'])?></option>
            <?php endforeach; ?>
          </select>
          <select class="form-control" name="status_id">
            <option value="">— Status operacional —</option>
            <?php foreach([1=>'Em operação',2=>'Em estoque',3=>'Emprestado',4=>'Alugado',5=>'Em manutenção',6=>'Baixado'] as $k=>$v): ?>
              <option value="<?=$k?>" <?=$status===$k?'selected':''?>><?=$v?></option>
            <?php endforeach; ?>
          </select>
          <select class="form-control" name="ativo">
            <option value="">— Registro —</option>
            <option value="1" <?=$ativo===1?'selected':''?>>Ativo</option>
            <option value="0" <?=$ativo===0?'selected':''?>>Inativo</option>
          </select>
          <select class="form-control" name="limit">
            <?php foreach([20,50,100] as $L): ?><option <?=$L==$limit?'selected':''?>><?=$L?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-primary">Aplicar</button>
          <a class="btn btn-default" href="<?= $_SERVER['PHP_SELF'] ?>">Limpar</a>
          <a class="btn btn-success pull-right" href="<?= BASE_URL ?>/modules/gestao_ativos/ativos-form.php" style="margin-left:8px">+ Novo</a>
          <a class="btn btn-info pull-right" href="<?= BASE_URL ?>/modules/gestao_ativos/ativos-importar.php">Importar CSV</a>
        </form>
      </div>
    </div>

    <div class="panel panel-default">
      <div class="panel-heading">Listagem (<?= (int)$total ?>)</div>
      <div class="panel-body">

        <?php if (!empty($_SESSION['flash_ok'])): ?>
          <div class="alert alert-success"><?= h($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_err'])): ?>
          <div class="alert alert-danger"><?= h($_SESSION['flash_err']); unset($_SESSION['flash_err']); ?></div>
        <?php endif; ?>

        <?php if(!$rows): ?>
          <div class="alert alert-info">Nenhum ativo encontrado.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-striped table-bordered">
              <thead><tr>
                <th>ID</th><th>Tag</th><th>Nome</th><th>Categoria</th><th>Marca / Modelo</th><th>Série</th><th>Status</th><th>Local</th><th style="width:220px">Ações</th>
              </tr></thead>
              <tbody>
                <?php foreach($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <td><?= h($r['tag_patrimonial'] ?: '—') ?></td>
                  <td><?= h($r['nome']) ?></td>
                  <td><?= h($r['categoria'] ?: '—') ?></td>
                  <td><?= h(trim(($r['marca']?:'').' / '.($r['modelo']?:''),' /') ?: '—') ?></td>
                  <td><?= h($r['numero_serie'] ?: '—') ?></td>
                  <td><?= h([1=>'Em operação',2=>'Em estoque',3=>'Emprestado',4=>'Alugado',5=>'Em manutenção',6=>'Baixado'][$r['status_id']] ?? $r['status_id']) ?></td>
                  <td><?= h($r['local_nome'] ?: '—') ?></td>
                  <td>
                    <a class="btn btn-xs btn-default" href="<?= BASE_URL ?>/modules/gestao_ativos/ativos-form.php?id=<?= (int)$r['id'] ?>">Editar</a>

                    <?php if ($hasAtivoFlag): ?>
                      <form method="post" style="display:inline" onsubmit="return confirmToggle(<?= (int)$r['id'] ?>, <?= (int)$r['ativo'] ?>)">
                        <input type="hidden" name="csrf" value="<?= h($csrf_list) ?>">
                        <input type="hidden" name="acao" value="toggle">
                        <input type="hidden" name="id"   value="<?= (int)$r['id'] ?>">
                        <?php if ((int)$r['ativo']===1): ?>
                          <button class="btn btn-xs btn-warning" type="submit" title="Inativar">Inativar</button>
                        <?php else: ?>
                          <button class="btn btn-xs btn-success" type="submit" title="Ativar">Ativar</button>
                        <?php endif; ?>
                      </form>
                    <?php endif; ?>

                    <form method="post" style="display:inline" onsubmit="return confirmDelete(<?= (int)$r['id'] ?>,'<?= h($r['nome']) ?>')">
                      <input type="hidden" name="csrf" value="<?= h($csrf_list) ?>">
                      <input type="hidden" name="acao" value="delete">
                      <input type="hidden" name="id"   value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-xs btn-danger" type="submit" title="Excluir">Excluir</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if($pages>1):
            $mk=function($p)use($q,$cat,$marca,$status,$ativo,$limit){ return $_SERVER['PHP_SELF'].'?'.http_build_query(compact('q','cat','marca','status','ativo','limit')+['p'=>$p]); }; ?>
            <nav><ul class="pagination">
              <li class="<?= $page<=1?'disabled':'' ?>"><a href="<?= $mk(max(1,$page-1)) ?>">&laquo;</a></li>
              <?php for($i=1;$i<=$pages;$i++): ?><li class="<?= $i===$page?'active':'' ?>"><a href="<?= $mk($i) ?>"><?= $i ?></a></li><?php endfor; ?>
              <li class="<?= $page>=$pages?'disabled':'' ?>"><a href="<?= $mk(min($pages,$page+1)) ?>">&raquo;</a></li>
            </ul></nav>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

  </div></div>
</div></div>

<script>
  function confirmDelete(id, nome) {
    return confirm(`Tem certeza que deseja EXCLUIR o ativo #${id} — ${nome}? Esta ação é irreversível.`);
  }
  function confirmToggle(id, ativo) {
    const msg = Number(ativo) === 1
      ? `Inativar o ativo #${id}? Ele deixará de aparecer como "Ativo".`
      : `Ativar o ativo #${id}?`;
    return confirm(msg);
  }
</script>

<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
