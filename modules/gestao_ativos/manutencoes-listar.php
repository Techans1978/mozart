<?php
// public/modules/gestao_ativos/manutencoes-listar.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
require_once ROOT_PATH.'/modules/gestao_ativos/manutencao_lib.php';

if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

if (empty($_SESSION['csrf_manut'])) $_SESSION['csrf_manut'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_manut'];

// roda geração automática (rápido e seguro)
$criadas = gerar_preventivas($dbc);

$ativo_id = (int)($_GET['ativo_id'] ?? 0);

/* ========= Helpers (evita redeclare) ========= */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('has_col')) {
  function has_col(mysqli $db,$t,$c){
    $rt=$db->real_escape_string($t);
    $rc=$db->real_escape_string($c);
    $r=$db->query("SHOW COLUMNS FROM `$rt` LIKE '$rc'");
    return $r && $r->num_rows>0;
  }
}
if (!function_exists('table_exists')) {
  function table_exists(mysqli $db,$t){
    $rt=$db->real_escape_string($t);
    $r=$db->query("SHOW TABLES LIKE '$rt'");
    return $r && $r->num_rows>0;
  }
}

/* ========= Export CSV (compatível com schema) ========= */
if (($_GET['export'] ?? '') === 'csv') {

  // CSRF via query (pra não deixar download aberto)
  if (!hash_equals($csrf, $_GET['csrf'] ?? '')) die('CSRF inválido.');

  $hasEmpresas = table_exists($dbc,'empresas');
  $hasGaList   = table_exists($dbc,'ga_list_item');

  $col_sector_item = has_col($dbc,'moz_ativo','sector_item_id');
  $col_sector_sub  = has_col($dbc,'moz_ativo','sector_sub_id');
  $col_sector_id   = has_col($dbc,'moz_ativo','sector_id');

  // coluna de setor a usar no join (se existir)
  $sectorExpr = "NULL";
  if ($col_sector_item) $sectorExpr = "a.sector_item_id";
  else if ($col_sector_sub) $sectorExpr = "a.sector_sub_id";
  else if ($col_sector_id) $sectorExpr = "a.sector_id";

  // colunas variáveis do moz_ativo_manutencao
  $m_has_tipo   = has_col($dbc,'moz_ativo_manutencao','tipo');
  $m_has_origem = has_col($dbc,'moz_ativo_manutencao','origem');
  $m_has_prev   = has_col($dbc,'moz_ativo_manutencao','data_prevista');
  $m_has_manut  = has_col($dbc,'moz_ativo_manutencao','data_manutencao');
  $m_has_status = has_col($dbc,'moz_ativo_manutencao','status');
  $m_has_valor  = has_col($dbc,'moz_ativo_manutencao','valor');

  $sql = "
    SELECT
      m.id,
      a.nome AS ativo,
      ".($hasEmpresas ? "TRIM(COALESCE(NULLIF(e.nome_fantasia,''), NULLIF(e.nome_empresarial,''), ''))" : "''")." AS local,
      ".($hasGaList ? "COALESCE(s.name,'')" : "''")." AS setor,

      ".($m_has_tipo   ? "m.tipo" : "''")." AS tipo,
      ".($m_has_origem ? "m.origem" : "''")." AS origem,
      ".($m_has_prev   ? "m.data_prevista" : "NULL")." AS data_prevista,
      ".($m_has_manut  ? "m.data_manutencao" : "NULL")." AS data_manutencao,
      ".($m_has_status ? "m.status" : "''")." AS status,
      ".($m_has_valor  ? "m.valor" : "NULL")." AS valor

    FROM moz_ativo_manutencao m
    LEFT JOIN moz_ativo a ON a.id = m.ativo_id
    ".($hasEmpresas ? "LEFT JOIN empresas e ON e.id = a.local_id" : "")."
    ".($hasGaList ? "LEFT JOIN ga_list_item s ON s.id = $sectorExpr" : "")."
    WHERE 1=1
    ".($ativo_id ? " AND m.ativo_id = ?" : "")."
    ORDER BY COALESCE(
      ".($m_has_manut ? "m.data_manutencao" : "NULL").",
      ".($m_has_prev  ? "m.data_prevista" : "NULL").",
      m.created_at
    ) DESC, m.id DESC
  ";

  $st = $dbc->prepare($sql);
  if ($ativo_id) $st->bind_param('i', $ativo_id);
  $st->execute();
  $rs = $st->get_result();

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="manutencoes'.($ativo_id?('_ativo_'.$ativo_id):'').'.csv"');
  header('Pragma: no-cache');
  header('Expires: 0');

  // BOM UTF-8 (Excel)
  echo "\xEF\xBB\xBF";

  $out = fopen('php://output', 'w');
  fputcsv($out, ['id','ativo','local','setor','tipo','origem','prevista','manutencao','status','valor'], ';');

  while ($r = $rs->fetch_assoc()) {
    $prev = !empty($r['data_prevista']) ? date('d/m/Y', strtotime($r['data_prevista'])) : '';
    $manu = !empty($r['data_manutencao']) ? date('d/m/Y', strtotime($r['data_manutencao'])) : '';
    $val  = ($r['valor'] !== null && $r['valor'] !== '') ? number_format((float)$r['valor'], 2, ',', '.') : '';

    fputcsv($out, [
      (int)$r['id'],
      (string)$r['ativo'],
      (string)$r['local'],
      (string)$r['setor'],
      (string)$r['tipo'],
      (string)$r['origem'],
      $prev,
      $manu,
      (string)$r['status'],
      $val
    ], ';');
  }

  fclose($out);
  $st->close();
  exit;
}

// inclui layout
include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.1">

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

  <section class="bpm"><div class="container">

    <header class="toolbar">
      <h1>Manutenções</h1>
      <div class="actions">
        <a class="btn" href="ativos-listar.php">Voltar Ativos</a>

        <a class="btn" href="manutencoes-listar.php?export=csv&csrf=<?= h($csrf) ?><?= $ativo_id?('&ativo_id='.(int)$ativo_id):'' ?>">
          Exportar CSV
        </a>

        <a class="btn primary" href="manutencoes-form.php?extra=1<?= $ativo_id?('&ativo_id='.(int)$ativo_id):'' ?>">+ Manutenção extra</a>
      </div>
    </header>

    <?php if (!empty($_SESSION['flash_ok'])): ?>
      <div class="alert alert-success"><?= h($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_err'])): ?>
      <div class="alert alert-danger"><?= h($_SESSION['flash_err']); unset($_SESSION['flash_err']); ?></div>
    <?php endif; ?>

    <?php if ($criadas > 0): ?>
      <div class="alert alert-info">Preventivas criadas automaticamente hoje: <?= (int)$criadas ?></div>
    <?php endif; ?>

    <div class="card">
      <table id="tbl" class="table table-striped table-bordered" style="width:100%">
        <thead>
          <tr>
            <th>ID</th>
            <th>Ativo</th>
            <th>Local</th>
            <th>Setor</th>
            <th>Tipo</th>
            <th>Origem</th>
            <th>Prevista</th>
            <th>Manutenção</th>
            <th>Status</th>
            <th>Valor</th>
            <th>Ações</th>
          </tr>
        </thead>
      </table>
    </div>

  </div></section>
</div></div>

<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>

<script>
$(function(){
  const table = $('#tbl').DataTable({
    processing: true,
    serverSide: true,
    pageLength: 25,
    ajax: {
      url: 'manutencoes-endpoint.php',
      type: 'POST',
      data: {
        csrf: '<?= h($csrf) ?>',
        ativo_id: <?= (int)$ativo_id ?> // ✅ FILTRO FIXO
      }
    },
    order: [[6,'asc']], // prevista
    columns: [
      {data:'id'},
      {data:'ativo'},
      {data:'local'},
      {data:'setor'},
      {data:'tipo'},
      {data:'origem'},
      {data:'prevista'},
      {data:'data_manutencao'},
      {data:'status'},
      {data:'valor'},
      {data:'acoes', orderable:false, searchable:false}
    ]
  });
});
</script>
