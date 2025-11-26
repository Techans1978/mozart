<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');
$log=[];

$statuses = [
  'EM_OPERACAO'=>1,'EM_ESTOQUE'=>2,'EMPRESTADO'=>3,'ALUGADO'=>4,'EM_MANUTENCAO'=>5,'BAIXADO'=>6
];

if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_FILES['csv']) && $_FILES['csv']['error']===UPLOAD_ERR_OK){
  $f = fopen($_FILES['csv']['tmp_name'],'r');
  if(!$f) die('Falha ao abrir CSV.');
  $header = fgetcsv($f, 0, ';');
  $cols = array_map('strtolower',$header);

  // índice por nome de coluna
  $idx=function($name) use($cols){ $i=array_search(strtolower($name),$cols); return $i===false?null:$i; };

  $i_nome=$idx('nome'); $i_cat=$idx('categoria'); $i_marca=$idx('marca'); $i_modelo=$idx('modelo');
  $i_tag=$idx('tag_patrimonial'); $i_serial=$idx('numero_serie'); $i_status=$idx('status');
  $i_local=$idx('local'); $i_nf=$idx('nf_numero'); $i_dtcompra=$idx('data_compra'); $i_garantia=$idx('garantia_ate');
  $i_custo=$idx('custo_aquisicao'); $i_depr=$idx('depreciacao_meses'); $i_obs=$idx('observacoes');
  $i_fornecedor=$idx('fornecedor');

  $ins=$dbc->prepare("INSERT INTO moz_ativo (nome, cat_id, marca_id, modelo_id, tag_patrimonial, numero_serie, status_id, local_id, fornecedor_id, nf_numero, data_compra, garantia_ate, custo_aquisicao, depreciacao_meses, observacoes, ativo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)");

  while(($row=fgetcsv($f,0,';'))!==false){
    if(!$row || trim(implode('', $row))==='') continue;

    $nome = trim($row[$i_nome]??'');
    if($nome===''){ $log[]="Linha ignorada: nome em branco."; continue; }

    // resolve/insere categoria
    $cat_nome = trim($row[$i_cat]??'');
    $cat_id = null;
    if($cat_nome!==''){
      $q=$dbc->prepare("SELECT id FROM moz_cat_ativo WHERE nome=? LIMIT 1");
      $q->bind_param('s',$cat_nome); $q->execute(); $q->bind_result($cat_id); $q->fetch(); $q->close();
      if(!$cat_id){ $c=$dbc->prepare("INSERT INTO moz_cat_ativo (nome,ativo) VALUES (?,1)"); $c->bind_param('s',$cat_nome); $c->execute(); $cat_id=$c->insert_id; $c->close(); }
    }

    // resolve marca
    $marca_id=null; $marca_nome=trim($row[$i_marca]??'');
    if($marca_nome!==''){
      $q=$dbc->prepare("SELECT id FROM moz_marca WHERE nome=? LIMIT 1");
      $q->bind_param('s',$marca_nome); $q->execute(); $q->bind_result($marca_id); $q->fetch(); $q->close();
    }

    // resolve modelo (por nome e opcionalmente marca)
    $modelo_id=null; $modelo_nome=trim($row[$i_modelo]??'');
    if($modelo_nome!==''){
      if($marca_id){
        $q=$dbc->prepare("SELECT id FROM moz_modelo WHERE nome=? AND marca_id=? LIMIT 1");
        $q->bind_param('si',$modelo_nome,$marca_id);
      } else {
        $q=$dbc->prepare("SELECT id FROM moz_modelo WHERE nome=? LIMIT 1");
        $q->bind_param('s',$modelo_nome);
      }
      $q->execute(); $q->bind_result($modelo_id); $q->fetch(); $q->close();
    }

    // resolve local
    $local_id=null; $local_nome=trim($row[$i_local]??'');
    if($local_nome!==''){
      $q=$dbc->prepare("SELECT id FROM moz_local WHERE nome=? LIMIT 1");
      $q->bind_param('s',$local_nome); $q->execute(); $q->bind_result($local_id); $q->fetch(); $q->close();
    }

    // resolve fornecedor
    $forn_id=null; $forn_nome=trim($row[$i_fornecedor]??'');
    if($forn_nome!==''){
      $q=$dbc->prepare("SELECT id FROM moz_fornecedor WHERE nome=? LIMIT 1");
      $q->bind_param('s',$forn_nome); $q->execute(); $q->bind_result($forn_id); $q->fetch(); $q->close();
    }

    // status
    $status_txt=strtoupper(preg_replace('/\s+/','_',trim($row[$i_status]??'')));
    $status_id = $statuses[$status_txt] ?? 2; // default: EM_ESTOQUE

    // datas e valores
    $nf = trim($row[$i_nf]??'');
    $data_compra = trim($row[$i_dtcompra]??'') ?: null;
    $garantia_ate = trim($row[$i_garantia]??'') ?: null;
    $custo = is_numeric(str_replace([','],['.'],$row[$i_custo]??'')) ? str_replace([','],['.'],$row[$i_custo]) : null;
    $depr = is_numeric($row[$i_depr]??'') ? (int)$row[$i_depr] : null;
    $obs = trim($row[$i_obs]??'');

    $ins->bind_param(
      'siissiiisssssiss',
      $nome, $cat_id, $marca_id, $modelo_id,
      $row[$i_tag], $row[$i_serial], $status_id, $local_id,
      $forn_id, $nf, $data_compra, $garantia_ate, $custo, $depr, $obs
    );
    $ins->execute();
    $log[]="Importado: {$nome} (ID {$ins->insert_id})";
  }
  fclose($f);
}
?>
<?php include_once ROOT_PATH.'system/includes/head.php'; ?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<?php include_once ROOT_PATH.'system/includes/navbar.php'; ?>

<div id="page-wrapper"><div class="container-fluid">
  <h3 class="page-header">Ativos — Importar CSV</h3>

  <form class="card" method="post" enctype="multipart/form-data">
    <p class="subtitle">Arquivo</p>
    <div class="grid cols-2">
      <div class="stack">
        <label>CSV (separador ;)</label>
        <input type="file" name="csv" accept=".csv" required>
        <span class="hint">Use o modelo abaixo.</span>
      </div>
      <div class="stack">
        <label>Modelo de CSV</label>
        <textarea rows="6" readonly>
nome;categoria;marca;modelo;tag_patrimonial;numero_serie;status;local;fornecedor;nf_numero;data_compra;garantia_ate;custo_aquisicao;depreciacao_meses;observacoes
Notebook João;Notebook;Dell;Latitude 5440;TAG-001;ABC123;EM_ESTOQUE;Depósito;Distribuidora X;12345;2024-01-10;2026-01-10;4500,00;36;Primeira carga
Switch Core;Switch;;;;;EM_OPERACAO;Sala TI;;;;;;</textarea>
        <span class="hint">Datas no formato YYYY-MM-DD. Status aceitos: EM_OPERACAO, EM_ESTOQUE, EMPRESTADO, ALUGADO, EM_MANUTENCAO, BAIXADO.</span>
      </div>
    </div>
    <div class="row" style="justify-content:flex-end;gap:8px">
      <a class="btn" href="ativos-listar.php">Voltar</a>
      <button class="btn primary">Importar</button>
    </div>
  </form>

  <?php if(!empty($log)): ?>
    <div class="card"><p class="subtitle">Resultado</p>
      <ul><?php foreach($log as $l) echo '<li>'.h($l).'</li>'; ?></ul>
    </div>
  <?php endif; ?>
</div></div>

<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
