<?php
// public/modules/gestao_ativos/etiquetas-qr-imprimir.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

/* ================= helpers ================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function table_exists(mysqli $db,$t){ $r=$db->query("SHOW TABLES LIKE '".$db->real_escape_string($t)."'"); return $r && $r->num_rows>0; }
function has_col(mysqli $db,$t,$c){ $r=$db->query("SHOW COLUMNS FROM $t LIKE '".$db->real_escape_string($c)."'"); return $r && $r->num_rows>0; }

/**
 * QR placeholder em SVG inline (não é QR padrão; serve de mock visual).
 * Para produção, recomendo trocar por uma lib (ex.: chillerlan/php-qrcode)
 */
function qr_svg($text, $sizePx=120){
  $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
  // gera um padrao “pseudo-QR” a partir de hash; apenas visual
  $hash = hash('sha256', $safe, true); // binário 32 bytes
  $bits = '';
  for($i=0;$i<strlen($hash);$i++){ $bits .= str_pad(decbin(ord($hash[$i])),8,'0',STR_PAD_LEFT); }
  $n = 21; // 21x21 “módulos” (tamanho fixo tipo QR vers.1)
  $mod = $sizePx / $n;
  $i=0;
  $rects='';
  for($y=0;$y<$n;$y++){
    for($x=0;$x<$n;$x++){
      $bit = ($bits[$i % strlen($bits)]==='1');
      // garante “posicionadores” nos cantos para parecer QR
      $finder = ($x<7 && $y<7) || ($x>=$n-7 && $y<7) || ($x<7 && $y>=$n-7);
      if($finder){
        // desenha quadros dos posicionadores
        if( ($x==0||$x==6||$y==0||$y==6) || ($x>=2&&$x<=4&&$y>=2&&$y<=4) ){
          $rects .= '<rect x="'.($x*$mod).'" y="'.($y*$mod).'" width="'.$mod.'" height="'.$mod.'" fill="#000"/>';
        }
      } else if ($bit){
        $rects .= '<rect x="'.($x*$mod).'" y="'.($y*$mod).'" width="'.$mod.'" height="'.$mod.'" fill="#000"/>';
      }
      $i++;
    }
  }
  return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$sizePx.'" height="'.$sizePx.'" viewBox="0 0 '.$sizePx.' '.$sizePx.'"><rect width="100%" height="100%" fill="#fff"/>'.$rects.'</svg>';
}

/* ================ filtros ================== */
$idsParam       = trim($_REQUEST['ids'] ?? '');                 // "1,2,3"
$deposito       = ($_REQUEST['deposito'] ?? '')!=='' ? (int)$_REQUEST['deposito'] : null; // local_id
$q              = trim($_REQUEST['q'] ?? '');                   // nome/tag
$serieRaw       = trim($_REQUEST['serie'] ?? '');               // lista separada por vírgula/linha
$fornecedor_id  = ($_REQUEST['fornecedor_id'] ?? '')!=='' ? (int)$_REQUEST['fornecedor_id'] : null;
$responsavel    = trim($_REQUEST['responsavel'] ?? '');
$categoria_id   = ($_REQUEST['categoria_id'] ?? '')!=='' ? (int)$_REQUEST['categoria_id'] : null;
$marca_id       = ($_REQUEST['marca_id'] ?? '')!=='' ? (int)$_REQUEST['marca_id'] : null;
$modelo_id      = ($_REQUEST['modelo_id'] ?? '')!=='' ? (int)$_REQUEST['modelo_id'] : null;
$limit          = max(1, min(1000, (int)($_REQUEST['limit'] ?? 200)));

$layout = $_REQUEST['layout'] ?? '62x29'; // 62x29, 30x20, 100x50
$layouts = [
  '62x29' => ['w'=>62,  'h'=>29,  'qr'=>24],
  '30x20' => ['w'=>30,  'h'=>20,  'qr'=>16],
  '100x50'=> ['w'=>100, 'h'=>50,  'qr'=>36],
];
if(!isset($layouts[$layout])) $layout='62x29';
$L = $layouts[$layout];

/* ================ schema dinâmico ================== */
$hasModeloTable = table_exists($dbc,'moz_modelo');
$localTable = table_exists($dbc,'moz_local') ? 'moz_local' : (table_exists($dbc,'moz_deposito')?'moz_deposito':null);

/* ================ monta consulta ================== */
$where = []; $types=''; $args=[];
if($idsParam!==''){
  $ids = array_values(array_filter(array_map('intval', preg_split('/[,\s]+/',$idsParam)), fn($x)=>$x>0));
  if($ids){ $where[] = 'a.id IN ('.implode(',', $ids).')'; }
}
if($deposito!==null && $localTable){ $where[]='a.local_id=?'; $types.='i'; $args[]=$deposito; }
if($q!==''){ $where[]='(a.nome LIKE ? OR a.tag_patrimonial LIKE ? OR a.numero_serie LIKE ?)'; $types.='sss'; $args[]="%$q%"; $args[]="%$q%"; $args[]="%$q%"; }
if($serieRaw!==''){
  $series = array_values(array_filter(array_map('trim', preg_split('/[,\r\n]+/',$serieRaw))));
  if($series){
    $ph = implode(',', array_fill(0,count($series),'?')); $where[]="a.numero_serie IN ($ph)";
    $types .= str_repeat('s', count($series)); $args = array_merge($args,$series);
  }
}
if($fornecedor_id!==null){ $where[]='a.fornecedor_id=?'; $types.='i'; $args[]=$fornecedor_id; }
if($responsavel!==''){ $where[]='a.responsavel LIKE ?'; $types.='s'; $args[]="%$responsavel%"; }
if($categoria_id!==null){ $where[]='a.cat_id=?'; $types.='i'; $args[]=$categoria_id; }
if($marca_id!==null){ $where[]='a.marca_id=?'; $types.='i'; $args[]=$marca_id; }
if($modelo_id!==null){ $where[]='a.modelo_id=?'; $types.='i'; $args[]=$modelo_id; }
$wsql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

$colsModelo = $hasModeloTable ? "mo.nome AS modelo," : "'' AS modelo,";
$colsLocal  = $localTable ? "l.nome AS local_nome," : "'' AS local_nome,";

$sql = "SELECT a.id, a.nome, a.tag_patrimonial, a.numero_serie, a.responsavel,
               c.nome AS categoria, m.nome AS marca, $colsModelo $colsLocal
               f.nome AS fornecedor
        FROM moz_ativo a
        LEFT JOIN moz_cat_ativo c ON c.id=a.cat_id
        LEFT JOIN moz_marca m ON m.id=a.marca_id
        ".($hasModeloTable?"LEFT JOIN moz_modelo mo ON mo.id=a.modelo_id ":"")."
        ".($localTable? "LEFT JOIN {$localTable} l ON l.id=a.local_id ":"")."
        LEFT JOIN moz_fornecedor f ON f.id=a.fornecedor_id
        $wsql
        ORDER BY a.id ASC
        LIMIT ?";
$types2 = $types.'i'; $args2 = $args; $args2[] = $limit;

$st = $dbc->prepare($sql); if($types2) $st->bind_param($types2, ...$args2);
$st->execute(); $res = $st->get_result(); $rows = $res->fetch_all(MYSQLI_ASSOC); $st->close();

/* ================ view ================ */
include_once ROOT_PATH . 'system/includes/head.php';
?>
<style>
/* Layout de impressão */
body { background:#f6f7f9; }
.sheet { background:#fff; padding:10mm; margin:10px auto; box-shadow:0 2px 6px rgba(0,0,0,.08); width:210mm; }
.print-actions { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
.btn { display:inline-block; padding:6px 10px; border:1px solid #bbb; background:#fff; text-decoration:none; border-radius:4px; }
.btn.primary { background:#1976d2; color:#fff; border-color:#1976d2; }
.grid { display:grid; grid-template-columns: repeat(3, 1fr); gap:8px; }

.label {
  border:1px dashed #aaa; border-radius:4px; padding:4px 6px; display:flex; align-items:center; gap:6px;
  /* mm para px: 1mm ≈ 3.78px; vamos usar cm-friendly via CSS custom props */
  /* vamos converter mm para px inline no style da label abaixo */
}
.label .info { font-size:11px; line-height:1.2; }
.label .info .title { font-weight:700; font-size:12px; }
.label .info .muted { opacity:.7; font-size:10px; }
.label .qr { flex:0 0 auto; }
.small { font-size:10px; }
@media print {
  .no-print { display:none !important; }
  body { background:#fff; }
  .sheet { box-shadow:none; margin:0; padding:0; width:auto; }
  .grid { gap:4px; }
  .label { border:0; }
}
</style>

<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<?php include_once ROOT_PATH . 'system/includes/navbar.php'; ?>

<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

    <div class="row"><div class="col-lg-12">
      <div class="sheet">
        <div class="print-actions no-print">
          <div><strong>Etiquetas / QR — Impressão</strong> <span class="small">Layout: <?= h($layout) ?> • Itens: <?= (int)count($rows) ?></span></div>
          <div>
            <button class="btn primary" onclick="window.print()">Imprimir / PDF</button>
            <a class="btn" href="etiquetas-qr-form.php">Voltar</a>
          </div>
        </div>

<?php
// define dimensões CSS (px) a partir do layout (mm -> px ~ 3.78)
$mm = function($v){ return round($v*3.78); };
$wpx = $mm($L['w']); $hpx = $mm($L['h']); $qrpx = $mm($L['qr']);
$cols = 3;
?>
        <div class="grid" style="grid-template-columns: repeat(<?= $cols ?>, <?= $wpx ?>px); gap:8px">
<?php foreach($rows as $r):
  $tag   = $r['tag_patrimonial'] ?: ('ID '.$r['id']);
  $nome  = $r['nome'];
  $marca = $r['marca'] ?: '';
  $modelo= $r['modelo'] ?: '';
  $serie = $r['numero_serie'] ?: '—';
  $local = $r['local_nome'] ?: '—';
  $forn  = $r['fornecedor'] ?: '—';
  $resp  = $r['responsavel'] ?: '—';

  // URL de destino (ler/editar ativo)
  $url  = BASE_URL . "/modules/gestao_ativos/ativos-form.php?id=".(int)$r['id'];
  $qr   = qr_svg($url, min($qrpx, 140)); // limita 140px para não pesar
?>
          <div class="label" style="width:<?= $wpx ?>px;height:<?= $hpx ?>px">
            <div class="qr"><?= $qr ?></div>
            <div class="info">
              <div class="title"><?= h($tag) ?></div>
              <div class="muted"><?= h(trim($marca.' '.$modelo)) ?: '—' ?></div>
              <div><?= h($nome) ?></div>
              <div class="muted">Série: <?= h($serie) ?></div>
              <div class="muted">Local: <?= h($local) ?></div>
              <div class="muted">Fornecedor: <?= h($forn) ?></div>
              <div class="muted">Resp.: <?= h($resp) ?></div>
            </div>
          </div>
<?php endforeach; ?>
        </div>

        <?php if(!$rows): ?>
          <div class="alert alert-info" style="margin-top:12px">Nenhum item encontrado para os filtros informados.</div>
        <?php endif; ?>

        <div class="no-print" style="margin-top:12px">
          <details>
            <summary><strong>Parâmetros aceitos</strong></summary>
            <pre style="white-space:pre-wrap">
ids=1,2,3
deposito={local_id}
q={texto}                ; busca em nome, tag, série
serie=A1,B2              ; múltiplos por vírgula/linha
fornecedor_id=#
responsavel=nome
categoria_id=#
marca_id=#
modelo_id=#
limit=200
layout=62x29|30x20|100x50
            </pre>
          </details>
        </div>

      </div>
    </div></div>
  </div>
</div>

<?php include_once ROOT_PATH . 'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . 'system/includes/footer.php'; ?>
