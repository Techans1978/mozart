<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start(); proteger_pagina();
$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$q=trim($_GET['q']??''); $tipo=$_GET['tipo']??''; $status=$_GET['status']??''; $periodo=$_GET['periodo']??'';
$where=[]; $types=''; $args=[];
if($q!==''){ $where[]="(origem LIKE ? OR destino LIKE ?)"; $types.='ss'; array_push($args,"%$q%","%$q%"); }
if($tipo!==''){ $where[]="tipo=?"; $types.='s'; $args[]=$tipo; }
if($status!==''){ $where[]="status=?"; $types.='s'; $args[]=$status; }
if($periodo==='30'){ $where[]="created_at>=DATE_SUB(NOW(), INTERVAL 30 DAY)"; }
if($periodo==='ano'){ $where[]="YEAR(created_at)=YEAR(CURDATE())"; }
$w = $where ? 'WHERE '.implode(' AND ',$where) : '';

$sql="SELECT t.*, (SELECT COUNT(*) FROM moz_transfer_item i WHERE i.transfer_id=t.id) itens FROM moz_transfer t $w ORDER BY t.created_at DESC";
$st=$dbc->prepare($sql); if($types) $st->bind_param($types,...$args); $st->execute(); $res=$st->get_result(); $rows=$res->fetch_all(MYSQLI_ASSOC); $st->close();

include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header">Transferências — Listar</h1></div></div>

  <div class="panel panel-default">
    <div class="panel-heading">Filtros</div>
    <div class="panel-body">
      <form class="form-inline" method="get">
        <input class="form-control" name="q" value="<?=h($q)?>" placeholder="origem, destino">
        <select class="form-control" name="tipo">
          <option value="">— Tipo —</option>
          <option value="TEMPORARIA" <?=$tipo==='TEMPORARIA'?'selected':''?>>Temporária</option>
          <option value="DEFINITIVA" <?=$tipo==='DEFINITIVA'?'selected':''?>>Definitiva</option>
          <option value="EMPRESTIMO" <?=$tipo==='EMPRESTIMO'?'selected':''?>>Empréstimo</option>
        </select>
        <select class="form-control" name="status">
          <option value="">— Status —</option>
          <?php foreach(['PREPARACAO','TRANSITO','CONCLUIDA','CANCELADA'] as $s): ?>
            <option <?=$status===$s?'selected':''?>><?=$s?></option>
          <?php endforeach; ?>
        </select>
        <select class="form-control" name="periodo">
          <option value="">— Período —</option>
          <option value="30" <?=$periodo==='30'?'selected':''?>>Últimos 30d</option>
          <option value="ano" <?=$periodo==='ano'?'selected':''?>>Este ano</option>
        </select>
        <button class="btn btn-primary">Aplicar</button>
        <a class="btn btn-default" href="<?= $_SERVER['PHP_SELF'] ?>">Limpar</a>
        <a class="btn btn-success pull-right" href="transferencias-form.php">+ Nova</a>
      </form>
    </div>
  </div>

  <div class="panel panel-default">
    <div class="panel-heading">Listagem (<?= count($rows) ?>)</div>
    <div class="table-responsive">
      <table class="table table-striped">
        <thead><tr><th>#</th><th>Tipo</th><th>Origem → Destino</th><th>Data</th><th>Itens</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
          <?php foreach($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= h($r['tipo']) ?></td>
            <td><?= h($r['origem'].' → '.$r['destino']) ?></td>
            <td><?= h($r['data_mov'] ?: '—') ?></td>
            <td><?= (int)$r['itens'] ?></td>
            <td><?= h($r['status']) ?></td>
            <td><a class="btn btn-xs btn-default" href="transferencias-form.php?id=<?= (int)$r['id'] ?>">Editar</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div></div>
<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
