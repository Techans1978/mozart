<?php
// public/modules/gestao_ativos/etiquetas-qr-form.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();
$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function table_exists(mysqli $db,$t){ $r=$db->query("SHOW TABLES LIKE '".$db->real_escape_string($t)."'"); return $r && $r->num_rows>0; }

if (empty($_SESSION['csrf_etq'])) $_SESSION['csrf_etq']=bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_etq'];

/* Combos */
$cats=[]; $r=$dbc->query("SELECT id,nome FROM moz_cat_ativo ORDER BY nome"); if($r) while($x=$r->fetch_assoc()) $cats[]=$x;
$marcas=[]; $r=$dbc->query("SELECT id,nome FROM moz_marca ORDER BY nome"); if($r) while($x=$r->fetch_assoc()) $marcas[]=$x;
$modelos=[]; if(table_exists($dbc,'moz_modelo')){ $r=$dbc->query("SELECT id,nome FROM moz_modelo ORDER BY nome"); if($r) while($x=$r->fetch_assoc()) $modelos[]=$x; }
$locais=[];  $localTable = table_exists($dbc,'moz_local')?'moz_local':(table_exists($dbc,'moz_deposito')?'moz_deposito':null);
if($localTable){ $r=$dbc->query("SELECT id,nome FROM {$localTable} ORDER BY nome"); if($r) while($x=$r->fetch_assoc()) $locais[]=$x; }
$forn=[]; if(table_exists($dbc,'moz_fornecedor')){ $r=$dbc->query("SELECT id,nome FROM moz_fornecedor ORDER BY nome"); if($r) while($x=$r->fetch_assoc()) $forn[]=$x; }

/* POST: criar lote */
$err=''; $ok=''; $lote_id=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals($csrf, $_POST['csrf']??'')) die('CSRF inválido.');

  $titulo = trim($_POST['titulo'] ?? '');
  $layout = $_POST['layout'] ?? '62x29';
  $campos = $_POST['campos'] ?? 'tag,serial,modelo,qr';
  $logo   = isset($_POST['logo']) ? 1 : 0;

  // filtros de seleção
  $f = [
    'emitir_por'   => $_POST['emitir_por'] ?? 'ativos',
    'q'            => trim($_POST['q'] ?? ''),
    'local_id'     => ($_POST['local_id']??'')!==''?(int)$_POST['local_id']:null,
    'cat_id'       => ($_POST['cat_id']??'')!==''?(int)$_POST['cat_id']:null,
    'marca_id'     => ($_POST['marca_id']??'')!==''?(int)$_POST['marca_id']:null,
    'modelo_id'    => ($_POST['modelo_id']??'')!==''?(int)$_POST['modelo_id']:null,
    'status_id'    => ($_POST['status_id']??'')!==''?(int)$_POST['status_id']:null,
    'fornecedor_id'=> ($_POST['fornecedor_id']??'')!==''?(int)$_POST['fornecedor_id']:null,
    'responsavel'  => trim($_POST['responsavel'] ?? ''),
    'tags'         => trim($_POST['tags'] ?? ''),        // múltiplos, separados por vírgula/linha
    'seriais'      => trim($_POST['seriais'] ?? ''),      // múltiplos, separados por vírgula/linha
  ];

  // CSV (opcional): colunas: tag_patrimonial;numero_serie;nome;modelo;marca
  $csv_rows=[];
  if(!empty($_FILES['csv']) && $_FILES['csv']['error']===UPLOAD_ERR_OK){
    $h = fopen($_FILES['csv']['tmp_name'],'r');
    if($h){
      $head = fgetcsv($h,0,';'); $map = array_map('strtolower',$head ?? []);
      $ix=function($n)use($map){$i=array_search(strtolower($n),$map); return $i===false?null:$i;};
      $i_tag=$ix('tag_patrimonial'); $i_ser=$ix('numero_serie'); $i_nome=$ix('nome'); $i_modelo=$ix('modelo'); $i_marca=$ix('marca');
      while(($row=fgetcsv($h,0,';'))!==false){
        if(!trim(implode('', $row))) continue;
        $csv_rows[]=[
          'tag'=>$i_tag!==null?trim($row[$i_tag]):'',
          'serial'=>$i_ser!==null?trim($row[$i_ser]):'',
          'nome'=>$i_nome!==null?trim($row[$i_nome]):'',
          'modelo'=>$i_modelo!==null?trim($row[$i_modelo]):'',
          'marca'=>$i_marca!==null?trim($row[$i_marca]):'',
        ];
      }
      fclose($h);
    }
  }

  // constrói WHERE para seleção de moz_ativo
  $where=[]; $types=''; $args=[];
  if($f['q']!==''){ $where[]="(a.nome LIKE ? OR a.tag_patrimonial LIKE ? OR a.numero_serie LIKE ?)"; $types.='sss'; array_push($args,"%{$f['q']}%","%{$f['q']}%","%{$f['q']}%"); }
  if($f['local_id']){ $where[]="a.local_id=?"; $types.='i'; $args[]=$f['local_id']; }
  if($f['cat_id']){ $where[]="a.cat_id=?"; $types.='i'; $args[]=$f['cat_id']; }
  if($f['marca_id']){ $where[]="a.marca_id=?"; $types.='i'; $args[]=$f['marca_id']; }
  if($f['modelo_id']){ $where[]="a.modelo_id=?"; $types.='i'; $args[]=$f['modelo_id']; }
  if($f['status_id']){ $where[]="a.status_id=?"; $types.='i'; $args[]=$f['status_id']; }
  if($f['fornecedor_id']){ $where[]="a.fornecedor_id=?"; $types.='i'; $args[]=$f['fornecedor_id']; }
  if($f['responsavel']!==''){ $where[]="a.responsavel LIKE ?"; $types.='s'; $args[]="%{$f['responsavel']}%"; }
  if($f['tags']!==''){
    $list = array_filter(array_map('trim', preg_split('/[\s,]+/',$f['tags'])));
    if($list){ $where[]="a.tag_patrimonial IN (".str_repeat('?,',count($list)-1).'?)'; $types.=str_repeat('s',count($list)); $args=array_merge($args,$list); }
  }
  if($f['seriais']!==''){
    $list = array_filter(array_map('trim', preg_split('/[\s,]+/',$f['seriais'])));
    if($list){ $where[]="a.numero_serie IN (".str_repeat('?,',count($list)-1).'?)'; $types.=str_repeat('s',count($list)); $args=array_merge($args,$list); }
  }

  $wsql = $where?('WHERE '.implode(' AND ',$where)):'';
  $sql="SELECT a.id,a.nome,a.tag_patrimonial,a.numero_serie,
              c.nome AS categoria, ma.nome AS marca,
              ".(table_exists($dbc,'moz_modelo')?'mo.nome':'NULL')." AS modelo,
              ".($localTable? 'lo.nome':'NULL')." AS deposito,
              ".(table_exists($dbc,'moz_fornecedor')?'fo.nome':'NULL')." AS fornecedor,
              a.responsavel
        FROM moz_ativo a
        LEFT JOIN moz_cat_ativo c ON c.id=a.cat_id
        LEFT JOIN moz_marca ma ON ma.id=a.marca_id
        ".(table_exists($dbc,'moz_modelo')?'LEFT JOIN moz_modelo mo ON mo.id=a.modelo_id':'')."
        ".($localTable? "LEFT JOIN {$localTable} lo ON lo.id=a.local_id" :'')."
        ".(table_exists($dbc,'moz_fornecedor')?'LEFT JOIN moz_fornecedor fo ON fo.id=a.fornecedor_id':'')."
        $wsql
        LIMIT 5000";

  $sel=[];
  if($wsql){ $st=$dbc->prepare($sql); if($types) $st->bind_param($types,...$args); $st->execute(); $res=$st->get_result(); while($r=$res->fetch_assoc()) $sel[]=$r; $st->close(); }

  // cria lote
  $dbc->begin_transaction();
  try{
    $filtro_json = json_encode($f, JSON_UNESCAPED_UNICODE);
    $st=$dbc->prepare("INSERT INTO moz_etiqueta_lote (titulo, layout, campos, logo, filtro_json, created_by) VALUES (?,?,?,?,?,?)");
    $user = $_SESSION['usuario'] ?? null;
    $st->bind_param('sssiss',$titulo,$layout,$campos,$logo,$filtro_json,$user);
    $st->execute(); $lote_id=$st->insert_id; $st->close();

    // insere itens vindos da seleção do banco
    if($sel){
      $ins=$dbc->prepare("INSERT INTO moz_etiqueta_item (lote_id, ativo_id, tag, serial, nome, categoria, marca, modelo, fornecedor, responsavel, deposito, qr_href)
                          VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
      foreach($sel as $a){
        $href = BASE_URL."/modules/gestao_ativos/ativos-form.php?id=".$a['id'];
        $ins->bind_param('iissssssssss', $lote_id, $a['id'], $a['tag_patrimonial'],$a['numero_serie'], $a['nome'], $a['categoria'],$a['marca'],$a['modelo'], $a['fornecedor'],$a['responsavel'],$a['deposito'], $href);
        $ins->execute();
      }
      $ins->close();
    }

    // insere itens “soltos” do CSV (sem ativo_id obrigatório)
    if($csv_rows){
      $ins=$dbc->prepare("INSERT INTO moz_etiqueta_item (lote_id, tag, serial, nome, modelo, marca, qr_href) VALUES (?,?,?,?,?,?,?)");
      foreach($csv_rows as $r){
        $href = ($r['serial']||$r['tag']) ? (BASE_URL."/modules/gestao_ativos/ativos-listar.php?q=".urlencode($r['serial']?:$r['tag'])) : null;
        $ins->bind_param('issssss', $lote_id, $r['tag'],$r['serial'],$r['nome'],$r['modelo'],$r['marca'],$href);
        $ins->execute();
      }
      $ins->close();
    }

    $dbc->commit();
    header('Location: '.BASE_URL.'/modules/gestao_ativos/etiquetas-qr-imprimir.php?id='.$lote_id); exit;
  }catch(Exception $e){
    $dbc->rollback();
    $err='Erro ao criar lote: '.$e->getMessage();
  }
}

/* layout */
include_once ROOT_PATH.'system/includes/head.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<?php include_once ROOT_PATH.'system/includes/navbar.php'; ?>

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

  <session class="bpm"><div class="container">
    <header class="toolbar">
      <h1>Etiquetas / QR — Emissão</h1>
      <div class="actions"><a class="btn" href="etiquetas-qr-listar.php">Listar</a></div>
    </header>

    <?php if($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>

    <form class="card" method="post" enctype="multipart/form-data" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

      <p class="subtitle">Configuração</p>
      <div class="grid cols-4">
        <div><label>Título</label><input type="text" name="titulo" placeholder="Lote de etiquetas"/></div>
        <div><label>Layout</label>
          <select name="layout">
            <option value="62x29">Padrão 62×29mm</option>
            <option value="30x20">Pequeno 30×20mm</option>
            <option value="100x50">Grande 100×50mm</option>
          </select>
        </div>
        <div><label>Campos (vírgula)</label><input type="text" name="campos" value="tag,serial,modelo,qr" placeholder="tag,serial,modelo,qr"/></div>
        <div><label><input type="checkbox" name="logo" value="1"> Incluir logo padrão</label></div>
      </div>

      <div class="divider"></div>
      <p class="subtitle">Selecionar itens para emissão</p>
      <div class="grid cols-4">
        <div><label>Emitir por</label>
          <select name="emitir_por" id="emitir_por">
            <option value="ativos">Ativos (filtros abaixo)</option>
            <option value="csv">CSV (adiciona linhas soltas)</option>
          </select>
        </div>
        <div><label>Busca rápida</label><input type="text" name="q" placeholder="nome, tag, serial"/></div>
        <div><label>Depósito/Local</label>
          <select name="local_id"><option value="">—</option>
            <?php foreach($locais as $l): ?><option value="<?= (int)$l['id'] ?>"><?= h($l['nome']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label>Responsável</label><input type="text" name="responsavel" placeholder="login/nome"/></div>
      </div>

      <div class="grid cols-4">
        <div><label>Categoria</label>
          <select name="cat_id"><option value="">—</option>
            <?php foreach($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h($c['nome']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label>Marca</label>
          <select name="marca_id"><option value="">—</option>
            <?php foreach($marcas as $m): ?><option value="<?= (int)$m['id'] ?>"><?= h($m['nome']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label>Modelo</label>
          <select name="modelo_id"><option value="">—</option>
            <?php foreach($modelos as $mo): ?><option value="<?= (int)$mo['id'] ?>"><?= h($mo['nome']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label>Status</label>
          <select name="status_id"><option value="">—</option>
            <?php foreach([1=>'Em operação',2=>'Em estoque',3=>'Emprestado',4=>'Alugado',5=>'Em manutenção',6=>'Baixado'] as $k=>$v): ?>
              <option value="<?= $k ?>"><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="grid cols-3">
        <div><label>Fornecedor</label>
          <select name="fornecedor_id"><option value="">—</option>
            <?php foreach($forn as $f): ?><option value="<?= (int)$f['id'] ?>"><?= h($f['nome']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label>Tags/Patrimônio (lista)</label><textarea name="tags" rows="2" placeholder="TAG001, TAG002 ou em linhas"></textarea></div>
        <div><label>Números de série (lista)</label><textarea name="seriais" rows="2" placeholder="SER001, SER002 ou em linhas"></textarea></div>
      </div>

      <div class="divider"></div>
      <p class="subtitle">CSV opcional (adiciona itens “soltos”)</p>
      <div class="grid cols-2">
        <div class="stack">
          <label>Arquivo CSV</label>
          <input type="file" name="csv" accept=".csv">
          <span class="hint">Colunas: tag_patrimonial;numero_serie;nome;modelo;marca</span>
        </div>
        <div class="stack">
          <label>Modelo CSV</label>
          <textarea rows="5" readonly>tag_patrimonial;numero_serie;nome;modelo;marca
NB-0001;ABC123;Notebook João;Latitude 5440;Dell
;;Etiqueta livre sem ID;Zebra ZT-230;Zebra</textarea>
        </div>
      </div>

      <div class="row" style="justify-content:flex-end;gap:10px">
        <a class="btn" href="etiquetas-qr-listar.php">Cancelar</a>
        <button class="btn primary">Criar lote e pré-visualizar</button>
      </div>
    </form>
  </div></session>
</div></div>

<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
