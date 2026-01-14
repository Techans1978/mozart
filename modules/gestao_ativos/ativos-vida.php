<?php
// public/modules/gestao_ativos/ativos-vida.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$ativo_id = (int)($_GET['id'] ?? 0);
if ($ativo_id<=0) die('ID inválido.');

// ========= Export CSV (timeline) =========
$doExport = (($_GET['export'] ?? '') === 'csv');
if ($doExport) {
  // CSV será gerado mais abaixo, depois de montar $timeline
  // (só marcamos aqui)
}

function has_col(mysqli $db,$t,$c){
  $rt=$db->real_escape_string($t);
  $rc=$db->real_escape_string($c);
  $r=$db->query("SHOW COLUMNS FROM `$rt` LIKE '$rc'");
  return $r && $r->num_rows>0;
}
function table_exists(mysqli $db,$t){
  $rt=$db->real_escape_string($t);
  $r=$db->query("SHOW TABLES LIKE '$rt'");
  return $r && $r->num_rows>0;
}

$hasEmpresas = table_exists($dbc,'empresas');
$hasGaList   = table_exists($dbc,'ga_list') && table_exists($dbc,'ga_list_item');
$hasManutTbl = table_exists($dbc,'moz_ativo_manutencao');

$col_sector_item = has_col($dbc,'moz_ativo','sector_item_id');
$col_sector_id   = has_col($dbc,'moz_ativo','sector_id');      // schema antigo
$col_sector_sub  = has_col($dbc,'moz_ativo','sector_sub_id');  // schema antigo
$hasCreatedAt    = has_col($dbc,'moz_ativo','created_at');

$hasManUnid   = has_col($dbc,'moz_ativo','manut_periodo_unid');
$hasManQtd    = has_col($dbc,'moz_ativo','manut_periodo_qtd');
$hasManUlt    = has_col($dbc,'moz_ativo','manut_ultimo');
$hasManProx   = has_col($dbc,'moz_ativo','manut_proxima');
$hasManAlert  = has_col($dbc,'moz_ativo','manut_alertar');

$cols = "a.id,a.nome,a.local_id";
if ($hasCreatedAt) $cols .= ",a.created_at";
if (has_col($dbc,'moz_ativo','data_compra')) $cols .= ",a.data_compra";
if (has_col($dbc,'moz_ativo','garantia_ate')) $cols .= ",a.garantia_ate";
if (has_col($dbc,'moz_ativo','garantia_meses')) $cols .= ",a.garantia_meses";
if ($hasManUnid) $cols .= ",a.manut_periodo_unid";
if ($hasManQtd)  $cols .= ",a.manut_periodo_qtd";
if ($hasManUlt)  $cols .= ",a.manut_ultimo";
if ($hasManProx) $cols .= ",a.manut_proxima";
if ($hasManAlert)$cols .= ",a.manut_alertar";
if ($col_sector_item) $cols .= ",a.sector_item_id";
if ($col_sector_id)   $cols .= ",a.sector_id";
if ($col_sector_sub)  $cols .= ",a.sector_sub_id";

$st=$dbc->prepare("SELECT $cols FROM moz_ativo a WHERE a.id=? LIMIT 1");
$st->bind_param('i',$ativo_id);
$st->execute();
$ativo=$st->get_result()->fetch_assoc();
$st->close();

if(!$ativo) die('Ativo não encontrado.');

$local_nome = '';
if ($hasEmpresas && !empty($ativo['local_id'])){
  $st=$dbc->prepare("SELECT TRIM(COALESCE(NULLIF(nome_fantasia,''), NULLIF(nome_empresarial,''), '(sem nome)')) AS nome FROM empresas WHERE id=? LIMIT 1");
  $st->bind_param('i',$ativo['local_id']);
  $st->execute();
  $row=$st->get_result()->fetch_assoc();
  $st->close();
  $local_nome = $row['nome'] ?? '';
}

$setor_nome = '';
if ($hasGaList){
  // tenta pegar setor via sector_item_id primeiro
  $sid = 0;
  if ($col_sector_item && !empty($ativo['sector_item_id'])) $sid = (int)$ativo['sector_item_id'];
  // fallback: schema antigo: sector_sub_id, senão sector_id
  if (!$sid && $col_sector_sub && !empty($ativo['sector_sub_id'])) $sid = (int)$ativo['sector_sub_id'];
  if (!$sid && $col_sector_id && !empty($ativo['sector_id'])) $sid = (int)$ativo['sector_id'];

  if ($sid>0){
    $st=$dbc->prepare("SELECT name FROM ga_list_item WHERE id=? LIMIT 1");
    $st->bind_param('i',$sid);
    $st->execute();
    $row=$st->get_result()->fetch_assoc();
    $st->close();
    $setor_nome = $row['name'] ?? '';
  }
}

function fmtDate($d){
  if(!$d) return '';
  $ts=strtotime($d);
  if(!$ts) return (string)$d;
  return date('d/m/Y', $ts);
}

$timeline = [];

// marcos básicos
if (!empty($ativo['created_at'])) $timeline[] = ['dt'=>substr($ativo['created_at'],0,10), 'title'=>'Cadastro do ativo', 'desc'=>'Registro criado no sistema.'];
if (!empty($ativo['data_compra'])) $timeline[] = ['dt'=>$ativo['data_compra'], 'title'=>'Compra/Aquisição', 'desc'=>'Data de compra informada.'];

if (!empty($ativo['garantia_ate'])) {
  $timeline[] = ['dt'=>$ativo['garantia_ate'], 'title'=>'Garantia até', 'desc'=>'Data limite de garantia.'];
} else if (!empty($ativo['data_compra']) && !empty($ativo['garantia_meses'])) {
  $dc = strtotime($ativo['data_compra']);
  if ($dc){
    $gm = (int)$ativo['garantia_meses'];
    $ga = date('Y-m-d', strtotime("+$gm months", $dc));
    $timeline[] = ['dt'=>$ga, 'title'=>'Garantia (calculada)', 'desc'=>"Garantia estimada em $gm mês(es)."];
  }
}

if (!empty($ativo['manut_ultimo'])) $timeline[] = ['dt'=>$ativo['manut_ultimo'], 'title'=>'Última preventiva (informada)', 'desc'=>'Campo do cadastro do ativo.'];
if (!empty($ativo['manut_proxima'])) $timeline[] = ['dt'=>$ativo['manut_proxima'], 'title'=>'Próxima preventiva (informada)', 'desc'=>'Campo do cadastro do ativo.'];

// manutenções (histórico)
$manuts = [];
if ($hasManutTbl){
  $st=$dbc->prepare("SELECT id,tipo,status,data_prevista,data_manutencao,numero_chamado,fornecedor,valor
                     FROM moz_ativo_manutencao
                     WHERE ativo_id=?
                     ORDER BY COALESCE(data_manutencao,data_prevista,created_at) DESC, id DESC");
  $st->bind_param('i',$ativo_id);
  $st->execute();
  $rs=$st->get_result();
  while($r=$rs->fetch_assoc()) $manuts[]=$r;
  $st->close();

  foreach($manuts as $m){
    $dt = $m['data_manutencao'] ?: ($m['data_prevista'] ?: null);
    if(!$dt) continue;
    $titulo = "Manutenção: ".$m['tipo'];
    $desc = "Status: ".$m['status'];
    if (!empty($m['numero_chamado'])) $desc .= " | Chamado: ".$m['numero_chamado'];
    if (!empty($m['fornecedor'])) $desc .= " | Fornecedor: ".$m['fornecedor'];
    if ($m['valor'] !== null && $m['valor'] !== '') $desc .= " | R$ ".number_format((float)$m['valor'],2,',','.');
    $timeline[] = ['dt'=>$dt, 'title'=>$titulo, 'desc'=>$desc, 'manut_id'=>(int)$m['id']];
  }
}

// ordena por data desc
usort($timeline, function($a,$b){
  $da = $a['dt'] ?? '0000-00-00';
  $db = $b['dt'] ?? '0000-00-00';
  return strcmp($db,$da);
});

// ========= Se pediu export, gera CSV =========
if ($doExport) {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="ativo_'.$ativo_id.'_vida.csv"');
  header('Pragma: no-cache');
  header('Expires: 0');

  // BOM UTF-8 (Excel)
  echo "\xEF\xBB\xBF";

  $out = fopen('php://output', 'w');

  // Cabeçalho
  fputcsv($out, ['ativo_id','ativo_nome','local','setor','data','titulo','descricao','manut_id'], ';');

  foreach ($timeline as $t) {
    fputcsv($out, [
      (int)$ativo_id,
      $ativo['nome'] ?? '',
      $local_nome ?: ('ID '.$ativo['local_id']),
      $setor_nome ?: '',
      fmtDate($t['dt'] ?? ''),
      (string)($t['title'] ?? ''),
      (string)($t['desc'] ?? ''),
      (int)($t['manut_id'] ?? 0),
    ], ';');
  }

  fclose($out);
  exit;
}

include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<style>
  .card{background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:14px;margin:12px 0;}
  .topbar{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
  .btn{display:inline-block;padding:8px 12px;border:1px solid #ddd;border-radius:10px;text-decoration:none;color:#222;background:#fafafa}
  .btn.primary{background:#111;color:#fff;border-color:#111}
  .grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
  @media(max-width:900px){.grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
  @media(max-width:560px){.grid{grid-template-columns:1fr;}}
  .kv{padding:10px;border:1px solid #eee;border-radius:12px;background:#fcfcfc}
  .kv b{display:block;font-size:12px;color:#666;margin-bottom:4px}
  .timeline{position:relative;margin:0;padding-left:18px}
  .timeline:before{content:"";position:absolute;left:6px;top:0;bottom:0;width:2px;background:#e7e7e7}
  .titem{position:relative;margin:0 0 14px 0}
  .titem:before{content:"";position:absolute;left:-15px;top:6px;width:10px;height:10px;border-radius:50%;background:#111}
  .tdate{font-size:12px;color:#666;margin-bottom:2px}
  .ttitle{font-weight:700}
  .tdesc{color:#333}
  .badge{display:inline-block;font-size:11px;padding:2px 8px;border:1px solid #ddd;border-radius:999px;background:#fafafa;margin-left:6px}
</style>

<div id="page-wrapper"><div class="container-fluid">
  <div class="card">
    <div class="topbar">
      <div>
        <h2 style="margin:0">Vida do Ativo</h2>
        <div style="color:#666;margin-top:4px">#<?= (int)$ativo['id'] ?> — <?= h($ativo['nome']) ?></div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn" href="<?= BASE_URL ?>/modules/gestao_ativos/ativos-form.php?id=<?= (int)$ativo['id'] ?>">Editar ativo</a>
        <a class="btn" href="<?= BASE_URL ?>/modules/gestao_ativos/ativos-vida.php?id=<?= (int)$ativo['id'] ?>&export=csv">Exportar CSV</a>
        <a class="btn primary" href="<?= BASE_URL ?>/modules/gestao_ativos/manutencoes-listar.php?ativo_id=<?= (int)$ativo['id'] ?>">Manutenções</a>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="grid">
      <div class="kv"><b>Local</b><?= h($local_nome ?: ('ID '.$ativo['local_id'])) ?></div>
      <div class="kv"><b>Setor</b><?= h($setor_nome ?: '—') ?></div>
      <div class="kv"><b>Última preventiva</b><?= h(fmtDate($ativo['manut_ultimo'] ?? '')) ?: '—' ?></div>
      <div class="kv"><b>Próxima preventiva</b><?= h(fmtDate($ativo['manut_proxima'] ?? '')) ?: '—' ?></div>
    </div>
  </div>

  <div class="card">
    <h3 style="margin:0 0 10px 0">Timeline</h3>

    <?php if (!$timeline): ?>
      <div style="color:#666">Sem eventos ainda.</div>
    <?php else: ?>
      <div class="timeline">
        <?php foreach($timeline as $t): ?>
          <div class="titem">
            <div class="tdate"><?= h(fmtDate($t['dt'] ?? '')) ?></div>
            <div class="ttitle">
              <?= h($t['title'] ?? '') ?>
              <?php if (!empty($t['manut_id'])): ?>
                <a class="badge" href="<?= BASE_URL ?>/modules/gestao_ativos/manutencoes-form.php?id=<?= (int)$t['manut_id'] ?>&ativo_id=<?= (int)$ativo['id'] ?>">ver</a>
              <?php endif; ?>
            </div>
            <div class="tdesc"><?= h($t['desc'] ?? '') ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div></div>
<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
