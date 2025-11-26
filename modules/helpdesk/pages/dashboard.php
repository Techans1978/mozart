<?php
// public/modules/helpdesk/pages/dashboard.php (versão robusta)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
include_once ROOT_PATH . '/system/includes/head.php';
require_once ROOT_PATH.'/system/includes/head_hd.php';
proteger_pagina();

/**
 * Helpers para checar schema e consultar com segurança.
 */
function has_col(mysqli $db, string $table, string $col): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  $r = $db->query($sql);
  return ($r && $r->num_rows>0);
}
function q1(mysqli $db, string $sql, string $types='', ...$params) {
  try {
    $st = $db->prepare($sql);
    if(!$st){ return null; }
    if($types){ $st->bind_param($types, ...$params); }
    $st->execute();
    $res = $st->get_result();
    $row = $res ? $res->fetch_row() : null;
    $st->close();
    return $row ? (int)$row[0] : 0;
  } catch (Throwable $e) {
    return 0; // não quebra a página
  }
}
function qrows(mysqli $db, string $sql, string $types='', ...$params): array {
  try {
    $st = $db->prepare($sql);
    if(!$st){ return []; }
    if($types){ $st->bind_param($types, ...$params); }
    $st->execute();
    $res = $st->get_result();
    $out = [];
    if($res){ while($r=$res->fetch_assoc()) $out[]=$r; }
    $st->close();
    return $out;
  } catch (Throwable $e) {
    return [];
  }
}

$tz = new DateTimeZone(date_default_timezone_get());
$today = (new DateTime('today',$tz))->format('Y-m-d');
$month_start = (new DateTime('first day of this month',$tz))->format('Y-m-d');

// flags de colunas para montar consultas sem quebrar
$has_created     = has_col($conn,'hd_ticket','created_at');
$has_closed      = has_col($conn,'hd_ticket','closed_at');
$has_updated     = has_col($conn,'hd_ticket','updated_at');
$has_status      = has_col($conn,'hd_ticket','status');
$has_planned     = has_col($conn,'hd_ticket','is_planned');
$has_recurring   = has_col($conn,'hd_ticket','is_recurring');
$has_assignee    = has_col($conn,'hd_ticket','assignee_user_id');
$has_sla_due     = has_col($conn,'hd_ticket','sla_due_at');

// ===== Cards principais =====
$total_chamados = q1($conn, "SELECT COUNT(*) FROM hd_ticket");

$novos_hoje = $has_created
  ? q1($conn, "SELECT COUNT(*) FROM hd_ticket WHERE DATE(created_at)=?", 's', $today)
  : 0;

$atrasados = ($has_status && $has_sla_due)
  ? q1($conn, "SELECT COUNT(*) FROM hd_ticket
               WHERE status IN ('pendente','em_atendimento','atribuido')
                 AND sla_due_at IS NOT NULL AND NOW() > sla_due_at")
  : 0; // sem sla_due_at, mostramos 0 e seguimos

$solucionados_m = ($has_status && $has_created)
  ? q1($conn, "SELECT COUNT(*) FROM hd_ticket
               WHERE status='solucionado' AND created_at>=? AND created_at< DATE_ADD(?, INTERVAL 1 DAY)", 'ss', $month_start, $today)
  : 0;

$fechados_m = ($has_closed)
  ? q1($conn, "SELECT COUNT(*) FROM hd_ticket
               WHERE closed_at IS NOT NULL AND closed_at>=? AND closed_at< DATE_ADD(?, INTERVAL 1 DAY)", 'ss', $month_start, $today)
  : 0;

$planejados = $has_planned ? q1($conn, "SELECT COUNT(*) FROM hd_ticket WHERE is_planned=1") : 0;
$pendentes  = $has_status  ? q1($conn, "SELECT COUNT(*) FROM hd_ticket WHERE status='pendente'") : 0;
$atribuido  = $has_assignee? q1($conn, "SELECT COUNT(*) FROM hd_ticket WHERE assignee_user_id IS NOT NULL".($has_status?" AND status IN ('em_atendimento','atribuido')":"")) : 0;
$recorrentes= $has_recurring ? q1($conn, "SELECT COUNT(*) FROM hd_ticket WHERE is_recurring=1") : 0;

// ===== Séries: evolução últimos 12 meses =====
$labels=[]; $abertos=[]; $solucionados=[]; $fechados=[]; $atrasos=[];
for($i=11; $i>=0; $i--){
  $ym = (new DateTime("first day of -$i months",$tz))->format('Y-m');
  $labels[] = $ym;
  $abertos[] = $has_created ? q1($conn,"SELECT COUNT(*) FROM hd_ticket WHERE DATE_FORMAT(created_at,'%Y-%m')=?", 's', $ym) : 0;
  $solucionados[] = ($has_status && $has_updated)
    ? q1($conn,"SELECT COUNT(*) FROM hd_ticket WHERE status='solucionado' AND DATE_FORMAT(updated_at,'%Y-%m')=?", 's', $ym)
    : 0;
  $fechados[] = $has_closed
    ? q1($conn,"SELECT COUNT(*) FROM hd_ticket WHERE closed_at IS NOT NULL AND DATE_FORMAT(closed_at,'%Y-%m')=?", 's', $ym)
    : 0;
  $atrasos[] = ($has_created && $has_sla_due)
    ? q1($conn,"SELECT COUNT(*) FROM hd_ticket WHERE DATE_FORMAT(created_at,'%Y-%m')=? AND sla_due_at IS NOT NULL AND (closed_at IS NULL OR closed_at>sla_due_at)", 's', $ym)
    : 0;
}

// ===== Barras: status no mês atual =====
$bar_labels=[]; $bar_data=[];
if($has_status && $has_created){
  $rows = qrows($conn, "SELECT status, COUNT(*) qt
                        FROM hd_ticket
                        WHERE created_at>=? AND created_at< DATE_ADD(?, INTERVAL 1 DAY)
                        GROUP BY status", 'ss', $month_start, $today);
  foreach($rows as $r){ $bar_labels[]=$r['status']; $bar_data[]=(int)$r['qt']; }
}

// ===== Donuts: Top categorias & entidades (ano corrente) =====
// use os nomes das suas tabelas auxiliares, se existirem
$cats = qrows($conn, "
  SELECT COALESCE(c.nome,'(sem categoria)') label, COUNT(*) qt
  FROM hd_ticket t LEFT JOIN hd_categoria c ON c.id=t.categoria_id
  WHERE ".($has_created? "t.created_at >= DATE_FORMAT(NOW(),'%Y-01-01')" : "1=1")."
  GROUP BY c.nome
  ORDER BY qt DESC
  LIMIT 6
");
$ents = qrows($conn, "
  SELECT COALESCE(e.nome,'(sem entidade)') label, COUNT(*) qt
  FROM hd_ticket t LEFT JOIN empresa e ON e.id=t.entidade_id
  WHERE ".($has_created? "t.created_at >= DATE_FORMAT(NOW(),'%Y-01-01')" : "1=1")."
  GROUP BY e.nome
  ORDER BY qt DESC
  LIMIT 6
");

// includes padrão
include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<!-- layout -->
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>
    <div class="row">
      <div class="col-lg-12">
	  <!-- Content -->

<div class="container-fluid">
  <h3 class="mt-3 mb-3">Help Desk — Dashboard</h3>

  <?php if(!$has_sla_due): ?>
    <div class="alert alert-info py-2">
      Dica: para habilitar o card <strong>“Chamados atrasados”</strong> e a série de atraso, adicione a coluna <code>sla_due_at</code> (veja o script no final desta página).
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-md-2"><div class="p-3 bg-success text-white rounded"><div class="h4 mb-0"><?= (int)$novos_hoje ?></div><small>Chamados novos</small></div></div>
    <div class="col-md-2"><div class="p-3 bg-warning text-white rounded"><div class="h4 mb-0"><?= (int)$atrasados ?></div><small>Chamados atrasados</small></div></div>
    <div class="col-md-2"><div class="p-3 bg-primary text-white rounded"><div class="h4 mb-0"><?= (int)$total_chamados ?></div><small>Chamados</small></div></div>
    <div class="col-md-2"><div class="p-3 bg-info text-white rounded"><div class="h4 mb-0"><?= (int)$planejados ?></div><small>Chamados planejados</small></div></div>
    <div class="col-md-2"><div class="p-3 bg-secondary text-white rounded"><div class="h4 mb-0"><?= (int)$solucionados_m ?></div><small>Solucionados (mês)</small></div></div>
    <div class="col-md-2"><div class="p-3 bg-dark text-white rounded"><div class="h4 mb-0"><?= (int)$fechados_m ?></div><small>Fechados (mês)</small></div></div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-md-2"><div class="p-3 bg-light border rounded"><div class="h5 mb-0"><?= (int)$pendentes ?></div><small>Pendentes</small></div></div>
    <div class="col-md-2"><div class="p-3 bg-light border rounded"><div class="h5 mb-0"><?= (int)$atribuido ?></div><small>Atribuídos</small></div></div>
    <div class="col-md-2"><div class="p-3 bg-light border rounded"><div class="h5 mb-0"><?= (int)$recorrentes ?></div><small>Recorrentes</small></div></div>
  </div>

  <div class="row g-3 mt-2">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-body">
          <h6 class="mb-2">Evolução dos chamados no último ano</h6>
          <canvas id="chartEvol"></canvas>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card mb-3">
        <div class="card-body">
          <h6 class="mb-2">Status dos chamados (mês)</h6>
          <canvas id="chartStatus"></canvas>
        </div>
      </div>
      <div class="card">
        <div class="card-body d-flex">
          <div class="w-50 text-center">
            <h6>Top por categorias</h6>
            <canvas id="chartCat"></canvas>
          </div>
          <div class="w-50 text-center">
            <h6>Top por entidades</h6>
            <canvas id="chartEnt"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

	  <!-- End content -->
      </div>
    </div>
  </div>
</div>
<!-- layout -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const evolLabels = <?= json_encode($labels) ?>;
const dsAbertos = <?= json_encode($abertos) ?>;
const dsSoluc   = <?= json_encode($solucionados) ?>;
const dsFech    = <?= json_encode($fechados) ?>;
const dsAtraso  = <?= json_encode($atrasos) ?>;

const statusLabels = <?= json_encode($bar_labels) ?>;
const statusData   = <?= json_encode($bar_data) ?>;

const catLabels = <?= json_encode(array_column($cats,'label')) ?>;
const catData   = <?= json_encode(array_map('intval', array_column($cats,'qt'))) ?>;
const entLabels = <?= json_encode(array_column($ents,'label')) ?>;
const entData   = <?= json_encode(array_map('intval', array_column($ents,'qt'))) ?>;

new Chart(document.getElementById('chartEvol'), {
  type:'line',
  data:{ labels: evolLabels, datasets:[
    {label:'Abertos', data: dsAbertos},
    {label:'Solucionados', data: dsSoluc},
    {label:'Atrasado', data: dsAtraso},
    {label:'Fechado', data: dsFech}
  ]},
  options:{ responsive:true, maintainAspectRatio:false, aspectRatio:2.4, tension:0.2 }
});

new Chart(document.getElementById('chartStatus'), {
  type:'bar',
  data:{ labels: statusLabels, datasets:[{label:'Qtd', data: statusData}] },
  options:{ responsive:true, maintainAspectRatio:false, aspectRatio:1.2 }
});

new Chart(document.getElementById('chartCat'), {
  type:'doughnut',
  data:{ labels: catLabels, datasets:[{data: catData}] },
  options:{ plugins:{ legend:{display:false} } }
});
new Chart(document.getElementById('chartEnt'), {
  type:'doughnut',
  data:{ labels: entLabels, datasets:[{data: entData}] },
  options:{ plugins:{ legend:{display:false} } }
});
</script>

<?php require_once ROOT_PATH.'/system/includes/footer_hd.php'; ?>
