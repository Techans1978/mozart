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
function ensure_dir($abs){
  if(!is_dir($abs)) @mkdir($abs,0775,true);
  return is_dir($abs) && is_writable($abs);
}

/* ============================================================
   LISTAS PADRÃO (Gestão de Ativos)
   ============================================================
   ATENÇÃO: como seu schema pode variar, este form:
   - Detecta se existem as tabelas das listas;
   - Detecta quais colunas existem em moz_ativo para salvar os IDs;
   - Se não existir, o campo fica desabilitado (não quebra nada).
*/
$hasListTbl = table_exists($dbc, 'moz_list') && table_exists($dbc, 'moz_list_item');

function pick_first_existing_col(mysqli $db, string $table, array $cands): ?string {
  foreach ($cands as $c) if (has_col($db, $table, $c)) return $c;
  return null;
}

/**
 * Retorna list_id pelo slug.
 */
function list_id_by_slug(mysqli $dbc, string $slug): ?int {
  if (!table_exists($dbc,'moz_list')) return null;
  $st = $dbc->prepare("SELECT id FROM moz_list WHERE slug=? LIMIT 1");
  $st->bind_param('s', $slug);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();
  return $row ? (int)$row['id'] : null;
}

/**
 * Carrega itens da lista (com hierarquia) e devolve em formato "flat"
 * label = "Pai — Filho — Neto"
 */
function list_items_flat(mysqli $dbc, int $list_id, bool $onlyActive=true): array {
  if (!table_exists($dbc,'moz_list_item')) return [];
  $where = "list_id=?";
  if ($onlyActive && has_col($dbc,'moz_list_item','active')) $where .= " AND active=1";

  // Campos esperados
  $cols = "id, parent_id, name";
  $cols .= has_col($dbc,'moz_list_item','description') ? ", description" : ", '' AS description";
  $cols .= has_col($dbc,'moz_list_item','sort_order') ? ", sort_order" : ", 0 AS sort_order";
  $cols .= has_col($dbc,'moz_list_item','active') ? ", active" : ", 1 AS active";

  $sql = "SELECT $cols
            FROM moz_list_item
           WHERE $where
           ORDER BY sort_order ASC, name ASC, id ASC";
  $st = $dbc->prepare($sql);
  $st->bind_param('i', $list_id);
  $st->execute();
  $rs = $st->get_result();
  $rows = [];
  while($r = $rs->fetch_assoc()){
    $rows[] = [
      'id' => (int)$r['id'],
      'parent_id' => $r['parent_id'] !== null ? (int)$r['parent_id'] : null,
      'name' => (string)$r['name'],
      'description' => (string)($r['description'] ?? ''),
      'sort_order' => (int)($r['sort_order'] ?? 0),
      'active' => (int)($r['active'] ?? 1),
    ];
  }
  $st->close();

  // monta children map
  $byId = [];
  $children = [];
  foreach($rows as $r){
    $byId[$r['id']] = $r;
    $pid = $r['parent_id'] ?? 0;
    if (!isset($children[$pid])) $children[$pid] = [];
    $children[$pid][] = $r['id'];
  }

  // ordenação já vem do SELECT, então preserva
  $out = [];
  $walk = function($pid, $trail) use (&$walk, &$out, $children, $byId){
    if (!isset($children[$pid])) return;
    foreach($children[$pid] as $cid){
      $node = $byId[$cid];
      $newTrail = array_merge($trail, [$node['name']]);
      $label = implode(' — ', $newTrail);
      $out[] = [
        'id' => $node['id'],
        'label' => $label,
        'name' => $node['name'],
        'description' => $node['description'],
        'parent_id' => $node['parent_id'],
      ];
      $walk($cid, $newTrail);
    }
  };
  $walk(0, []);
  return $out;
}

/**
 * Busca o texto (label) do item pelo ID
 */
function list_item_label_by_id(mysqli $dbc, int $item_id): ?string {
  if (!table_exists($dbc,'moz_list_item')) return null;
  $cols = "id,name";
  $cols .= has_col($dbc,'moz_list_item','parent_id') ? ",parent_id" : ",NULL AS parent_id";
  $st = $dbc->prepare("SELECT $cols FROM moz_list_item WHERE id=? LIMIT 1");
  $st->bind_param('i', $item_id);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();
  if (!$row) return null;

  // monta breadcrumb subindo os pais
  $name = (string)$row['name'];
  $pid  = ($row['parent_id']!==null) ? (int)$row['parent_id'] : null;

  $trail = [$name];
  $guard = 0;
  while($pid && $guard < 10){
    $guard++;
    $st2 = $dbc->prepare("SELECT id,name,parent_id FROM moz_list_item WHERE id=? LIMIT 1");
    $st2->bind_param('i', $pid);
    $st2->execute();
    $p = $st2->get_result()->fetch_assoc();
    $st2->close();
    if(!$p) break;
    array_unshift($trail, (string)$p['name']);
    $pid = ($p['parent_id']!==null) ? (int)$p['parent_id'] : null;
  }
  return implode(' — ', $trail);
}

/* ================== CSRF ================== */
if (empty($_SESSION['csrf_ativo'])) $_SESSION['csrf_ativo'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_ativo'];

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

/* ================== Colunas "listas" em moz_ativo (auto-detect) ==================
   Você pode ter escolhido nomes diferentes. Deixo vários candidatos.
*/
$col_setor_item   = pick_first_existing_col($dbc,'moz_ativo', ['setor_item_id','setor_lista_item_id','setor_list_item_id','setor_id_lista','setor_id']);
$col_status_item  = pick_first_existing_col($dbc,'moz_ativo', ['status_oper_item_id','status_item_id','status_lista_item_id','status_list_item_id','status_operacional_item_id']);
$col_vinc_item    = pick_first_existing_col($dbc,'moz_ativo', ['vinculo_item_id','vinculos_item_id','tipo_vinculo_item_id','tipo_vinculos_item_id']);
$col_cat_item     = pick_first_existing_col($dbc,'moz_ativo', ['categoria_item_id','categoria_lista_item_id','categoria_list_item_id','cat_item_id','cat_lista_item_id']);

/* ================== Slugs das listas (ajuste aqui se seus slugs forem diferentes) ================== */
$SLUG_NOME_ATIVO   = 'nome-do-ativo';       // Lista: Nome do Ativo
$SLUG_SETOR        = 'setor';               // Lista: Setor (com subsetores)
$SLUG_STATUS_OPER  = 'status-operacional';  // Lista: Status Operacional
$SLUG_TIPO_VINC    = 'tipo-vinculos';       // Lista: Tipo/Vínculos
$SLUG_CAT_ATIVOS   = 'categoria-ativos';    // Lista: Categoria de Ativos

$listIdNome   = $hasListTbl ? list_id_by_slug($dbc, $SLUG_NOME_ATIVO) : null;
$listIdSetor  = $hasListTbl ? list_id_by_slug($dbc, $SLUG_SETOR) : null;
$listIdStatus = $hasListTbl ? list_id_by_slug($dbc, $SLUG_STATUS_OPER) : null;
$listIdVinc   = $hasListTbl ? list_id_by_slug($dbc, $SLUG_TIPO_VINC) : null;
$listIdCat    = $hasListTbl ? list_id_by_slug($dbc, $SLUG_CAT_ATIVOS) : null;

$itNome   = ($listIdNome)   ? list_items_flat($dbc, $listIdNome, true) : [];
$itSetor  = ($listIdSetor)  ? list_items_flat($dbc, $listIdSetor, true) : [];
$itStatus = ($listIdStatus) ? list_items_flat($dbc, $listIdStatus, true) : [];
$itVinc   = ($listIdVinc)   ? list_items_flat($dbc, $listIdVinc, true) : [];
$itCat    = ($listIdCat)    ? list_items_flat($dbc, $listIdCat, true) : [];

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

/* ===== DEPOSITOS ===== */
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

/* ================== AJAX: busca de ativos (atrelados) ================== */
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

/* ====== Campos customizados (mantive como está, depende de cat_id antigo) ====== */
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

/* ================== POST: salvar ================== */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!hash_equals($csrf, $_POST['csrf'] ?? '')) die('CSRF inválido.');

  $id = (int)($_POST['id'] ?? 0);

  // ===== Nome (lista + digitar) =====
  $nome_digit = isset($_POST['nome_digit']) ? 1 : 0;
  $nome_item_id = (int)($_POST['nome_item_id'] ?? 0);
  $nome_txt = trim($_POST['nome_txt'] ?? '');

  $nome = '';
  if ($nome_digit) {
    $nome = $nome_txt;
  } else {
    // se selecionou item da lista, grava o label no campo "nome" (não quebra schema antigo)
    if ($nome_item_id > 0) {
      $lbl = list_item_label_by_id($dbc, $nome_item_id);
      $nome = $lbl ?: '';
    }
    // fallback se não achou label
    if ($nome==='') $nome = $nome_txt;
  }

  // ===== Setor / Status / Vínculo / Categoria (IDs de lista, se colunas existirem) =====
  $setor_item_id  = (int)($_POST['setor_item_id'] ?? 0);
  $status_item_id = (int)($_POST['status_item_id'] ?? 0);
  $vinc_item_id   = (int)($_POST['vinc_item_id'] ?? 0);

  $informar_categoria = isset($_POST['informar_categoria']) ? 1 : 0;
  $categoria_item_id  = (int)($_POST['categoria_item_id'] ?? 0);

  // ===== Restante (mantive igual) =====
  $marca_id  = ($_POST['marca_id'] ?? '')==='' ? null : (int)$_POST['marca_id'];
  $modelo_id = ($_POST['modelo_id'] ?? '')==='' ? null : (int)$_POST['modelo_id'];
  $tag   = trim($_POST['tag_patrimonial'] ?? '');
  $serie = trim($_POST['numero_serie'] ?? '');

  // Se existir lista de Status Operacional + coluna destino, usamos ela.
  // Caso contrário, mantém o status_id antigo (hardcoded).
  $status_id = (int)($_POST['status_id'] ?? 2);

  $ativo_reg = $hasAtivoFlag ? (int)($_POST['ativo'] ?? 1) : 1;

  // Local (empresa), Depósito e Fornecedor
  $local_id     = ($_POST['local_id'] ?? '')==='' ? null : (int)$_POST['local_id'];
  $deposito_id  = ($hasDepositoCol && ($_POST['deposito_id'] ?? '')!=='') ? (int)$_POST['deposito_id'] : null;
  $rack         = trim($_POST['rack'] ?? '');
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

  // ===== Validações mínimas (sem travar seu fluxo) =====
  if ($nome==='') $err = 'Informe o nome do ativo.';
  if (!$local_id) $err = $err ?: 'Selecione o local.';
  // Categoria agora é opcional (só se marcar Informar categoria)
  if ($informar_categoria && $col_cat_item && $categoria_item_id<=0) {
    $err = $err ?: 'Selecione a categoria.';
  }

  // Status Operacional:
  // - se temos lista + coluna, exige status_item_id
  // - senão, exige status_id (antigo)
  if ($col_status_item && $listIdStatus) {
    if ($status_item_id<=0) $err = $err ?: 'Informe o status operacional.';
  } else {
    if (!$status_id) $err = $err ?: 'Informe o status operacional.';
  }

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
      // ==========================================================
      // Monta SQL dinamicamente (mantendo compatibilidade)
      // ==========================================================
      $colsSet = [];
      $types = "";
      $args = [];

      // campos base
      $colsSet[] = "nome=?";
      $types .= "s"; $args[] = $nome;

      $colsSet[] = "marca_id=?";
      $types .= "i"; $args[] = ($marca_id!==null ? $marca_id : null);

      $colsSet[] = "modelo_id=?";
      $types .= "i"; $args[] = ($modelo_id!==null ? $modelo_id : null);

      $colsSet[] = "tag_patrimonial=?";
      $types .= "s"; $args[] = $tag;

      $colsSet[] = "numero_serie=?";
      $types .= "s"; $args[] = $serie;

      $colsSet[] = "local_id=?";
      $types .= "i"; $args[] = ($local_id!==null ? $local_id : null);

      if ($hasDepositoCol) {
        $colsSet[] = "deposito_id=?";
        $types .= "i"; $args[] = ($deposito_id!==null ? $deposito_id : null);
      }

      $colsSet[] = "fornecedor_id=?";
      $types .= "i"; $args[] = ($fornecedor_id!==null ? $fornecedor_id : null);

      if ($hasNF) { $colsSet[]="nf_numero=?"; $types.="s"; $args[]=$nf_numero; }
      $colsSet[]="data_compra=?"; $types.="s"; $args[]=$data_compra;
      if ($hasGarantiaAte){ $colsSet[]="garantia_ate=?"; $types.="s"; $args[]=$garantia_ate; }
      if ($hasCusto){ $colsSet[]="custo_aquisicao=?"; $types.="s"; $args[]=$custo; }
      if ($hasDeprec){ $colsSet[]="depreciacao_meses=?"; $types.="i"; $args[]=$deprec; }
      if ($hasCentroCusto){ $colsSet[]="centro_custo=?"; $types.="s"; $args[]=$centro_custo; }

      $colsSet[]="observacoes=?"; $types.="s"; $args[]=$observacoes;

      if ($hasAtivoFlag){ $colsSet[]="ativo=?"; $types.="i"; $args[]=$ativo_reg; }

      if ($hasManutUnid){ $colsSet[]="manut_periodo_unid=?"; $types.="s"; $args[]=$man_unid; }
      if ($hasManutQtd) { $colsSet[]="manut_periodo_qtd=?";  $types.="i"; $args[]=$man_qtd; }
      if ($hasManutUlt) { $colsSet[]="manut_ultimo=?";       $types.="s"; $args[]=$man_ult; }
      if ($hasManutAlertar){ $colsSet[]="manut_alertar=?";   $types.="i"; $args[]=$man_alertar; }

      // ===== LISTAS: salva IDs se colunas existirem =====
      if ($col_setor_item) {
        $colsSet[] = "`$col_setor_item`=?";
        $types.="i"; $args[] = ($setor_item_id>0 ? $setor_item_id : null);
      }
      if ($col_vinc_item) {
        $colsSet[] = "`$col_vinc_item`=?";
        $types.="i"; $args[] = ($vinc_item_id>0 ? $vinc_item_id : null);
      }
      if ($col_cat_item) {
        // só grava se checkbox marcou; senão mantém como NULL
        $colsSet[] = "`$col_cat_item`=?";
        $types.="i"; $args[] = ($informar_categoria && $categoria_item_id>0) ? $categoria_item_id : null;
      }

      if ($col_status_item && $listIdStatus) {
        $colsSet[] = "`$col_status_item`=?";
        $types.="i"; $args[] = ($status_item_id>0 ? $status_item_id : null);
      } else {
        // fallback antigo
        $colsSet[] = "status_id=?";
        $types.="i"; $args[] = $status_id;
      }

      if ($id>0) {
        $sql = "UPDATE moz_ativo SET ".implode(", ", $colsSet)." WHERE id=?";
        $types .= "i"; $args[] = $id;
        $st=$dbc->prepare($sql);
        $st->bind_param($types, ...$args);
        $st->execute();
        $st->close();
      } else {
        // INSERT: reaproveita colsSet, mas vira colunas/values
        $cols = [];
        $vals = [];
        foreach($colsSet as $piece){
          // "campo=?" -> campo
          $cols[] = trim(explode('=', $piece, 2)[0]);
          $vals[] = "?";
        }
        $sql = "INSERT INTO moz_ativo (".implode(",", $cols).") VALUES (".implode(",", $vals).")";
        $st=$dbc->prepare($sql);
        $st->bind_param($types, ...$args);
        $st->execute();
        $id = (int)$st->insert_id;
        $st->close();
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

      // relações (mantive intacto)
      if ($hasRelTbl) {
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
            throw new Exception('Os seguintes ativos já estão atrelados a outro item: #'.implode(', #',$conflitos).'. Remova-os da seleção.');
          }
        }

        if (!empty($_POST['atrelados']) && is_array($_POST['atrelados'])) {
          foreach ($_POST['atrelados'] as $aid) {
            $aid = (int)$aid; if ($aid<=0) continue;
            $_POST['rel_tipo'][]      = 'depends';
            $_POST['rel_destino'][]   = $aid;
            $_POST['rel_descricao'][] = '';
          }
        }

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

/* ================== GET: combos e edição ================== */
$marcas=[]; $rs=$dbc->query("SELECT id,nome FROM moz_marca ".(has_col($dbc,'moz_marca','ativo')?'WHERE ativo=1':'')." ORDER BY nome"); if($rs) while($r=$rs->fetch_assoc()) $marcas[]=$r;
$modelos=[];
$locais = $empresas; // Local = empresas

// fornecedores (mantive, só garantindo que exista o array)
$fornecedores = [];
if ($hasFornecedorTbl) {
  $q = $dbc->query("SELECT id, nome FROM moz_fornecedor ".(has_col($dbc,'moz_fornecedor','ativo')?'WHERE ativo=1':'')." ORDER BY nome");
  if ($q) while($r=$q->fetch_assoc()) $fornecedores[]=$r;
}

$rec = [
  'nome'=>'',
  'marca_id'=>'','modelo_id'=>'',
  'tag_patrimonial'=>'','numero_serie'=>'',
  'status_id'=>2,'ativo'=>1,
  'local_id'=>'','deposito_id'=>'','rack'=>'','responsavel'=>'',
  'fornecedor_id'=>'','nf_numero'=>'','data_compra'=>'','garantia_ate'=>'','custo_aquisicao'=>'',
  'depreciacao_meses'=>'','centro_custo'=>'','observacoes'=>'',
  'manut_unid'=>'','manut_qtd'=>0,'manut_ultimo'=>'','manut_alertar'=>1,

  // listas
  'setor_item_id'=>0,
  'status_item_id'=>0,
  'vinc_item_id'=>0,
  'categoria_item_id'=>0,
  'informar_categoria'=>0,

  // nome (lista/digitar)
  'nome_digit'=>0,
  'nome_item_id'=>0,
  'nome_txt'=>'',
];

$cf_defs=[]; $cf_vals=[]; $rels=[];

// tenta ler colunas de lista do registro
if ($id>0 && $_SERVER['REQUEST_METHOD']!=='POST'){
  $cols = "id,nome,marca_id,modelo_id,tag_patrimonial,numero_serie,local_id"
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
        . ($hasManutAlertar ? ",manut_alertar"   : "")
        . ($col_setor_item ? ",`$col_setor_item` AS setor_item_id" : "")
        . ($col_status_item ? ",`$col_status_item` AS status_item_id" : "")
        . ($col_vinc_item ? ",`$col_vinc_item` AS vinc_item_id" : "")
        . ($col_cat_item ? ",`$col_cat_item` AS categoria_item_id" : "")
        . (has_col($dbc,'moz_ativo','status_id') ? ",status_id" : "");

  $st=$dbc->prepare("SELECT $cols FROM moz_ativo WHERE id=?");
  $st->bind_param('i',$id); $st->execute();
  $row=$st->get_result()->fetch_assoc(); $st->close();
  if($row){
    $rec['nome'] = $row['nome'];
    $rec['nome_txt'] = $row['nome']; // default mostra texto também
    $rec['marca_id'] = $row['marca_id'];
    $rec['modelo_id']= $row['modelo_id'];
    $rec['tag_patrimonial'] = $row['tag_patrimonial'];
    $rec['numero_serie'] = $row['numero_serie'];
    $rec['local_id'] = $row['local_id'];
    if($hasDepositoCol) $rec['deposito_id']=$row['deposito_id'];
    $rec['fornecedor_id'] = $row['fornecedor_id'];
    if($hasNF)          $rec['nf_numero'] = $row['nf_numero'];
    $rec['data_compra'] = $row['data_compra'];
    if($hasGarantiaAte) $rec['garantia_ate'] = $row['garantia_ate'];
    if($hasCusto)       $rec['custo_aquisicao'] = $row['custo_aquisicao'];
    if($hasDeprec)      $rec['depreciacao_meses'] = $row['depreciacao_meses'];
    if($hasCentroCusto) $rec['centro_custo'] = $row['centro_custo'];
    $rec['observacoes'] = $row['observacoes'];
    if($hasAtivoFlag)   $rec['ativo'] = (int)$row['ativo'];
    if($hasManutUnid)   $rec['manut_unid'] = $row['manut_periodo_unid'];
    if($hasManutQtd)    $rec['manut_qtd']  = (int)$row['manut_periodo_qtd'];
    if($hasManutUlt)    $rec['manut_ultimo']= $row['manut_ultimo'];
    if($hasManutAlertar)$rec['manut_alertar']= (int)$row['manut_alertar'];

    if(isset($row['status_id'])) $rec['status_id'] = (int)$row['status_id'];
    if(isset($row['setor_item_id'])) $rec['setor_item_id'] = (int)$row['setor_item_id'];
    if(isset($row['status_item_id'])) $rec['status_item_id'] = (int)$row['status_item_id'];
    if(isset($row['vinc_item_id'])) $rec['vinc_item_id'] = (int)$row['vinc_item_id'];
    if(isset($row['categoria_item_id'])) {
      $rec['categoria_item_id'] = (int)$row['categoria_item_id'];
      $rec['informar_categoria'] = $rec['categoria_item_id'] ? 1 : 0;
    }

    if($rec['marca_id'] && $hasModeloTbl){
      $m=$dbc->prepare("SELECT id,nome FROM moz_modelo WHERE marca_id=? ORDER BY nome");
      $m->bind_param('i', $rec['marca_id']); $m->execute();
      $rr=$m->get_result(); while($x=$rr->fetch_assoc()) $modelos[]=$x; $m->close();
    }
  }

  if ($hasCFDef && has_col($dbc,'moz_ativo','cat_id')) {
    // Se você ainda usa cat_id antigo para CF, mantém.
    // (Se quiser migrar CF para lista, fazemos em uma próxima fase)
    $cat_id_old = 0;
    $qcat = $dbc->query("SELECT cat_id FROM moz_ativo WHERE id=".(int)$id." LIMIT 1");
    if ($qcat && ($rc=$qcat->fetch_assoc())) $cat_id_old = (int)($rc['cat_id'] ?? 0);

    if ($cat_id_old>0) {
      $sql = "SELECT $cfCols FROM moz_cf_def WHERE cat_id=? AND $cfWhere ORDER BY $cfOrder";
      $q=$dbc->prepare($sql);
      $q->bind_param('i',$cat_id_old); $q->execute();
      $r=$q->get_result(); while($row=$r->fetch_assoc()) $cf_defs[]=$row; $q->close();

      if ($hasCFVal){
        $r=$dbc->query("SELECT def_id,valor FROM moz_cf_val WHERE ativo_id=".(int)$id);
        while($x=$r->fetch_assoc()) $cf_vals[(int)$x['def_id']]=$x['valor'];
      }
    }
  }

  if ($hasRelTbl){
    $r=$dbc->query("SELECT destino_id,tipo,descricao FROM moz_ativo_relacao WHERE origem_id=".(int)$id." ORDER BY id");
    while($x=$r->fetch_assoc()) $rels[]=$x;
  }
}

/* Pré-carrega depósitos se já houver local/registro */
$depositos = ($hasDepositoTbl) ? carregarDepositos($dbc, (int)($rec['local_id'] ?: 0) ?: null, $depositoTable) : [];

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

        <!-- NOME (Lista + Digitar) -->
        <div>
          <label>Nome *</label>

          <div style="display:flex; gap:10px; align-items:center; margin-bottom:6px;">
            <label style="display:flex; gap:6px; align-items:center; font-weight:normal; margin:0;">
              <input type="checkbox" id="nome_digit" name="nome_digit" value="1" <?= !empty($rec['nome_digit'])?'checked':'' ?>>
              Digitar
            </label>
            <span class="hint">Se não marcar, você escolhe na lista “Nome do Ativo”.</span>
          </div>

          <select name="nome_item_id" id="nome_item_id" <?= ($listIdNome && $itNome) ? '' : 'disabled' ?>>
            <option value="">— selecione —</option>
            <?php foreach($itNome as $it): ?>
              <option value="<?= (int)$it['id'] ?>" <?= ((int)$rec['nome_item_id']===(int)$it['id'])?'selected':'' ?>>
                <?= h($it['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <input type="text" name="nome_txt" id="nome_txt" value="<?= h($rec['nome_txt'] ?: $rec['nome']) ?>" placeholder="Ex.: Notebook João Silva" style="display:none;" />

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

        <div>
          <label>Modelo</label>
          <select name="modelo_id" id="modelo_id">
            <option value="">—</option>
            <?php foreach($modelos as $mo): ?>
              <option value="<?= (int)$mo['id'] ?>" <?= (string)$rec['modelo_id']===(string)$mo['id']?'selected':'' ?>><?= h($mo['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="grid cols-4">
        <div><label>Tag / Patrimônio</label><input type="text" name="tag_patrimonial" placeholder="TAG" value="<?= h($rec['tag_patrimonial']) ?>"/></div>
        <div><label>Nº de série</label><input type="text" name="numero_serie" placeholder="Serial" value="<?= h($rec['numero_serie']) ?>"/></div>

        <!-- SETOR -->
        <div>
          <label>Setor</label>
          <select name="setor_item_id" <?= ($col_setor_item && $listIdSetor && $itSetor) ? '' : 'disabled' ?>>
            <option value="">—</option>
            <?php foreach($itSetor as $it): ?>
              <option value="<?= (int)$it['id'] ?>" <?= ((int)$rec['setor_item_id']===(int)$it['id'])?'selected':'' ?>>
                <?= h($it['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if(!($col_setor_item && $listIdSetor && $itSetor)): ?>
            <span class="hint">Setor desabilitado: coluna em moz_ativo ou lista (slug: <?= h($SLUG_SETOR) ?>) não encontrada.</span>
          <?php endif; ?>
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

      <div class="grid cols-4">
        <!-- Depósito -->
        <div>
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

        <div><label>Rack</label><input type="text" name="rack" value="<?= h($rec['rack']) ?>" placeholder="Opcional"/></div>

        <!-- STATUS OPERACIONAL (Lista) com fallback -->
        <div>
          <label>Status Operacional *</label>

          <?php if ($col_status_item && $listIdStatus && $itStatus): ?>
            <select name="status_item_id" required>
              <option value="">—</option>
              <?php foreach($itStatus as $it): ?>
                <option value="<?= (int)$it['id'] ?>" <?= ((int)$rec['status_item_id']===(int)$it['id'])?'selected':'' ?>>
                  <?= h($it['label']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <select name="status_id" required>
              <?php foreach([1=>'Em operação',2=>'Em estoque',3=>'Emprestado',4=>'Alugado',5=>'Em manutenção',6=>'Baixado'] as $k=>$v): ?>
                <option value="<?= $k ?>" <?= (string)$rec['status_id']===(string)$k?'selected':'' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
            <span class="hint">Status via lista não está ativo (coluna/lista não encontrada). Mantido fallback antigo.</span>
          <?php endif; ?>
        </div>

        <!-- VÍNCULOS (Tipo/Vínculos) — aqui é o ponto que estava “errado” -->
        <div>
          <label>Vínculos</label>
          <select name="vinc_item_id" <?= ($col_vinc_item && $listIdVinc && $itVinc) ? '' : 'disabled' ?>>
            <option value="">—</option>
            <?php foreach($itVinc as $it): ?>
              <option value="<?= (int)$it['id'] ?>" <?= ((int)$rec['vinc_item_id']===(int)$it['id'])?'selected':'' ?>>
                <?= h($it['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if(!($col_vinc_item && $listIdVinc && $itVinc)): ?>
            <span class="hint">Vínculos desabilitado: coluna em moz_ativo ou lista (slug: <?= h($SLUG_TIPO_VINC) ?>) não encontrada.</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- CATEGORIA (opcional por checkbox) -->
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
          <select name="categoria_item_id" <?= ($col_cat_item && $listIdCat && $itCat) ? '' : 'disabled' ?>>
            <option value="">—</option>
            <?php foreach($itCat as $it): ?>
              <option value="<?= (int)$it['id'] ?>" <?= ((int)$rec['categoria_item_id']===(int)$it['id'])?'selected':'' ?>>
                <?= h($it['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if(!($col_cat_item && $listIdCat && $itCat)): ?>
            <span class="hint">Categoria desabilitada: coluna em moz_ativo ou lista (slug: <?= h($SLUG_CAT_ATIVOS) ?>) não encontrada.</span>
          <?php endif; ?>
        </div>

        <div><label>Responsável (usuário)</label><input type="text" name="responsavel" value="<?= h($rec['responsavel']) ?>" placeholder="login/nome"/></div>
        <div></div>
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
        <div><label>Data de compra</label><input type="date" name="data_compra" value="<?= h($rec['data_compra']) ?>"/></div>
        <div><label>Garantia até</label><input type="date" name="garantia_ate" value="<?= h($rec['garantia_ate']) ?>" <?= $hasGarantiaAte?'':'disabled' ?>/></div>
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
      <p class="subtitle">Atributos (campos customizados por categoria antiga)</p>
      <div id="attr-list" class="stack">
        <?php if ($hasCFDef && !empty($cf_defs)): ?>
          <?php foreach($cf_defs as $def): $val = $cf_vals[$def['id']] ?? ''; ?>
            <div class="grid cols-2" style="align-items:end">
              <div>
                <label><?= h($def['nome']) ?><?= !empty($def['required'])?' *':'' ?></label>
                <input type="text" name="cf[<?= (int)$def['id'] ?>]" value="<?= h($val) ?>" <?= !empty($def['required'])?'required':'' ?> />
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="hint">Sem campos customizados carregados (depende da categoria antiga do cadastro).</div>
        <?php endif; ?>
      </div>

      <div class="divider"></div>
      <p class="subtitle">Relacionamentos (rede/energia/depende)</p>
      <div id="attach-list" class="stack">
        <?php if ($rels): foreach($rels as $r): ?>
          <div class="grid cols-4" style="align-items:end">
            <div><label>Tipo</label>
              <select name="rel_tipo[]">
                <option <?= $r['tipo']==='network'?'selected':'' ?>>network</option>
                <option <?= $r['tipo']==='power'?'selected':'' ?>>power</option>
                <option <?= $r['tipo']==='depends'?'selected':'' ?>>depends</option>
              </select>
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
      <button type="button" class="btn small" id="add-attach">+ Adicionar relacionamento</button>

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
          <div class="hint">Nenhum atrelado adicionado.</div>
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

    <div class="card">
      <p class="hint">
        Observação técnica: este formulário detecta automaticamente se existem colunas/tabelas das listas.
        Se algum select aparecer desabilitado, é porque falta a coluna correspondente em <code>moz_ativo</code> ou o slug da lista não bateu.
      </p>
    </div>
  </div></session>
</div></div>

<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
<script>
  // ===== Nome: alterna Lista <-> Digitação =====
  const cbNomeDigit = document.getElementById('nome_digit');
  const selNomeItem = document.getElementById('nome_item_id');
  const inpNomeTxt  = document.getElementById('nome_txt');

  function syncNomeUI() {
    const dig = cbNomeDigit && cbNomeDigit.checked;
    if (dig) {
      if (selNomeItem) selNomeItem.style.display = 'none';
      if (inpNomeTxt)  inpNomeTxt.style.display  = '';
    } else {
      if (selNomeItem) selNomeItem.style.display = '';
      if (inpNomeTxt)  inpNomeTxt.style.display  = 'none';
    }

    // Se a lista não existe, força digitar
    if (selNomeItem && selNomeItem.disabled) {
      if (cbNomeDigit) cbNomeDigit.checked = true;
      if (selNomeItem) selNomeItem.style.display='none';
      if (inpNomeTxt)  inpNomeTxt.style.display='';
    }
  }
  cbNomeDigit?.addEventListener('change', syncNomeUI);
  syncNomeUI();

  // ===== Categoria: checkbox mostra/esconde =====
  const cbCat = document.getElementById('informar_categoria');
  const wrapCat = document.getElementById('wrap_categoria');
  function syncCatUI(){
    const on = cbCat && cbCat.checked;
    if (wrapCat) wrapCat.style.display = on ? '' : 'none';
  }
  cbCat?.addEventListener('change', syncCatUI);
  syncCatUI();

  // ===== modelos por marca =====
  const marcaSel=document.getElementById('marca_id');
  const modeloSel=document.getElementById('modelo_id');
  marcaSel?.addEventListener('change', async ()=>{
    if(!modeloSel) return;
    modeloSel.innerHTML='<option value="">—</option>';
    if(!marcaSel.value) return;
    const r=await fetch('?ajax=modelos&marca_id='+encodeURIComponent(marcaSel.value));
    const j=await r.json();
    j.forEach(m=>{
      const o=document.createElement('option'); o.value=m.id; o.textContent=m.nome; modeloSel.appendChild(o);
    });
  });

  // ===== relacionamentos add =====
  document.getElementById('add-attach')?.addEventListener('click', ()=>{
    const wrap=document.getElementById('attach-list');
    const el=document.createElement('div'); el.className='grid cols-4'; el.style.alignItems='end';
    el.innerHTML=`<div><label>Tipo</label><select name="rel_tipo[]"><option>network</option><option>power</option><option>depends</option></select></div>
                  <div><label>Ativo destino (ID)</label><input type="number" name="rel_destino[]" placeholder="ID do ativo"/></div>
                  <div><label>Descrição</label><input type="text" name="rel_descricao[]" placeholder="porta/observação"/></div>
                  <div class="row"><button type="button" class="btn small danger" onclick="this.closest('.grid').remove()">Remover</button></div>`;
    wrap.appendChild(el);
  });

  // ===== manutenção: cálculo visual da próxima data =====
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
  per?.addEventListener('change', calcProx);
  ult?.addEventListener('change', calcProx);
  calcProx();

  // ===== Depósitos: carrega quando muda Local =====
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
  selLocal?.addEventListener('change', ()=>carregarDepositosPorEmpresa(selLocal.value));

  // ===== Autocomplete de ativos (atrelados) =====
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

<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
