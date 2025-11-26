<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

// sanity checks
$tblDep = $dbc->query("SHOW TABLES LIKE 'moz_deposito'"); 
if(!$tblDep || $tblDep->num_rows===0){
  die('<div class="alert alert-warning" style="margin:15px">Tabela <b>moz_deposito</b> não encontrada.</div>');
}

// filtros
$q       = trim($_GET['q'] ?? '');
$empresa = (int)($_GET['empresa_id'] ?? 0);
$tipo    = trim($_GET['tipo'] ?? '');
$status  = $_GET['status'] ?? ''; // '', '1', '0'
$limit   = max(5, min(100,(int)($_GET['limit']??20)));
$page    = max(1,(int)($_GET['p']??1));
$offset  = ($page-1)*$limit;

// monta where
$where=[]; $types=''; $args=[];
if($q!==''){ $where[]="(d.nome LIKE ? OR d.logradouro LIKE ? OR d.municipio LIKE ?)"; $types.='sss'; $args[]="%$q%"; $args[]="%$q%"; $args[]="%$q%"; }
if($empresa>0){ $where[]="d.empresa_id=?"; $types.='i'; $args[]=$empresa; }
if($tipo!==''){ $where[]="d.tipo=?"; $types.='s'; $args[]=$tipo; }
if($status==='1' || $status==='0'){ $where[]="d.status=?"; $types.='i'; $args[]=(int)$status; }
$wsql = $where? ('WHERE '.implode(' AND ',$where)) : '';

// total
$sqlCount = "SELECT COUNT(*) FROM moz_deposito d $wsql";
$st = $dbc->prepare($sqlCount); if($types) $st->bind_param($types, ...$args);
$st->execute(); $st->bind_result($total); $st->fetch(); $st->close();
$pages = max(1,(int)ceil($total/$limit));

// dados
$sql = "SELECT d.id,d.nome,d.tipo,d.status,d.capacidade_txt,d.categorias_permitidas,
               d.municipio,d.uf,e.nome_fantasia AS empresa_nome
        FROM moz_deposito d
        LEFT JOIN empresas e ON e.id=d.empresa_id
        $wsql
        ORDER BY d.nome
        LIMIT ? OFFSET ?";
$types2=$types.'ii'; $args2=$args; $args2[]=$limit; $args2[]=$offset;
$st=$dbc->prepare($sql); if($types2) $st->bind_param($types2, ...$args2);
$st->execute(); $res=$st->get_result(); $rows=$res->fetch_all(MYSQLI_ASSOC); $st->close();

// empresas pro filtro
$emp=[]; $re=$dbc->query("SELECT id, COALESCE(NULLIF(nome_fantasia,''),nome_empresarial) AS nome FROM empresas WHERE ativo=1 ORDER BY nome");
if($re) while($r=$re->fetch_assoc()) $emp[]=$r;

// Abre layout
include_once ROOT_PATH . 'system/includes/head.php';
include_once ROOT_PATH . 'system/includes/navbar.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header">Depósitos</h1></div></div>
  <div class="row"><div class="col-lg-12">

  <form class="panel panel-default" method="get" style="padding:0;margin-bottom:15px">
    <div class="panel-heading">Filtros</div>
    <div class="panel-body">
      <div class="form-inline" style="display:flex;gap:10px;flex-wrap:wrap">
        <input class="form-control" name="q" placeholder="busca (nome/endereço/município)" value="<?= h($q) ?>">
        <select class="form-control" name="empresa_id">
          <option value="0">— Empresa/Entidade —</option>
          <?php foreach($emp as $e): ?>
          <option value="<?= (int)$e['id'] ?>" <?= $empresa===(int)$e['id']?'selected':'' ?>><?= h($e['nome']) ?></option>
          <?php endforeach; ?>
        </select>
        <select class="form-control" name="tipo">
          <option value="">— Tipo —</option>
          <?php foreach(['Geral','Peças','Consumíveis','Ativos de TI'] as $t): ?>
            <option value="<?= $t ?>" <?= $tipo===$t?'selected':'' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
        <select class="form-control" name="status">
          <option value="">— Status —</option>
          <option value="1" <?= $status==='1'?'selected':'' ?>>Ativo</option>
          <option value="0" <?= $status==='0'?'selected':'' ?>>Inativo</option>
        </select>
        <select class="form-control" name="limit">
          <?php foreach([20,50,100] as $L): ?>
            <option value="<?= $L ?>" <?= $limit===$L?'selected':'' ?>><?= $L ?>/pág</option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary">Aplicar</button>
        <a class="btn btn-default" href="<?= $_SERVER['PHP_SELF'] ?>">Limpar</a>
        <a class="btn btn-success pull-right" href="<?= BASE_URL ?>/modules/gestao_ativos/depositos-form.php" style="margin-left:auto">+ Novo</a>
      </div>
    </div>
  </form>

  <div class="panel panel-default">
    <div class="panel-heading">Listagem (<?= (int)$total ?>)</div>
    <div class="panel-body">
      <?php if(!$rows): ?>
        <div class="alert alert-info">Nenhum depósito encontrado.</div>
      <?php else: ?>
        <div class="row">
        <?php foreach($rows as $r): ?>
          <div class="col-sm-6">
            <div class="well">
              <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <div>
                  <div style="font-weight:700"><?= h($r['nome']) ?></div>
                  <div class="text-muted"><?= h($r['empresa_nome'] ?: '—') ?> • <?= h(trim(($r['municipio']?:'').' / '.($r['uf']?:''),' /')) ?></div>
                </div>
                <span class="label <?= !empty($r['status'])?'label-success':'label-default' ?>"><?= !empty($r['status'])?'Ativo':'Inativo' ?></span>
              </div>
              <hr style="margin:10px 0">
              <div class="row">
                <div class="col-xs-4"><small class="text-muted">Tipo</small><div><?= h($r['tipo'] ?: '—') ?></div></div>
                <div class="col-xs-4"><small class="text-muted">Capacidade</small><div><?= h($r['capacidade_txt'] ?: '—') ?></div></div>
                <div class="col-xs-4"><small class="text-muted">Categorias</small><div><?= h($r['categorias_permitidas'] ?: '—') ?></div></div>
              </div>
              <div class="text-right" style="margin-top:8px">
                <a class="btn btn-xs btn-default" href="<?= BASE_URL ?>/modules/gestao_ativos/depositos-form.php?id=<?= (int)$r['id'] ?>">Editar</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        </div>

        <?php if($pages>1):
          $mk=function($p)use($q,$empresa,$tipo,$status,$limit){ return $_SERVER['PHP_SELF'].'?'.http_build_query(compact('q','empresa','tipo','status','limit')+['p'=>$p]); }; ?>
          <nav aria-label="Paginação"><ul class="pagination">
            <li class="<?= $page<=1?'disabled':'' ?>"><a href="<?= $mk(max(1,$page-1)) ?>">&laquo;</a></li>
            <?php for($i=1;$i<=$pages;$i++): ?>
              <li class="<?= $i===$page?'active':'' ?>"><a href="<?= $mk($i) ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            <li class="<?= $page>=$pages?'disabled':'' ?>"><a href="<?= $mk(min($pages,$page+1)) ?>">&raquo;</a></li>
          </ul></nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  </div></div>
</div></div>

<?php include_once ROOT_PATH . 'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . 'system/includes/footer.php'; ?>
