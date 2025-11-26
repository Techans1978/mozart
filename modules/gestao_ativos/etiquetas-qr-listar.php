<?php
// public/modules/gestao_ativos/etiquetas-qr-listar.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();
$dbc=$conn??null; if(!$dbc) die('Sem conexão.');
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['csrf_etq_list'])) $_SESSION['csrf_etq_list']=bin2hex(random_bytes(16));
$csrf=$_SESSION['csrf_etq_list'];

/* Ações rápidas */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['acao'],$_POST['id']) && hash_equals($csrf, $_POST['csrf'] ?? '')){
  $id=(int)$_POST['id'];
  if($_POST['acao']==='inativar'){ $dbc->query("UPDATE moz_etiqueta_lote SET ativo=0 WHERE id={$id}"); }
  if($_POST['acao']==='excluir'){
    $dbc->query("DELETE FROM moz_etiqueta_lote WHERE id={$id}"); // cascade apaga itens
  }
  if($_POST['acao']==='export'){
    // CSV de itens do lote
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="etiquetas_lote_'.$id.'.csv"');
    $out=fopen('php://output','w');
    fputcsv($out, ['lote_id','ativo_id','tag','serial','nome','categoria','marca','modelo','fornecedor','responsavel','deposito','qr_href'], ';');
    $r=$dbc->query("SELECT lote_id,ativo_id,tag,serial,nome,categoria,marca,modelo,fornecedor,responsavel,deposito,qr_href FROM moz_etiqueta_item WHERE lote_id={$id}");
    while($row=$r->fetch_assoc()) fputcsv($out, array_values($row), ';');
    fclose($out); exit;
  }
  header('Location: '.$_SERVER['REQUEST_URI']); exit;
}

/* Filtros lista */
$q=trim($_GET['q']??''); $layout=$_GET['layout']??''; $ativo=($_GET['ativo']??'')!==''?(int)$_GET['ativo']:null;
$where=[]; $types=''; $args=[];
if($q!==''){ $where[]="(titulo LIKE ? OR campos LIKE ?)"; $types.='ss'; $args[]="%$q%"; $args[]="%$q%"; }
if($layout!==''){ $where[]="layout=?"; $types.='s'; $args[]=$layout; }
if($ativo!==null){ $where[]="ativo=?"; $types.='i'; $args[]=$ativo; }
$wsql=$where?('WHERE '.implode(' AND ',$where)):'';

$rows=[]; $st=$dbc->prepare("SELECT id,titulo,layout,campos,logo,ativo,impressoes,created_at FROM moz_etiqueta_lote $wsql ORDER BY id DESC LIMIT 200");
if($types) $st->bind_param($types,...$args); $st->execute(); $res=$st->get_result(); while($r=$res->fetch_assoc()) $rows[]=$r; $st->close();

include_once ROOT_PATH.'system/includes/head.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<?php include_once ROOT_PATH.'system/includes/navbar.php'; ?>

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

  <session class="bpm"><div class="container">
    <header class="toolbar">
      <h1>Etiquetas / QR — Listar</h1>
      <div class="actions"><a class="btn" href="etiquetas-qr-form.php">Nova emissão</a></div>
    </header>

    <form class="card" method="get">
      <p class="subtitle">Filtros</p>
      <div class="grid cols-4">
        <div><label>Busca</label><input type="text" name="q" value="<?= h($q) ?>" placeholder="título, campos"/></div>
        <div><label>Layout</label>
          <select name="layout"><option value="">—</option>
            <?php foreach(['62x29'=>'62×29mm','30x20'=>'30×20mm','100x50'=>'100×50mm'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $layout===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>Situação</label>
          <select name="ativo"><option value="">—</option>
            <option value="1" <?= $ativo===1?'selected':'' ?>>Ativo</option>
            <option value="0" <?= $ativo===0?'selected':'' ?>>Inativo</option>
          </select>
        </div>
        <div class="row" style="align-items:flex-end;justify-content:flex-end">
          <button class="btn">Aplicar</button>
          <a class="btn" href="etiquetas-qr-listar.php">Limpar</a>
        </div>
      </div>
    </form>

    <section id="list" class="grid cols-2">
      <?php if(!$rows): ?>
        <div class="card"><div class="hint">Sem lotes.</div></div>
      <?php else: foreach($rows as $r): ?>
        <article class="card">
          <div class="row" style="justify-content:space-between;align-items:flex-start">
            <div>
              <div style="font-weight:700"><?= h($r['titulo'] ?: ('Lote #'.$r['id'])) ?></div>
              <div class="hint"><?= h($r['layout']) ?> • Campos: <?= h($r['campos']) ?> <?= $r['logo']?'• com logo':'' ?></div>
            </div>
            <div class="row" style="gap:6px;flex-wrap:wrap">
              <span class="pill"><?= $r['ativo']?'Ativo':'Inativo' ?></span>
              <span class="pill">Impressões: <?= (int)$r['impressoes'] ?></span>
            </div>
          </div>
          <div class="divider"></div>
          <div class="grid cols-3">
            <div><label>Criado</label><div><?= h($r['created_at']) ?></div></div>
            <div><label>Itens</label>
              <div>
                <?php
                  $c=$dbc->query("SELECT COUNT(*) c FROM moz_etiqueta_item WHERE lote_id=".(int)$r['id'])->fetch_assoc()['c'] ?? 0;
                  echo (int)$c;
                ?>
              </div>
            </div>
            <div><label>ID</label><div>#<?= (int)$r['id'] ?></div></div>
          </div>
          <div class="divider"></div>
          <div class="row" style="justify-content:flex-end;gap:8px">
            <a class="btn small" href="etiquetas-qr-imprimir.php?id=<?= (int)$r['id'] ?>">Imprimir</a>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn small" name="acao" value="inativar" onclick="return confirm('Inativar lote?')">Inativar</button>
            </form>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn small" name="acao" value="export">Exportar CSV</button>
            </form>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn small danger" name="acao" value="excluir" onclick="return confirm('Excluir definitivamente?')">Excluir</button>
            </form>
          </div>
        </article>
      <?php endforeach; endif; ?>
    </section>
  </div></session>
</div></div>

<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
