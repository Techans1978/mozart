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
function has_col(mysqli $db,$t,$c){
  $rt = $db->real_escape_string($t);
  $rc = $db->real_escape_string($c);
  $r=$db->query("SHOW COLUMNS FROM `$rt` LIKE '$rc'");
  return $r && $r->num_rows>0;
}
function table_exists(mysqli $db,$t){
  $rt = $db->real_escape_string($t);
  $r=$db->query("SHOW TABLES LIKE '$rt'");
  return $r && $r->num_rows>0;
}
function ensure_dir($abs){ if(!is_dir($abs)) @mkdir($abs,0775,true); return is_dir($abs) && is_writable($abs); }

if (empty($_SESSION['csrf_ativo'])) $_SESSION['csrf_ativo'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_ativo'];

/* ================== LISTAS (ga_list / ga_list_item) ==================
   Obs.: Atenção as listas ga-list quando tiver subcategorias em todos deve
   apresentar "Pai - Filho - Neto" e assim por diante.
*/
$hasGaList = table_exists($dbc,'ga_list') && table_exists($dbc,'ga_list_item');

// Slugs conforme seu checklist:
$SLUG_NOME_ATIVO   = 'asset_name';     // 1) Nome do Ativo
$SLUG_STATUS_OP    = 'op_status';      // 2) Status Operacional
$SLUG_CAT_ATIVOS   = 'asset_category'; // 3) Categoria de Ativos
$SLUG_SECTOR       = 'sector';         // 5) SETOR/SUBSETOR
$SLUG_LINK_TYPE    = 'link_type';      // 8) Tipo/Vínculos

function ga_list_id(mysqli $dbc, string $slug): ?int {
  $slug = preg_replace('/[^a-z0-9_\-]/i','',$slug);
  if ($slug==='') return null;
  $st = $dbc->prepare("SELECT id FROM ga_list WHERE slug=? LIMIT 1");
  $st->bind_param('s',$slug);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();
  return $row ? (int)$row['id'] : null;
}

/**
 * Carrega itens de uma lista e devolve opções com caminho "Pai - Filho - Neto".
 */
function ga_list_options_path(mysqli $dbc, int $list_id): array {
  $out = [];
  if ($list_id<=0) return $out;

  $hasActive = has_col($dbc,'ga_list_item','active');
  $sql = "SELECT id, parent_id, name, sort_order".($hasActive?", active":"")."
            FROM ga_list_item
           WHERE list_id=? ".($hasActive?"AND active=1":"")."
           ORDER BY sort_order ASC, name ASC";
  $st = $dbc->prepare($sql);
  $st->bind_param('i',$list_id);
  $st->execute();
  $rs = $st->get_result();

  $items = [];
  while($r=$rs->fetch_assoc()){
    $items[(int)$r['id']] = [
      'id'=>(int)$r['id'],
      'parent_id'=> ($r['parent_id']===null ? null : (int)$r['parent_id']),
      'name'=>$r['name'],
      'sort_order'=>(int)($r['sort_order'] ?? 0),
    ];
  }
  $st->close();

  $children = [];
  foreach($items as $id=>$it){
    $pid = $it['parent_id'] ?? 0;
    if (!isset($children[$pid])) $children[$pid] = [];
    $children[$pid][] = $id;
  }

  foreach($children as $pid=>$arr){
    usort($arr, function($a,$b) use ($items){
      $oa = $items[$a]['sort_order'] ?? 0;
      $ob = $items[$b]['sort_order'] ?? 0;
      if ($oa !== $ob) return $oa <=> $ob;
      return strcasecmp($items[$a]['name'] ?? '', $items[$b]['name'] ?? '');
    });
    $children[$pid] = $arr;
  }

  $walk = function($pid, $path) use (&$walk, &$out, $children, $items) {
    $kids = $children[$pid] ?? [];
    foreach($kids as $id){
      $name = $items[$id]['name'] ?? '';
      $newPath = $path ? ($path.' - '.$name) : $name;
      $out[] = ['id'=>$id, 'label'=>$newPath, 'name'=>$name];
      $walk($id, $newPath);
    }
  };
  $walk(0, '');
  return $out;
}

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
$hasGarantiaMeses   = has_col($dbc,'moz_ativo','garantia_meses'); // (se existir, usa direto)
$hasNF              = has_col($dbc,'moz_ativo','nf_numero');
$hasCusto           = has_col($dbc,'moz_ativo','custo_aquisicao');
$hasDeprec          = has_col($dbc,'moz_ativo','depreciacao_meses');
$hasCentroCusto     = has_col($dbc,'moz_ativo','centro_custo');
$hasAtivoFlag       = has_col($dbc,'moz_ativo','ativo');
$hasCreatedAt       = has_col($dbc,'moz_ativo','created_at');

// 3) Categoria lista — coluna opcional
$col_cat_item       = has_col($dbc,'moz_ativo','categoria_item_id');
// 5) SETOR/SUBSETOR (lista sector) — coluna opcional
$col_sector_item    = has_col($dbc,'moz_ativo','sector_item_id');
// 6) Aquisição/Locação — coluna opcional
$col_aq_loc         = has_col($dbc,'moz_ativo','aquisicao_tipo'); // 'Aquisicao'/'Locacao'
// 1) Nome (lista + digitar) — colunas opcionais
$col_nome_item       = has_col($dbc,'moz_ativo','nome_item_id');
$col_nome_digit      = has_col($dbc,'moz_ativo','nome_digit');
$col_nome_txt        = has_col($dbc,'moz_ativo','nome_txt');
// 3) checkbox categoria — coluna opcional
$col_inf_cat         = has_col($dbc,'moz_ativo','informar_categoria');
// 4) checkbox depósito — coluna opcional
$col_inf_deposito    = has_col($dbc,'moz_ativo','informar_deposito');

$hasAnexoTipoCol     = $hasAnexoTbl && has_col($dbc,'moz_ativo_anexo','tipo');
$hasAnexoPathCol     = $hasAnexoTbl && has_col($dbc,'moz_ativo_anexo','path');

/* ===== EMPRESAS (usadas em Local) ===== */
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

/* ===== DEPOSITOS (tabela) ===== */
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

/* ================== AJAX: busca de ativos ================== */
if (isset($_GET['ajax']) && $_GET['ajax']==='busca_ativos') {
  header('Content-Type: application/json; charset=utf-8');

  $q  = trim($_GET['q'] ?? '');
  $idAtual = (int)($_GET['idAtual'] ?? 0);
  $out = [];

  if ($q !== '' && table_exists($dbc,'moz_ativo')) {
    $where = []; $types = ''; $args  = [];
    $where[] = 'a.id <> ?'; $types  .= 'i'; $args[]  = $idAtual;

    if (ctype_digit($q)) {
      $where[] = '(a.id = ? OR a.tag_patrimonial LIKE CONCAT("%",?,"%") OR a.numero_serie LIKE CONCAT("%",?,"%"))';
      $types  .= 'iss'; $args[]  = (int)$q; $args[]=$q; $args[]=$q;
    } else {
      $where[] = '(a.nome LIKE CONCAT("%",?,"%") OR a.tag_patrimonial LIKE CONCAT("%",?,"%") OR a.numero_serie LIKE CONCAT("%",?,"%"))';
      $types  .= 'sss'; $args[]  = $q; $args[]=$q; $args[]=$q;
    }

    $where[] = "NOT EXISTS (
                  SELECT 1
                    FROM moz_ativo_relacao r
                   WHERE r.destino_id = a.id
                     AND r.tipo='depends'
                     AND (r.descricao IS NULL OR r.descricao='')
                     AND r.origem_id <> ?
                )";
    $types .= 'i'; $args[] = $idAtual;

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

/* ================== AJAX: depósitos por empresa ================== */
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

/* ================== Pré-carrega listas ================== */
$listIdNome = $listIdStatus = $listIdCat = $listIdSector = $listIdLink = null;
$itNome = $itStatus = $itCat = $itSector = $itLink = [];

if ($hasGaList) {
  $listIdNome   = ga_list_id($dbc, $SLUG_NOME_ATIVO);
  $listIdStatus = ga_list_id($dbc, $SLUG_STATUS_OP);
  $listIdCat    = ga_list_id($dbc, $SLUG_CAT_ATIVOS);
  $listIdSector = ga_list_id($dbc, $SLUG_SECTOR);
  $listIdLink   = ga_list_id($dbc, $SLUG_LINK_TYPE);

  if ($listIdNome)   $itNome   = ga_list_options_path($dbc, $listIdNome);
  if ($listIdStatus) $itStatus = ga_list_options_path($dbc, $listIdStatus);
  if ($listIdCat)    $itCat    = ga_list_options_path($dbc, $listIdCat);
  if ($listIdSector) $itSector = ga_list_options_path($dbc, $listIdSector);
  if ($listIdLink)   $itLink   = ga_list_options_path($dbc, $listIdLink);
}

/* ================== POST: salvar ================== */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!hash_equals($csrf, $_POST['csrf'] ?? '')) die('CSRF inválido.');

  $id = (int)($_POST['id'] ?? 0);

  // 1) Nome (lista Nome do Ativo + checkbox Digitar)
  $nome_digit   = isset($_POST['nome_digit']) ? 1 : 0;
  $nome_item_id = ($_POST['nome_item_id'] ?? '')==='' ? null : (int)$_POST['nome_item_id'];
  $nome_txt     = trim($_POST['nome_txt'] ?? '');

  $nome = '';
  if ($nome_digit || !$listIdNome || !$itNome) {
    $nome = $nome_txt;
  } else {
    foreach ($itNome as $it) {
      if ((int)$it['id'] === (int)$nome_item_id) { $nome = (string)$it['label']; break; }
    }
  }

  // 2) Status Operacional (ga-list op_status) -> grava em status_id
  $status_id = ($_POST['status_id'] ?? '')==='' ? 0 : (int)$_POST['status_id'];

  // 3) Categoria (checkbox) -> coluna opcional categoria_item_id
  $informar_categoria = isset($_POST['informar_categoria']) ? 1 : 0;
  $categoria_item_id  = ($informar_categoria && ($_POST['categoria_item_id'] ?? '')!=='') ? (int)$_POST['categoria_item_id'] : null;

  // 4) Depósito (checkbox) -> usa deposito_id já existente
  $informar_deposito = isset($_POST['informar_deposito']) ? 1 : 0;

  // Local (empresa) e Depósito
  $local_id     = ($_POST['local_id'] ?? '')==='' ? null : (int)$_POST['local_id'];
  $deposito_id  = ($hasDepositoCol && $informar_deposito && ($_POST['deposito_id'] ?? '')!=='') ? (int)$_POST['deposito_id'] : null;

  // 5) SETOR/SUBSETOR (ga-list sector) -> coluna opcional sector_item_id
  $sector_item_id = ($_POST['sector_item_id'] ?? '')==='' ? null : (int)$_POST['sector_item_id'];

  // 6) Aquisição / Locação
  $aquisicao_tipo = trim($_POST['aquisicao_tipo'] ?? '');
  if ($aquisicao_tipo !== 'Aquisicao' && $aquisicao_tipo !== 'Locacao') $aquisicao_tipo = '';

  // Mantidos
  $marca_id = ($_POST['marca_id'] ?? '')==='' ? null : (int)$_POST['marca_id'];
  $modelo_id = ($_POST['modelo_id'] ?? '')==='' ? null : (int)$_POST['modelo_id'];
  $tag   = trim($_POST['tag_patrimonial'] ?? '');
  $serie = trim($_POST['numero_serie'] ?? '');
  $ativo_reg = $hasAtivoFlag ? (int)($_POST['ativo'] ?? 1) : 1;

  $fornecedor_id = $hasFornecedorTbl && ($_POST['fornecedor_id']??'')!=='' ? (int)$_POST['fornecedor_id'] : null;
  $nf_numero     = $hasNF ? trim($_POST['nf_numero'] ?? '') : null;
  $data_compra   = ($_POST['data_compra'] ?? '') ?: null;
  $cat_id = null;

  // 7) Garantia (meses)
  $garantia_meses = (int)($_POST['garantia_meses'] ?? 0);
  if ($garantia_meses < 0) $garantia_meses = 0;

  // compatibilidade: se não existir garantia_meses no BD e existir garantia_ate, calcula garantia_ate pelo data_compra
  $garantia_ate = null;
  if ($hasGarantiaMeses) {
    // grava na coluna garantia_meses (mais abaixo)
  } elseif ($hasGarantiaAte) {
    if ($data_compra && $garantia_meses > 0) {
      try {
        $dt = new DateTimeImmutable($data_compra);
        $dt2 = $dt->add(new DateInterval('P'.$garantia_meses.'M'));
        $garantia_ate = $dt2->format('Y-m-d');
      } catch(Exception $e){
        $garantia_ate = null;
      }
    } else {
      $garantia_ate = null;
    }
  }

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

  // validações mínimas
  if ($nome==='') $err = 'Informe o nome do ativo.';
  if (!$status_id) $err = $err ?: 'Informe o status operacional.';
  if (!$local_id)  $err = $err ?: 'Informe o local.';

  // uploads
  $photos_paths=[]; $contrato_path=null; $outros_paths=[];
  $baseDir = rtrim(ROOT_PATH,'/').'/uploads/ativos';
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
        $sql = "UPDATE moz_ativo SET
                  nome=?, cat_id=?, marca_id=?, modelo_id=?, tag_patrimonial=?, numero_serie=?, status_id=?, local_id=?"
             . ($hasDepositoCol ? ", deposito_id=?" : "")
             . ", fornecedor_id=?, "
             . ($hasNF ? "nf_numero=?," : "")
             . " data_compra=?, "
             . ($hasGarantiaAte ? "garantia_ate=?," : "")
             . ($hasGarantiaMeses ? "garantia_meses=?," : "")
             . ($hasCusto ? "custo_aquisicao=?," : "")
             . ($hasDeprec ? "depreciacao_meses=?," : "")
             . ($hasCentroCusto ? "centro_custo=?," : "")
             . " observacoes=? "
             . ($hasAtivoFlag ? ", ativo=? " : "")
             . ($hasManutUnid ? ", manut_periodo_unid=? " : "")
             . ($hasManutQtd  ? ", manut_periodo_qtd=? " : "")
             . ($hasManutUlt  ? ", manut_ultimo=? " : "")
             . ($hasManutAlertar ? ", manut_alertar=? " : "")
             // colunas opcionais (listas/checkbox)
             . ($col_cat_item ? ", categoria_item_id=? " : "")
             . ($col_inf_cat ? ", informar_categoria=? " : "")
             . ($col_nome_item ? ", nome_item_id=? " : "")
             . ($col_nome_digit ? ", nome_digit=? " : "")
             . ($col_nome_txt ? ", nome_txt=? " : "")
             . ($col_inf_deposito ? ", informar_deposito=? " : "")
             . ($col_sector_item ? ", sector_item_id=? " : "")
             . ($col_aq_loc ? ", aquisicao_tipo=? " : "")
             . " WHERE id=?";

        $types = "siiissii";
        $args  = [$nome,$cat_id,$marca_id,$modelo_id,$tag,$serie,$status_id,$local_id];

        if ($hasDepositoCol){ $types.="i"; $args[]=$deposito_id; }

        $types.="i"; $args[]=$fornecedor_id;
        if ($hasNF){ $types.="s"; $args[]=$nf_numero; }
        $types.="s"; $args[]=$data_compra;

        if ($hasGarantiaAte){ $types.="s"; $args[]=$garantia_ate; }
        if ($hasGarantiaMeses){ $types.="i"; $args[]=$garantia_meses; }

        if ($hasCusto){ $types.="s"; $args[]=$custo; }
        if ($hasDeprec){ $types.="i"; $args[]=$deprec; }
        if ($hasCentroCusto){ $types.="s"; $args[]=$centro_custo; }
        $types.="s"; $args[]=$observacoes;

        if ($hasAtivoFlag){ $types.="i"; $args[]=$ativo_reg; }
        if ($hasManutUnid){ $types.="s"; $args[]=$man_unid; }
        if ($hasManutQtd) { $types.="i"; $args[]=$man_qtd; }
        if ($hasManutUlt) { $types.="s"; $args[]=$man_ult; }
        if ($hasManutAlertar){ $types.="i"; $args[]=$man_alertar; }

        if ($col_cat_item){ $types.="i"; $args[]=$categoria_item_id; }
        if ($col_inf_cat){ $types.="i"; $args[]=$informar_categoria; }
        if ($col_nome_item){ $types.="i"; $args[]=$nome_item_id; }
        if ($col_nome_digit){ $types.="i"; $args[]=$nome_digit; }
        if ($col_nome_txt){ $types.="s"; $args[]=$nome_txt; }
        if ($col_inf_deposito){ $types.="i"; $args[]=$informar_deposito; }
        if ($col_sector_item){ $types.="i"; $args[]=$sector_item_id; }
        if ($col_aq_loc){ $types.="s"; $args[]=$aquisicao_tipo; }

        $types.="i"; $args[]=$id;

        $st=$dbc->prepare($sql);
        $st->bind_param($types, ...$args);
        $st->execute();
        $st->close();

      } else {
        // INSERT
        $sql = "INSERT INTO moz_ativo (
                  nome, cat_id, marca_id, modelo_id, tag_patrimonial, numero_serie, status_id, local_id"
             . ($hasDepositoCol ? ", deposito_id" : "")
             . ", fornecedor_id, "
             . ($hasNF ? "nf_numero, " : "")
             . "data_compra, "
             . ($hasGarantiaAte ? "garantia_ate, " : "")
             . ($hasGarantiaMeses ? "garantia_meses, " : "")
             . ($hasCusto ? "custo_aquisicao, " : "")
             . ($hasDeprec ? "depreciacao_meses, " : "")
             . ($hasCentroCusto ? "centro_custo, " : "")
             . "observacoes"
             . ($hasAtivoFlag ? ", ativo" : "")
             . ($hasManutUnid ? ", manut_periodo_unid" : "")
             . ($hasManutQtd  ? ", manut_periodo_qtd"  : "")
             . ($hasManutUlt  ? ", manut_ultimo"       : "")
             . ($hasManutAlertar ? ", manut_alertar"   : "")
             . ($col_cat_item ? ", categoria_item_id" : "")
             . ($col_inf_cat ? ", informar_categoria" : "")
             . ($col_nome_item ? ", nome_item_id" : "")
             . ($col_nome_digit ? ", nome_digit" : "")
             . ($col_nome_txt ? ", nome_txt" : "")
             . ($col_inf_deposito ? ", informar_deposito" : "")
             . ($col_sector_item ? ", sector_item_id" : "")
             . ($col_aq_loc ? ", aquisicao_tipo" : "")
             . ") VALUES (?,?,?,?,?,?,?,?"
             . ($hasDepositoCol ? ",?" : "")
             . ",?"
             . ($hasNF ? ",?" : "")
             . ",?"
             . ($hasGarantiaAte ? ",?" : "")
             . ($hasGarantiaMeses ? ",?" : "")
             . ($hasCusto ? ",?" : "")
             . ($hasDeprec ? ",?" : "")
             . ($hasCentroCusto ? ",?" : "")
             . ",?"
             . ($hasAtivoFlag ? ",?" : "")
             . ($hasManutUnid ? ",?" : "")
             . ($hasManutQtd  ? ",?" : "")
             . ($hasManutUlt  ? ",?" : "")
             . ($hasManutAlertar ? ",?" : "")
             . ($col_cat_item ? ",?" : "")
             . ($col_inf_cat ? ",?" : "")
             . ($col_nome_item ? ",?" : "")
             . ($col_nome_digit ? ",?" : "")
             . ($col_nome_txt ? ",?" : "")
             . ($col_inf_deposito ? ",?" : "")
             . ($col_sector_item ? ",?" : "")
             . ($col_aq_loc ? ",?" : "")
             . ")";

        $types = "siiissii";
        $args  = [$nome,$cat_id,$marca_id,$modelo_id,$tag,$serie,$status_id,$local_id];

        if ($hasDepositoCol){ $types.="i"; $args[]=$deposito_id; }

        $types.="i"; $args[]=$fornecedor_id;
        if ($hasNF){ $types.="s"; $args[]=$nf_numero; }
        $types.="s"; $args[]=$data_compra;

        if ($hasGarantiaAte){ $types.="s"; $args[]=$garantia_ate; }
        if ($hasGarantiaMeses){ $types.="i"; $args[]=$garantia_meses; }

        if ($hasCusto){ $types.="s"; $args[]=$custo; }
        if ($hasDeprec){ $types.="i"; $args[]=$deprec; }
        if ($hasCentroCusto){ $types.="s"; $args[]=$centro_custo; }
        $types.="s"; $args[]=$observacoes;

        if ($hasAtivoFlag){ $types.="i"; $args[]=$ativo_reg; }
        if ($hasManutUnid){ $types.="s"; $args[]=$man_unid; }
        if ($hasManutQtd) { $types.="i"; $args[]=$man_qtd; }
        if ($hasManutUlt) { $types.="s"; $args[]=$man_ult; }
        if ($hasManutAlertar){ $types.="i"; $args[]=$man_alertar; }

        if ($col_cat_item){ $types.="i"; $args[]=$categoria_item_id; }
        if ($col_inf_cat){ $types.="i"; $args[]=$informar_categoria; }
        if ($col_nome_item){ $types.="i"; $args[]=$nome_item_id; }
        if ($col_nome_digit){ $types.="i"; $args[]=$nome_digit; }
        if ($col_nome_txt){ $types.="s"; $args[]=$nome_txt; }
        if ($col_inf_deposito){ $types.="i"; $args[]=$informar_deposito; }
        if ($col_sector_item){ $types.="i"; $args[]=$sector_item_id; }
        if ($col_aq_loc){ $types.="s"; $args[]=$aquisicao_tipo; }

        $st=$dbc->prepare($sql);
        $st->bind_param($types, ...$args);
        $st->execute();
        $id=$st->insert_id;
        $st->close();
      }

      // custom fields (mantido)
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

      // impedimento de atrelamento duplicado (mantido)
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
          if ($chk->fetch()) $conflitos[] = $aid;
          $chk->free_result();
        }
        $chk->close();

        if ($conflitos) {
          $err = 'Os seguintes ativos já estão atrelados a outro item: #'.implode(', #',$conflitos).'. Remova-os da seleção.';
        }
      }

      if (!$err && !empty($_POST['atrelados']) && is_array($_POST['atrelados'])) {
        foreach ($_POST['atrelados'] as $aid) {
          $aid = (int)$aid; if ($aid<=0) continue;
          $_POST['rel_tipo'][]      = 'depends';
          $_POST['rel_destino'][]   = $aid;
          $_POST['rel_descricao'][] = '';
        }
      }

      // 8) Vínculos: tipo vem do ga-list link_type (value = name)
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

      // 12) anexos (mantido: grava; e no form abaixo lista para baixar)
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

/* ================== GET: combos e edição ================== */
$cats=[]; $rs=$dbc->query("SELECT id,nome FROM moz_cat_ativo WHERE ".(has_col($dbc,'moz_cat_ativo','ativo')?'ativo=1':'1=1')." ORDER BY nome"); if($rs) while($r=$rs->fetch_assoc()) $cats[]=$r;
$marcas=[]; $rs=$dbc->query("SELECT id,nome FROM moz_marca ".(has_col($dbc,'moz_marca','ativo')?'WHERE ativo=1':'')." ORDER BY nome"); if($rs) while($r=$rs->fetch_assoc()) $marcas[]=$r;
$modelos=[];

$locais = $empresas; // Local = empresas

$rec = [
  'nome'=>'','cat_id'=>'','marca_id'=>'','modelo_id'=>'','tag_patrimonial'=>'','numero_serie'=>'',
  'status_id'=>'','ativo'=>1,'local_id'=>'','deposito_id'=>'',
  'fornecedor_id'=>'','nf_numero'=>'','data_compra'=>'',
  'garantia_ate'=>'','garantia_meses'=>0,
  'custo_aquisicao'=>'','depreciacao_meses'=>'','centro_custo'=>'','observacoes'=>'',
  'manut_unid'=>'','manut_qtd'=>0,'manut_ultimo'=>'','manut_alertar'=>1,

  // 1) Nome (opcional persistência por colunas)
  'nome_digit'=>0,'nome_item_id'=>'','nome_txt'=>'',
  // 3) Categoria lista + checkbox
  'informar_categoria'=>0,'categoria_item_id'=>'',
  // 4) Depósito checkbox
  'informar_deposito'=>0,
  // 5) SETOR/SUBSETOR
  'sector_item_id'=>'',
  // 6) Aquisição/Locação
  'aquisicao_tipo'=>'',
];
$cf_defs=[]; $cf_vals=[]; $rels=[]; $atrelados=[];

// fornecedores (se tabela existir)
$fornecedores=[];
if ($hasFornecedorTbl) {
  $r=$dbc->query("SELECT id,nome FROM moz_fornecedor ".(has_col($dbc,'moz_fornecedor','ativo')?'WHERE ativo=1':'')." ORDER BY nome");
  if($r) while($x=$r->fetch_assoc()) $fornecedores[]=$x;
}

if ($id>0 && !$err && $_SERVER['REQUEST_METHOD']!=='POST'){
  $cols = "id,nome,cat_id,marca_id,modelo_id,tag_patrimonial,numero_serie,status_id,local_id"
        . ($hasDepositoCol ? ",deposito_id" : "")
        . ",fornecedor_id,"
        . ($hasNF ? "nf_numero," : "")
        . "data_compra,"
        . ($hasGarantiaAte ? "garantia_ate," : "")
        . ($hasGarantiaMeses ? "garantia_meses," : "")
        . ($hasCusto ? "custo_aquisicao," : "")
        . ($hasDeprec ? "depreciacao_meses," : "")
        . ($hasCentroCusto ? "centro_custo," : "")
        . "observacoes"
        . ($hasAtivoFlag ? ",ativo" : "")
        . ($hasManutUnid ? ",manut_periodo_unid" : "")
        . ($hasManutQtd  ? ",manut_periodo_qtd"  : "")
        . ($hasManutUlt  ? ",manut_ultimo"       : "")
        . ($hasManutProx ? ",manut_proxima"      : "")
        . ($hasManutAlertar ? ",manut_alertar"   : "")
        . ($col_cat_item ? ",categoria_item_id" : "")
        . ($col_inf_cat ? ",informar_categoria" : "")
        . ($col_nome_item ? ",nome_item_id" : "")
        . ($col_nome_digit ? ",nome_digit" : "")
        . ($col_nome_txt ? ",nome_txt" : "")
        . ($col_inf_deposito ? ",informar_deposito" : "")
        . ($col_sector_item ? ",sector_item_id" : "")
        . ($col_aq_loc ? ",aquisicao_tipo" : "");

  $st=$dbc->prepare("SELECT $cols FROM moz_ativo WHERE id=?");
  $st->bind_param('i',$id); $st->execute();
  $row=$st->get_result()->fetch_assoc(); $st->close();

  if($row){
    $rec['nome']=$row['nome'];
    $rec['cat_id']=$row['cat_id'];
    $rec['marca_id']=$row['marca_id'];
    $rec['modelo_id']=$row['modelo_id'];
    $rec['tag_patrimonial']=$row['tag_patrimonial'];
    $rec['numero_serie']=$row['numero_serie'];
    $rec['status_id']=$row['status_id'];
    $rec['local_id']=$row['local_id'];
    if($hasDepositoCol) $rec['deposito_id']=$row['deposito_id'];
    $rec['fornecedor_id']=$row['fornecedor_id'];
    if($hasNF) $rec['nf_numero']=$row['nf_numero'];
    $rec['data_compra']=$row['data_compra'];
    if($hasGarantiaAte) $rec['garantia_ate']=$row['garantia_ate'];
    if($hasGarantiaMeses) $rec['garantia_meses']=(int)$row['garantia_meses'];
    if($hasCusto) $rec['custo_aquisicao']=$row['custo_aquisicao'];
    if($hasDeprec) $rec['depreciacao_meses']=$row['depreciacao_meses'];
    if($hasCentroCusto) $rec['centro_custo']=$row['centro_custo'];
    $rec['observacoes']=$row['observacoes'];
    if($hasAtivoFlag) $rec['ativo']=(int)$row['ativo'];
    if($hasManutUnid) $rec['manut_unid']=$row['manut_periodo_unid'];
    if($hasManutQtd)  $rec['manut_qtd']=(int)$row['manut_periodo_qtd'];
    if($hasManutUlt)  $rec['manut_ultimo']=$row['manut_ultimo'];
    if($hasManutAlertar) $rec['manut_alertar']=$row['manut_alertar'];

    if($col_cat_item)   $rec['categoria_item_id']=$row['categoria_item_id'];
    if($col_inf_cat)    $rec['informar_categoria']=(int)$row['informar_categoria'];
    if($col_nome_item)  $rec['nome_item_id']=$row['nome_item_id'];
    if($col_nome_digit) $rec['nome_digit']=(int)$row['nome_digit'];
    if($col_nome_txt)   $rec['nome_txt']=$row['nome_txt'];
    if($col_inf_deposito) $rec['informar_deposito']=(int)$row['informar_deposito'];
    if($col_sector_item)  $rec['sector_item_id']=$row['sector_item_id'];
    if($col_aq_loc)       $rec['aquisicao_tipo']=$row['aquisicao_tipo'];

    // fallback visual de checkbox depósito: se não tiver coluna, marca se tiver deposito_id
    if(!$col_inf_deposito) $rec['informar_deposito'] = !empty($rec['deposito_id']) ? 1 : 0;

    // fallback visual de checkbox categoria: se não tiver coluna, marca se tiver categoria_item_id
    if(!$col_inf_cat) $rec['informar_categoria'] = !empty($rec['categoria_item_id']) ? 1 : 0;

    if($rec['marca_id'] && $hasModeloTbl){
      $m=$dbc->prepare("SELECT id,nome FROM moz_modelo WHERE marca_id=? ORDER BY nome");
      $m->bind_param('i', $rec['marca_id']); $m->execute();
      $rr=$m->get_result(); while($x=$rr->fetch_assoc()) $modelos[]=$x; $m->close();
    }
  }

  if ($hasCFDef){
    $sql = "SELECT $cfCols FROM moz_cf_def WHERE cat_id=? AND $cfWhere ORDER BY $cfOrder";
    $q=$dbc->prepare($sql);
    $q->bind_param('i',$rec['cat_id']); $q->execute();
    $r=$q->get_result(); while($row=$r->fetch_assoc()) $cf_defs[]=$row; $q->close();

    if ($hasCFVal){
      $r=$dbc->query("SELECT def_id,valor FROM moz_cf_val WHERE ativo_id=".(int)$id);
      while($x=$r->fetch_assoc()) $cf_vals[(int)$x['def_id']]=$x['valor'];
    }
  }

  if ($hasRelTbl){
    $r=$dbc->query("SELECT destino_id,tipo,descricao FROM moz_ativo_relacao WHERE origem_id=".(int)$id." ORDER BY id");
    while($x=$r->fetch_assoc()) $rels[]=$x;
  }

  // anexos para exibir/baixar
  if ($hasAnexoTbl && $hasAnexoTipoCol && $hasAnexoPathCol){
    $anexos = ['foto'=>[], 'contrato'=>[], 'outros'=>[]];
    $r=$dbc->query("SELECT tipo,path FROM moz_ativo_anexo WHERE ativo_id=".(int)$id." ORDER BY id DESC");
    if($r) while($x=$r->fetch_assoc()){
      $t = $x['tipo'] ?? 'outros';
      $p = $x['path'] ?? '';
      if($p==='') continue;
      if(!isset($anexos[$t])) $anexos[$t]=[];
      $anexos[$t][] = $p;
    }
  } else {
    $anexos = ['foto'=>[], 'contrato'=>[], 'outros'=>[]];
  }
} else {
  $anexos = ['foto'=>[], 'contrato'=>[], 'outros'=>[]];
}

/* Pré-carrega depósitos se já houver local/registro */
$depositos = ($hasDepositoTbl) ? carregarDepositos($dbc, (int)($rec['local_id'] ?: 0) ?: null, $depositoTable) : [];

/* 7) Garantia (meses): se não houver coluna garantia_meses, tenta inferir por garantia_ate - data_compra */
if (!$hasGarantiaMeses) {
  $meses = 0;
  if (!empty($rec['data_compra']) && !empty($rec['garantia_ate'])) {
    try{
      $dc = new DateTimeImmutable($rec['data_compra']);
      $ga = new DateTimeImmutable($rec['garantia_ate']);
      if ($ga >= $dc) {
        $diff = $dc->diff($ga);
        $meses = ((int)$diff->y)*12 + (int)$diff->m;
        if ((int)$diff->d > 0) $meses++; // arredonda para cima se houver dias
      }
    }catch(Exception $e){ $meses = 0; }
  }
  $rec['garantia_meses'] = $meses;
}

/* ================== Includes visuais ================== */
include_once ROOT_PATH.'system/includes/head.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.1" rel="stylesheet">
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
          <label>Local *</label>
          <select name="local_id" id="local_id" required>
            <option value="">—</option>
            <?php foreach($locais as $l): ?>
              <option value="<?= (int)$l['id'] ?>" <?= (string)$rec['local_id']===(string)$l['id']?'selected':'' ?>><?= h($l['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

<div>
  <label>Setor/Subsetor</label>

  <input type="hidden" name="sector_item_id" id="sector_item_id" value="<?= h($rec['sector_item_id']) ?>">

  <div id="sector_cascade" class="stack" style="gap:8px;"></div>

  <?php if(!($col_sector_item && $listIdSector)): ?>
    <span class="hint">Setor/Subsetor desabilitado: coluna em moz_ativo ou lista (slug: <?= h($SLUG_SECTOR) ?>) não encontrada.</span>
  <?php endif; ?>
</div>


                 
        <!-- 1) NOME (lista + Digitar) -->
        <div>
          <label>Nome *</label>

          <div id="wrap_nome_select">
            <select name="nome_item_id" id="nome_item_id" <?= ($listIdNome && $itNome) ? '' : 'disabled' ?>>
              <option value="">— selecione —</option>
              <?php foreach($itNome as $it): ?>
                <option value="<?= (int)$it['id'] ?>" <?= ((int)$rec['nome_item_id']===(int)$it['id'])?'selected':'' ?>>
                  <?= h($it['label']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div id="wrap_nome_txt" style="display:none;">
            <input type="text" name="nome_txt" id="nome_txt" value="<?= h($rec['nome_txt'] ?: $rec['nome']) ?>" placeholder="Ex.: Notebook João Silva" />
          </div>

          <div style="display:flex; gap:10px; align-items:center; margin-bottom:6px;">
            <label style="display:flex; gap:6px; align-items:center; font-weight:normal; margin:0;">
              <input type="checkbox" id="nome_digit" name="nome_digit" value="1" <?= !empty($rec['nome_digit'])?'checked':'' ?>>
              Digitar
            </label>
            <span class="hint">Marque para digitar o “Nome do Ativo”.</span>
          </div> 

          <?php if(!($listIdNome && $itNome)): ?>
            <span class="hint">Lista “Nome do Ativo” não encontrada (slug: <?= h($SLUG_NOME_ATIVO) ?>). Campo ficará como digitação.</span>
          <?php endif; ?>
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

      </div>

      <div class="grid cols-4">

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

        <!-- 2) Status operacional (ga-list op_status) -->
        <div>
          <label>Status operacional *</label>
          <select name="status_id" id="status_id" required <?= ($listIdStatus && $itStatus) ? '' : '' ?>>
            <option value="">—</option>
            <?php foreach($itStatus as $it): ?>
              <option value="<?= (int)$it['id'] ?>" <?= ((int)$rec['status_id']===(int)$it['id'])?'selected':'' ?>>
                <?= h($it['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if(!($listIdStatus && $itStatus)): ?>
            <span class="hint">Lista “Status Operacional” não encontrada (slug: <?= h($SLUG_STATUS_OP) ?>).</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- 5) Inserir após Local: SETOR/SUBSETOR (ga-list sector) -->
      <div class="grid cols-4">
      <!-- Incluir depois -->
        <div>
          <label>Ativo?</label>
          <select name="ativo" <?= $hasAtivoFlag?'':'disabled' ?>>
            <option value="1" <?= !empty($rec['ativo'])? 'selected':'' ?>>Sim</option>
            <option value="0" <?= empty($rec['ativo'])? 'selected':'' ?>>Não</option>
          </select>
          <?php if(!$hasAtivoFlag): ?><span class="hint">Campo "ativo" não existe em moz_ativo</span><?php endif; ?>
        </div>

        <!-- 6) Aquisição / Locação -->
        <div>
          <label>Aquisição / Locação</label>
          <select name="aquisicao_tipo" <?= $col_aq_loc ? '' : 'disabled' ?>>
            <option value="">—</option>
            <option value="Aquisicao" <?= ($rec['aquisicao_tipo']==='Aquisicao')?'selected':'' ?>>Aquisição</option>
            <option value="Locacao" <?= ($rec['aquisicao_tipo']==='Locacao')?'selected':'' ?>>Locação</option>
          </select>
          <?php if(!$col_aq_loc): ?><span class="hint">Coluna moz_ativo.aquisicao_tipo não encontrada.</span><?php endif; ?>
        </div>
      </div>

      <!-- 3) Categoria de Ativos (opcional por checkbox) -->
      <div class="grid cols-4" style="align-items:end">
        <div>
          <label style="display:flex; gap:6px; align-items:center; font-weight:normal; margin:0;">
            <input type="checkbox" id="informar_categoria" name="informar_categoria" value="1" <?= !empty($rec['informar_categoria'])?'checked':'' ?>>
            Informar categoria
          </label>
          <span class="hint">Se marcar, mostra a lista “Categoria de Ativos”.</span>
        </div>

        <div id="wrap_categoria" style="display:none;">
<label>Categoria de Ativos</label>

<input type="hidden" name="categoria_item_id" id="categoria_item_id" value="<?= h($rec['categoria_item_id']) ?>">

<div id="cat_cascade" class="stack" style="gap:8px;"></div>

<?php if(!($col_cat_item && $listIdCat)): ?>
  <span class="hint">Categoria desabilitada: coluna em moz_ativo ou lista (slug: <?= h($SLUG_CAT_ATIVOS) ?>) não encontrada.</span>
<?php endif; ?>

        </div>

        <div></div><div></div>
      </div>

      <!-- 4) Depósito (checkbox para exibir) -->
      <div class="grid cols-4" style="align-items:end">
        <div>
          <label style="display:flex; gap:6px; align-items:center; font-weight:normal; margin:0;">
            <input type="checkbox" id="informar_deposito" name="informar_deposito" value="1" <?= !empty($rec['informar_deposito'])?'checked':'' ?>>
            Informar Depósito
          </label>
          <span class="hint">Se marcar, mostra o select de Depósitos.</span>
        </div>

        <div id="wrap_deposito" style="display:none;">
          <label>Depósito</label>
          <select name="deposito_id" id="deposito_id" <?= ($hasDepositoTbl && $hasDepositoCol)?'':'disabled' ?>>
            <option value="">—</option>
            <?php if ($depositos): foreach($depositos as $d): ?>
              <option value="<?= (int)$d['id'] ?>" <?= (string)$rec['deposito_id']===(string)$d['id']?'selected':'' ?>><?= h($d['nome']) ?></option>
            <?php endforeach; endif; ?>
          </select>
          <?php if(!($hasDepositoTbl && $hasDepositoCol)): ?>
            <span class="hint">Tabela/coluna de depósito ausente (esperado: <?= h($depositoTable?:'moz_deposito') ?> e moz_ativo.deposito_id)</span>
          <?php endif; ?>
        </div>

        <div></div><div></div>
      </div>

      <div class="divider"></div>
      <p class="subtitle">Dados de aquisição</p>
      <div class="grid cols-4">
        <div>
          <label>Fornecedor</label>
          <select name="fornecedor_id" <?= $hasFornecedorTbl?'':'disabled' ?>>
            <option value="">—</option>
            <?php foreach($fornecedores as $f): ?>
              <option value="<?= (int)$f['id'] ?>" <?= (string)$rec['fornecedor_id']===(string)$f['id']?'selected':'' ?>><?= h($f['nome']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if(!$hasFornecedorTbl): ?><span class="hint">Tabela moz_fornecedor ausente</span><?php endif; ?>
        </div>
        <div><label>Nota fiscal</label><input type="text" name="nf_numero" value="<?= h($rec['nf_numero']) ?>" placeholder="NF-e" <?= $hasNF?'':'disabled' ?>/></div>
        <div><label>Data de compra</label><input type="date" name="data_compra" id="data_compra" value="<?= h($rec['data_compra']) ?>"/></div>

        <!-- 7) Garantia (meses) + alerta expirada -->
        <?php
          $garantiaInfo = '';
          $garantiaExpirada = false;
          if (!empty($rec['data_compra']) && (int)$rec['garantia_meses'] > 0) {
            try {
              $dc = new DateTimeImmutable($rec['data_compra']);
              $exp = $dc->add(new DateInterval('P'.((int)$rec['garantia_meses']).'M'));
              $hoje = new DateTimeImmutable('today');
              if ($exp < $hoje) { $garantiaExpirada = true; $garantiaInfo = 'Garantia expirada'; }
            } catch(Exception $e){}
          }
        ?>
        <div>
          <label>Garantia (meses)</label>
          <input type="number" min="0" name="garantia_meses" id="garantia_meses" value="<?= (int)$rec['garantia_meses'] ?>" <?= ($hasGarantiaMeses || $hasGarantiaAte) ? '' : 'disabled' ?>/>
          <?php if(!($hasGarantiaMeses || $hasGarantiaAte)): ?>
            <span class="hint">Coluna de garantia não encontrada (garantia_meses ou garantia_ate).</span>
          <?php endif; ?>
          <?php if($garantiaExpirada): ?>
            <div style="color:#c00; font-weight:600; margin-top:4px;"><?= h($garantiaInfo) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="grid cols-3">
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
            <option value="1" <?= ($rec['manut_alertar']??1)?'selected':'' ?>>Sim</option>
            <option value="0" <?= !($rec['manut_alertar']??1)?'selected':'' ?>>Não</option>
          </select>
        </div>
      </div>

      <div class="divider"></div>
      <p class="subtitle">Atributos (campos customizados por categoria)</p>
      <div id="attr-list" class="stack">
        <?php if ($hasCFDef && $rec['cat_id']): ?>
          <?php
            if (!$cf_defs){
              $sql = "SELECT $cfCols FROM moz_cf_def WHERE cat_id=? AND $cfWhere ORDER BY $cfOrder";
              $q=$dbc->prepare($sql);
              $q->bind_param('i',$rec['cat_id']); $q->execute();
              $r=$q->get_result(); while($row=$r->fetch_assoc()) $cf_defs[]=$row; $q->close();
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
            <div>
              <label>Tipo</label>
              <select name="rel_tipo[]" <?= ($listIdLink && $itLink) ? '' : '' ?>>
                <option value="">—</option>
                <?php foreach($itLink as $it): ?>
                  <?php $val = (string)($it['name'] ?? $it['label']); ?>
                  <option value="<?= h($val) ?>" <?= ((string)$r['tipo']===(string)$val)?'selected':'' ?>>
                    <?= h($it['label']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if(!($listIdLink && $itLink)): ?>
                <span class="hint">Lista “Tipo/Vínculos” não encontrada (slug: <?= h($SLUG_LINK_TYPE) ?>).</span>
              <?php endif; ?>
            </div>
            <div><label>Ativo destino (ID)</label><input type="number" name="rel_destino[]" value="<?= (int)$r['destino_id'] ?>" placeholder="ID do ativo"/></div>
            <div><label>Descrição</label><input type="text" name="rel_descricao[]" value="<?= h($r['descricao']) ?>" placeholder="porta/observação"/></div>
            <div class="row"><button type="button" class="btn small danger" onclick="this.closest('.grid').remove()">Remover</button></div>
          </div>
        <?php endforeach; else: ?>
          <div class="grid cols-4" style="align-items:end">
            <div>
              <label>Tipo</label>
              <select name="rel_tipo[]" <?= ($listIdLink && $itLink) ? '' : '' ?>>
                <option value="">—</option>
                <?php foreach($itLink as $it): ?>
                  <?php $val = (string)($it['name'] ?? $it['label']); ?>
                  <option value="<?= h($val) ?>"><?= h($it['label']) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if(!($listIdLink && $itLink)): ?>
                <span class="hint">Lista “Tipo/Vínculos” não encontrada (slug: <?= h($SLUG_LINK_TYPE) ?>).</span>
              <?php endif; ?>
            </div>
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

      <?php if($id>0): ?>
        <div class="card" style="margin-bottom:12px;">
          <p class="hint" style="margin:0 0 10px 0;">Arquivos já anexados (clique para baixar):</p>

          <?php if(!empty($anexos['foto'])): ?>
            <div style="margin-bottom:8px;">
              <strong>Fotos</strong>
              <ul style="margin:6px 0 0 18px;">
                <?php foreach($anexos['foto'] as $p): ?>
                  <li><a href="<?= h(BASE_URL.'/'.ltrim($p,'/')) ?>" target="_blank" rel="noopener">baixar</a> — <?= h(basename($p)) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php if(!empty($anexos['contrato'])): ?>
            <div style="margin-bottom:8px;">
              <strong>Garantia/Contrato</strong>
              <ul style="margin:6px 0 0 18px;">
                <?php foreach($anexos['contrato'] as $p): ?>
                  <li><a href="<?= h(BASE_URL.'/'.ltrim($p,'/')) ?>" target="_blank" rel="noopener">baixar</a> — <?= h(basename($p)) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php if(!empty($anexos['outros'])): ?>
            <div>
              <strong>Outros</strong>
              <ul style="margin:6px 0 0 18px;">
                <?php foreach($anexos['outros'] as $p): ?>
                  <li><a href="<?= h(BASE_URL.'/'.ltrim($p,'/')) ?>" target="_blank" rel="noopener">baixar</a> — <?= h(basename($p)) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php if(empty($anexos['foto']) && empty($anexos['contrato']) && empty($anexos['outros'])): ?>
            <div class="hint">Nenhum arquivo anexado ainda.</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

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
          <div class="file-zone">Arraste aqui ou <button type="button" class="btn small" onclick="document.getElementById('outros').click()">escolher</button><input id="outros" type="file" name="outros[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.png,.jpg,.jpeg,.webp"/></div>
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
  // 1) Nome: alterna select <-> input (Digitar)
  const chkNome = document.getElementById('nome_digit');
  const wrapNomeSelect = document.getElementById('wrap_nome_select');
  const wrapNomeTxt = document.getElementById('wrap_nome_txt');
  function syncNomeUI(){
    const dig = chkNome && chkNome.checked;
    if (wrapNomeSelect) wrapNomeSelect.style.display = dig ? 'none' : '';
    if (wrapNomeTxt) wrapNomeTxt.style.display = dig ? '' : 'none';

    // se a lista não existir, força digitação
    const sel = document.getElementById('nome_item_id');
    if (sel && sel.disabled) {
      if (wrapNomeSelect) wrapNomeSelect.style.display = 'none';
      if (wrapNomeTxt) wrapNomeTxt.style.display = '';
    }
  }
  chkNome?.addEventListener('change', syncNomeUI);
  syncNomeUI();

  // 3) Categoria: checkbox mostra/esconde select
  const chkCat = document.getElementById('informar_categoria');
  const wrapCat = document.getElementById('wrap_categoria');
  function syncCatUI(){
    const on = chkCat && chkCat.checked;
    if (wrapCat) wrapCat.style.display = on ? '' : 'none';
  }
  chkCat?.addEventListener('change', syncCatUI);
  syncCatUI();

  // 4) Depósito: checkbox mostra/esconde select
  const chkDep = document.getElementById('informar_deposito');
  const wrapDep = document.getElementById('wrap_deposito');
  function syncDepUI(){
    const on = chkDep && chkDep.checked;
    if (wrapDep) wrapDep.style.display = on ? '' : 'none';
  }
  chkDep?.addEventListener('change', syncDepUI);
  syncDepUI();

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

  /* ================== AJAX: ga_list children (cascata) ================== */
if (isset($_GET['ajax']) && $_GET['ajax']==='ga_children') {
  header('Content-Type: application/json; charset=utf-8');

  if (!$hasGaList) { echo json_encode([]); exit; }

  $slug = preg_replace('/[^a-z0-9_\-]/i','', $_GET['slug'] ?? '');
  $parent_id = ($_GET['parent_id'] ?? '') === '' ? 0 : (int)$_GET['parent_id'];

  $list_id = ga_list_id($dbc, $slug);
  if (!$list_id) { echo json_encode([]); exit; }

  $hasActive = has_col($dbc,'ga_list_item','active');

  // puxa itens da lista inteira (pra montar labels com path)
  $sqlAll = "SELECT id, parent_id, name, sort_order".($hasActive?", active":"")."
              FROM ga_list_item
             WHERE list_id=? ".($hasActive?"AND active=1":"")."
             ORDER BY sort_order ASC, name ASC";
  $st = $dbc->prepare($sqlAll);
  $st->bind_param('i', $list_id);
  $st->execute();
  $rs = $st->get_result();

  $items = [];
  $children = [];
  while($r=$rs->fetch_assoc()){
    $id = (int)$r['id'];
    $pid = ($r['parent_id']===null ? 0 : (int)$r['parent_id']);
    $items[$id] = [
      'id'=>$id,
      'parent_id'=>$pid,
      'name'=>$r['name'],
      'sort_order'=>(int)($r['sort_order'] ?? 0),
    ];
    if (!isset($children[$pid])) $children[$pid]=[];
    $children[$pid][]=$id;
  }
  $st->close();

  // ordena filhos por sort_order + name
  foreach($children as $pid=>$arr){
    usort($arr, function($a,$b) use ($items){
      $oa = $items[$a]['sort_order'] ?? 0;
      $ob = $items[$b]['sort_order'] ?? 0;
      if ($oa !== $ob) return $oa <=> $ob;
      return strcasecmp($items[$a]['name'] ?? '', $items[$b]['name'] ?? '');
    });
    $children[$pid]=$arr;
  }

  // monta label "Pai - Filho - Neto"
  $mkLabel = function($id) use (&$mkLabel, $items){
    if (!isset($items[$id])) return '';
    $name = $items[$id]['name'] ?? '';
    $pid  = (int)($items[$id]['parent_id'] ?? 0);
    if ($pid<=0) return $name;
    $p = $mkLabel($pid);
    return $p ? ($p.' - '.$name) : $name;
  };

  $out = [];
  foreach(($children[$parent_id] ?? []) as $cid){
    $out[] = ['id'=>$cid, 'label'=>$mkLabel($cid)];
  }

  echo json_encode($out); exit;
}

/* ================== AJAX: ga_list path (root -> leaf) ================== */
if (isset($_GET['ajax']) && $_GET['ajax']==='ga_path') {
  header('Content-Type: application/json; charset=utf-8');

  if (!$hasGaList) { echo json_encode([]); exit; }

  $slug = preg_replace('/[^a-z0-9_\-]/i','', $_GET['slug'] ?? '');
  $id   = (int)($_GET['id'] ?? 0);

  $list_id = ga_list_id($dbc, $slug);
  if (!$list_id || $id<=0) { echo json_encode([]); exit; }

  $hasActive = has_col($dbc,'ga_list_item','active');
  $sqlAll = "SELECT id, parent_id".($hasActive?", active":"")."
              FROM ga_list_item
             WHERE list_id=? ".($hasActive?"AND active=1":"")."";
  $st = $dbc->prepare($sqlAll);
  $st->bind_param('i', $list_id);
  $st->execute();
  $rs = $st->get_result();

  $parentOf = [];
  while($r=$rs->fetch_assoc()){
    $iid = (int)$r['id'];
    $pid = ($r['parent_id']===null ? 0 : (int)$r['parent_id']);
    $parentOf[$iid]=$pid;
  }
  $st->close();

  // sobe até raiz
  $path = [];
  $cur = $id;
  $guard = 0;
  while($cur>0 && isset($parentOf[$cur]) && $guard < 50){
    array_unshift($path, $cur);
    $cur = (int)$parentOf[$cur];
    $guard++;
  }
  echo json_encode($path); exit;
}

// vínculos add
  document.getElementById('add-attach')?.addEventListener('click', ()=>{
    const wrap=document.getElementById('attach-list');
    const el=document.createElement('div'); el.className='grid cols-4'; el.style.alignItems='end';

    // monta opções do select com o que já está renderizado no 1º select (se existir)
    let optHtml = '<option value="">—</option>';
    const anySel = document.querySelector('#attach-list select[name="rel_tipo[]"]');
    if (anySel) optHtml = anySel.innerHTML;

    el.innerHTML = `
      <div><label>Tipo</label><select name="rel_tipo[]">${optHtml}</select></div>
      <div><label>Ativo destino (ID)</label><input type="number" name="rel_destino[]" placeholder="ID do ativo"/></div>
      <div><label>Descrição</label><input type="text" name="rel_descricao[]" placeholder="porta/observação"/></div>
      <div class="row"><button type="button" class="btn small danger" onclick="this.closest('.grid').remove()">Remover</button></div>
    `;
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

  // Depósitos: carrega quando muda Local (mantido) — só atualiza se o wrap estiver visível/checkbox marcado
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
  selLocal?.addEventListener('change', async ()=>{
    if (chkDep && chkDep.checked) await carregarDepositosPorEmpresa(selLocal.value);
  });

  // Autocomplete de ativos para "Ativos atrelados" (mantido)
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
    const hint = $listAtr.querySelector('.hint'); if (hint) hint.remove();
    $listAtr.appendChild(chip);
  }

  function resolveEscolhaAtual() {
    const val = $buscaAtr.value.trim();
    const hit = sugCache.find(s => s.label === val);
    if (hit) return hit;
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

  $buscaAtr?.addEventListener('keydown', (e)=>{
    if (e.key === 'Enter') { e.preventDefault(); $btnAddAtr.click(); }
  });
</script>

<script>
  async function gaFetchChildren(slug, parentId){
    const url = `?ajax=ga_children&slug=${encodeURIComponent(slug)}&parent_id=${encodeURIComponent(parentId ?? 0)}`;
    const r = await fetch(url);
    return await r.json();
  }

  async function gaFetchPath(slug, id){
    if (!id) return [];
    const url = `?ajax=ga_path&slug=${encodeURIComponent(slug)}&id=${encodeURIComponent(id)}`;
    const r = await fetch(url);
    return await r.json();
  }

  function makeSelect(options, placeholder='—'){
    const sel = document.createElement('select');
    sel.innerHTML = `<option value="">${placeholder}</option>`;
    (options || []).forEach(o=>{
      const opt = document.createElement('option');
      opt.value = String(o.id);
      opt.textContent = o.label;
      sel.appendChild(opt);
    });
    return sel;
  }

  async function initCascade({ slug, containerId, hiddenId, enabled }) {
    const box = document.getElementById(containerId);
    const hid = document.getElementById(hiddenId);
    if (!box || !hid) return;

    if (!enabled) {
      box.innerHTML = '';
      return;
    }

    const selectedLeaf = parseInt(hid.value || '0', 10) || 0;

    // pega caminho root->leaf (ids)
    const path = selectedLeaf ? await gaFetchPath(slug, selectedLeaf) : [];

    // limpa container
    box.innerHTML = '';

    // monta níveis dinamicamente
    let parentId = 0;
    let level = 0;

    while(true){
      const opts = await gaFetchChildren(slug, parentId);
      if (!opts || opts.length === 0) break;

      const sel = makeSelect(opts, level===0 ? '— selecione —' : '— selecione —');
      sel.dataset.level = String(level);
      sel.dataset.parent = String(parentId);

      // preseleciona se estiver no path
      const wanted = path[level] ? String(path[level]) : '';
      if (wanted) sel.value = wanted;

      box.appendChild(sel);

      // handler: ao trocar, remove níveis abaixo, atualiza hidden com o leaf atual (o último select selecionado)
      sel.addEventListener('change', async ()=>{
        // remove selects abaixo
        const lv = parseInt(sel.dataset.level,10);
        [...box.querySelectorAll('select')].forEach(s=>{
          const slv = parseInt(s.dataset.level,10);
          if (slv > lv) s.remove();
        });

        const v = parseInt(sel.value || '0',10) || 0;

        // se não selecionou, o leaf vira o último selecionado acima (ou 0)
        if (!v) {
          const sels = [...box.querySelectorAll('select')];
          let leaf = 0;
          sels.forEach(s=>{
            const vv = parseInt(s.value||'0',10)||0;
            if (vv) leaf = vv;
          });
          hid.value = leaf ? String(leaf) : '';
          return;
        }

        // atualiza leaf provisoriamente
        hid.value = String(v);

        // tenta carregar próximo nível (filhos do selecionado)
        const next = await gaFetchChildren(slug, v);
        if (next && next.length){
          const nextSel = makeSelect(next, '— selecione —');
          nextSel.dataset.level = String(lv+1);
          nextSel.dataset.parent = String(v);
          box.appendChild(nextSel);

          // dispara o mesmo comportamento (recursivo “manual”)
          nextSel.addEventListener('change', async ()=> {
            // remove abaixo do novo
            const nlv = parseInt(nextSel.dataset.level,10);
            [...box.querySelectorAll('select')].forEach(s=>{
              const slv = parseInt(s.dataset.level,10);
              if (slv > nlv) s.remove();
            });

            const nv = parseInt(nextSel.value || '0',10) || 0;
            if (!nv) {
              hid.value = String(v);
              return;
            }
            hid.value = String(nv);

            // se esse também tiver filhos, cria outro nível
            const more = await gaFetchChildren(slug, nv);
            if (more && more.length){
              // cria o próximo select “na sequência” chamando artificialmente change
              const moreSel = makeSelect(more, '— selecione —');
              moreSel.dataset.level = String(nlv+1);
              moreSel.dataset.parent = String(nv);
              box.appendChild(moreSel);

              // encadeia (deixa para o usuário selecionar)
              moreSel.addEventListener('change', async ()=> {
                const mlv = parseInt(moreSel.dataset.level,10);
                [...box.querySelectorAll('select')].forEach(s=>{
                  const slv = parseInt(s.dataset.level,10);
                  if (slv > mlv) s.remove();
                });
                const mv = parseInt(moreSel.value || '0',10) || 0;
                hid.value = mv ? String(mv) : String(nv);

                const last = await gaFetchChildren(slug, mv);
                if (last && last.length){
                  const lastSel = makeSelect(last, '— selecione —');
                  lastSel.dataset.level = String(mlv+1);
                  lastSel.dataset.parent = String(mv);
                  box.appendChild(lastSel);
                }
              });
            }
          });
        }
      });

      // se tem path neste nível, desce; senão para com um nível (sem auto-add)
      const chosen = path[level] ? path[level] : 0;
      if (!chosen) break;

      parentId = chosen;
      level++;
    }

    // ao final, garante hidden = leaf
    if (selectedLeaf) hid.value = String(selectedLeaf);
  }

  // init Setor/Subsetor
  initCascade({
    slug: 'sector',
    containerId: 'sector_cascade',
    hiddenId: 'sector_item_id',
    enabled: <?= ($col_sector_item && $listIdSector) ? 'true' : 'false' ?>
  });

  // init Categoria de Ativos (lista)
  initCascade({
    slug: 'asset_category',
    containerId: 'cat_cascade',
    hiddenId: 'categoria_item_id',
    enabled: <?= ($col_cat_item && $listIdCat) ? 'true' : 'false' ?>
  });
</script>


<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
 