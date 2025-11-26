<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

// helpers
function has_table(mysqli $db,$t){ $r=$db->query("SHOW TABLES LIKE '".$db->real_escape_string($t)."'"); return $r && $r->num_rows>0; }
$hasModelo = has_table($dbc,'moz_modelo');

if (empty($_SESSION['csrf_atv'])) $_SESSION['csrf_atv']=bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_atv'];

$id = (int)($_GET['id'] ?? 0);
$err=''; $ok='';

$padrao = [
  'nome'=>'','tag_patrimonial'=>'','numero_serie'=>'','cat_id'=>0,'status_id'=>1,'marca_id'=>0,
  'modelo_id'=>0,'local_id'=>0,'fornecedor_id'=>0,'nf_numero'=>'','data_compra'=>'',
  'garantia_ate'=>'','custo_aquisicao'=>'','depreciacao_meses'=>'','observacoes'=>'','ativo'=>1
];

// POST
if ($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals($csrf, $_POST['csrf'] ?? '')) die('CSRF inválido.');
  $id = (int)($_POST['id'] ?? 0);

  // coleta
  $p = $padrao;
  foreach($p as $k=>$_){ if(isset($_POST[$k])) $p[$k]=is_string($_POST[$k])?trim($_POST[$k]):$_POST[$k]; }
  $p['cat_id']=(int)$p['cat_id']; $p['status_id']=(int)$p['status_id']; $p['marca_id']=(int)$p['marca_id'];
  $p['modelo_id']=(int)$p['modelo_id']; $p['local_id']=(int)$p['local_id']; $p['fornecedor_id']=(int)$p['fornecedor_id'];
  $p['ativo']=isset($_POST['ativo'])?1:0;

  // fornecedor rápido por nome (opcional)
  if (!$p['fornecedor_id'] && !empty($_POST['fornecedor_nome'])) {
    $fn=trim($_POST['fornecedor_nome']);
    if($fn!==''){
      $q=$dbc->prepare("SELECT id FROM moz_fornecedor WHERE nome=? LIMIT 1");
      $q->bind_param('s',$fn); $q->execute(); $q->bind_result($fid); $q->fetch(); $q->close();
      if(!$fid){ $c=$dbc->prepare("INSERT INTO moz_fornecedor (nome) VALUES (?)"); $c->bind_param('s',$fn); $c->execute(); $fid=$c->insert_id; $c->close(); }
      $p['fornecedor_id']=(int)$fid;
    }
  }

  try{
    if($p['nome']==='' || $p['cat_id']<=0 || $p['status_id']<=0){
      throw new Exception('Preencha Nome, Categoria e Status.');
    }
    // INSERT/UPDATE
    if ($id>0){
      $sql="UPDATE moz_ativo SET nome=?, tag_patrimonial=?, numero_serie=?, cat_id=?, status_id=?, marca_id=?, ".
           ($hasModelo?"modelo_id=?, ":"").
           "local_id=?, fornecedor_id=?, nf_numero=?, data_compra=?, garantia_ate=?, custo_aquisicao=?, depreciacao_meses=?, observacoes=?, ativo=? WHERE id=?";
      $types="sssii".($hasModelo?'i':'')."iiisssdisii";
      // montar args
      $args = [
        $p['nome'],$p['tag_patrimonial'],$p['numero_serie'],$p['cat_id'],$p['status_id'],$p['marca_id']
      ];
      if($hasModelo) $args[]=$p['modelo_id'];
      array_push($args,$p['local_id'],$p['fornecedor_id'],$p['nf_numero'],$p['data_compra']?:null,$p['garantia_ate']?:null,
                 ($p['custo_aquisicao']!==''?(float)str_replace(',','.',$p['custo_aquisicao']):null),
                 ($p['depreciacao_meses']!==''?(int)$p['depreciacao_meses']:null),
                 $p['observacoes'],$p['ativo'],$id);
      $st=$dbc->prepare($sql);
      // bind dinâmico
      $st->bind_param(str_replace(' ','',$types), ...$args);
      $st->execute(); $st->close();
      $ok='Ativo atualizado.';
    } else {
      $sql="INSERT INTO moz_ativo (nome, tag_patrimonial, numero_serie, cat_id, status_id, marca_id, ".
           ($hasModelo?"modelo_id, ":"").
           "local_id, fornecedor_id, nf_numero, data_compra, garantia_ate, custo_aquisicao, depreciacao_meses, observacoes, ativo) ".
           "VALUES (?,?,?,?,?,?,".($hasModelo?'?,':'')."?,?,?,?,?,?,?,?,?)";
      $types="sssii".($hasModelo?'i':'')."iiisssdisi";
      $args=[
        $p['nome'],$p['tag_patrimonial'],$p['numero_serie'],$p['cat_id'],$p['status_id'],$p['marca_id']
      ];
      if($hasModelo) $args[]=$p['modelo_id'];
      array_push($args,$p['local_id'],$p['fornecedor_id'],$p['nf_numero'],$p['data_compra']?:null,$p['garantia_ate']?:null,
                 ($p['custo_aquisicao']!==''?(float)str_replace(',','.',$p['custo_aquisicao']):null),
                 ($p['depreciacao_meses']!==''?(int)$p['depreciacao_meses']:null), $p['observacoes'],$p['ativo']);
      $st=$dbc->prepare($sql);
      $st->bind_param(str_replace(' ','',$types), ...$args);
      $st->execute(); $id=$st->insert_id; $st->close();
      $ok='Ativo criado.';
    }
  } catch(Exception $e){ $err=$e->getMessage(); }
}

// GET (carregar)
$atv=$padrao;
if($id>0){
  $col="id,nome,tag_patrimonial,numero_serie,cat_id,status_id,marca_id,".($hasModelo?"modelo_id,":"")."local_id,fornecedor_id,nf_numero,data_compra,garantia_ate,custo_aquisicao,depreciacao_meses,observacoes,ativo";
  $st=$dbc->prepare("SELECT $col FROM moz_ativo WHERE id=?"); $st->bind_param('i',$id); $st->execute();
  $res=$st->get_result()->fetch_assoc(); $st->close();
  if($res){ foreach($atv as $k=>$_){ if(isset($res[$k])) $atv[$k]=$res[$k]; } }
}

// combos
$cats=[]; $r=$dbc->query("SELECT id,nome FROM moz_cat_ativo WHERE ativo=1 ORDER BY nome"); if($r) while($x=$r->fetch_assoc()) $cats[]=$x;
$marcas=[]; $r=$dbc->query("SELECT id,nome FROM moz_marca WHERE ativo=1 ORDER BY nome"); if($r) while($x=$r->fetch_assoc()) $marcas[]=$x;
$modelos=[]; if($hasModelo){ $r=$dbc->query("SELECT id,nome FROM moz_modelo ORDER BY nome"); if($r) while($x=$r->fetch_assoc()) $modelos[]=$x; }
$locais=[]; $r=$dbc->query("SELECT id,nome FROM moz_deposito ORDER BY nome"); if($r) while($x=$r->fetch_assoc()) $locais[]=$x;
$forns=[]; $r=$dbc->query("SELECT id,nome FROM moz_fornecedor ORDER BY nome"); if($r) while($x=$r->fetch_assoc()) $forns[]=$x;

// layout
include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= $id>0?'Editar ativo':'Novo ativo' ?></h1></div></div>
  <div class="row"><div class="col-lg-12">

    <?php if($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
    <?php if($ok): ?><div class="alert alert-success"><?= h($ok) ?></div><?php endif; ?>

    <form class="panel panel-default" method="post" autocomplete="off">
      <div class="panel-heading">Identificação</div>
      <div class="panel-body">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <div class="row">
          <div class="col-sm-4"><label>Categoria *</label>
            <select class="form-control" name="cat_id" required>
              <option value="0">— Selecione —</option>
              <?php foreach($cats as $c): ?><option value="<?=$c['id']?>" <?=$atv['cat_id']==$c['id']?'selected':''?>><?=h($c['nome'])?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-4"><label>Marca</label>
            <select class="form-control" name="marca_id">
              <option value="0">—</option>
              <?php foreach($marcas as $m): ?><option value="<?=$m['id']?>" <?=$atv['marca_id']==$m['id']?'selected':''?>><?=h($m['nome'])?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-4"><label>Modelo</label>
            <?php if($hasModelo): ?>
              <select class="form-control" name="modelo_id">
                <option value="0">—</option>
                <?php foreach($modelos as $mo): ?><option value="<?=$mo['id']?>" <?=$atv['modelo_id']==$mo['id']?'selected':''?>><?=h($mo['nome'])?></option><?php endforeach; ?>
              </select>
            <?php else: ?>
              <input class="form-control" value="— tabela moz_modelo não encontrada —" disabled>
            <?php endif; ?>
          </div>
        </div>

        <div class="row" style="margin-top:10px">
          <div class="col-sm-4"><label>Tag / Patrimônio *</label><input class="form-control" name="tag_patrimonial" value="<?=h($atv['tag_patrimonial'])?>"></div>
          <div class="col-sm-4"><label>Nº de série</label><input class="form-control" name="numero_serie" value="<?=h($atv['numero_serie'])?>"></div>
          <div class="col-sm-4"><label>Status operacional *</label>
            <select class="form-control" name="status_id" required>
              <?php foreach([1=>'Em operação',2=>'Em estoque',3=>'Emprestado',4=>'Alugado',5=>'Em manutenção',6=>'Baixado'] as $k=>$v): ?>
                <option value="<?=$k?>" <?=$atv['status_id']==$k?'selected':''?>><?=$v?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="row" style="margin-top:10px">
          <div class="col-sm-4"><label>Local *</label>
            <select class="form-control" name="local_id">
              <option value="0">—</option>
              <?php foreach($locais as $l): ?><option value="<?=$l['id']?>" <?=$atv['local_id']==$l['id']?'selected':''?>><?=h($l['nome'])?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-4"><label>Fornecedor</label>
            <select class="form-control" name="fornecedor_id">
              <option value="0">—</option>
              <?php foreach($forns as $f): ?><option value="<?=$f['id']?>" <?=$atv['fornecedor_id']==$f['id']?'selected':''?>><?=h($f['nome'])?></option><?php endforeach; ?>
            </select>
            <small class="text-muted">ou informe um nome:</small>
            <input class="form-control" name="fornecedor_nome" placeholder="criar rápido (opcional)">
          </div>
          <div class="col-sm-4"><label>Nome/Descrição *</label><input class="form-control" name="nome" required value="<?=h($atv['nome'])?>"></div>
        </div>
      </div>

      <div class="panel-heading">Aquisição</div>
      <div class="panel-body">
        <div class="row">
          <div class="col-sm-3"><label>Nota fiscal</label><input class="form-control" name="nf_numero" value="<?=h($atv['nf_numero'])?>"></div>
          <div class="col-sm-3"><label>Data de compra</label><input type="date" class="form-control" name="data_compra" value="<?=h($atv['data_compra'])?>"></div>
          <div class="col-sm-3"><label>Garantia até</label><input type="date" class="form-control" name="garantia_ate" value="<?=h($atv['garantia_ate'])?>"></div>
          <div class="col-sm-3"><label>Valor de compra</label><input class="form-control" name="custo_aquisicao" value="<?=h($atv['custo_aquisicao'])?>" placeholder="0.00"></div>
        </div>
        <div class="row" style="margin-top:10px">
          <div class="col-sm-3"><label>Vida útil (meses)</label><input type="number" min="0" class="form-control" name="depreciacao_meses" value="<?=h($atv['depreciacao_meses'])?>"></div>
          <div class="col-sm-6"><label>Observações</label><input class="form-control" name="observacoes" value="<?=h($atv['observacoes'])?>"></div>
          <div class="col-sm-3"><label>Status do cadastro</label><div><label class="checkbox-inline"><input type="checkbox" name="ativo" value="1" <?=$atv['ativo']?'checked':''?>> Ativo</label></div></div>
        </div>
      </div>

      <div class="panel-footer" style="text-align:right">
        <a class="btn btn-default" href="<?= BASE_URL ?>/modules/gestao_ativos/ativos-listar.php">Cancelar</a>
        <button class="btn btn-primary">Salvar</button>
      </div>
    </form>

  </div></div>
</div></div>

<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
