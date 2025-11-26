<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start(); proteger_pagina();
$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$busca=trim($_GET['q']??''); $w=$busca?"WHERE (nome LIKE '%".$dbc->real_escape_string($busca)."%' OR categoria LIKE '%".$dbc->real_escape_string($busca)."%')":'';
$rs=$dbc->query("SELECT * FROM moz_sla_servico $w ORDER BY categoria,nome"); $rows=[]; if($rs) while($r=$rs->fetch_assoc()) $rows[]=$r;

include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header">Tempos de serviço</h1></div></div>

  <div class="panel panel-default">
    <div class="panel-heading">Filtros</div>
    <div class="panel-body">
      <form class="form-inline" method="get">
        <input class="form-control" name="q" value="<?=h($busca)?>" placeholder="buscar por nome/categoria">
        <a class="btn btn-success" href="tempos-servico-form.php">+ Novo</a>
        <button class="btn btn-primary">Aplicar</button>
        <a class="btn btn-default" href="<?= $_SERVER['PHP_SELF'] ?>">Limpar</a>
      </form>
    </div>
  </div>

  <div class="panel panel-default">
    <div class="panel-heading">Listagem (<?= count($rows) ?>)</div>
    <div class="table-responsive">
      <table class="table table-striped">
        <thead><tr><th>Nome</th><th>Categoria</th><th>Tempo</th><th>Descrição</th><th>Ações</th></tr></thead>
        <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?=h($r['nome'])?></td>
              <td><?=h($r['categoria']?:'—')?></td>
              <td><?= (int)$r['tempo_min'] ?> min</td>
              <td><?=h($r['descricao']?:'—')?></td>
              <td><a class="btn btn-xs btn-default" href="tempos-servico-form.php?id=<?=$r['id']?>">Editar</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div></div>
<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
