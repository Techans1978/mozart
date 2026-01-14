<?php
// public/modules/gestao_ativos/manutencoes-form.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
require_once ROOT_PATH.'/modules/gestao_ativos/manutencao_lib.php';
require_once ROOT_PATH.'/system/updown/lib_storage.php'; // storage fora do public_html

if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

if (empty($_SESSION['csrf_manut'])) $_SESSION['csrf_manut'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_manut'];

$id = (int)($_GET['id'] ?? 0);
$isExtra = (int)($_GET['extra'] ?? 0) === 1;

// >>> contexto vindo do ativo
$fixedAtivoId = (int)($_GET['ativo_id'] ?? 0);

$err = '';
$rec = [
  'id'=>0,'ativo_id'=>'','ativo_nome'=>'','local_id'=>'','setor_txt'=>'',
  'numero_chamado'=>'','data_chamado'=>'',
  'tipo'=>'PREVENTIVA','data_manutencao'=>'','fornecedor'=>'','valor'=>'','aprovado'=>'',
  'descricao_servico'=>'',
  'os_path'=>'','nota_servico_path'=>'',
  'origem'=> $isExtra ? 'EXTRA_MANUAL' : 'AUTO_PREVENTIVA',
  'data_prevista'=>'',
  'status'=>'PENDENTE'
];

// helper: carrega snapshot do ativo (nome/local/setor_txt)
function carregar_snapshot_ativo(mysqli $dbc, int $ativo_id, &$ativo_nome, &$local_id, &$setor_txt, &$err){
  $ativo_nome=''; $local_id=null; $setor_txt='';
  $cols = "id,nome,local_id
           ".(has_col($dbc,'moz_ativo','sector_item_id')?', sector_item_id':'')."
           ".(has_col($dbc,'moz_ativo','sector_id')?', sector_id':'')."
           ".(has_col($dbc,'moz_ativo','sector_sub_id')?', sector_sub_id':'')."
          ";
  $st=$dbc->prepare("SELECT $cols FROM moz_ativo WHERE id=? LIMIT 1");
  $st->bind_param('i',$ativo_id);
  $st->execute();
  $a=$st->get_result()->fetch_assoc();
  $st->close();

  if(!$a){ $err='Ativo não encontrado.'; return; }

  $ativo_nome = (string)$a['nome'];
  $local_id = ($a['local_id']!==null ? (int)$a['local_id'] : null);
  $setor_txt = resolve_setor_texto($dbc, $a);
}

// ===== lista ativos só se extra e NÃO tiver ativo fixo e for NOVO =====
$ativos = [];
if ($isExtra && $id<=0 && $fixedAtivoId<=0) {
  $r = $dbc->query("SELECT id,nome,local_id,manut_proxima
                      ".(has_col($dbc,'moz_ativo','sector_item_id')?', sector_item_id':'')."
                      ".(has_col($dbc,'moz_ativo','sector_id')?', sector_id':'')."
                      ".(has_col($dbc,'moz_ativo','sector_sub_id')?', sector_sub_id':'')."
                    FROM moz_ativo WHERE ativo=1 ORDER BY nome ASC LIMIT 2000");
  if($r) while($a=$r->fetch_assoc()){
    $label = '#'.$a['id'].' — '.$a['nome'];
    $ativos[] = ['id'=>$a['id'],'label'=>$label,'row'=>$a];
  }
}

// ===== GET: carrega registro (edição) =====
if($id>0 && $_SERVER['REQUEST_METHOD']!=='POST'){
  $st=$dbc->prepare("SELECT * FROM moz_ativo_manut WHERE id=?");
  $st->bind_param('i',$id);
  $st->execute();
  $row=$st->get_result()->fetch_assoc();
  $st->close();

  if($row) {
    $rec = array_merge($rec, $row);
    // se veio ativo_id na URL, mantém contexto mas não muda o registro
    if ($fixedAtivoId<=0) $fixedAtivoId = (int)($rec['ativo_id'] ?? 0);
  } else {
    $err='Registro não encontrado.';
  }
}

// ===== se abriu NOVO com ativo_id fixo, já preenche o ativo_id no rec =====
if($id<=0 && $fixedAtivoId>0 && $_SERVER['REQUEST_METHOD']!=='POST'){
  $rec['ativo_id'] = $fixedAtivoId;
  // se for extra manual, deixa origem EXTRA_MANUAL; caso contrário, ok
}

// ===== POST: salvar =====
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!hash_equals($csrf, $_POST['csrf'] ?? '')) die('CSRF inválido.');

  $id = (int)($_POST['id'] ?? 0);

  // se existe ativo fixo, ignora qualquer coisa do form
  $postAtivoId = (int)($_POST['ativo_id'] ?? 0);
  $ativo_id = ($fixedAtivoId>0) ? $fixedAtivoId : $postAtivoId;

  $numero_chamado = trim($_POST['numero_chamado'] ?? '');
  $data_chamado = ($_POST['data_chamado'] ?? '') ?: null;

  $tipo = $_POST['tipo'] ?? 'PREVENTIVA';
  if (!in_array($tipo, ['CONSERTO','PREVENTIVA','TROCA_INATIVA'], true)) $tipo='PREVENTIVA';

  $data_manutencao = ($_POST['data_manutencao'] ?? '') ?: null;
  $fornecedor = trim($_POST['fornecedor'] ?? '');
  $valor = ($_POST['valor'] ?? '') !== '' ? str_replace(',','.',$_POST['valor']) : null;
  $aprovado = trim($_POST['aprovado'] ?? '');
  $descricao_servico = trim($_POST['descricao_servico'] ?? '');

  $status = $_POST['status'] ?? 'PENDENTE';
  if (!in_array($status, ['PENDENTE','CONCLUIDA','CANCELADA'], true)) $status='PENDENTE';

  $origem = $_POST['origem'] ?? 'AUTO_PREVENTIVA';
  if (!in_array($origem, ['AUTO_PREVENTIVA','EXTRA_MANUAL'], true)) $origem='AUTO_PREVENTIVA';

  $data_prevista = ($_POST['data_prevista'] ?? '') ?: null;

  if ($ativo_id <= 0) $err = 'Informe o equipamento (ativo).';

  // snapshot do ativo
  $ativo_nome=''; $local_id=null; $setor_txt='';
  if(!$err){
    carregar_snapshot_ativo($dbc, $ativo_id, $ativo_nome, $local_id, $setor_txt, $err);
  }

// uploads (NOVO PADRÃO: fora do public_html em MOZART_DATA_PATH)
$os_path   = $_POST['os_path_old'] ?? '';
$nota_path = $_POST['nota_path_old'] ?? '';

/** cria um "lote" único por salvamento */
function manut_make_lote_dir(int $ativo_id): array {
  $token = date('Ymd_His') . '_' . bin2hex(random_bytes(3)); // ex: 20260114_153022_7f3a9c
  $relDir = "manutencoes/$ativo_id/$token";
  $absDir = rtrim(MOZART_DATA_PATH,'/').'/'.$relDir;

  if (!is_dir($absDir)) {
    @mkdir($absDir, 0750, true);
  }
  if (!is_dir($absDir) || !is_writable($absDir)) {
    throw new Exception('Pasta de storage indisponível.');
  }
  return [$absDir, $relDir];
}

if(!$err){
  try{
    [$absDir, $relDir] = manut_make_lote_dir($ativo_id);

    // OS
    if (!empty($_FILES['os_file']) && $_FILES['os_file']['error'] !== UPLOAD_ERR_NO_FILE) {
      if ($_FILES['os_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['os_file']['name'], PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]+/i','', $ext);
        $fname = 'os'.($ext?('.'.$ext):'');
        $dest = $absDir.'/'.$fname;

        if (!move_uploaded_file($_FILES['os_file']['tmp_name'], $dest)) {
          throw new Exception('Falha ao salvar OS.');
        }
        // salva caminho RELATIVO ao data_files
        $os_path = $relDir.'/'.$fname;
      }
    }

    // Nota
    if (!empty($_FILES['nota_file']) && $_FILES['nota_file']['error'] !== UPLOAD_ERR_NO_FILE) {
      if ($_FILES['nota_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['nota_file']['name'], PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]+/i','', $ext);
        $fname = 'nota'.($ext?('.'.$ext):'');
        $dest = $absDir.'/'.$fname;

        if (!move_uploaded_file($_FILES['nota_file']['tmp_name'], $dest)) {
          throw new Exception('Falha ao salvar Nota.');
        }
        $nota_path = $relDir.'/'.$fname;
      }
    }

  } catch(Exception $e){
    $err = $e->getMessage();
  }
}

  if(!$err){
    if($id>0){
      $st=$dbc->prepare("UPDATE moz_ativo_manut
                            SET ativo_id=?, ativo_nome=?, local_id=?, setor_txt=?,
                                numero_chamado=?, data_chamado=?,
                                tipo=?, data_manutencao=?, fornecedor=?, valor=?, aprovado=?,
                                descricao_servico=?,
                                os_path=?, nota_servico_path=?,
                                origem=?, data_prevista=?, status=?
                          WHERE id=?");
      $st->bind_param(
        "isisssssssdssssssi",
        $ativo_id,$ativo_nome,$local_id,$setor_txt,
        $numero_chamado,$data_chamado,
        $tipo,$data_manutencao,$fornecedor,$valor,$aprovado,
        $descricao_servico,
        $os_path,$nota_path,
        $origem,$data_prevista,$status,
        $id
      );
      $ok = $st->execute();
      $st->close();
      if(!$ok) $err='Erro ao atualizar.';
    } else {
      $st=$dbc->prepare("INSERT INTO moz_ativo_manut
        (ativo_id,ativo_nome,local_id,setor_txt,numero_chamado,data_chamado,tipo,data_manutencao,fornecedor,valor,aprovado,descricao_servico,os_path,nota_servico_path,origem,data_prevista,status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
      $st->bind_param(
        "isisssssssdssssss",
        $ativo_id,$ativo_nome,$local_id,$setor_txt,
        $numero_chamado,$data_chamado,
        $tipo,$data_manutencao,$fornecedor,$valor,$aprovado,
        $descricao_servico,
        $os_path,$nota_path,
        $origem,$data_prevista,$status
      );
      $ok = $st->execute();
      $st->close();
      if(!$ok) $err='Erro ao inserir.';
    }
  }

  // se concluir preventiva, atualiza manutenção do ativo
  if(!$err && $status==='CONCLUIDA' && $tipo==='PREVENTIVA' && $data_manutencao){
    $hasQ = has_col($dbc,'moz_ativo','manut_periodo_qtd');
    $hasU = has_col($dbc,'moz_ativo','manut_periodo_unid');
    if($hasQ && $hasU && has_col($dbc,'moz_ativo','manut_ultimo') && has_col($dbc,'moz_ativo','manut_proxima')){
      $st=$dbc->prepare("SELECT manut_periodo_qtd, manut_periodo_unid FROM moz_ativo WHERE id=?");
      $st->bind_param('i',$ativo_id);
      $st->execute();
      $p=$st->get_result()->fetch_assoc();
      $st->close();

      $qtd = (int)($p['manut_periodo_qtd'] ?? 0);
      $un  = (string)($p['manut_periodo_unid'] ?? 'meses');

      $prox = null;
      try{
        $base = new DateTimeImmutable($data_manutencao);
        if($qtd>0){
          $prox = ($un==='dias')
            ? $base->add(new DateInterval('P'.$qtd.'D'))->format('Y-m-d')
            : $base->add(new DateInterval('P'.$qtd.'M'))->format('Y-m-d');
        }
      }catch(Exception $e){ $prox=null; }

      $st=$dbc->prepare("UPDATE moz_ativo SET manut_ultimo=?, manut_proxima=? WHERE id=?");
      $st->bind_param('ssi',$data_manutencao,$prox,$ativo_id);
      $st->execute();
      $st->close();
    }
  }

  if(!$err){
    $_SESSION['flash_ok'] = 'Manutenção salva com sucesso.';
    // volta para a listagem no contexto do ativo (se houver)
    $back = 'manutencoes-listar.php';
    if($fixedAtivoId>0) $back .= '?ativo_id='.$fixedAtivoId;
    header('Location: '.$back);
    exit;
  }
}

// ===== UI =====
include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.1">

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

  <section class="bpm"><div class="container">

    <header class="toolbar">
      <h1><?= $id>0 ? 'Editar manutenção' : 'Nova manutenção' ?></h1>
      <div class="actions">
        <a class="btn" href="manutencoes-listar.php<?= $fixedAtivoId>0 ? ('?ativo_id='.$fixedAtivoId) : '' ?>">Voltar</a>
      </div>
    </header>

    <?php if($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>

    <form class="card" method="post" enctype="multipart/form-data" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <input type="hidden" name="origem" value="<?= h($rec['origem']) ?>">
      <input type="hidden" name="os_path_old" value="<?= h($rec['os_path']) ?>">
      <input type="hidden" name="nota_path_old" value="<?= h($rec['nota_servico_path']) ?>">

      <p class="subtitle">Equipamento</p>

      <div class="grid cols-4">
        <div>
          <label>ID Equipamento *</label>

          <?php if($fixedAtivoId>0): ?>
            <input type="number" value="<?= (int)$fixedAtivoId ?>" readonly>
            <input type="hidden" name="ativo_id" value="<?= (int)$fixedAtivoId ?>">
            <span class="hint">Equipamento travado (veio do ativo).</span>

          <?php elseif($isExtra && $id<=0): ?>
            <select name="ativo_id" required>
              <option value="">— selecione —</option>
              <?php foreach($ativos as $a): ?>
                <option value="<?= (int)$a['id'] ?>" <?= ((int)$rec['ativo_id']===(int)$a['id'])?'selected':'' ?>>
                  <?= h($a['label']) ?>
                </option>
              <?php endforeach; ?>
            </select>

          <?php else: ?>
            <input type="number" name="ativo_id" value="<?= h($rec['ativo_id']) ?>" required <?= $id>0 ? 'readonly':'' ?>>
            <span class="hint">Nome/Local/Setor carregam automaticamente ao salvar (snapshot no histórico).</span>
          <?php endif; ?>
        </div>

        <div><label>Data prevista (preventiva)</label>
          <input type="date" name="data_prevista" value="<?= h($rec['data_prevista']) ?>" <?= ($rec['origem']==='AUTO_PREVENTIVA'?'readonly':'') ?>>
        </div>

        <div>
          <label>Status</label>
          <select name="status">
            <?php foreach(['PENDENTE','CONCLUIDA','CANCELADA'] as $s): ?>
              <option value="<?= $s ?>" <?= ($rec['status']===$s?'selected':'') ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Tipo</label>
          <select name="tipo">
            <?php foreach(['CONSERTO','PREVENTIVA','TROCA_INATIVA'] as $t): ?>
              <option value="<?= $t ?>" <?= ($rec['tipo']===$t?'selected':'') ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <p class="subtitle">Chamado</p>
      <div class="grid cols-3">
        <div><label>Número chamado</label><input type="text" name="numero_chamado" value="<?= h($rec['numero_chamado']) ?>"></div>
        <div><label>Data chamado</label><input type="date" name="data_chamado" value="<?= h($rec['data_chamado']) ?>"></div>
        <div><label>Data manutenção</label><input type="date" name="data_manutencao" value="<?= h($rec['data_manutencao']) ?>"></div>
      </div>

      <p class="subtitle">Serviço</p>
      <div class="grid cols-3">
        <div><label>Fornecedor</label><input type="text" name="fornecedor" value="<?= h($rec['fornecedor']) ?>"></div>
        <div><label>Valor</label><input type="number" step="0.01" min="0" name="valor" value="<?= h($rec['valor']) ?>"></div>
        <div><label>Aprovado</label><input type="text" name="aprovado" value="<?= h($rec['aprovado']) ?>"></div>
      </div>

      <div>
        <label>Descrição do serviço</label>
        <textarea name="descricao_servico" rows="4"><?= h($rec['descricao_servico']) ?></textarea>
      </div>

      <p class="subtitle">Anexos</p>
      <div class="grid cols-2">
        <div>
          <label>OS (Prestador)</label>
          <?php if(!empty($rec['os_path'])): ?>
            <div class="hint">
              <a target="_blank" rel="noopener"
                href="<?= h(BASE_URL.'/system/updown/download.php?p='.urlencode($rec['os_path'])) ?>">
                baixar arquivo atual
              </a>
            </div>
          <?php endif; ?>
          <input type="file" name="os_file">
        </div>

        <div>
          <label>Nota de serviço</label>
          <?php if(!empty($rec['nota_servico_path'])): ?>
            <div class="hint">
              <a target="_blank" rel="noopener"
                href="<?= h(BASE_URL.'/system/updown/download.php?p='.urlencode($rec['nota_servico_path'])) ?>">
                baixar arquivo atual
              </a>
            </div>
          <?php endif; ?>
          <input type="file" name="nota_file">
        </div>
      </div>

      <div class="divider"></div>
      <div style="display:flex;justify-content:flex-end;gap:10px">
        <a class="btn" href="manutencoes-listar.php<?= $fixedAtivoId>0 ? ('?ativo_id='.$fixedAtivoId) : '' ?>">Cancelar</a>
        <button class="btn primary">Salvar</button>
      </div>
    </form>

  </div></section>
</div></div>

<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
