<?php
// public/modules/gestao_ativos/fabricantes-distribuidores-cadastro.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

/* ========= Helpers ========= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function has_col(mysqli $db,$t,$c){ $r=$db->query("SHOW COLUMNS FROM $t LIKE '".$db->real_escape_string($c)."'"); return $r && $r->num_rows>0; }
function ensure_dir($abs){ if(!is_dir($abs)) @mkdir($abs,0775,true); return is_dir($abs) && is_writable($abs); }
function save_upload($field,$baseDirAbs,$prefix='file_'){
  if(empty($_FILES[$field])||$_FILES[$field]['error']===UPLOAD_ERR_NO_FILE) return null;
  if($_FILES[$field]['error']!==UPLOAD_ERR_OK) throw new Exception('Falha no upload: '.$field);
  if(!ensure_dir($baseDirAbs)) throw new Exception('Pasta indisponível para upload.');
  $ext=strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
  $name=$prefix.bin2hex(random_bytes(6)).($ext?('.'.$ext):'');
  $dest=$baseDirAbs.'/'.$name;
  if(!move_uploaded_file($_FILES[$field]['tmp_name'],$dest)) throw new Exception('Não foi possível gravar o arquivo: '.$field);
  // caminho relativo a partir do public/
  return 'uploads/'.basename($baseDirAbs).'/'.$name;
}

/* ========= Contexto ========= */
$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');
if (empty($_SESSION['csrf_fabdist_full'])) $_SESSION['csrf_fabdist_full']=bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_fabdist_full'];

$tab  = $_GET['tab'] ?? 'fabricantes';
$tab  = in_array($tab,['fabricantes','distribuidores']) ? $tab : 'fabricantes';
$id   = (int)($_GET['id'] ?? 0);
$err  = '';

/* ========= Campos opcionais detectados ========= */
$marcaHasAtivo = has_col($dbc,'moz_marca','ativo');
$fornHasAtivo  = has_col($dbc,'moz_fornecedor','ativo');

/* ========= POST: salvar ========= */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!hash_equals($csrf, $_POST['csrf'] ?? '')) die('CSRF inválido.');
  $tab = $_POST['tab']==='distribuidores' ? 'distribuidores' : 'fabricantes';
  $id  = (int)($_POST['id'] ?? 0);

  try {
    if ($tab==='fabricantes') {
      // Campos do FABRICANTE
      $nome      = trim($_POST['marca_nome'] ?? '');
      $site_url  = trim($_POST['site_url'] ?? '');
      $obs       = trim($_POST['obs'] ?? '');
      $suporte_url      = trim($_POST['suporte_url'] ?? '');
      $suporte_tel      = trim($_POST['suporte_tel'] ?? '');
      $suporte_whatsapp = trim($_POST['suporte_whatsapp'] ?? '');
      $suporte_outros   = trim($_POST['suporte_outros_url'] ?? '');
      $suporte_email    = trim($_POST['suporte_email'] ?? '');
      $ativo     = $marcaHasAtivo ? (int)($_POST['ativo'] ?? 1) : 1;

      if ($nome==='') throw new Exception('Informe o nome da marca.');

      // Upload de logo (opcional)
      $logo_path=null;
      if (!empty($_FILES['logo']) && $_FILES['logo']['error']!==UPLOAD_ERR_NO_FILE){
        $ext=strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if(!in_array($ext,['png','jpg','jpeg','svg','webp'])) throw new Exception('Formato de logo inválido.');
        $logo_path = save_upload('logo', ROOT_PATH.'uploads/fabricantes', 'logo_');
      }

      $dbc->begin_transaction();
      if ($id>0){
        // UPDATE
        $sql = "UPDATE moz_marca SET nome=?, site_url=?, obs=?, suporte_url=?, suporte_tel=?, suporte_whatsapp=?, suporte_outros_url=?, suporte_email=?"
             . ($marcaHasAtivo ? ", ativo=?" : "")
             . ($logo_path ? ", logo_path=?" : "")
             . " WHERE id=?";
        $types="ssssssss"; $args=[$nome,$site_url,$obs,$suporte_url,$suporte_tel,$suporte_whatsapp,$suporte_outros,$suporte_email];
        if ($marcaHasAtivo){ $types.="i"; $args[]=$ativo; }
        if ($logo_path){ $types.="s"; $args[]=$logo_path; }
        $types.="i"; $args[]=$id;
        $st=$dbc->prepare($sql); $st->bind_param($types, ...$args); $st->execute(); $st->close();

        // Vínculos “onde compramos” — limpa e reinsere
        $dbc->query("DELETE FROM moz_fabricante_fornecedor WHERE marca_id=".(int)$id);
        if (!empty($_POST['dist_nome']) && is_array($_POST['dist_nome'])) {
          // para simplificar: cria fornecedores “rápidos” por nome se não existirem
          $ins=$dbc->prepare("INSERT INTO moz_fabricante_fornecedor (marca_id, fornecedor_id, contato) VALUES (?,?,?)");
          foreach($_POST['dist_nome'] as $i=>$n){
            $n=trim((string)$n); if($n==='') continue;
            $contato=trim((string)($_POST['dist_contato'][$i] ?? ''));
            // tenta achar fornecedor pelo nome
            $fid=null;
            $q=$dbc->prepare("SELECT id FROM moz_fornecedor WHERE nome=? LIMIT 1");
            $q->bind_param('s',$n); $q->execute(); $q->bind_result($fid); $q->fetch(); $q->close();
            if(!$fid){
              $c=$dbc->prepare("INSERT INTO moz_fornecedor (nome) VALUES (?)");
              $c->bind_param('s',$n); $c->execute(); $fid=$c->insert_id; $c->close();
            }
            $ins->bind_param('iis',$id,$fid,$contato); $ins->execute();
          }
          $ins->close();
        }

      } else {
        // INSERT
        $sql = "INSERT INTO moz_marca (nome, site_url, obs, suporte_url, suporte_tel, suporte_whatsapp, suporte_outros_url, suporte_email"
             . ($marcaHasAtivo ? ", ativo" : "")
             . ($logo_path ? ", logo_path" : "")
             . ") VALUES (?,?,?,?,?,?,?,?"
             . ($marcaHasAtivo ? ",?" : "")
             . ($logo_path ? ",?" : "")
             . ")";
        $types="ssssssss"; $args=[$nome,$site_url,$obs,$suporte_url,$suporte_tel,$suporte_whatsapp,$suporte_outros,$suporte_email];
        if ($marcaHasAtivo){ $types.="i"; $args[]=$ativo; }
        if ($logo_path){ $types.="s"; $args[]=$logo_path; }
        $st=$dbc->prepare($sql); $st->bind_param($types, ...$args); $st->execute(); $id=$st->insert_id; $st->close();

        // vínculos distribuidores (mesma lógica do update)
        if (!empty($_POST['dist_nome']) && is_array($_POST['dist_nome'])) {
          $ins=$dbc->prepare("INSERT INTO moz_fabricante_fornecedor (marca_id, fornecedor_id, contato) VALUES (?,?,?)");
          foreach($_POST['dist_nome'] as $i=>$n){
            $n=trim((string)$n); if($n==='') continue;
            $contato=trim((string)($_POST['dist_contato'][$i] ?? ''));
            $fid=null;
            $q=$dbc->prepare("SELECT id FROM moz_fornecedor WHERE nome=? LIMIT 1");
            $q->bind_param('s',$n); $q->execute(); $q->bind_result($fid); $q->fetch(); $q->close();
            if(!$fid){
              $c=$dbc->prepare("INSERT INTO moz_fornecedor (nome) VALUES (?)");
              $c->bind_param('s',$n); $c->execute(); $fid=$c->insert_id; $c->close();
            }
            $ins->bind_param('iis',$id,$fid,$contato); $ins->execute();
          }
          $ins->close();
        }
      }
      $dbc->commit();
      $_SESSION['flash_ok']='Fabricante salvo.';
      header('Location: '.BASE_URL.'/modules/gestao_ativos/fabricantes-distribuidores-listar.php?tab=fabricantes'); exit;
    }

    else { // DISTRIBUIDOR
      // Campos do DISTRIBUIDOR
      $razao  = trim($_POST['razao_social'] ?? '');
      $nome   = trim($_POST['nome_fantasia'] ?? '');
      $cnpj   = trim($_POST['cnpj'] ?? '');
      $ie     = trim($_POST['ie'] ?? '');
      $im     = trim($_POST['im'] ?? '');
      $porte  = trim($_POST['porte'] ?? '');
      $mf     = trim($_POST['matriz_filial'] ?? 'Matriz');
      $abertura = $_POST['data_abertura'] ?? null;
      $natureza = trim($_POST['natureza_juridica'] ?? '');
      $cnae     = trim($_POST['cnae_principal'] ?? '');
      $cep      = trim($_POST['cep'] ?? '');
      $logradouro = trim($_POST['logradouro'] ?? '');
      $numero     = trim($_POST['numero'] ?? '');
      $complemento= trim($_POST['complemento'] ?? '');
      $bairro     = trim($_POST['bairro'] ?? '');
      $municipio  = trim($_POST['municipio'] ?? '');
      $uf         = trim($_POST['uf'] ?? '');
      $email_geral= trim($_POST['email_geral'] ?? '');
      $tel_geral  = trim($_POST['telefone_geral'] ?? '');
      $ativo      = $fornHasAtivo ? (int)($_POST['ativo'] ?? 1) : 1;

      if ($razao==='') throw new Exception('Informe a razão social.');
      if ($email_geral && !filter_var($email_geral, FILTER_VALIDATE_EMAIL)) throw new Exception('E-mail geral inválido.');

      // uploads contrato/cnpj/outros (cada um opcional)
      $contrato_path = null; $cnpj_doc_path=null; $outros_paths=[];
      if(!empty($_FILES['contrato']))  $contrato_path  = save_upload('contrato', ROOT_PATH.'uploads/fornecedores', 'contrato_');
      if(!empty($_FILES['cnpj_doc']))  $cnpj_doc_path  = save_upload('cnpj_doc', ROOT_PATH.'uploads/fornecedores', 'cnpj_');
      if(!empty($_FILES['outros']) && is_array($_FILES['outros']['name'])){
        $base=ROOT_PATH.'uploads/fornecedores';
        for($i=0;$i<count($_FILES['outros']['name']);$i++){
          if($_FILES['outros']['error'][$i]===UPLOAD_ERR_NO_FILE) continue;
          if($_FILES['outros']['error'][$i]!==UPLOAD_ERR_OK) continue;
          if(!ensure_dir($base)) throw new Exception('Pasta de upload indisponível.');
          $ext=strtolower(pathinfo($_FILES['outros']['name'][$i], PATHINFO_EXTENSION));
          $name='outro_'.bin2hex(random_bytes(6)).($ext?('.'.$ext):'');
          $dest=$base.'/'.$name;
          if(move_uploaded_file($_FILES['outros']['tmp_name'][$i], $dest)) $outros_paths[]='uploads/fornecedores/'.$name;
        }
      }

      $dbc->begin_transaction();
      if ($id>0){
        $sql="UPDATE moz_fornecedor SET razao_social=?, nome_fantasia=?, nome=?, cnpj=?, ie=?, im=?, porte=?, matriz_filial=?, data_abertura=?, natureza_juridica=?, cnae_principal=?, cep=?, logradouro=?, numero=?, complemento=?, bairro=?, municipio=?, uf=?, email_geral=?, telefone_geral=?"
            .($fornHasAtivo?", ativo=?":"")
            ." WHERE id=?";
        $types="ssssssssssssssssssssi"; // 21 s/i antes do ativo/id
        $args=[$razao,$nome,$nome,$cnpj,$ie,$im,$porte,$mf,$abertura,$natureza,$cnae,$cep,$logradouro,$numero,$complemento,$bairro,$municipio,$uf,$email_geral,$tel_geral];
        if ($fornHasAtivo){ $types.="i"; $args[]=$ativo; }
        $types.="i"; $args[]=$id;
        $st=$dbc->prepare($sql); $st->bind_param($types, ...$args); $st->execute(); $st->close();

        // Contatos: limpa e reinsere
        $dbc->query("DELETE FROM moz_fornecedor_contato WHERE fornecedor_id=".(int)$id);
        if (!empty($_POST['ct_nome'])) {
          $ins=$dbc->prepare("INSERT INTO moz_fornecedor_contato (fornecedor_id, nome, departamento, email, telefone, whatsapp) VALUES (?,?,?,?,?,?)");
          $n=$_POST['ct_nome']; $d=$_POST['ct_depto']; $e=$_POST['ct_email']; $t=$_POST['ct_tel']; $w=$_POST['ct_whats'];
          for($i=0;$i<count($n);$i++){
            $nn=trim((string)$n[$i]); if($nn==='') continue;
            $ins->bind_param('isssss',$id,$nn, trim($d[$i]??''), trim($e[$i]??''), trim($t[$i]??''), trim($w[$i]??''));
            $ins->execute();
          }
          $ins->close();
        }

        // Marcas atendidas: limpa e reinsere (chips/tags de marca_id)
        $dbc->query("DELETE FROM moz_fabricante_fornecedor WHERE fornecedor_id=".(int)$id);
        if (!empty($_POST['marcas']) && is_array($_POST['marcas'])){
          $ins=$dbc->prepare("INSERT INTO moz_fabricante_fornecedor (marca_id, fornecedor_id) VALUES (?,?)");
          foreach($_POST['marcas'] as $mid){
            $mid=(int)$mid; if($mid<=0) continue;
            $ins->bind_param('ii',$mid,$id); $ins->execute();
          }
          $ins->close();
        }

        // Arquivos: registra metadados
        if ($contrato_path){
          $a=$dbc->prepare("INSERT INTO moz_fornecedor_arquivo (fornecedor_id, tipo, path) VALUES (?,?,?)");
          $tipo='contrato'; $a->bind_param('iss',$id,$tipo,$contrato_path); $a->execute(); $a->close();
        }
        if ($cnpj_doc_path){
          $a=$dbc->prepare("INSERT INTO moz_fornecedor_arquivo (fornecedor_id, tipo, path) VALUES (?,?,?)");
          $tipo='cnpj'; $a->bind_param('iss',$id,$tipo,$cnpj_doc_path); $a->execute(); $a->close();
        }
        if ($outros_paths){
          $a=$dbc->prepare("INSERT INTO moz_fornecedor_arquivo (fornecedor_id, tipo, path) VALUES (?,?,?)");
          foreach($outros_paths as $p){ $tipo='outros'; $a->bind_param('iss',$id,$tipo,$p); $a->execute(); }
          $a->close();
        }

      } else {
        $sql="INSERT INTO moz_fornecedor (razao_social, nome_fantasia, nome, cnpj, ie, im, porte, matriz_filial, data_abertura, natureza_juridica, cnae_principal, cep, logradouro, numero, complemento, bairro, municipio, uf, email_geral, telefone_geral"
            .($fornHasAtivo?", ativo":"").") VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?"
            .($fornHasAtivo?",?":"").")";
        $types="ssssssssssssssssssss"; $args=[$razao,$nome,$nome,$cnpj,$ie,$im,$porte,$mf,$abertura,$natureza,$cnae,$cep,$logradouro,$numero,$complemento,$bairro,$municipio,$uf,$email_geral,$tel_geral];
        if ($fornHasAtivo){ $types.="i"; $args[]=$ativo; }
        $st=$dbc->prepare($sql); $st->bind_param($types, ...$args); $st->execute(); $id=$st->insert_id; $st->close();

        // Contatos
        if (!empty($_POST['ct_nome'])) {
          $ins=$dbc->prepare("INSERT INTO moz_fornecedor_contato (fornecedor_id, nome, departamento, email, telefone, whatsapp) VALUES (?,?,?,?,?,?)");
          $n=$_POST['ct_nome']; $d=$_POST['ct_depto']; $e=$_POST['ct_email']; $t=$_POST['ct_tel']; $w=$_POST['ct_whats'];
          for($i=0;$i<count($n);$i++){
            $nn=trim((string)$n[$i]); if($nn==='') continue;
            $ins->bind_param('isssss',$id,$nn, trim($d[$i]??''), trim($e[$i]??''), trim($t[$i]??''), trim($w[$i]??''));
            $ins->execute();
          }
          $ins->close();
        }

        // Marcas atendidas
        if (!empty($_POST['marcas']) && is_array($_POST['marcas'])){
          $ins=$dbc->prepare("INSERT INTO moz_fabricante_fornecedor (marca_id, fornecedor_id) VALUES (?,?)");
          foreach($_POST['marcas'] as $mid){
            $mid=(int)$mid; if($mid<=0) continue;
            $ins->bind_param('ii',$mid,$id); $ins->execute();
          }
          $ins->close();
        }

        // Arquivos
        if ($contrato_path){
          $a=$dbc->prepare("INSERT INTO moz_fornecedor_arquivo (fornecedor_id, tipo, path) VALUES (?,?,?)");
          $tipo='contrato'; $a->bind_param('iss',$id,$tipo,$contrato_path); $a->execute(); $a->close();
        }
        if ($cnpj_doc_path){
          $a=$dbc->prepare("INSERT INTO moz_fornecedor_arquivo (fornecedor_id, tipo, path) VALUES (?,?,?)");
          $tipo='cnpj'; $a->bind_param('iss',$id,$tipo,$cnpj_doc_path); $a->execute(); $a->close();
        }
        if ($outros_paths){
          $a=$dbc->prepare("INSERT INTO moz_fornecedor_arquivo (fornecedor_id, tipo, path) VALUES (?,?,?)");
          foreach($outros_paths as $p){ $tipo='outros'; $a->bind_param('iss',$id,$tipo,$p); $a->execute(); }
          $a->close();
        }
      }
      $dbc->commit();
      $_SESSION['flash_ok']='Distribuidor salvo.';
      header('Location: '.BASE_URL.'/modules/gestao_ativos/fabricantes-distribuidores-listar.php?tab=distribuidores'); exit;
    }

  } catch(Exception $e){
    if($dbc->errno===0) { /* ignore */ }
    $dbc->rollback();
    $err = $e->getMessage();
  }
}

/* ========= GET: carregar valores ========= */
$marcas = []; $rm=$dbc->query("SELECT id,nome FROM moz_marca ORDER BY nome"); if($rm) while($r=$rm->fetch_assoc()) $marcas[]=$r;

$fab = ['marca_nome'=>'','site_url'=>'','obs'=>'','suporte_url'=>'','suporte_tel'=>'','suporte_whatsapp'=>'','suporte_outros_url'=>'','suporte_email'=>'','logo_path'=>'','ativo'=>1,'dist'=>[]];
$dist= [
  'razao_social'=>'','nome_fantasia'=>'','cnpj'=>'','ie'=>'','im'=>'','porte'=>'','matriz_filial'=>'Matriz','data_abertura'=>'',
  'natureza_juridica'=>'','cnae_principal'=>'','cep'=>'','logradouro'=>'','numero'=>'','complemento'=>'','bairro'=>'','municipio'=>'','uf'=>'',
  'email_geral'=>'','telefone_geral'=>'','ativo'=>1,'contatos'=>[],'marcas'=>[]
];

if ($id>0){
  if ($tab==='fabricantes'){
    $cols="id,nome,site_url,obs,suporte_url,suporte_tel,suporte_whatsapp,suporte_outros_url,suporte_email,logo_path".($marcaHasAtivo?",ativo":"");
    $st=$dbc->prepare("SELECT $cols FROM moz_marca WHERE id=?"); $st->bind_param('i',$id); $st->execute();
    $res=$st->get_result()->fetch_assoc(); $st->close();
    if($res){
      $fab['marca_nome']=$res['nome']; $fab['site_url']=$res['site_url']; $fab['obs']=$res['obs'];
      $fab['suporte_url']=$res['suporte_url']; $fab['suporte_tel']=$res['suporte_tel']; $fab['suporte_whatsapp']=$res['suporte_whatsapp'];
      $fab['suporte_outros_url']=$res['suporte_outros_url']; $fab['suporte_email']=$res['suporte_email']; $fab['logo_path']=$res['logo_path'];
      if(isset($res['ativo'])) $fab['ativo']=(int)$res['ativo'];

      // distribuidores da marca
      $fab['dist']=[];
      $q=$dbc->query("SELECT f.nome, rel.contato FROM moz_fabricante_fornecedor rel JOIN moz_fornecedor f ON f.id=rel.fornecedor_id WHERE rel.marca_id=".(int)$id);
      while($r=$q->fetch_assoc()) $fab['dist'][]=$r;
    }
  } else {
    $cols="id,razao_social,nome_fantasia,cnpj,ie,im,porte,matriz_filial,data_abertura,natureza_juridica,cnae_principal,cep,logradouro,numero,complemento,bairro,municipio,uf,email_geral,telefone_geral".($fornHasAtivo?",ativo":"");
    $st=$dbc->prepare("SELECT $cols FROM moz_fornecedor WHERE id=?"); $st->bind_param('i',$id); $st->execute();
    $res=$st->get_result()->fetch_assoc(); $st->close();
    if($res){
      foreach($dist as $k=>$v){ if(isset($res[$k])) $dist[$k]=$res[$k]; }
      if(isset($res['ativo'])) $dist['ativo']=(int)$res['ativo'];
      // contatos
      $dist['contatos']=[]; $q=$dbc->query("SELECT nome,departamento,email,telefone,whatsapp FROM moz_fornecedor_contato WHERE fornecedor_id=".(int)$id." ORDER BY id");
      while($r=$q->fetch_assoc()) $dist['contatos'][]=$r;
      // marcas
      $dist['marcas']=[]; $q=$dbc->query("SELECT marca_id FROM moz_fabricante_fornecedor WHERE fornecedor_id=".(int)$id);
      while($r=$q->fetch_assoc()) $dist['marcas'][]=(int)$r['marca_id'];
    }
  }
}

/* ========= Includes visuais ========= */
include_once ROOT_PATH.'system/includes/head.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<?php include_once ROOT_PATH.'system/includes/navbar.php'; ?>

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

  <div class="row"><div class="col-lg-12">
<session class="bpm"><div class="container">

  <header>
    <h1>Cadastro — Fabricante / Distribuidor</h1>
    <div class="chips"><span class="chip">Módulo: Ativos</span><span class="chip">Cadastro completo</span></div>
    <?php if($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
    <?php if(!empty($_SESSION['flash_ok'])): ?><div class="alert alert-success"><?= h($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div><?php endif; ?>
  </header>

  <!-- TIPO -->
  <div class="card">
    <p class="subtitle">Tipo de cadastro</p>
    <div class="toggle" role="tablist" aria-label="Escolha o tipo de cadastro">
      <input type="radio" id="t-fab" name="tipo" value="fabricante" <?= $tab==='fabricantes'?'checked':'' ?> onclick="switchTab('fabricantes')">
      <label for="t-fab">Fabricante</label>
      <input type="radio" id="t-dist" name="tipo" value="distribuidor" <?= $tab==='distribuidores'?'checked':'' ?> onclick="switchTab('distribuidores')">
      <label for="t-dist">Distribuidor</label>
    </div>
    <p class="hint" style="margin-top:8px">Você pode cadastrar como <b>Fabricante</b> (com marca/logo) ou <b>Distribuidor</b> (fornecedor da marca).</p>
  </div>

  <!-- ===== FABRICANTE ===== -->
  <form class="card" id="form-fabricante" method="post" enctype="multipart/form-data" autocomplete="off" novalidate style="<?= $tab==='fabricantes'?'':'display:none' ?>">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="tab" value="fabricantes">
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <p class="subtitle">Dados do Fabricante</p>
    <div class="grid cols-2">
      <div>
        <label>Nome da marca *</label>
        <input type="text" name="marca_nome" required placeholder="Ex.: Dell, HP, Toledo" value="<?= h($fab['marca_nome']) ?>"/>
      </div>
      <div>
        <label>Site do fabricante</label>
        <input type="url" name="site_url" placeholder="https://www.exemplo.com" value="<?= h($fab['site_url']) ?>"/>
      </div>
    </div>

    <div class="grid cols-2">
      <div class="stack">
        <label>Logo / Foto da marca</label>
        <div class="logo-drop">
          Arraste a imagem ou <button type="button" class="btn small" onclick="document.getElementById('logo').click()">escolha</button>
          <input type="file" id="logo" name="logo" accept="image/*" />
        </div>
        <?php if ($fab['logo_path']): ?>
          <div class="logo-preview" style="display:flex"><img alt="Logo preview" src="<?= BASE_URL.'/'.ltrim($fab['logo_path'],'/') ?>"></div>
          <span class="note">Atual: <a target="_blank" href="<?= BASE_URL.'/'.ltrim($fab['logo_path'],'/') ?>"><?= h(basename($fab['logo_path'])) ?></a></span>
        <?php endif; ?>
        <span class="note">Formatos: PNG/JPG/SVG/WEBP • fundo transparente recomendado</span>
      </div>
      <div>
        <label>Observações</label>
        <textarea name="obs" placeholder="Obs gerais, linhas de produto, particularidades de garantia, etc."><?= h($fab['obs']) ?></textarea>
      </div>
    </div>

    <div class="divider"></div>

    <p class="subtitle">Informações de Suporte do Fabricante</p>
    <div class="grid cols-3">
      <div><label>Link da página de suporte</label><input type="url" name="suporte_url" placeholder="https://..." value="<?= h($fab['suporte_url']) ?>"/></div>
      <div><label>Telefone</label><input type="tel" name="suporte_tel" placeholder="0800 ..." value="<?= h($fab['suporte_tel']) ?>"/></div>
      <div><label>WhatsApp</label><input type="tel" name="suporte_whatsapp" placeholder="(11) 90000-0000" value="<?= h($fab['suporte_whatsapp']) ?>"/></div>
    </div>
    <div class="grid cols-2">
      <div><label>Outros canais (URL)</label><input type="url" name="suporte_outros_url" placeholder="Portal de drivers, RMA..." value="<?= h($fab['suporte_outros_url']) ?>"/></div>
      <div><label>E-mail do suporte</label><input type="email" name="suporte_email" placeholder="suporte@..." value="<?= h($fab['suporte_email']) ?>"/></div>
    </div>

    <?php if ($marcaHasAtivo): ?>
    <div class="grid"><div><label>Status</label><label class="checkbox-inline"><input type="checkbox" name="ativo" value="1" <?= $fab['ativo']?'checked':'' ?>> Ativo</label></div></div>
    <?php endif; ?>

    <div class="divider"></div>

    <p class="subtitle">Distribuidores / Onde compramos essa marca</p>
    <div id="dist-list" class="stack">
      <?php if (!$fab['dist']) $fab['dist']=[['nome'=>'','contato'=>'']]; ?>
      <?php foreach($fab['dist'] as $d): ?>
        <div class="grid cols-3" style="align-items:end">
          <div><label>Distribuidor</label><input type="text" name="dist_nome[]" value="<?= h($d['nome']) ?>" placeholder="Nome do distribuidor"/></div>
          <div><label>Contato (tel/whats/email)</label><input type="text" name="dist_contato[]" value="<?= h($d['contato'] ?? '') ?>" placeholder="(11) 9... / comercial@..."/></div>
          <div class="row"><button type="button" class="btn small" onclick="this.closest('.grid').remove()">Remover</button></div>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn small" onclick="addDist()">+ Adicionar distribuidor</button>

    <div class="foot-actions" style="margin-top:14px">
      <a class="btn ghost" href="<?= BASE_URL ?>/modules/gestao_ativos/fabricantes-distribuidores-listar.php?tab=fabricantes">Cancelar</a>
      <button class="btn primary">Salvar Fabricante</button>
    </div>
  </form>

  <!-- ===== DISTRIBUIDOR ===== -->
  <form class="card" id="form-distribuidor" method="post" enctype="multipart/form-data" autocomplete="off" novalidate style="<?= $tab==='distribuidores'?'':'display:none' ?>">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="tab" value="distribuidores">
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <p class="subtitle">Dados do Distribuidor</p>
    <div class="grid cols-2">
      <div><label>Razão social *</label><input type="text" name="razao_social" required value="<?= h($dist['razao_social']) ?>" placeholder="Nome como no CNPJ"/></div>
      <div><label>Nome fantasia</label><input type="text" name="nome_fantasia" value="<?= h($dist['nome_fantasia']) ?>" placeholder="Opcional"/></div>
    </div>
    <div class="grid cols-3">
      <div><label>CNPJ *</label><input type="text" name="cnpj" value="<?= h($dist['cnpj']) ?>" placeholder="00.000.000/0000-00"/></div>
      <div><label>Inscrição Estadual</label><input type="text" name="ie" value="<?= h($dist['ie']) ?>" placeholder="Isento / nº"/></div>
      <div><label>Inscrição Municipal</label><input type="text" name="im" value="<?= h($dist['im']) ?>" placeholder="Opcional"/></div>
    </div>
    <div class="grid cols-3">
      <div><label>Porte</label><select name="porte">
        <?php foreach(['','MEI','Microempresa','Pequeno Porte','Médio/Grande'] as $p): ?>
          <option value="<?= h($p) ?>" <?= $dist['porte']===$p?'selected':'' ?>><?= $p?:'—' ?></option>
        <?php endforeach; ?>
      </select></div>
      <div><label>Matriz / Filial</label><select name="matriz_filial"><option <?= $dist['matriz_filial']==='Matriz'?'selected':'' ?>>Matriz</option><option <?= $dist['matriz_filial']==='Filial'?'selected':'' ?>>Filial</option></select></div>
      <div><label>Data de abertura</label><input type="date" name="data_abertura" value="<?= h($dist['data_abertura']) ?>"/></div>
    </div>
    <div class="grid cols-2">
      <div><label>Natureza jurídica</label><input type="text" name="natureza_juridica" value="<?= h($dist['natureza_juridica']) ?>" placeholder="Ex.: LTDA"/></div>
      <div><label>CNAE principal</label><input type="text" name="cnae_principal" value="<?= h($dist['cnae_principal']) ?>" placeholder="Código e descrição"/></div>
    </div>

    <div class="divider"></div>

    <p class="subtitle">Endereço (CNPJ)</p>
    <div class="grid cols-4">
      <div><label>CEP</label><input type="text" name="cep" value="<?= h($dist['cep']) ?>" placeholder="00000-000"/></div>
      <div class="cols-span-3"><label>Logradouro</label><input type="text" name="logradouro" value="<?= h($dist['logradouro']) ?>" placeholder="Rua/Avenida"/></div>
    </div>
    <div class="grid cols-4">
      <div><label>Número</label><input type="text" name="numero" value="<?= h($dist['numero']) ?>" placeholder="S/N"/></div>
      <div><label>Complemento</label><input type="text" name="complemento" value="<?= h($dist['complemento']) ?>" placeholder="Sala, bloco..."/></div>
      <div><label>Bairro</label><input type="text" name="bairro" value="<?= h($dist['bairro']) ?>"/></div>
      <div><label>Município</label><input type="text" name="municipio" value="<?= h($dist['municipio']) ?>"/></div>
    </div>
    <div class="grid cols-3">
      <div><label>UF</label><input type="text" name="uf" value="<?= h($dist['uf']) ?>" placeholder="SP, RJ..."/></div>
      <div><label>E-mail geral</label><input type="email" name="email_geral" value="<?= h($dist['email_geral']) ?>" placeholder="contato@..."/></div>
      <div><label>Telefone geral</label><input type="tel" name="telefone_geral" value="<?= h($dist['telefone_geral']) ?>" placeholder="(11) 4000-0000"/></div>
    </div>

    <div class="divider"></div>

    <p class="subtitle">Contatos (múltiplos)</p>
    <div id="contacts" class="grid" style="gap:14px">
      <?php if(!$dist['contatos']) $dist['contatos']=[['nome'=>'','departamento'=>'','email'=>'','telefone'=>'','whatsapp'=>'']]; ?>
      <?php foreach($dist['contatos'] as $c): ?>
        <div class="grid cols-3">
          <div><label>Nome do contato</label><input type="text" name="ct_nome[]" value="<?= h($c['nome']) ?>" placeholder="Fulano de Tal"/></div>
          <div><label>Departamento / Função</label><input type="text" name="ct_depto[]" value="<?= h($c['departamento']) ?>" placeholder="Comercial / Financeiro / Suporte"/></div>
          <div class="row"><button type="button" class="btn small danger" onclick="this.closest('.grid').remove()">Remover</button></div>
          <div><label>E-mail</label><input type="email" name="ct_email[]" value="<?= h($c['email']) ?>" placeholder="contato@empresa.com"/></div>
          <div><label>Telefone</label><input type="tel" name="ct_tel[]" value="<?= h($c['telefone']) ?>" placeholder="(11) 4000-0000"/></div>
          <div><label>WhatsApp</label><input type="tel" name="ct_whats[]" value="<?= h($c['whatsapp']) ?>" placeholder="(11) 90000-0000"/></div>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn small" onclick="addContact()">+ Adicionar contato</button>

    <div class="divider"></div>

    <p class="subtitle">Marcas atendidas por este distribuidor</p>
    <div class="stack">
      <div class="row" style="gap:8px; flex-wrap:wrap;">
        <?php foreach($marcas as $m): ?>
          <label class="checkbox-inline" style="min-width:200px">
            <input type="checkbox" name="marcas[]" value="<?= (int)$m['id'] ?>" <?= in_array((int)$m['id'],$dist['marcas'])?'checked':'' ?>> <?= h($m['nome']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="divider"></div>

    <p class="subtitle">Arquivos</p>
    <div class="grid cols-3">
      <div class="stack">
        <label>Contrato (PDF)</label>
        <div class="file-zone">Arraste aqui ou <button type="button" class="btn small" onclick="document.getElementById('f-contrato').click()">escolha</button>
          <input id="f-contrato" type="file" name="contrato" accept="application/pdf" /></div>
      </div>
      <div class="stack">
        <label>Cartão CNPJ (PDF/Imagem)</label>
        <div class="file-zone">Arraste aqui ou <button type="button" class="btn small" onclick="document.getElementById('f-cnpj').click()">escolha</button>
          <input id="f-cnpj" type="file" name="cnpj_doc" accept="application/pdf,image/*" /></div>
      </div>
      <div class="stack">
        <label>Outros</label>
        <div class="file-zone">Arraste aqui ou <button type="button" class="btn small" onclick="document.getElementById('f-outros').click()">escolha</button>
          <input id="f-outros" type="file" name="outros[]" multiple /></div>
      </div>
    </div>

    <?php if ($fornHasAtivo): ?>
    <div class="grid"><div><label>Status</label><label class="checkbox-inline"><input type="checkbox" name="ativo" value="1" <?= $dist['ativo']?'checked':'' ?>> Ativo</label></div></div>
    <?php endif; ?>

    <div class="foot-actions" style="margin-top:14px">
      <a class="btn ghost" href="<?= BASE_URL ?>/modules/gestao_ativos/fabricantes-distribuidores-listar.php?tab=distribuidores">Cancelar</a>
      <button class="btn primary">Salvar Distribuidor</button>
    </div>
  </form>

</div></session>
  </div></div>
</div></div>

<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
<script>
function switchTab(t){
  const fab=document.getElementById('form-fabricante');
  const dist=document.getElementById('form-distribuidor');
  if(t==='fabricantes'){ fab.style.display=''; dist.style.display='none'; }
  else { fab.style.display='none'; dist.style.display=''; }
}
function addDist(){
  const wrap=document.getElementById('dist-list');
  const el=document.createElement('div'); el.className='grid cols-3'; el.style.alignItems='end';
  el.innerHTML=`<div><label>Distribuidor</label><input type="text" name="dist_nome[]" placeholder="Nome do distribuidor"/></div>
                <div><label>Contato (tel/whats/email)</label><input type="text" name="dist_contato[]" placeholder="(11) 9... / comercial@..."/></div>
                <div class="row"><button type="button" class="btn small" onclick="this.closest('.grid').remove()">Remover</button></div>`;
  wrap.appendChild(el);
}
function addContact(){
  const w=document.getElementById('contacts');
  const el=document.createElement('div'); el.className='grid cols-3';
  el.innerHTML=`<div><label>Nome do contato</label><input type="text" name="ct_nome[]" placeholder="Fulano de Tal"/></div>
                <div><label>Departamento / Função</label><input type="text" name="ct_depto[]" placeholder="Comercial / Financeiro / Suporte"/></div>
                <div class="row"><button type="button" class="btn small danger" onclick="this.closest('.grid').remove()">Remover</button></div>
                <div><label>E-mail</label><input type="email" name="ct_email[]" placeholder="contato@empresa.com"/></div>
                <div><label>Telefone</label><input type="tel" name="ct_tel[]" placeholder="(11) 4000-0000"/></div>
                <div><label>WhatsApp</label><input type="tel" name="ct_whats[]" placeholder="(11) 90000-0000"/></div>`;
  w.appendChild(el);
}
</script>
<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
