<?php
// public/modules/gestao_ativos/ativos-form.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function has_col(mysqli $db,$t,$c){ $r=$db->query("SHOW COLUMNS FROM $t LIKE '".$db->real_escape_string($c)."'"); return $r && $r->num_rows>0; }
function table_exists(mysqli $db,$t){ $r=$db->query("SHOW TABLES LIKE '".$db->real_escape_string($t)."'"); return $r && $r->num_rows>0; }
function ensure_dir($abs){ if(!is_dir($abs)) @mkdir($abs,0775,true); return is_dir($abs) && is_writable($abs); }

if (empty($_SESSION['csrf_ativo'])) $_SESSION['csrf_ativo'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_ativo'];

/* ================== AJAX: busca de ativos ================== */
if (isset($_GET['ajax']) && $_GET['ajax']==='busca_ativos') {
  header('Content-Type: application/json; charset=utf-8');

  $q  = trim($_GET['q'] ?? '');
  $idAtual = (int)($_GET['idAtual'] ?? 0);
  $out = [];

  if ($q !== '' && table_exists($dbc,'moz_ativo')) {
    $where = [];
    $types = '';
    $args  = [];

    // não sugerir o próprio ativo
    $where[] = 'a.id <> ?';
    $types  .= 'i';
    $args[]  = $idAtual;

    if (ctype_digit($q)) {
      $where[] = '(a.id = ? OR a.tag_patrimonial LIKE CONCAT("%",?,"%") OR a.numero_serie LIKE CONCAT("%",?,"%"))';
      $types  .= 'iss';
      $args[]  = (int)$q; $args[]=$q; $args[]=$q;
    } else {
      $where[] = '(a.nome LIKE CONCAT("%",?,"%") OR a.tag_patrimonial LIKE CONCAT("%",?,"%") OR a.numero_serie LIKE CONCAT("%",?,"%"))';
      $types  .= 'sss';
      $args[]  = $q; $args[]=$q; $args[]=$q;
    }

    // regra: um ativo não pode ter 2 atrelamentos
    // não sugerir se já estiver atrelado (tipo='depends' e descricao vazia) a OUTRO ativo
    $where[] = "NOT EXISTS (
                  SELECT 1
                    FROM moz_ativo_relacao r
                   WHERE r.destino_id = a.id
                     AND r.tipo='depends'
                     AND (r.descricao IS NULL OR r.descricao='')
                     AND r.origem_id <> ?
                )";
    $types .= 'i';
    $args[] = $idAtual;

    $sql = "SELECT a.id, a.nome, a.tag_patrimonial, a.numero_serie
              FROM moz_ativo a
             WHERE ".implode(' AND ',$where)."
             ORDER BY a.nome ASC
             LIMIT 20";
    $st = $dbc->prepare($sql);
    $st->bind_param($types, ...$args);
    $st->execute();
    $rs = $st->get_result();
    while($r=$rs->fetch_assoc()){
      $label = '#'.$r['id'].' — '.$r['nome'];
      if (!empty($r['tag_patrimonial'])) $label .= ' (TAG '.$r['tag_patrimonial'].')';
      elseif (!empty($r['numero_serie'])) $label .= ' (S/N '.$r['numero_serie'].')';
      $out[] = ['id'=>(int)$r['id'], 'label'=>$label];
    }
    $st->close();
  }

  echo json_encode($out); exit;
}

/* ================== AJAX: modelos por marca ================== */
if (isset($_GET['ajax']) && $_GET['ajax']==='modelos') {
  header('Content-Type: application/json; charset=utf-8');
  $marca_id = (int)($_GET['marca_id'] ?? 0);
  $out = [];
  if ($marca_id>0 && table_exists($dbc,'moz_modelo')) {
    $rs = $dbc->prepare("SELECT id,nome FROM moz_modelo WHERE marca_id=? ORDER BY nome");
    $rs->bind_param('i',$marca_id); $rs->execute();
    $res=$rs->get_result(); while($r=$res->fetch_assoc()) $out[]=$r; $rs->close();
  }
  echo json_encode($out); exit;
}

/* ================== Flags de schema ================== */
$hasModeloTbl       = table_exists($dbc,'moz_modelo');
$hasLocalTbl        = table_exists($dbc,'moz_local') || table_exists($dbc,'moz_deposito'); // fallback nome antigo
$localTable         = table_exists($dbc,'moz_local') ? 'moz_local' : (table_exists($dbc,'moz_deposito')?'moz_deposito':null);
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
$hasCreatedAt       = has_col($dbc,'moz_ativo','created_at');

/* ================== Entrada ================== */
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$err = '';
$ok  = '';

/* ================== POST: salvar ================== */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!hash_equals($csrf, $_POST['csrf'] ?? '')) die('CSRF inválido.');

  $id = (int)($_POST['id'] ?? 0);

  $nome   = trim($_POST['nome'] ?? '');
  $cat_id = ($_POST['cat_id'] ?? '')==='' ? null : (int)$_POST['cat_id'];
  $marca_id = ($_POST['marca_id'] ?? '')==='' ? null : (int)$_POST['marca_id'];
  $modelo_id = ($_POST['modelo_id'] ?? '')==='' ? null : (int)$_POST['modelo_id'];
  $tag   = trim($_POST['tag_patrimonial'] ?? '');
  $serie = trim($_POST['numero_serie'] ?? '');
  $status_id = (int)($_POST['status_id'] ?? 2);
  $ativo_reg = $hasAtivoFlag ? (int)($_POST['ativo'] ?? 1) : 1;

  $local_id = ($_POST['local_id'] ?? '')==='' ? null : (int)$_POST['local_id'];
  $rack     = trim($_POST['rack'] ?? '');
  $u_inicio = ($_POST['u_inicio'] ?? '')==='' ? null : (int)$_POST['u_inicio'];
  $responsavel = trim($_POST['responsavel'] ?? '');

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

  // validação mínima
  if ($nome==='') $err = 'Informe o nome do ativo.';
  if (!$cat_id)   $err = $err ?: 'Selecione a categoria.';
  if (!$status_id)$err = $err ?: 'Informe o status operacional.';

  // uploads (opcional): salva em /uploads/ativos
  $photos_paths=[]; $contrato_path=null; $outros_paths=[];
  $baseDir = ROOT_PATH.'uploads/ativos';
  if (!$err && !ensure_dir($baseDir)) $err='Pasta de upload indisponível.';

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
        $sql = "UPDATE moz_ativo SET nome=?, cat_id=?, marca_id=?, modelo_id=?, tag_patrimonial=?, numero_serie=?, status_id=?, local_id=?, fornecedor_id=?, "
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
        $types = "siissiiis";
        $args  = [$nome,$cat_id,$marca_id,$modelo_id,$tag,$serie,$status_id,$local_id,$fornecedor_id];

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
        $sql = "INSERT INTO moz_ativo (nome, cat_id, marca_id, modelo_id, tag_patrimonial, numero_serie, status_id, local_id, fornecedor_id, "
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
             . ") VALUES (?,?,?,?,?,?,?,?,?,"
             . ($hasNF ? "?," : "")
             . "?,"
             . ($hasGarantiaAte ? "?," : "")
             . ($hasCusto ? "?," : "")
             . ($hasDeprec ? "?," : "")
             . ($hasCentroCusto ? "?," : "")
             . "?"
             . ($hasAtivoFlag ? ",?" : "")
             . ($hasManutUnid ? ",?" : "")
             . ($hasManutQtd  ? ",?" : "")
             . ($hasManutUlt  ? ",?" : "")
             . ($hasManutAlertar ? ",?" : "")
             . ")";
        $types = "siissiiis";
        $args  = [$nome,$cat_id,$marca_id,$modelo_id,$tag,$serie,$status_id,$local_id,$fornecedor_id];

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

      // atributos por categoria (custom fields)
      if ($hasCFDef && $hasCFVal) {
        // limpa os antigos (para update) e regrava
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

      // ===== Validação: um ativo não pode ter 2 atrelamentos
      if (!empty($_POST['atrelados']) && is_array($_POST['atrelados'])) {
        $conflitos = [];
        $chk = $dbc->prepare("SELECT origem_id FROM moz_ativo_relacao
                              WHERE destino_id=? AND tipo='depends'
                                AND (descricao IS NULL OR descricao='')
                                AND origem_id<>?");
        foreach ($_POST['atrelados'] as $aid) {
          $aid = (int)$aid; if ($aid<=0) continue;
          $chk->bind_param('ii', $aid, $id);
          $chk->execute(); $chk->bind_result($origem);
          if ($chk->fetch()) $conflitos[] = $aid; // já atrelado a outro
          $chk->free_result();
        }
        $chk->close();

        if ($conflitos) {
          $err = 'Os seguintes ativos já estão atrelados a outro item: #'.implode(', #',$conflitos).'. Remova-os da seleção.';
        }
      }

      // se passou, empilha como vínculos 'depends'
      if (!$err && !empty($_POST['atrelados']) && is_array($_POST['atrelados'])) {
        foreach ($_POST['atrelados'] as $aid) {
          $aid = (int)$aid; if ($aid<=0) continue;
          $_POST['rel_tipo'][]      = 'depends';
          $_POST['rel_destino'][]   = $aid;
          $_POST['rel_descricao'][] = '';
        }
      }

      // ===== Atrelados => empilha como vínculos tipo 'depends' (sem descrição)
      if (!empty($_POST['atrelados']) && is_array($_POST['atrelados'])) {
        foreach ($_POST['atrelados'] as $aid) {
          $aid = (int)$aid;
          if ($aid <= 0) continue;

          $_POST['rel_tipo'][]      = 'depends';
          $_POST['rel_destino'][]   = $aid;
          $_POST['rel_descricao'][] = '';
        }
      }

      // vínculos (rede/energia/depende)
      if ($hasRelTbl) {
        $dbc->query("DELETE FROM moz_ativo_relacao WHERE origem_id=".(int)$id);
        if (!empty($_POST['rel_tipo'])) {
          $tipos=$_POST['rel_tipo']; $dest=$_POST['rel_destino']; $desc=$_POST['rel_descricao'];
          $ins=$dbc->prepare("INSERT IGNORE INTO moz_ativo_relacao (origem_id, destino_id, tipo, descricao) VALUES (?,?,?,?)");
          for($i=0;$i<count($tipos);$i++){
            $t=trim((string)$tipos[$i]); $d=(int)($dest[$i]??0); $ds=trim((string)($desc[$i]??''));
            if($t==='' || $d<=0) continue;
            $ins->bind_param('iiss',$id,$d,$t,$ds); $ins->execute();
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
      header('Location: '.BASE_URL.'/modules/gestao_ativos/ativos-listar.php'); exit;

    } catch(Exception $e){
      $dbc->rollback();
      $err = 'Erro ao salvar: '.$e->getMessage();
    }
  }
}

/* ================== GET: carregar combobox e editar ================== */
$cats=[]; $rs=$dbc->query("SELECT id,nome FROM moz_cat_ativo WHERE ativo=1 ORDER BY nome"); if($rs) while($r=$rs->fetch_assoc()) $cats[]=$r;
$marcas=[]; $rs=$dbc->query("SELECT id,nome FROM moz_marca WHERE ".(has_col($dbc,'moz_marca','ativo')?'ativo=1':'1=1')." ORDER BY nome"); if($rs) while($r=$rs->fetch_assoc()) $marcas[]=$r;
$modelos=[]; // carregamos sob demanda via ajax; mas se estiver editando com marca preenchida, trazemos aqui
$locais=[];
if ($localTable){
  $rs=$dbc->query("SELECT id,nome FROM {$localTable} ORDER BY nome"); if($rs) while($r=$rs->fetch_assoc()) $locais[]=$r;
}
$fornecedores=[];
if ($hasFornecedorTbl){
  $rs=$dbc->query("SELECT id,nome FROM moz_fornecedor ORDER BY nome"); if($rs) while($r=$rs->fetch_assoc()) $fornecedores[]=$r;
}

$rec = [
  'nome'=>'','cat_id'=>'','marca_id'=>'','modelo_id'=>'','tag_patrimonial'=>'','numero_serie'=>'',
  'status_id'=>2,'ativo'=>1,'local_id'=>'','rack'=>'','u_inicio'=>'','responsavel'=>'',
  'fornecedor_id'=>'','nf_numero'=>'','data_compra'=>'','garantia_ate'=>'','custo_aquisicao'=>'',
  'depreciacao_meses'=>'','centro_custo'=>'','observacoes'=>'',
  'manut_unid'=>'','manut_qtd'=>0,'manut_ultimo'=>'','manut_alertar'=>1
];
$cf_defs=[]; $cf_vals=[]; $rels=[];

if ($id>0 && !$err && $_SERVER['REQUEST_METHOD']!=='POST'){
  // carrega registro
  $cols = "id,nome,cat_id,marca_id,modelo_id,tag_patrimonial,numero_serie,status_id,local_id,fornecedor_id,"
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
    if($hasNF)          $rec['nf_numero'] = $row['nf_numero'];
    if($hasGarantiaAte) $rec['garantia_ate'] = $row['garantia_ate'];
    if($hasCusto)       $rec['custo_aquisicao'] = $row['custo_aquisicao'];
    if($hasDeprec)      $rec['depreciacao_meses'] = $row['depreciacao_meses'];
    if($hasCentroCusto) $rec['centro_custo'] = $row['centro_custo'];
    if($hasAtivoFlag)   $rec['ativo'] = (int)$row['ativo'];
    if($hasManutUnid)   $rec['manut_unid'] = $row['manut_periodo_unid'];
    if($hasManutQtd)    $rec['manut_qtd']  = (int)$row['manut_periodo_qtd'];
    if($hasManutUlt)    $rec['manut_ultimo']= $row['manut_ultimo'];
    if($hasFornecedorTbl && $rec['marca_id'] && $hasModeloTbl){
      // pré-carrega modelos dessa marca
      $m=$dbc->prepare("SELECT id,nome FROM moz_modelo WHERE marca_id=? ORDER BY nome");
      $m->bind_param('i', $rec['marca_id']); $m->execute();
      $rr=$m->get_result(); while($x=$rr->fetch_assoc()) $modelos[]=$x; $m->close();
    }
  }

  // CF defs + valores
  if ($hasCFDef){
    $q=$dbc->prepare("SELECT id,nome,tipo,required FROM moz_cf_def WHERE cat_id=? AND ".(has_col($dbc,'moz_cf_def','ativo')?'ativo=1':'1=1')." ORDER BY ordem,id");
    $q->bind_param('i',$rec['cat_id']); $q->execute(); $r=$q->get_result(); while($row=$r->fetch_assoc()) $cf_defs[]=$row; $q->close();
    if ($hasCFVal){
      $r=$dbc->query("SELECT def_id,valor FROM moz_cf_val WHERE ativo_id=".(int)$id);
      while($x=$r->fetch_assoc()) $cf_vals[(int)$x['def_id']]=$x['valor'];
    }
  }

  // vínculos
  if ($hasRelTbl){
    $r=$dbc->query("SELECT destino_id,tipo,descricao FROM moz_ativo_relacao WHERE origem_id=".(int)$id." ORDER BY id");
    while($x=$r->fetch_assoc()) $rels[]=$x;
  }
}
$atrelados = [];
if ($hasRelTbl && $id>0) {
  // considera atrelado como 'depends' sem descrição (os criados por esta função)
  $q = $dbc->prepare("SELECT r.destino_id AS id, a.nome, a.tag_patrimonial, a.numero_serie
                        FROM moz_ativo_relacao r
                        JOIN moz_ativo a ON a.id = r.destino_id
                       WHERE r.origem_id=? AND r.tipo='depends' AND (r.descricao IS NULL OR r.descricao='')
                       ORDER BY a.nome");
  $q->bind_param('i',$id);
  $q->execute();
  $res=$q->get_result();
  while($x=$res->fetch_assoc()){
    $label = '#'.$x['id'].' — '.$x['nome'];
    if (!empty($x['tag_patrimonial'])) $label .= ' (TAG '.$x['tag_patrimonial'].')';
    elseif (!empty($x['numero_serie'])) $label .= ' (S/N '.$x['numero_serie'].')';
    $atrelados[] = ['id'=>(int)$x['id'], 'label'=>$label];
  }
  $q->close();
}


/* ================== Includes visuais ================== */
include_once ROOT_PATH.'system/includes/head.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<?php include_once ROOT_PATH.'system/includes/navbar.php'; ?>

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

  <session class="bpm"><div class="container">
    <header class="toolbar">
      <h1>Ativos — <?= $id>0 ? 'Editar' : 'Cadastro' ?></h1>
      <div class="actions">
        <a class="btn" href="ativos-listar.php">Listar ativos</a>
        <a class="btn" href="ativos-importar.php">Importar CSV</a>
      </div>
    </header>

    <?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
    <?php if (!empty($_SESSION['flash_ok'])): ?><div class="alert alert-success"><?= h($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div><?php endif; ?>

    <form class="card" method="post" enctype="multipart/form-data" autocomplete="off" novalidate>
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int)$id ?>">

      <p class="subtitle">Identificação</p>
      <div class="grid cols-4">
        <div>
          <label>Nome *</label>
          <input type="text" name="nome" required placeholder="Ex.: Notebook João Silva" value="<?= h($rec['nome']) ?>"/>
        </div>
        <div>
          <label>Categoria *</label>
          <select name="cat_id" id="cat_id" required>
            <option value="">—</option>
            <?php foreach($cats as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $rec['cat_id']==$c['id']?'selected':'' ?>><?= h($c['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Marca</label>
          <select name="marca_id" id="marca_id">
            <option value="">—</option>
            <?php foreach($marcas as $m): ?>
              <option value="<?= (int)$m['id'] ?>" <?= $rec['marca_id']==$m['id']?'selected':'' ?>><?= h($m['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Modelo</label>
          <select name="modelo_id" id="modelo_id">
            <option value="">—</option>
            <?php foreach($modelos as $mo): ?>
              <option value="<?= (int)$mo['id'] ?>" <?= $rec['modelo_id']==$mo['id']?'selected':'' ?>><?= h($mo['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="grid cols-4">
        <div><label>Tag / Patrimônio</label><input type="text" name="tag_patrimonial" placeholder="TAG" value="<?= h($rec['tag_patrimonial']) ?>"/></div>
        <div><label>Nº de série</label><input type="text" name="numero_serie" placeholder="Serial" value="<?= h($rec['numero_serie']) ?>"/></div>
        <div>
          <label>Status operacional *</label>
          <select name="status_id" required>
            <?php foreach([1=>'Em operação',2=>'Em estoque',3=>'Emprestado',4=>'Alugado',5=>'Em manutenção',6=>'Baixado'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $rec['status_id']==$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Ativo? </label>
          <select name="ativo" <?= $hasAtivoFlag?'':'disabled' ?>>
            <option value="1" <?= $rec['ativo']? 'selected':'' ?>>Sim</option>
            <option value="0" <?= !$rec['ativo']? 'selected':'' ?>>Não</option>
          </select>
          <?php if(!$hasAtivoFlag): ?><span class="hint">Campo "ativo" não existe em moz_ativo</span><?php endif; ?>
        </div>
      </div>

      <div class="grid cols-4">
        <div>
          <label>Local *</label>
          <select name="local_id" required>
            <option value="">—</option>
            <?php foreach($locais as $l): ?>
              <option value="<?= (int)$l['id'] ?>" <?= $rec['local_id']==$l['id']?'selected':'' ?>><?= h($l['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>Rack</label><input type="text" name="rack" value="<?= h($rec['rack']) ?>" placeholder="Opcional"/></div>
        <div><label>U início</label><input type="number" name="u_inicio" min="1" value="<?= h($rec['u_inicio']) ?>" placeholder="Ex.: 18"/></div>
        <div><label>Responsável (usuário)</label><input type="text" name="responsavel" value="<?= h($rec['responsavel']) ?>" placeholder="login/nome"/></div>
      </div>

      <div class="divider"></div>
      <p class="subtitle">Dados de aquisição</p>
      <div class="grid cols-4">
        <div>
          <label>Fornecedor</label>
          <select name="fornecedor_id" <?= $hasFornecedorTbl?'':'disabled' ?>>
            <option value="">—</option>
            <?php foreach($fornecedores as $f): ?>
              <option value="<?= (int)$f['id'] ?>" <?= $rec['fornecedor_id']==$f['id']?'selected':'' ?>><?= h($f['nome']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if(!$hasFornecedorTbl): ?><span class="hint">Tabela moz_fornecedor ausente</span><?php endif; ?>
        </div>
        <div><label>Nota fiscal</label><input type="text" name="nf_numero" value="<?= h($rec['nf_numero']) ?>" placeholder="NF-e" <?= $hasNF?'':'disabled' ?>/></div>
        <div><label>Data de compra</label><input type="date" name="data_compra" value="<?= h($rec['data_compra']) ?>"/></div>
        <div><label>Garantia até</label><input type="date" name="garantia_ate" value="<?= h($rec['garantia_ate']) ?>" <?= $hasGarantiaAte?'':'disabled' ?>/></div>
      </div>
      <div class="grid cols-3">
        <div><label>Valor de compra</label><input type="number" step="0.01" min="0" name="custo_aquisicao" value="<?= h($rec['custo_aquisicao']) ?>" placeholder="0,00" <?= $hasCusto?'':'disabled' ?>/></div>
        <div><label>Depreciação (meses)</label><input type="number" min="0" name="depreciacao_meses" value="<?= h($rec['depreciacao_meses']) ?>" placeholder="36" <?= $hasDeprec?'':'disabled' ?>/></div>
        <div><label>Centro de custo</label><input type="text" name="centro_custo" value="<?= h($rec['centro_custo']) ?>" placeholder="Opcional" <?= $hasCentroCusto?'':'disabled' ?>/></div>
      </div>

      <div class="divider"></div>
      <p class="subtitle">Manutenção preventiva</p>
      <?php
        $per_value = '';
        if ($rec['manut_qtd']>0) $per_value = ($rec['manut_unid']==='dias' ? $rec['manut_qtd'].'d' : $rec['manut_qtd'].'m');
      ?>
      <div class="grid cols-4">
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
          <label>Próxima manutenção</label>
          <input type="date" id="manut_proxima" value="" readonly/>
          <span class="hint">Calculado automaticamente no salvar</span>
        </div>
        <div>
          <label>Alertar?</label>
          <select name="manut_alertar" <?= $hasManutAlertar?'':'disabled' ?>>
            <option value="1" <?= $rec['manut_alertar']?'selected':'' ?>>Sim</option>
            <option value="0" <?= !$rec['manut_alertar']?'selected':'' ?>>Não</option>
          </select>
        </div>
      </div>

      <div class="divider"></div>
      <p class="subtitle">Atributos (campos customizados por categoria)</p>
      <div id="attr-list" class="stack">
        <?php if ($hasCFDef && $rec['cat_id']): ?>
          <?php
            if (!$cf_defs){
              $q=$dbc->prepare("SELECT id,nome,tipo,required FROM moz_cf_def WHERE cat_id=? AND ".(has_col($dbc,'moz_cf_def','ativo')?'ativo=1':'1=1')." ORDER BY ordem,id");
              $q->bind_param('i',$rec['cat_id']); $q->execute(); $r=$q->get_result(); while($row=$r->fetch_assoc()) $cf_defs[]=$row; $q->close();
            }
          ?>
          <?php if ($cf_defs): foreach($cf_defs as $def): $val = $cf_vals[$def['id']] ?? ''; ?>
            <div class="grid cols-2" style="align-items:end">
              <div><label><?= h($def['nome']) ?><?= !empty($def['required'])?' *':'' ?></label>
                <input type="text" name="cf[<?= (int)$def['id'] ?>]" value="<?= h($val) ?>" <?= !empty($def['required'])?'required':'' ?> />
              </div>
            </div>
          <?php endforeach; else: ?>
            <div class="hint">Sem campos customizados para esta categoria.</div>
          <?php endif; ?>
        <?php else: ?>
          <div class="hint">Selecione a categoria e salve para carregar os campos customizados.</div>
        <?php endif; ?>
      </div>

      <div class="divider"></div>
      <p class="subtitle">Vínculos (rede/energia/depende)</p>
      <div id="attach-list" class="stack">
        <?php if ($rels): foreach($rels as $r): ?>
          <div class="grid cols-4" style="align-items:end">
            <div><label>Tipo</label>
              <select name="rel_tipo[]"><option <?= $r['tipo']==='network'?'selected':'' ?>>network</option><option <?= $r['tipo']==='power'?'selected':'' ?>>power</option><option <?= $r['tipo']==='depends'?'selected':'' ?>>depends</option></select>
            </div>
            <div><label>Ativo destino (ID)</label><input type="number" name="rel_destino[]" value="<?= (int)$r['destino_id'] ?>" placeholder="ID do ativo"/></div>
            <div><label>Descrição</label><input type="text" name="rel_descricao[]" value="<?= h($r['descricao']) ?>" placeholder="porta/observação"/></div>
            <div class="row"><button type="button" class="btn small danger" onclick="this.closest('.grid').remove()">Remover</button></div>
          </div>
        <?php endforeach; else: ?>
          <div class="grid cols-4" style="align-items:end">
            <div><label>Tipo</label><select name="rel_tipo[]"><option>network</option><option>power</option><option>depends</option></select></div>
            <div><label>Ativo destino (ID)</label><input type="number" name="rel_destino[]" placeholder="ID do ativo"/></div>
            <div><label>Descrição</label><input type="text" name="rel_descricao[]" placeholder="porta/observação"/></div>
            <div class="row"><button type="button" class="btn small danger" onclick="this.closest('.grid').remove()">Remover</button></div>
          </div>
        <?php endif; ?>
      </div>
      <button type="button" class="btn small" id="add-attach">+ Adicionar vínculo</button>

      <div class="divider"></div>
      <p class="subtitle">Ativos atrelados</p>
      <div class="stack" id="atrelados-wrap">
        <div class="grid cols-3" style="align-items:end">
          <div>
            <label>Buscar ativo (ID, nome, TAG, S/N)</label>
            <input type="text" id="busca_atrelado" placeholder="Digite para buscar..." list="sug_ativos"/>
            <datalist id="sug_ativos"></datalist>
            <span class="hint">Escolha um item da lista para adicionar</span>
          </div>
          <div class="row">
            <button type="button" class="btn small" id="btn-add-atrelado">+ Adicionar</button>
          </div>
        </div>

        <div id="atrelados-list" class="stack">
          <?php if (!empty($atrelados)): foreach($atrelados as $a): ?>
            <div class="chip" data-id="<?= (int)$a['id'] ?>" style="display:flex;gap:8px;align-items:center;padding:6px 10px;border:1px solid #ddd;border-radius:16px;">
              <input type="hidden" name="atrelados[]" value="<?= (int)$a['id'] ?>"/>
              <span><?= h($a['label']) ?></span>
              <button type="button" class="btn small danger" onclick="this.closest('.chip').remove()">Remover</button>
            </div>
          <?php endforeach; else: ?>
            <div class="hint">Nenhum atrelado adicionado.</div>
          <?php endif; ?>
        </div>
      </div>
      
      <div class="divider"></div>
      <p class="subtitle">Documentos</p>
      <div class="grid cols-3">
        <div class="stack">
          <label>Fotos</label>
          <div class="file-zone">Arraste aqui ou <button type="button" class="btn small" onclick="document.getElementById('fotos').click()">escolher</button><input id="fotos" type="file" name="fotos[]" accept="image/*" multiple/></div>
        </div>
        <div class="stack">
          <label>Garantia/Contrato (PDF)</label>
          <div class="file-zone">Arraste aqui ou <button type="button" class="btn small" onclick="document.getElementById('contrato_pdf').click()">escolher</button><input id="contrato_pdf" type="file" name="contrato_pdf" accept="application/pdf"/></div>
        </div>
        <div class="stack">
          <label>Outros</label>
          <div class="file-zone">Arraste aqui ou <button type="button" class="btn small" onclick="document.getElementById('outros').click()">escolher</button><input id="outros" type="file" name="outros[]" multiple/></div>
        </div>
      </div>

      <div class="divider"></div>
      <div><label>Observações</label><textarea name="observacoes" placeholder="Observações gerais"><?= h($rec['observacoes']) ?></textarea></div>

      <div class="divider"></div>
      <div style="display:flex;justify-content:flex-end;gap:10px">
        <a class="btn" href="ativos-listar.php">Cancelar</a>
        <button class="btn primary">Salvar</button>
      </div>
    </form>

    <div class="card"><p class="hint">Os campos se adaptam conforme o seu schema (detecção de colunas). “Próxima manutenção” é recalculada por trigger ou na aplicação.</p></div>
  </div></session>
</div></div>

<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
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

  // vínculos add
  document.getElementById('add-attach').addEventListener('click', ()=>{
    const wrap=document.getElementById('attach-list');
    const el=document.createElement('div'); el.className='grid cols-4'; el.style.alignItems='end';
    el.innerHTML=`<div><label>Tipo</label><select name="rel_tipo[]"><option>network</option><option>power</option><option>depends</option></select></div>
                  <div><label>Ativo destino (ID)</label><input type="number" name="rel_destino[]" placeholder="ID do ativo"/></div>
                  <div><label>Descrição</label><input type="text" name="rel_descricao[]" placeholder="porta/observação"/></div>
                  <div class="row"><button type="button" class="btn small danger" onclick="this.closest('.grid').remove()">Remover</button></div>`;
    wrap.appendChild(el);
  });

  // manutenção: cálculo visual da próxima data
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
</script>
<script>
  // ===== Autocomplete de ativos para "Ativos atrelados"
  const $buscaAtr  = document.getElementById('busca_atrelado');
  const $datalist  = document.getElementById('sug_ativos');
  const $listAtr   = document.getElementById('atrelados-list');
  const $btnAddAtr = document.getElementById('btn-add-atrelado');

  let sugCache = [];
  let sugTimer = null;

  function option(label, value) {
    const o = document.createElement('option');
    o.value = label;
    o.dataset.id = value;
    return o;
  }

  async function carregaSugestoes(q) {
    if (!q || q.length < 2) { $datalist.innerHTML=''; sugCache=[]; return; }
    const url = `?ajax=busca_ativos&q=${encodeURIComponent(q)}&idAtual=${encodeURIComponent(<?= (int)$id ?>)}`;
    const r = await fetch(url);
    const j = await r.json();
    sugCache = j || [];
    $datalist.innerHTML = '';
    sugCache.forEach(s => $datalist.appendChild(option(s.label, s.id)));
  }

  $buscaAtr?.addEventListener('input', (e)=>{
    const q = e.target.value.trim();
    clearTimeout(sugTimer);
    sugTimer = setTimeout(()=>carregaSugestoes(q), 180);
  });

  function jaExisteAtrelado(id) {
    return !!$listAtr.querySelector(`.chip[data-id="${id}"]`);
  }

  function addChip(id, label) {
    if (!id || jaExisteAtrelado(id)) return;
    const chip = document.createElement('div');
    chip.className = 'chip';
    chip.dataset.id = String(id);
    chip.style.cssText = 'display:flex;gap:8px;align-items:center;padding:6px 10px;border:1px solid #ddd;border-radius:16px;';
    chip.innerHTML = `<input type="hidden" name="atrelados[]" value="${id}"/>
                      <span>${label}</span>
                      <button type="button" class="btn small danger" onclick="this.closest('.chip').remove()">Remover</button>`;
    // remove o placeholder "Nenhum atrelado..."
    const hint = $listAtr.querySelector('.hint'); if (hint) hint.remove();
    $listAtr.appendChild(chip);
  }

  function resolveEscolhaAtual() {
    const val = $buscaAtr.value.trim();
    if (!val) return null;
    // procura pelo label na cache para obter o ID
    const hit = sugCache.find(s => s.label === val);
    if (hit) return hit;
    // fallback: se digitou "#123" pega id 123
    const m = val.match(/^#?(\d+)\b/);
    if (m) return { id: parseInt(m[1],10), label: val };
    return null;
  }

  $btnAddAtr?.addEventListener('click', ()=>{
    const esc = resolveEscolhaAtual();
    if (!esc) { alert('Selecione um item da lista ou informe o ID no formato #123.'); return; }
    addChip(esc.id, esc.label);
    $buscaAtr.value = '';
    $datalist.innerHTML = ''; sugCache = [];
  });

  // permitir Enter para adicionar
  $buscaAtr?.addEventListener('keydown', (e)=>{
    if (e.key === 'Enter') { e.preventDefault(); $btnAddAtr.click(); }
  });
</script>

<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
