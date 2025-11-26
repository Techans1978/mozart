<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start(); proteger_pagina();
$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$q = trim($_GET['q']??'');
$status = $_GET['status'] ?? '';
$ent = trim($_GET['entidade']??'');
$month = (int)($_GET['m'] ?? date('n'));
$year  = (int)($_GET['y'] ?? date('Y'));

$startMonth = sprintf('%04d-%02d-01', $year, $month);
$endMonth   = date('Y-m-d', strtotime("$startMonth +1 month"));

$where = ["r.inicio < ?","r.fim >= ?"]; $types='ss'; $args=[$endMonth,$startMonth];
if($q!==''){ $where[]="(r.solicitante LIKE ? OR ri.descricao LIKE ?)"; $types.='ss'; array_push($args,"%$q%","%$q%"); }
if($status!==''){ $where[]="r.status=?"; $types.='s'; $args[]=$status; }
if($ent!==''){ $where[]="r.entidade=?"; $types.='s'; $args[]=$ent; }

$sql="SELECT r.*, COUNT(ri.id) itens
      FROM moz_reserva r
      LEFT JOIN moz_reserva_item ri ON ri.reserva_id=r.id
      WHERE ".implode(' AND ',$where)."
      GROUP BY r.id
      ORDER BY r.inicio";
$st=$dbc->prepare($sql); $st->bind_param($types,...$args); $st->execute();
$res=$st->get_result(); $rows=$res->fetch_all(MYSQLI_ASSOC); $st->close();

/* Mapa por dia para o calendário */
$map=[]; foreach($rows as $r){
  $d1 = new DateTime(max($startMonth, substr($r['inicio'],0,10)));
  $d2 = new DateTime(min(date('Y-m-d',strtotime($endMonth.' -1 day')), substr($r['fim'],0,10)));
  for($d=$d1; $d <= $d2; $d->modify('+1 day')){
    $key=$d->format('Y-m-d'); $map[$key][]=$r;
  }
}

include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<style>.cal{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}.day{border:1px solid #ddd;border-radius:8px;min-height:110px;padding:6px}.day h5{margin:0 0 4px;font-size:12px;color:#666}.tag{display:block;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin:2px 0;padding:2px 6px;border-radius:6px;background:#f3f6ff}</style>

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header">Reservas — Listar</h1></div></div>

  <div class="panel panel-default">
    <div class="panel-heading">Filtros</div>
    <div class="panel-body">
      <form class="form-inline" method="get">
        <input class="form-control" name="q" value="<?=h($q)?>" placeholder="solicitante, item">
        <select class="form-control" name="status">
          <option value="">— Status —</option>
          <?php foreach(['AGUARDANDO','APROVADA','EM_USO','CONCLUIDA','ATRASADA','CANCELADA'] as $s): ?>
            <option <?=$status===$s?'selected':''?>><?=$s?></option>
          <?php endforeach; ?>
        </select>
        <input class="form-control" name="entidade" value="<?=h($ent)?>" placeholder="Entidade">
        <input class="form-control" name="m" type="number" min="1" max="12" value="<?=$month?>" style="width:90px">
        <input class="form-control" name="y" type="number" min="2000" max="2100" value="<?=$year?>" style="width:110px">
        <button class="btn btn-primary">Aplicar</button>
        <a class="btn btn-default" href="<?= $_SERVER['PHP_SELF'] ?>">Limpar</a>
        <a class="btn btn-success pull-right" href="reservas-form.php">+ Nova</a>
      </form>
    </div>
  </div>

  <div class="panel panel-default">
    <div class="panel-heading">Calendário</div>
    <div class="panel-body">
      <?php
      $firstDow = (int)date('N', strtotime($startMonth)); // 1..7 (Mon..Sun)
      $daysInMonth = (int)date('t', strtotime($startMonth));
      echo '<div class="cal">';
      for($i=1;$i<$firstDow;$i++) echo '<div class="day"></div>';
      for($d=1;$d<=$daysInMonth;$d++){
        $key=sprintf('%04d-%02d-%02d',$year,$month,$d);
        echo '<div class="day"><h5>'.$d.'</h5>';
        if(!empty($map[$key])){
          foreach($map[$key] as $r){
            $lbl = h($r['solicitante']).' • '.h(substr($r['inicio'],11,5).'–'.substr($r['fim'],11,5)).' • '.h($r['status']);
            echo '<span class="tag">'.$lbl.'</span>';
          }
        }
        echo '</div>';
      }
      echo '</div>';
      ?>
    </div>
  </div>

  <div class="panel panel-default">
    <div class="panel-heading">Listagem (<?= count($rows) ?>)</div>
    <div class="table-responsive">
      <table class="table table-striped">
        <thead><tr><th>#</th><th>Período</th><th>Solicitante</th><th>Entidade</th><th>Itens</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
          <?php foreach($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= h(substr($r['inicio'],0,16).' → '.substr($r['fim'],0,16)) ?></td>
            <td><?= h($r['solicitante']) ?></td>
            <td><?= h($r['entidade']) ?></td>
            <td><?= (int)$r['itens'] ?></td>
            <td><?= h($r['status']) ?></td>
            <td><a class="btn btn-xs btn-default" href="reservas-form.php?id=<?= (int)$r['id'] ?>">Editar</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div></div>
<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
