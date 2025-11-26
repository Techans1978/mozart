<?php
// Técnicos & Filas — Listagem + filtros
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start(); proteger_pagina();
$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$tipo = $_GET['tipo'] ?? '';                         // Tecnico|Fila|'' (todos)
$skill_like = trim($_GET['skill'] ?? '');
$entidade = trim($_GET['entidade'] ?? '');
$disp = $_GET['disp'] ?? '';                         // 'online', 'turno', 'indisp', ''

include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header">Técnicos & Filas</h1></div></div>

  <div class="panel panel-default">
    <div class="panel-heading">Filtros</div>
    <div class="panel-body">
      <form class="form-inline" method="get">
        <select class="form-control" name="tipo">
          <option value="">— Tipo —</option>
          <option <?= $tipo==='Tecnico'?'selected':''?>>Tecnico</option>
          <option <?= $tipo==='Fila'?'selected':''?>>Fila</option>
        </select>
        <input class="form-control" name="skill" value="<?=h($skill_like)?>" placeholder="skills (contém)">
        <input class="form-control" name="entidade" value="<?=h($entidade)?>" placeholder="entidade (contém)">
        <select class="form-control" name="disp">
          <option value="">— Disponibilidade —</option>
          <option value="online" <?= $disp==='online'?'selected':''?>>Online</option>
          <option value="turno"  <?= $disp==='turno'?'selected':''?>>Dentro do turno</option>
          <option value="indisp" <?= $disp==='indisp'?'selected':''?>>Sem indisponibilidade</option>
        </select>
        <a class="btn btn-success pull-right" href="tecnicos-filas-form.php" style="margin-left:8px">+ Novo</a>
        <button class="btn btn-primary">Aplicar</button>
        <a class="btn btn-default" href="<?= $_SERVER['PHP_SELF'] ?>">Limpar</a>
      </form>
    </div>
  </div>

<?php
/* ------- CONSULTAS ------- */
// Técnicos
$wh=[]; if($entidade!=='') $wh[]="(t.entidades LIKE '%".$dbc->real_escape_string($entidade)."%')";
if($disp==='online') $wh[]="t.online=1";
if($disp==='turno')  $wh[]="(CASE t.turno WHEN '8x5' THEN (TIME(NOW()) BETWEEN '08:00:00' AND '17:59:59' AND WEEKDAY(NOW())<=4) ELSE 1 END)";
if($disp==='indisp') $wh[]="NOT EXISTS (SELECT 1 FROM moz_indisp_tecnico i WHERE i.tecnico_id=t.id AND NOW() BETWEEN i.ini AND i.fim)";
if($skill_like!=='') $wh[]="EXISTS(SELECT 1 FROM moz_tecnico_skill ts JOIN moz_skill s ON s.id=ts.skill_id WHERE ts.tecnico_id=t.id AND s.nome LIKE '%".$dbc->real_escape_string($skill_like)."%')";
$wsql = $wh? 'WHERE '.implode(' AND ',$wh) : '';
$tecs=[]; if($tipo==='' || $tipo==='Tecnico'){
  $sql="SELECT t.*, 
        (SELECT GROUP_CONCAT(s.nome ORDER BY s.nome SEPARATOR ', ') FROM moz_tecnico_skill ts JOIN moz_skill s ON s.id=ts.skill_id WHERE ts.tecnico_id=t.id) AS skills,
        (SELECT COUNT(*) FROM moz_fila_tecnico ft WHERE ft.tecnico_id=t.id) AS nfilas
        FROM moz_tecnico t $wsql ORDER BY t.nome";
  $r=$dbc->query($sql); if($r) while($x=$r->fetch_assoc()) $tecs[]=$x;
}

// Filas
$wf=[]; if($entidade!=='') $wf[]="(f.entidades LIKE '%".$dbc->real_escape_string($entidade)."%')";
if($skill_like!=='') $wf[]="EXISTS(SELECT 1 FROM moz_fila_skill fs JOIN moz_skill s ON s.id=fs.skill_id WHERE fs.fila_id=f.id AND s.nome LIKE '%".$dbc->real_escape_string($skill_like)."%')";
$wfsql = $wf? 'WHERE '.implode(' AND ',$wf) : '';
$filas=[]; if($tipo==='' || $tipo==='Fila'){
  $sql="SELECT f.*,
        (SELECT GROUP_CONCAT(s.nome ORDER BY s.nome SEPARATOR ', ') FROM moz_fila_skill fs JOIN moz_skill s ON s.id=fs.skill_id WHERE fs.fila_id=f.id) AS services,
        (SELECT COUNT(*) FROM moz_fila_tecnico ft WHERE ft.fila_id=f.id) AS ntecs
        FROM moz_fila f $wfsql ORDER BY f.nome";
  $r=$dbc->query($sql); if($r) while($x=$r->fetch_assoc()) $filas[]=$x;
}
?>

  <div class="panel panel-default">
    <div class="panel-heading">Resultados</div>
    <div class="panel-body">
      <?php if(!$tecs && !$filas): ?>
        <div class="alert alert-info">Nada encontrado.</div>
      <?php endif; ?>

      <?php if($tecs): ?>
        <h4>Técnicos (<?= count($tecs) ?>)</h4>
        <div class="table-responsive">
          <table class="table table-striped">
            <thead><tr><th>Nome</th><th>Time</th><th>Turno</th><th>Entidades</th><th>Skills</th><th>Filas</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach($tecs as $t): ?>
              <tr>
                <td><?=h($t['nome'])?></td>
                <td><?=h($t['time_nome']?:'—')?></td>
                <td><?=h($t['turno'])?></td>
                <td><?=h($t['entidades']?:'—')?></td>
                <td><?=h($t['skills']?:'—')?></td>
                <td><?= (int)$t['nfilas'] ?></td>
                <td><?= $t['ativo']?'<span class="label label-success">Ativo</span>':'<span class="label label-default">Inativo</span>' ?> <?= $t['online']?'<span class="label label-info">online</span>':'' ?></td>
                <td><a class="btn btn-xs btn-default" href="tecnicos-filas-form.php?tipo=tecnico&id=<?=$t['id']?>">Editar</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if($filas): ?>
        <h4>Filas (<?= count($filas) ?>)</h4>
        <div class="table-responsive">
          <table class="table table-striped">
            <thead><tr><th>Nome</th><th>Escopo</th><th>Horário</th><th>Roteamento</th><th>Limite</th><th>Auto-pull</th><th>Serviços/Skills</th><th>Técnicos</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach($filas as $f): ?>
              <tr>
                <td><?=h($f['nome'])?></td>
                <td><?=h($f['escopo'])?></td>
                <td><?=h($f['horario'])?></td>
                <td><?=h($f['roteamento'])?></td>
                <td><?= (int)$f['limite_simultaneo'] ?></td>
                <td><?= $f['auto_pull']? 'Sim':'Não' ?> (<?= (int)$f['pull_lote'] ?>/<?= (int)$f['pull_intervalo_seg'] ?>s)</td>
                <td><?=h($f['services']?:'—')?></td>
                <td><?= (int)$f['ntecs'] ?></td>
                <td><a class="btn btn-xs btn-default" href="tecnicos-filas-form.php?tipo=fila&id=<?=$f['id']?>">Editar</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div></div>

<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
