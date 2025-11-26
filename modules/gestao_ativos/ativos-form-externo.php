<?php
// public/modules/gestao_ativos/ativos-form-externo.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
// Se quiser, troque por uma proteção leve ou token externo
proteger_pagina();

$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function has_col(mysqli $db,$t,$c){ $r=$db->query("SHOW COLUMNS FROM $t LIKE '".$db->real_escape_string($c)."'"); return $r && $r->num_rows>0; }
function table_exists(mysqli $db,$t){ $r=$db->query("SHOW TABLES LIKE '".$db->real_escape_string($t)."'"); return $r && $r->num_rows>0; }
function ensure_dir($abs){ if(!is_dir($abs)) @mkdir($abs,0775,true); return is_dir($abs) && is_writable($abs); }

if (empty($_SESSION['csrf_ativo_ext'])) $_SESSION['csrf_ativo_ext'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_ativo_ext'];

/* ================== Flags de schema ================== */
$hasModeloTbl       = table_exists($dbc,'moz_modelo');
$hasFornecedorTbl   = table_exists($dbc,'moz_fornecedor');
$hasCFDef           = table_exists($dbc,'moz_cf_def');
$hasCFVal           = table_exists($dbc,'moz_cf_val');
$hasRelTbl          = table_exists($dbc,'moz_ativo_relacao');
$hasAnexoTbl        = table_exists($dbc,'moz_ativo_anexo');

$hasManutUnid       = has_col($dbc,'moz_ativo','manut_periodo_unid');
$hasManutQtd        = has_col($dbc,'moz_ativo','manut_periodo_qtd');
$hasManutUlt        = has_col($dbc,'moz_ativo','manut_ultimo');
$hasManutProx       = has_col($dbc,'moz_ativo','manut_proxima');
$hasManutAlertar    = has_col($dbc,'moz_ativo','manut_alertar');

$hasGarantiaAte     = has_col($dbc,'moz_ativo','garantia_ate');
$hasNF              = has_col($dbc,'moz_ativo','nf_numero');
$hasCusto           = has_col($dbc,'moz_ativo','custo_aquisicao');
$hasDeprec          = has_col($dbc,'moz_ativo','depreciacao_meses');
$hasCentroCusto     = has_col($dbc,'moz_ativo','centro_custo');
$hasAtivoFlag       = has_col($dbc,'moz_ativo','ativo');

/* ===== EMPRESAS (Local) ===== */
$hasEmpresaTbl = table_exists($dbc,'empresas');
$empresas = [];
if ($hasEmpresaTbl) {
  $sqlEmp = "
    SELECT id,
           TRIM(
             CONCAT(
               COALESCE(NULLIF(nome_fantasia,''), COALESCE(NULLIF(nome_empresarial,''),'(sem nome)')),
               CASE WHEN COALESCE(NULLIF(apelido,''),'') <> ''
                    THEN CONCAT(' - ', apelido) ELSE '' END
             )
           ) AS nome
      FROM empresas ".(has_col($dbc,'empresas','ativo') ? "WHERE ativo=1" : "WHERE 1=1")."
     ORDER BY nome_empresarial ASC, nome_fantasia ASC, apelido ASC";
  if ($rs = $dbc->query($sqlEmp)) while($r=$rs->fetch_assoc()) $empresas[]=$r;
}

/* ===== DEPÓSITOS ===== */
$depositoTable = table_exists($dbc,'moz_deposito') ? 'moz_deposito' : (table_exists($dbc,'depositos') ? 'depositos' : null);
$hasDepositoTbl = $depositoTable !== null;
$hasDepositoCol = has_col($dbc,'moz_ativo','deposito_id');

function carregarDepositos(mysqli $dbc, ?int $empresa_id, ?string $depositoTable){
  if(!$depositoTable) return [];
  $out=[];
  $hasEmpCol = has_col($dbc,$depositoTable,'empresa_id');
  if ($hasEmpCol && $empresa_id) {
    $st=$dbc->prepare("SELECT id, nome FROM $depositoTable WHERE empresa_id=? ORDER BY nome");
    $st->bind_param('i',$empresa_id); $st->execute();
    $r=$st->get_result(); while($x=$r->fetch_assoc()) $out[]=$x; $st->close();
  } else {
    $q=$dbc->query("SELECT id, nome FROM $depositoTable ORDER BY nome");
    if($q) while($x=$q->fetch_assoc()) $out[]=$x;
  }
  return $out;
}

/* ================== AJAXs ================== */
if (isset($_GET['ajax']) && $_GET['ajax']==='modelos') {
  header('Content-Type: application/json; charset=utf-8');
  $marca_id = (int)($_GET['marca_id'] ?? 0);
  $out = [];
  if ($marca_id>0 && $hasModeloTbl) {
    $rs = $dbc->prepare("SELECT id,nome FROM moz_modelo WHERE marca_id=? ORDER BY nome");
    $rs->bind_param('i',$marca_id); $rs->execute();
    $res=$rs->get_result(); while($r=$res->fetch_assoc()) $out[]=$r; $rs->close();
  }
  echo json_encode($out); exit;
}

if (isset($_GET['ajax']) && $_GET['ajax']==='depositos') {
  header('Content-Type: application/json; charset=utf-8');
  $empresa_id = (int)($_GET['empresa_id'] ?? 0);
  $out = carregarDepositos($dbc, $empresa_id ?: null, $depositoTable);
  echo json_encode($out); exit;
}

/* ====== Campos customizados ====== */
$hasCFRequired = $hasCFDef && has_col($dbc,'moz_cf_def','required');
$hasCFAtivo    = $hasCFDef && has_col($dbc,'moz_cf_def','ativo');
$hasCFOrdem    = $hasCFDef && has_col($dbc,'moz_cf_def','ordem');

$cfCols  = "id,nome,tipo";
$cfCols .= $hasCFRequired ? ",required" : ",0 AS required";
$cfWhere = $hasCFAtivo ? "ativo=1" : "1=1";
$cfOrder = $hasCFOrdem ? "ordem,id" : "id";

/* ================== Entrada ================== */
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$err = '';
$ok  = '';

/* ================== POST: salvar ================== */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!hash_equals($csrf, $_POST['csrf'] ?? '')) die('CSRF inválido.');

  $id = (int)($_POST['id'] ?? 0);

  // Campos SEM required aqui
  $nome      = trim($_POST['nome'] ?? '');
  $cat_id    = ($_POST['cat_id'] ?? '')==='' ? null : (int)$_POST['cat_id'];
  $marca_id  = ($_POST['marca_id'] ?? '')==='' ? null : (int)$_POST['marca_id'];
  $modelo_id = ($_POST['modelo_id'] ?? '')==='' ? null : (int)$_POST['modelo_id'];
  $tag       = trim($_POST['tag_patrimonial'] ?? '');
  $serie     = trim($_POST['numero_serie'] ?? '');
  $status_id = (int)($_POST['status_id'] ?? 2);
  $ativo_reg = $hasAtivoFlag ? (int)($_POST['ativo'] ?? 1) : 1;

  $local_id     = ($_POST['local_id'] ?? '')==='' ? null : (int)$_POST['local_id'];
  $deposito_id  = ($hasDepositoCol && ($_POST['deposito_id'] ?? '')!=='') ? (int)$_POST['deposito_id'] : null;
  $rack         = trim($_POST['rack'] ?? '');
  $u_inicio     = ($_POST['u_inicio'] ?? '')==='' ? null : (int)$_POST['u_inicio'];
  $responsavel  = trim($_POST['responsavel'] ?? '');

  $fornecedor_id = $hasFornecedorTbl && ($_POST['fornecedor_id']??'')!=='' ? (int)$_POST['fornecedor_id'] : null;
  $nf_numero     = $hasNF ? trim($_POST['nf_numero'] ?? '') : null;
  $data_compra   = ($_POST['data_compra'] ?? '') ?: null;
  $garantia_ate  = $hasGarantiaAte ? (($_POST['garantia_ate'] ?? '') ?: null) : null;
  $custo         = $hasCusto ? (($_POST['custo_aquisicao']??'')!=='' ? str_replace(',','.',$_POST['custo_aquisicao']) : null) : null;
  $deprec        = $hasDeprec ? (($_POST['depreciacao_meses']??'')!=='' ? (int)$_POST['depreciacao_meses'] : null) : null;
  $centro_custo  = $hasCentroCusto ? trim($_POST['centro_custo'] ?? '') : null;
  $observacoes   = trim($_POST['observacoes'] ?? '');

  // manutenção preventiva
  $per_txt = trim($_POST['manut_periodicidade'] ?? '');
  $man_unid = null; $man_qtd = 0;
  if ($per_txt && preg_match('/^(\d+)([dm])$/',$per_txt,$m)) {
    $man_qtd  = (int)$m[1];
    $man_unid = ($m[2]==='d') ? 'dias' : 'meses';
  }
  $man_ult = ($_POST['manut_ultimo'] ?? '') ?: null;
  $man_alertar = $hasManutAlertar ? (int)($_POST['manut_alertar'] ?? ($man_qtd>0?1:0)) : null;

  // uploads
  $photos_paths=[]; $contrato_path=null; $outros_paths=[];
  $baseDir = rtrim(ROOT_PATH,'/').'/uploads/ativos';
  if (!ensure_dir($baseDir)) $err='Pasta de upload indisponível.';

  if (!$err && !empty($_FILES['fotos']) && is_array($_FILES['fotos']['name'])) {
    for($i=0;$i<count($_FILES['fotos']['name']);$i++){
      if($_FILES['fotos']['error'][$i]===UPLOAD_ERR_NO_FILE) continue;
      if($_FILES['fotos']['error'][$i]!==UPLOAD_ERR_OK) continue;
      $ext=strtolower(pathinfo($_FILES['fotos']['name'][$i], PATHINFO_EXTENSION));
      if(!in_array($ext,['png','jpg','jpeg','webp'])) continue;
      $fname = 'foto_'.bin2hex(random_bytes(5)).'.'.$ext;
      if(move_uploaded_file($_FILES['fotos']['tmp_name'][$i], $baseDir.'/'.$fname)){
        $photos_paths[] = 'uploads/ativos/'.$fname;
      }
    }
  }
  if (!$err && !empty($_FILES['contrato_pdf']) && $_FILES['contrato_pdf']['error']!==UPLOAD_ERR_NO_FILE){
    if($_FILES['contrato_pdf']['error']===UPLOAD_ERR_OK){
      $ext=strtolower(pathinfo($_FILES['contrato_pdf']['name'], PATHINFO_EXTENSION));
      if(in_array($ext,['pdf'])){
        $fname='garantia_'.bin2hex(random_bytes(5)).'.pdf';
        if(move_uploaded_file($_FILES['contrato_pdf']['tmp_name'], $baseDir.'/'.$fname))
          $contrato_path='uploads/ativos/'.$fname;
      }
    }
  }
  if (!$err && !empty($_FILES['outros']) && is_array($_FILES['outros']['name'])){
    for($i=0;$i<count($_FILES['outros']['name']);$i++){
      if($_FILES['outros']['error'][$i]===UPLOAD_ERR_NO_FILE) continue;
      if($_FILES['outros']['error'][$i]!==UPLOAD_ERR_OK) continue;
      $ext=strtolower(pathinfo($_FILES['outros']['name'][$i], PATHINFO_EXTENSION));
      $fname='doc_'.bin2hex(random_bytes(5)).($ext?('.'.$ext):'');
      if(move_uploaded_file($_FILES['outros']['tmp_name'][$i], $baseDir.'/'.$fname))
        $outros_paths[]='uploads/ativos/'.$fname;
    }
  }

  if (!$err) {
    $dbc->begin_transaction();
    try {
      if ($id>0) {
        // UPDATE
        $sql = "UPDATE moz_ativo SET
                  nome=?, cat_id=?, marca_id=?, modelo_id=?, tag_patrimonial=?, numero_serie=?, status_id=?, local_id=?"
             . ($hasDepositoCol ? ", deposito_id=?" : "")
             . ", fornecedor_id=?, "
             . ($hasNF ? "nf_numero=?," : "")
             . " data_compra=?, "
             . ($hasGarantiaAte ? "garantia_ate=?," : "")
             . ($hasCusto ? "custo_aquisicao=?," : "")
             . ($hasDeprec ? "depreciacao_meses=?," : "")
             . ($hasCentroCusto ? "centro_custo=?," : "")
             . " observacoes=? "
             . ($hasAtivoFlag ? ", ativo=? " : "")
             . ($hasManutUnid ? ", manut_periodo_unid=? " : "")
             . ($hasManutQtd  ? ", manut_periodo_qtd=? " : "")
             . ($hasManutUlt  ? ", manut_ultimo=? " : "")
             . ($hasManutAlertar ? ", manut_alertar=? " : "")
             . " WHERE id=?";
        $types = "siiissii";
        $args  = [$nome,$cat_id,$marca_id,$modelo_id,$tag,$serie,$status_id,$local_id];
        if ($hasDepositoCol){ $types.="i"; $args[]=$deposito_id; }
        $types.="i"; $args[]=$fornecedor_id;
        if ($hasNF){ $types.="s"; $args[]=$nf_numero; }
        $types.="s"; $args[]=$data_compra;
        if ($hasGarantiaAte){ $types.="s"; $args[]=$garantia_ate; }
        if ($hasCusto){ $types.="s"; $args[]=$custo; }
        if ($hasDeprec){ $types.="i"; $args[]=$deprec; }
        if ($hasCentroCusto){ $types.="s"; $args[]=$centro_custo; }
        $types.="s"; $args[]=$observacoes;
        if ($hasAtivoFlag){ $types.="i"; $args[]=$ativo_reg; }
        if ($hasManutUnid){ $types.="s"; $args[]=$man_unid; }
        if ($hasManutQtd) { $types.="i"; $args[]=$man_qtd; }
        if ($hasManutUlt) { $types.="s"; $args[]=$man_ult; }
        if ($hasManutAlertar){ $types.="i"; $args[]=$man_alertar; }
        $types.="i"; $args[]=$id;

        $st=$dbc->prepare($sql); $st->bind_param($types, ...$args); $st->execute(); $st->close();

      } else {
        // INSERT
        $sql = "INSERT INTO moz_ativo (
                  nome, cat_id, marca_id, modelo_id, tag_patrimonial, numero_serie, status_id, local_id"
             . ($hasDepositoCol ? ", deposito_id" : "")
             . ", fornecedor_id, "
             . ($hasNF ? "nf_numero, " : "")
             . "data_compra, "
             . ($hasGarantiaAte ? "garantia_ate, " : "")
             . ($hasCusto ? "custo_aquisicao, " : "")
             . ($hasDeprec ? "depreciacao_meses, " : "")
             . ($hasCentroCusto ? "centro_custo, " : "")
             . "observacoes"
             . ($hasAtivoFlag ? ", ativo" : "")
             . ($hasManutUnid ? ", manut_periodo_unid" : "")
             . ($hasManutQtd  ? ", manut_periodo_qtd"  : "")
             . ($hasManutUlt  ? ", manut_ultimo"       : "")
             . ($hasManutAlertar ? ", manut_alertar"   : "")
             . ") VALUES (?,?,?,?,?,?,?,?"
             . ($hasDepositoCol ? ",?" : "")
             . ",?"
             . ($hasNF ? ",?" : "")
             . ",?"
             . ($hasGarantiaAte ? ",?" : "")
             . ($hasCusto ? ",?" : "")
             . ($hasDeprec ? ",?" : "")
             . ($hasCentroCusto ? ",?" : "")
             . ",?"
             . ($hasAtivoFlag ? ",?" : "")
             . ($hasManutUnid ? ",?" : "")
             . ($hasManutQtd  ? ",?" : "")
             . ($hasManutUlt  ? ",?" : "")
             . ($hasManutAlertar ? ",?" : "")
             . ")";
        $types = "siiissii";
        $args  = [$nome,$cat_id,$marca_id,$modelo_id,$tag,$serie,$status_id,$local_id];
        if ($hasDepositoCol){ $types.="i"; $args[]=$deposito_id; }
        $types.="i"; $args[]=$fornecedor_id;
        if ($hasNF){ $types.="s"; $args[]=$nf_numero; }
        $types.="s"; $args[]=$data_compra;
        if ($hasGarantiaAte){ $types.="s"; $args[]=$garantia_ate; }
        if ($hasCusto){ $types.="s"; $args[]=$custo; }
        if ($hasDeprec){ $types.="i"; $args[]=$deprec; }
        if ($hasCentroCusto){ $types.="s"; $args[]=$centro_custo; }
        $types.="s"; $args[]=$observacoes;
        if ($hasAtivoFlag){ $types.="i"; $args[]=$ativo_reg; }
        if ($hasManutUnid){ $types.="s"; $args[]=$man_unid; }
        if ($hasManutQtd) { $types.="i"; $args[]=$man_qtd; }
        if ($hasManutUlt) { $types.="s"; $args[]=$man_ult; }
        if ($hasManutAlertar){ $types.="i"; $args[]=$man_alertar; }

        $st=$dbc->prepare($sql); $st->bind_param($types, ...$args); $st->execute(); $id=$st->insert_id; $st->close();
      }

      // custom fields
      if ($hasCFDef && $hasCFVal) {
        $dbc->query("DELETE FROM moz_cf_val WHERE ativo_id=".(int)$id);
        if (!empty($_POST['cf']) && is_array($_POST['cf'])) {
          $ins=$dbc->prepare("INSERT INTO moz_cf_val (ativo_id, def_id, valor) VALUES (?,?,?)");
          foreach($_POST['cf'] as $def_id=>$valor){
            $def_id=(int)$def_id; $valor=trim((string)$valor);
            if($def_id<=0) continue;
            $ins->bind_param('iis',$id,$def_id,$valor); $ins->execute();
          }
          $ins->close();
        }
      }

      // anexos
      if ($hasAnexoTbl) {
        if ($photos_paths){
          $a=$dbc->prepare("INSERT INTO moz_ativo_anexo (ativo_id, tipo, path) VALUES (?,?,?)");
          foreach($photos_paths as $p){ $tipo='foto'; $a->bind_param('iss',$id,$tipo,$p); $a->execute(); }
          $a->close();
        }
        if ($contrato_path){
          $a=$dbc->prepare("INSERT INTO moz_ativo_anexo (ativo_id, tipo, path) VALUES (?,?,?)");
          $tipo='contrato'; $a->bind_param('iss',$id,$tipo,$contrato_path); $a->execute(); $a->close();
        }
        if ($outros_paths){
          $a=$dbc->prepare("INSERT INTO moz_ativo_anexo (ativo_id, tipo, path) VALUES (?,?,?)");
          foreach($outros_paths as $p){ $tipo='outros'; $a->bind_param('iss',$id,$tipo,$p); $a->execute(); }
          $a->close();
        }
      }

      $dbc->commit();
      $_SESSION['flash_ok']='Ativo salvo com sucesso.';

      if (isset($_POST['salvar_novo'])) {
        header('Location: '.BASE_URL.'/modules/gestao_ativos/ativos-form-externo.php'); exit;
      } else {
        header('Location: '.BASE_URL.'/modules/gestao_ativos/ativos-form-externo.php?id='.$id); exit;
      }

    } catch(Exception $e){
      $dbc->rollback();
      $err = 'Erro ao salvar: '.$e->getMessage();
    }
  }
}

/* ================== GET: combos e edição ================== */
$cats=[]; $rs=$dbc->query("SELECT id,nome FROM moz_cat_ativo WHERE ".(has_col($dbc,'moz_cat_ativo','ativo')?'ativo=1':'1=1')." ORDER BY nome"); if($rs) while($r=$rs->fetch_assoc()) $cats[]=$r;
$marcas=[]; $rs=$dbc->query("SELECT id,nome FROM moz_marca ".(has_col($dbc,'moz_marca','ativo')?'WHERE ativo=1':'')." ORDER BY nome"); if($rs) while($r=$rs->fetch_assoc()) $marcas[]=$r;
$modelos=[];

$locais = $empresas;

$rec = [
  'nome'=>'','cat_id'=>'','marca_id'=>'','modelo_id'=>'','tag_patrimonial'=>'','numero_serie'=>'',
  'status_id'=>2,'ativo'=>1,'local_id'=>'','deposito_id'=>'','rack'=>'','u_inicio'=>'','responsavel'=>'',
  'fornecedor_id'=>'','nf_numero'=>'','data_compra'=>'','garantia_ate'=>'','custo_aquisicao'=>'',
  'depreciacao_meses'=>'','centro_custo'=>'','observacoes'=>'',
  'manut_unid'=>'','manut_qtd'=>0,'manut_ultimo'=>'','manut_alertar'=>1
];

if ($id>0 && !$err && $_SERVER['REQUEST_METHOD']!=='POST'){
  $cols = "id,nome,cat_id,marca_id,modelo_id,tag_patrimonial,numero_serie,status_id,local_id"
        . ($hasDepositoCol ? ",deposito_id" : "")
        . ",fornecedor_id,"
        . ($hasNF ? "nf_numero," : "")
        . "data_compra,"
        . ($hasGarantiaAte ? "garantia_ate," : "")
        . ($hasCusto ? "custo_aquisicao," : "")
        . ($hasDeprec ? "depreciacao_meses," : "")
        . ($hasCentroCusto ? "centro_custo," : "")
        . "observacoes"
        . ($hasAtivoFlag ? ",ativo" : "")
        . ($hasManutUnid ? ",manut_periodo_unid" : "")
        . ($hasManutQtd  ? ",manut_periodo_qtd"  : "")
        . ($hasManutUlt  ? ",manut_ultimo"       : "")
        . ($hasManutProx ? ",manut_proxima"      : "")
        . ($hasManutAlertar ? ",manut_alertar"   : "");
  $st=$dbc->prepare("SELECT $cols FROM moz_ativo WHERE id=?");
  $st->bind_param('i',$id); $st->execute();
  $row=$st->get_result()->fetch_assoc(); $st->close();
  if($row){
    $rec = array_merge($rec, [
      'nome'=>$row['nome'], 'cat_id'=>$row['cat_id'], 'marca_id'=>$row['marca_id'], 'modelo_id'=>$row['modelo_id'],
      'tag_patrimonial'=>$row['tag_patrimonial'], 'numero_serie'=>$row['numero_serie'], 'status_id'=>$row['status_id'],
      'local_id'=>$row['local_id'], 'fornecedor_id'=>$row['fornecedor_id'], 'data_compra'=>$row['data_compra'],
      'observacoes'=>$row['observacoes']
    ]);
    if($hasDepositoCol) $rec['deposito_id']=$row['deposito_id'];
    if($hasNF)          $rec['nf_numero'] = $row['nf_numero'];
    if($hasGarantiaAte) $rec['garantia_ate'] = $row['garantia_ate'];
    if($hasCusto)       $rec['custo_aquisicao'] = $row['custo_aquisicao'];
    if($hasDeprec)      $rec['depreciacao_meses'] = $row['depreciacao_meses'];
    if($hasCentroCusto) $rec['centro_custo'] = $row['centro_custo'];
    if($hasAtivoFlag)   $rec['ativo'] = (int)$row['ativo'];
    if($hasManutUnid)   $rec['manut_unid'] = $row['manut_periodo_unid'];
    if($hasManutQtd)    $rec['manut_qtd']  = (int)$row['manut_periodo_qtd'];
    if($hasManutUlt)    $rec['manut_ultimo']= $row['manut_ultimo'];
  }
}

/* Pré-carrega depósitos conforme local */
$depositos = ($hasDepositoTbl) ? carregarDepositos($dbc, (int)($rec['local_id'] ?: 0) ?: null, $depositoTable) : [];

?><!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Ativos — Externo</title>
  <style>
    :root{
      --bg:#f7f7f8; --card:#fff; --txt:#1f2937; --muted:#6b7280; --pri:#2563eb; --b:#e5e7eb;
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);font:14px/1.4 system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, "Helvetica Neue", Arial;}
    .wrap{max-width:760px;margin:0 auto;padding:16px;}
    .card{background:var(--card);border:1px solid var(--b);border-radius:12px;padding:16px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
    h1{font-size:18px;margin:0 0 12px}
    .subtitle{margin:18px 0 8px;color:var(--muted);font-weight:600}
    label{display:block;font-weight:600;margin:10px 0 6px}
    input[type=text],input[type=date],input[type=number],select,textarea{
      width:100%;padding:10px;border:1px solid var(--b);border-radius:8px;background:#fff;color:var(--txt)
    }
    textarea{min-height:90px;resize:vertical}
    .grid{display:grid;grid-template-columns:1fr;gap:12px}
    .row{display:flex;gap:8px;align-items:center}
    .stack{display:flex;flex-direction:column;gap:8px}
    .divider{height:1px;background:var(--b);margin:16px 0}
    .btn{border:1px solid var(--b);background:#fff;padding:10px 14px;border-radius:10px;cursor:pointer}
    .btn.primary{background:var(--pri);color:#fff;border-color:transparent}
    .btn.small{padding:6px 10px;font-size:12px}
    .alert{padding:10px 12px;border-radius:10px;margin:8px 0}
    .alert-success{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}
    .alert-danger{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
    .file-zone{border:1px dashed var(--b);padding:12px;border-radius:10px}
    .hint{color:var(--muted);font-size:12px}
    .toolbar{display:flex;justify-content:space-between;align-items:center;margin:12px 0}
    @media (min-width:720px){
      h1{font-size:20px}
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="toolbar">
      <h1>Ativos — <?= $id>0 ? 'Editar' : 'Cadastro' ?> (Externo)</h1>
      <div class="row">
        <a class="btn" href="<?= BASE_URL ?>/modules/gestao_ativos/ativos-listar.php">Listar</a>
      </div>
    </div>

    <?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
    <?php if (!empty($_SESSION['flash_ok'])): ?><div class="alert alert-success"><?= h($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div><?php endif; ?>

    <form class="card" method="post" enctype="multipart/form-data" autocomplete="off" novalidate>
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int)$id ?>">

      <p class="subtitle">Identificação</p>
      <div class="grid">
        <div>
          <label>Nome</label>
          <input type="text" name="nome" placeholder="Ex.: Notebook João Silva" value="<?= h($rec['nome']) ?>"/>
        </div>

        <div>
          <label>Categoria</label>
          <select name="cat_id" id="cat_id">
            <option value="">—</option>
            <?php foreach($cats as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (string)$rec['cat_id']===(string)$c['id']?'selected':'' ?>><?= h($c['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Marca</label>
          <select name="marca_id" id="marca_id">
            <option value="">—</option>
            <?php foreach($marcas as $m): ?>
              <option value="<?= (int)$m['id'] ?>" <?= (string)$rec['marca_id']===(string)$m['id']?'selected':'' ?>><?= h($m['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Modelo</label>
          <select name="modelo_id" id="modelo_id">
            <option value="">—</option>
            <?php foreach($modelos as $mo): ?>
              <option value="<?= (int)$mo['id'] ?>" <?= (string)$rec['modelo_id']===(string)$mo['id']?'selected':'' ?>><?= h($mo['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div><label>Tag / Patrimônio</label><input type="text" name="tag_patrimonial" placeholder="TAG" value="<?= h($rec['tag_patrimonial']) ?>"/></div>
        <div><label>Nº de série</label><input type="text" name="numero_serie" placeholder="Serial" value="<?= h($rec['numero_serie']) ?>"/></div>

        <div>
          <label>Status operacional</label>
          <select name="status_id">
            <?php foreach([1=>'Em operação',2=>'Em estoque',3=>'Emprestado',4=>'Alugado',5=>'Em manutenção',6=>'Baixado'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= (string)$rec['status_id']===(string)$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Ativo?</label>
          <select name="ativo" <?= $hasAtivoFlag?'':'disabled' ?>>
            <option value="1" <?= !empty($rec['ativo'])? 'selected':'' ?>>Sim</option>
            <option value="0" <?= empty($rec['ativo'])? 'selected':'' ?>>Não</option>
          </select>
          <?php if(!$hasAtivoFlag): ?><span class="hint">Campo "ativo" não existe em moz_ativo</span><?php endif; ?>
        </div>
      </div>

      <div class="divider"></div>
      <p class="subtitle">Localização</p>
      <div class="grid">
        <div>
          <label>Local (Empresa)</label>
          <select name="local_id" id="local_id">
            <option value="">—</option>
            <?php foreach($locais as $l): ?>
              <option value="<?= (int)$l['id'] ?>" <?= (string)$rec['local_id']===(string)$l['id']?'selected':'' ?>><?= h($l['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Depósito</label>
          <select name="deposito_id" id="deposito_id" <?= ($hasDepositoTbl && $hasDepositoCol)?'':'disabled' ?>>
            <option value="">—</option>
            <?php if ($depositos): foreach($depositos as $d): ?>
              <option value="<?= (int)$d['id'] ?>" <?= (string)$rec['deposito_id']===(string)$d['id']?'selected':'' ?>><?= h($d['nome']) ?></option>
            <?php endforeach; endif; ?>
          </select>
          <?php if(!($hasDepositoTbl && $hasDepositoCol)): ?>
            <span class="hint">Esperado: tabela <?= h($depositoTable?:'moz_deposito') ?> e coluna moz_ativo.deposito_id</span>
          <?php endif; ?>
        </div>

        <div><label>Rack</label><input type="text" name="rack" value="<?= h($rec['rack']) ?>" placeholder="Opcional"/></div>
        <div><label>U início</label><input type="number" name="u_inicio" min="1" value="<?= h($rec['u_inicio']) ?>" placeholder="Ex.: 18"/></div>
        <div><label>Responsável</label><input type="text" name="responsavel" value="<?= h($rec['responsavel']) ?>" placeholder="login/nome"/></div>
      </div>

      <div class="divider"></div>
      <p class="subtitle">Aquisição</p>
      <div class="grid">
        <div>
          <label>Fornecedor</label>
          <select name="fornecedor_id" <?= $hasFornecedorTbl?'':'disabled' ?>>
            <option value="">—</option>
            <?php
              $fornecedores=[]; if ($hasFornecedorTbl){ $rs=$dbc->query("SELECT id,nome FROM moz_fornecedor ORDER BY nome"); if($rs) while($r=$rs->fetch_assoc()) $fornecedores[]=$r; }
              foreach($fornecedores as $f):
            ?>
              <option value="<?= (int)$f['id'] ?>" <?= (string)$rec['fornecedor_id']===(string)$f['id']?'selected':'' ?>><?= h($f['nome']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if(!$hasFornecedorTbl): ?><span class="hint">Tabela moz_fornecedor ausente</span><?php endif; ?>
        </div>
        <div><label>Nota fiscal</label><input type="text" name="nf_numero" value="<?= h($rec['nf_numero']) ?>" placeholder="NF-e" <?= $hasNF?'':'disabled' ?>/></div>
        <div><label>Data de compra</label><input type="date" name="data_compra" value="<?= h($rec['data_compra']) ?>"/></div>
        <div><label>Garantia até</label><input type="date" name="garantia_ate" value="<?= h($rec['garantia_ate']) ?>" <?= $hasGarantiaAte?'':'disabled' ?>/></div>
        <div><label>Valor de compra</label><input type="number" step="0.01" min="0" name="custo_aquisicao" value="<?= h($rec['custo_aquisicao']) ?>" placeholder="0,00" <?= $hasCusto?'':'disabled' ?>/></div>
        <div><label>Depreciação (meses)</label><input type="number" min="0" name="depreciacao_meses" value <?= $hasDeprec?('="'.h($rec['depreciacao_meses']).'"'):'=""' ?> placeholder="36" <?= $hasDeprec?'':'disabled' ?>/></div>
        <div><label>Centro de custo</label><input type="text" name="centro_custo" value="<?= h($rec['centro_custo']) ?>" placeholder="Opcional" <?= $hasCentroCusto?'':'disabled' ?>/></div>
      </div>

      <div class="divider"></div>
      <p class="subtitle">Manutenção preventiva</p>
      <?php
        $per_value = '';
        if (($rec['manut_qtd']??0)>0) $per_value = ($rec['manut_unid']==='dias' ? $rec['manut_qtd'].'d' : $rec['manut_qtd'].'m');
      ?>
      <div class="grid">
        <div>
          <label>Periodicidade</label>
          <select name="manut_periodicidade" id="manut_periodicidade" <?= ($hasManutUnid&&$hasManutQtd)?'':'disabled' ?>>
            <option value="">— sem alerta —</option>
            <optgroup label="Dias">
              <option value="7d"  <?= $per_value==='7d'?'selected':'' ?>>7 dias</option>
              <option value="15d" <?= $per_value==='15d'?'selected':'' ?>>15 dias</option>
            </optgroup>
            <optgroup label="Meses">
              <?php foreach([1,2,3,6,12,24] as $m): ?>
                <option value="<?= $m.'m' ?>" <?= $per_value===($m.'m')?'selected':'' ?>><?= $m ?> mês<?= $m>1?'es':'' ?></option>
              <?php endforeach; ?>
            </optgroup>
          </select>
          <?php if(!($hasManutUnid&&$hasManutQtd)): ?><span class="hint">Colunas de manutenção ausentes</span><?php endif; ?>
        </div>
        <div>
          <label>Última manutenção</label>
          <input type="date" name="manut_ultimo" id="manut_ultimo" value="<?= h($rec['manut_ultimo']) ?>" <?= $hasManutUlt?'':'disabled' ?>/>
        </div>
        <div>
          <label>Próxima manutenção (preview)</label>
          <input type="date" id="manut_proxima" value="" readonly/>
        </div>
        <div>
          <label>Alertar?</label>
          <select name="manut_alertar" <?= $hasManutAlertar?'':'disabled' ?>>
            <option value="1" <?= ($rec['manut_alertar']??1)?'selected':'' ?>>Sim</option>
            <option value="0" <?= !($rec['manut_alertar']??1)?'selected':'' ?>>Não</option>
          </select>
        </div>
      </div>

      <div class="divider"></div>
      <p class="subtitle">Documentos</p>
      <div class="grid">
        <div class="stack">
          <label>Fotos</label>
          <div class="file-zone">
            Arraste aqui ou
            <button type="button" class="btn small" onclick="document.getElementById('fotos').click()">escolher</button>
            <input id="fotos" type="file" name="fotos[]" accept="image/*" multiple/>
          </div>
        </div>
        <div class="stack">
          <label>Garantia/Contrato (PDF)</label>
          <div class="file-zone">
            Arraste aqui ou
            <button type="button" class="btn small" onclick="document.getElementById('contrato_pdf').click()">escolher</button>
            <input id="contrato_pdf" type="file" name="contrato_pdf" accept="application/pdf"/>
          </div>
        </div>
        <div class="stack">
          <label>Outros</label>
          <div class="file-zone">
            Arraste aqui ou
            <button type="button" class="btn small" onclick="document.getElementById('outros').click()">escolher</button>
            <input id="outros" type="file" name="outros[]" multiple/>
          </div>
        </div>
      </div>

      <div class="divider"></div>
      <div class="grid">
        <div><label>Observações</label><textarea name="observacoes" placeholder="Observações gerais"><?= h($rec['observacoes']) ?></textarea></div>
      </div>

      <div class="divider"></div>
      <div class="row" style="justify-content:flex-end">
        <a class="btn" href="<?= BASE_URL ?>/modules/gestao_ativos/ativos-listar.php">Cancelar</a>
        <button class="btn" name="salvar" value="1">Salvar</button>
        <button class="btn primary" name="salvar_novo" value="1">Salvar e cadastrar novo</button>
      </div>
    </form>

    <div class="card" style="margin-top:12px;"><p class="hint">Versão externa — campos sem obrigatoriedade. Layout otimizado para celular (1 por linha).</p></div>
  </div>

<script>
  // modelos por marca
  const marcaSel=document.getElementById('marca_id');
  const modeloSel=document.getElementById('modelo_id');
  marcaSel?.addEventListener('change', async ()=>{
    modeloSel.innerHTML='<option value="">—</option>';
    if(!marcaSel.value) return;
    const r=await fetch('?ajax=modelos&marca_id='+encodeURIComponent(marcaSel.value));
    const j=await r.json();
    j.forEach(m=>{
      const o=document.createElement('option'); o.value=m.id; o.textContent=m.nome; modeloSel.appendChild(o);
    });
  });

  // manutenção: preview próxima data
  const per=document.getElementById('manut_periodicidade');
  const ult=document.getElementById('manut_ultimo');
  const prox=document.getElementById('manut_proxima');
  function calcProx(){
    if(!prox) return;
    const pv=per?.value||''; if(!pv){ prox.value=''; return; }
    const m=pv.match(/^(\d+)([dm])$/); if(!m){ prox.value=''; return; }
    const q=parseInt(m[1],10), u=m[2];
    const base=ult?.value ? new Date(ult.value) : new Date();
    const d=new Date(base);
    if(u==='d') d.setDate(d.getDate()+q); else d.setMonth(d.getMonth()+q);
    prox.value=d.toISOString().slice(0,10);
  }
  per?.addEventListener('change', calcProx); ult?.addEventListener('change', calcProx); calcProx();

  // Depósitos: carrega quando muda Local
  const selLocal = document.getElementById('local_id');
  const selDepos = document.getElementById('deposito_id');
  async function carregarDepositosPorEmpresa(empId){
    if(!selDepos) return;
    selDepos.innerHTML = '<option value="">—</option>';
    if(!empId){ return; }
    const r = await fetch('?ajax=depositos&empresa_id='+encodeURIComponent(empId));
    const j = await r.json();
    j.forEach(d=>{
      const o = document.createElement('option');
      o.value = d.id; o.textContent = d.nome;
      selDepos.appendChild(o);
    });
  }
  selLocal?.addEventListener('change', ()=>{
    // opcional: limpar depósito ao trocar local
    selDepos.value='';
    carregarDepositosPorEmpresa(selLocal.value)
  });
</script>
</body>
</html>
