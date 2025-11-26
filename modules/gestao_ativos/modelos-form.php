<?php
// public/modules/gestao_ativos/modelos-form.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

// === CSRF ===
if (empty($_SESSION['csrf_modelo_full'])) $_SESSION['csrf_modelo_full'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_modelo_full'];

// === Conexão ===
$dbc = $conn ?? null;
if (!$dbc) { die('Sem conexão com o banco.'); }

// === Helpers ===
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function upsert_dir($path){ if(!is_dir($path)) @mkdir($path,0775,true); return is_dir($path) && is_writable($path); }
function has_col($dbc,$table,$col){
  $r=$dbc->query("SHOW COLUMNS FROM {$table} LIKE '{$dbc->real_escape_string($col)}'");
  return $r && $r->num_rows>0;
}

// === Descobrir colunas opcionais ===
$hasAtivo          = has_col($dbc,'moz_modelo','ativo');
$hasCodigoInterno  = has_col($dbc,'moz_modelo','codigo_interno');
$hasVidaUtilMeses  = has_col($dbc,'moz_modelo','vida_util_meses');
$hasDescricao      = has_col($dbc,'moz_modelo','descricao');
$hasImagem         = has_col($dbc,'moz_modelo','imagem_path');

// === Carregar selects ===
$cats = [];
$rc = $dbc->query("SELECT id,nome FROM moz_cat_ativo WHERE ativo=1 ORDER BY nome");
if ($rc) while($r=$rc->fetch_assoc()) $cats[]=$r;

$marcas = [];
$rm = $dbc->query("SELECT id,nome FROM moz_marca ORDER BY nome");
if ($rm) while($r=$rm->fetch_assoc()) $marcas[]=$r;

// === Entrada ===
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$err  = '';
$ok   = '';

// Defaults (para sticky form)
$cat_id=null; $marca_id=null; $nome=''; $codigo_interno=''; $ativo_val=1; $vida_util_meses=''; $descricao=''; $imagem_path='';
$specs=[]; // [['nome'=>'CPU','valor'=>'i5','ordem'=>1], ...]
$compats=[]; // [['tipo'=>'Peça','referencia'=>'','obs'=>'','ordem'=>1],...]

// === POST: salvar ===
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!hash_equals($csrf, $_POST['csrf'] ?? '')) die('CSRF inválido.');

  $id   = (int)($_POST['id'] ?? 0);
  $cat_id   = ($_POST['cat_id']   ?? '')==='' ? null : (int)$_POST['cat_id'];
  $marca_id = ($_POST['marca_id'] ?? '')==='' ? null : (int)$_POST['marca_id'];
  $nome = trim($_POST['nome'] ?? '');
  $codigo_interno = trim($_POST['codigo_interno'] ?? '');
  $ativo_val = $hasAtivo ? (int)($_POST['ativo'] ?? 1) : 1;
  $vida_util_meses = $hasVidaUtilMeses ? (int)($_POST['vida_util_meses'] ?? 0) : 0;
  $descricao = $hasDescricao ? trim($_POST['descricao'] ?? '') : '';

  // specs
  $spec_nome  = $_POST['spec_nome']  ?? [];
  $spec_valor = $_POST['spec_valor'] ?? [];
  $specs = [];
  for ($i=0;$i<count($spec_nome);$i++){
    $n = trim((string)$spec_nome[$i]);
    $v = trim((string)($spec_valor[$i] ?? ''));
    if ($n!=='' && $v!=='') $specs[]=['nome'=>$n,'valor'=>$v,'ordem'=>($i+1)];
  }

  // compat
  $c_tipo = $_POST['compat_tipo'] ?? [];
  $c_ref  = $_POST['compat_ref']  ?? [];
  $c_obs  = $_POST['compat_obs']  ?? [];
  $compats=[];
  for ($i=0;$i<count($c_tipo);$i++){
    $t = trim((string)$c_tipo[$i]); if($t==='') $t='Peça';
    $r = trim((string)($c_ref[$i] ?? ''));
    $o = trim((string)($c_obs[$i] ?? ''));
    if ($r!=='') $compats[]=['tipo'=>$t,'referencia'=>$r,'obs'=>$o,'ordem'=>($i+1)];
  }

  // validações
  if (!$cat_id)   $err = $err ?: 'Selecione a categoria.';
  if (!$marca_id) $err = $err ?: 'Selecione o fabricante.';
  if ($nome==='') $err = $err ?: 'Informe o nome do modelo.';
  if ($vida_util_meses<0) $err = $err ?: 'Vida útil não pode ser negativa.';

  // upload (opcional)
  $upload_rel = '';
  if (!$err && $hasImagem && isset($_FILES['imagem']) && is_array($_FILES['imagem']) && $_FILES['imagem']['error']!==UPLOAD_ERR_NO_FILE) {
    if ($_FILES['imagem']['error']!==UPLOAD_ERR_OK) {
      $err = 'Falha no upload da imagem.';
    } else {
      $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['png','jpg','jpeg','svg','webp'])) $err = 'Formato de imagem inválido.';
      if (!$err) {
        $baseDir = ROOT_PATH . 'uploads/modelos';
        if (!upsert_dir($baseDir)) $err='Pasta de upload indisponível.';
        if (!$err) {
          $fname = 'modelo_'.($id?:'new').'_' . bin2hex(random_bytes(6)) . '.' . $ext;
          $destAbs = $baseDir . '/' . $fname;
          if (!move_uploaded_file($_FILES['imagem']['tmp_name'], $destAbs)) $err='Não foi possível gravar a imagem.';
          if (!$err) {
            // caminho relativo a partir de BASE_URL public/
            $upload_rel = 'uploads/modelos/' . $fname;
          }
        }
      }
    }
  }

  if (!$err) {
    $dbc->begin_transaction();
    try {
      if ($id>0) {
        // UPDATE mestre
        $sql = "UPDATE moz_modelo SET cat_id=?, marca_id=?, nome=?"
             . ($hasCodigoInterno ? ", codigo_interno=?" : "")
             . ($hasAtivo ? ", ativo=?" : "")
             . ($hasVidaUtilMeses ? ", vida_util_meses=?" : "")
             . ($hasDescricao ? ", descricao=?" : "")
             . ($hasImagem && $upload_rel ? ", imagem_path=?" : "")
             . " WHERE id=?";
        $types = "iis";
        $args  = [$cat_id, $marca_id, $nome];
        if ($hasCodigoInterno){ $types.="s"; $args[]=$codigo_interno; }
        if ($hasAtivo){ $types.="i"; $args[]=$ativo_val; }
        if ($hasVidaUtilMeses){ $types.="i"; $args[]=$vida_util_meses; }
        if ($hasDescricao){ $types.="s"; $args[]=$descricao; }
        if ($hasImagem && $upload_rel){ $types.="s"; $args[]=$upload_rel; }
        $types.="i"; $args[]=$id;

        $st = $dbc->prepare($sql);
        $st->bind_param($types, ...$args);
        $st->execute(); $st->close();

        // Limpa filhos e reinsere (simples e robusto)
        if ($dbc->query("DELETE FROM moz_modelo_spec WHERE modelo_id=".(int)$id)===false) throw new Exception('Erro ao limpar specs');
        if ($dbc->query("DELETE FROM moz_modelo_compat WHERE modelo_id=".(int)$id)===false) throw new Exception('Erro ao limpar compat');

        if ($specs){
          $st=$dbc->prepare("INSERT INTO moz_modelo_spec (modelo_id,nome,valor,ordem) VALUES (?,?,?,?)");
          foreach($specs as $s){ $st->bind_param('issi',$id,$s['nome'],$s['valor'],$s['ordem']); $st->execute(); }
          $st->close();
        }
        if ($compats){
          $st=$dbc->prepare("INSERT INTO moz_modelo_compat (modelo_id,tipo,referencia,obs,ordem) VALUES (?,?,?,?,?)");
          foreach($compats as $c){ $st->bind_param('isssi',$id,$c['tipo'],$c['referencia'],$c['obs'],$c['ordem']); $st->execute(); }
          $st->close();
        }

      } else {
        // INSERT mestre
        $sql = "INSERT INTO moz_modelo (cat_id,marca_id,nome"
             . ($hasCodigoInterno ? ",codigo_interno" : "")
             . ($hasAtivo ? ",ativo" : "")
             . ($hasVidaUtilMeses ? ",vida_util_meses" : "")
             . ($hasDescricao ? ",descricao" : "")
             . ($hasImagem && $upload_rel ? ",imagem_path" : "")
             . ") VALUES (?,?,?"
             . ($hasCodigoInterno ? ",?" : "")
             . ($hasAtivo ? ",?" : "")
             . ($hasVidaUtilMeses ? ",?" : "")
             . ($hasDescricao ? ",?" : "")
             . ($hasImagem && $upload_rel ? ",?" : "")
             . ")";
        $types = "iis";
        $args  = [$cat_id,$marca_id,$nome];
        if ($hasCodigoInterno){ $types.="s"; $args[]=$codigo_interno; }
        if ($hasAtivo){ $types.="i"; $args[]=$ativo_val; }
        if ($hasVidaUtilMeses){ $types.="i"; $args[]=$vida_util_meses; }
        if ($hasDescricao){ $types.="s"; $args[]=$descricao; }
        if ($hasImagem && $upload_rel){ $types.="s"; $args[]=$upload_rel; }

        $st=$dbc->prepare($sql);
        $st->bind_param($types, ...$args);
        $st->execute();
        $id = (int)$st->insert_id;
        $st->close();

        // Insere filhos
        if ($specs){
          $st=$dbc->prepare("INSERT INTO moz_modelo_spec (modelo_id,nome,valor,ordem) VALUES (?,?,?,?)");
          foreach($specs as $s){ $st->bind_param('issi',$id,$s['nome'],$s['valor'],$s['ordem']); $st->execute(); }
          $st->close();
        }
        if ($compats){
          $st=$dbc->prepare("INSERT INTO moz_modelo_compat (modelo_id,tipo,referencia,obs,ordem) VALUES (?,?,?,?,?)");
          foreach($compats as $c){ $st->bind_param('isssi',$id,$c['tipo'],$c['referencia'],$c['obs'],$c['ordem']); $st->execute(); }
          $st->close();
        }
      }

      $dbc->commit();
      $_SESSION['flash_ok'] = 'Modelo salvo com sucesso.';
      header('Location: '.BASE_URL.'/modules/gestao_ativos/modelos-listar.php');
      exit;

    } catch(Exception $e){
      $dbc->rollback();
      $err = 'Erro ao salvar: '.$e->getMessage();
    }
  }
}

// === GET: carregar dados existentes / ficar sticky após erro ===
if ($id>0 && !$err && $_SERVER['REQUEST_METHOD']!=='POST') {
  $cols = "id,cat_id,marca_id,nome"
        . ($hasCodigoInterno ? ",codigo_interno" : "")
        . ($hasAtivo ? ",ativo" : "")
        . ($hasVidaUtilMeses ? ",vida_util_meses" : "")
        . ($hasDescricao ? ",descricao" : "")
        . ($hasImagem ? ",imagem_path" : "");
  $st=$dbc->prepare("SELECT $cols FROM moz_modelo WHERE id=?");
  $st->bind_param('i',$id);
  $st->execute();
  if ($hasCodigoInterno && $hasAtivo && $hasVidaUtilMeses && $hasDescricao && $hasImagem) {
    $st->bind_result($rid,$cat_id,$marca_id,$nome,$codigo_interno,$ativo_val,$vida_util_meses,$descricao,$imagem_path);
  } else {
    // liga dinamicamente
    $meta=$st->get_result()->fetch_assoc();
    if ($meta){
      $cat_id = (int)$meta['cat_id']; $marca_id=(int)$meta['marca_id']; $nome=$meta['nome'];
      if(isset($meta['codigo_interno'])) $codigo_interno=$meta['codigo_interno'];
      if(isset($meta['ativo'])) $ativo_val=(int)$meta['ativo'];
      if(isset($meta['vida_util_meses'])) $vida_util_meses=(int)$meta['vida_util_meses'];
      if(isset($meta['descricao'])) $descricao=$meta['descricao'];
      if(isset($meta['imagem_path'])) $imagem_path=$meta['imagem_path'];
    }
    $st->close(); $st=null;
  }
  if ($st) { $st->fetch(); $st->close(); }

  // filhos
  $specs=[];
  $rs=$dbc->query("SELECT nome,valor,ordem FROM moz_modelo_spec WHERE modelo_id=".$id." ORDER BY ordem,id");
  if ($rs) while($r=$rs->fetch_assoc()) $specs[]=$r;

  $compats=[];
  $rc=$dbc->query("SELECT tipo,referencia,obs,ordem FROM moz_modelo_compat WHERE modelo_id=".$id." ORDER BY ordem,id");
  if ($rc) while($r=$rc->fetch_assoc()) $compats[]=$r;
}

// === Includes visuais ===
include_once ROOT_PATH . 'system/includes/head.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<?php include_once ROOT_PATH . 'system/includes/navbar.php'; ?>

<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

    <div class="row"><div class="col-lg-12">
<session class="bpm">
  <div class="container">

<header class="toolbar">
  <h1>Modelos — Cadastro</h1>
  <div class="actions">
    <a class="btn" href="<?= BASE_URL ?>/modules/gestao_ativos/modelos-listar.php">Listar modelos</a>
  </div>
</header>

<?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>

<form class="card" method="post" enctype="multipart/form-data" autocomplete="off" novalidate>
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="id" value="<?= (int)$id ?>">

  <p class="subtitle">Identificação</p>
  <div class="grid cols-3">
    <div>
      <label>Categoria *</label>
      <select name="cat_id" required>
        <option value="">— selecione —</option>
        <?php foreach($cats as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ($cat_id===(int)$c['id'])?'selected':'' ?>><?= h($c['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Fabricante *</label>
      <select name="marca_id" required>
        <option value="">— selecione —</option>
        <?php foreach($marcas as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= ($marca_id===(int)$m['id'])?'selected':'' ?>><?= h($m['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Nome do modelo *</label>
      <input type="text" name="nome" required placeholder="Ex.: Latitude 5440 / ZT-230 / Prix 5" value="<?= h($nome) ?>"/>
    </div>
  </div>

  <div class="grid cols-3">
    <div>
      <label>Código interno</label>
      <input type="text" name="codigo_interno" placeholder="SKU interno / referência" value="<?= h($codigo_interno) ?>"/>
    </div>
    <div>
      <label>Status *</label>
      <select name="ativo">
        <option value="1" <?= $ativo_val? 'selected':'' ?>>Ativo</option>
        <option value="0" <?= !$ativo_val? 'selected':'' ?>>Inativo</option>
      </select>
    </div>
    <div>
      <label>Vida útil sugerida (meses)</label>
      <input type="number" name="vida_util_meses" min="0" placeholder="36" value="<?= h((string)$vida_util_meses) ?>"/>
    </div>
  </div>

  <div class="grid cols-2">
    <div class="stack">
      <label>Imagem principal</label>
      <div class="file-zone">
        Arraste aqui ou <button type="button" class="btn small" onclick="document.getElementById('img_in').click()">escolha</button>
        <input id="img_in" type="file" name="imagem" accept="image/*" style="display:none"/>
      </div>
      <?php if ($imagem_path): ?>
        <div class="hint">Atual: <a href="<?= BASE_URL . '/' . ltrim($imagem_path,'/') ?>" target="_blank"><?= h(basename($imagem_path)) ?></a></div>
      <?php endif; ?>
      <span class="hint">PNG/JPG/SVG/WEBP • fundo transparente recomendado</span>
    </div>
    <div>
      <label>Descrição</label>
      <textarea name="descricao" placeholder="Resumo do modelo, indicação de uso, observações."><?= h($descricao) ?></textarea>
    </div>
  </div>

  <div class="divider"></div>

  <p class="subtitle">Especificações do modelo</p>
  <div id="spec-list" class="stack">
    <?php if (!$specs) $specs=[['nome'=>'','valor'=>'','ordem'=>1]]; ?>
    <?php foreach ($specs as $s): ?>
      <div class="grid cols-3 spec-row" style="align-items:end">
        <div><label>Nome *</label><input type="text" name="spec_nome[]" value="<?= h($s['nome']) ?>" placeholder="Ex.: CPU, RAM, Armazenamento"/></div>
        <div><label>Valor *</label><input type="text" name="spec_valor[]" value="<?= h($s['valor']) ?>" placeholder="Ex.: i5 / 16 GB / 512 GB SSD"/></div>
        <div class="row"><button type="button" class="btn small danger" onclick="this.closest('.spec-row').remove()">Remover</button></div>
      </div>
    <?php endforeach; ?>
  </div>
  <button type="button" class="btn small" id="add-spec">+ Adicionar especificação</button>

  <div class="divider"></div>

  <p class="subtitle">Compatibilidades (peças/acessórios)</p>
  <div id="compat-list" class="stack">
    <?php if (!$compats) $compats=[['tipo'=>'Peça','referencia'=>'','obs'=>'','ordem'=>1]]; ?>
    <?php foreach ($compats as $c): ?>
      <div class="grid cols-4 compat-row" style="align-items:end">
        <div>
          <label>Tipo</label>
          <select name="compat_tipo[]">
            <?php foreach (['Peça','Acessório','Consumível'] as $opt): ?>
              <option value="<?= h($opt) ?>" <?= ($c['tipo']===$opt)?'selected':'' ?>><?= h($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>Referência *</label><input type="text" name="compat_ref[]" value="<?= h($c['referencia']) ?>" placeholder="Categoria/Modelo"/></div>
        <div><label>Observação</label><input type="text" name="compat_obs[]" value="<?= h($c['obs']) ?>" placeholder="Ex.: compatível até rev. B"/></div>
        <div class="row"><button type="button" class="btn small danger" onclick="this.closest('.compat-row').remove()">Remover</button></div>
      </div>
    <?php endforeach; ?>
  </div>
  <button type="button" class="btn small" id="add-compat">+ Adicionar compatibilidade</button>

  <div class="divider"></div>

  <div style="display:flex;justify-content:flex-end;gap:10px">
    <a class="btn" href="<?= BASE_URL ?>/modules/gestao_ativos/modelos-listar.php">Cancelar</a>
    <button class="btn primary">Salvar</button>
  </div>
</form>

<div class="card"><p class="hint">Preencha e salve. As listas de Especificações e Compatibilidades são persistidas como linhas filhas do modelo.</p></div>

</session>
    </div></div>
  </div>
</div>

<?php include_once ROOT_PATH . 'system/includes/code_footer.php'; ?>
<script>
(function(){
  function specRow(n='',v=''){
    const el=document.createElement('div'); el.className='grid cols-3 spec-row'; el.style.alignItems='end';
    el.innerHTML=`<div><label>Nome *</label><input type="text" name="spec_nome[]" value="${n.replace(/"/g,'&quot;')}" placeholder="Ex.: CPU, RAM, Armazenamento"/></div>
                  <div><label>Valor *</label><input type="text" name="spec_valor[]" value="${v.replace(/"/g,'&quot;')}" placeholder="Ex.: i5 / 16 GB / 512 GB SSD"/></div>
                  <div class="row"><button type="button" class="btn small danger">Remover</button></div>`;
    el.querySelector('.btn.danger').addEventListener('click',()=>el.remove());
    return el;
  }
  function compatRow(t='Peça',r='',o=''){
    const el=document.createElement('div'); el.className='grid cols-4 compat-row'; el.style.alignItems='end';
    el.innerHTML=`<div><label>Tipo</label><select name="compat_tipo[]">
                     <option ${t==='Peça'?'selected':''}>Peça</option>
                     <option ${t==='Acessório'?'selected':''}>Acessório</option>
                     <option ${t==='Consumível'?'selected':''}>Consumível</option>
                   </select></div>
                   <div><label>Referência *</label><input type="text" name="compat_ref[]" value="${r.replace(/"/g,'&quot;')}" placeholder="Categoria/Modelo"/></div>
                   <div><label>Observação</label><input type="text" name="compat_obs[]" value="${o.replace(/"/g,'&quot;')}" placeholder="Ex.: compatível até rev. B"/></div>
                   <div class="row"><button type="button" class="btn small danger">Remover</button></div>`;
    el.querySelector('.btn.danger').addEventListener('click',()=>el.remove());
    return el;
  }
  const specList=document.getElementById('spec-list');
  const compatList=document.getElementById('compat-list');
  document.getElementById('add-spec').addEventListener('click',()=>specList.appendChild(specRow()));
  document.getElementById('add-compat').addEventListener('click',()=>compatList.appendChild(compatRow()));
})();
</script>
<?php include_once ROOT_PATH . 'system/includes/footer.php'; ?>
