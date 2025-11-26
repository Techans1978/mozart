<?php
// public/modules/gestao_ativos/ativos-importar.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

$dbc = $conn ?? null;
if (!$dbc) die('Sem conexão.');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function table_exists(mysqli $db, $t){
  $t = $db->real_escape_string($t);
  $r = $db->query("SHOW TABLES LIKE '{$t}'");
  return $r && $r->num_rows > 0;
}
function has_col(mysqli $db, $t, $c){
  $t = $db->real_escape_string($t);
  $c = $db->real_escape_string($c);
  $r = $db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
  return $r && $r->num_rows > 0;
}
function sniff_delimiter($path){
  $sample = file_get_contents($path, false, null, 0, 4096);
  $sc = substr_count($sample, ';');
  $cc = substr_count($sample, ',');
  return ($sc >= $cc) ? ';' : ',';
}

// ===== Status =====
$statusesMap = [
  'EM_OPERACAO'   => 1,
  'EM_ESTOQUE'    => 2,
  'EMPRESTADO'    => 3,
  'ALUGADO'       => 4,
  'EM_MANUTENCAO' => 5,
  'BAIXADO'       => 6,
];

$statusLabels = [
  1 => 'Em operação',
  2 => 'Em estoque',
  3 => 'Emprestado',
  4 => 'Alugado',
  5 => 'Em manutenção',
  6 => 'Baixado',
];

// ===== Combos: Categorias, Marcas, Modelos, Locais (empresas), Fornecedores =====

// Categorias
$categorias = [];
if (table_exists($dbc,'moz_cat_ativo')) {
  $sqlCat = "SELECT id, nome FROM moz_cat_ativo ".
            (has_col($dbc,'moz_cat_ativo','ativo') ? "WHERE ativo=1 " : "WHERE 1=1 ").
            "ORDER BY nome";
  if ($rs = $dbc->query($sqlCat)) {
    while($r = $rs->fetch_assoc()) $categorias[] = $r;
  }
}

// Marcas
$marcas = [];
if (table_exists($dbc,'moz_marca')) {
  $sqlMarca = "SELECT id,nome FROM moz_marca ".
              (has_col($dbc,'moz_marca','ativo') ? "WHERE ativo=1 " : "WHERE 1=1 ").
              "ORDER BY nome";
  if ($rs = $dbc->query($sqlMarca)) {
    while($r = $rs->fetch_assoc()) $marcas[] = $r;
  }
}

// Modelos
$modelos = [];
$modelosPorId = [];
if (table_exists($dbc,'moz_modelo')) {
  $hasMarcaColModelo = has_col($dbc,'moz_modelo','marca_id');
  if ($hasMarcaColModelo) {
    $sqlMod = "SELECT id,nome,marca_id FROM moz_modelo ORDER BY nome";
  } else {
    $sqlMod = "SELECT id,nome,0 AS marca_id FROM moz_modelo ORDER BY nome";
  }
  if ($rs = $dbc->query($sqlMod)) {
    while($r = $rs->fetch_assoc()){
      $modelos[] = $r;
      $modelosPorId[(int)$r['id']] = $r;
    }
  }
}

// Locais (empresas)
$locais = [];
if (table_exists($dbc,'empresas')) {
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
  if ($rs = $dbc->query($sqlEmp)) {
    while($r = $rs->fetch_assoc()) $locais[] = $r;
  }
}

// Fornecedores
$fornecedores = [];
$hasFornecedorTbl = table_exists($dbc,'moz_fornecedor');
if ($hasFornecedorTbl) {
  $sqlF = "SELECT id,nome FROM moz_fornecedor ".
          (has_col($dbc,'moz_fornecedor','ativo') ? "WHERE ativo=1 " : "WHERE 1=1 ").
          "ORDER BY nome";
  if ($rs = $dbc->query($sqlF)) {
    while($r = $rs->fetch_assoc()) $fornecedores[] = $r;
  }
}

// ===== Controle de passo =====
$step = $_POST['step'] ?? 'upload';
$messages = [];
$previewRows = [];
$importOk = 0;
$importFail = 0;

// ===== PASSO 2: PREVIEW =====
if ($step === 'preview' && $_SERVER['REQUEST_METHOD'] === 'POST') {

  if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
    $messages[] = ['tipo'=>'danger','msg'=>'Arquivo CSV inválido.'];
    $step = 'upload';
  } else {
    $tmpfile = $_FILES['csv']['tmp_name'];
    $delim   = sniff_delimiter($tmpfile);

    if (($fh = fopen($tmpfile, 'r')) === false) {
      $messages[] = ['tipo'=>'danger','msg'=>'Falha ao abrir o arquivo CSV.'];
      $step = 'upload';
    } else {
      $header = null;
      $rawRows = [];
      while (($r = fgetcsv($fh, 0, $delim)) !== false) {
        if ($header === null) {
          if (isset($r[0])) {
            $r[0] = preg_replace('/^\xEF\xBB\xBF/', '', $r[0]);
          }
          $header = $r;
          continue;
        }
        if (count(array_filter($r, fn($x)=>trim((string)$x) !== ''))===0) continue;
        $rawRows[] = $r;
      }
      fclose($fh);

      if (!$header || !$rawRows) {
        $messages[] = ['tipo'=>'danger','msg'=>'Cabeçalho ou linhas do CSV não encontrados.'];
        $step = 'upload';
      } else {
        // mapeia header → campo lógico
        $map = [];
        foreach ($header as $idx=>$col) {
          $k = mb_strtolower(preg_replace('/[^a-z0-9_]+/i','_', $col),'UTF-8');
          $map[$idx] = null;

          if (in_array($k,['nome','descricao','descricao_ativo','nome_ativo'])) { $map[$idx] = 'nome'; continue; }
          if (in_array($k,['categoria','categoria_ativo','cat'])) { $map[$idx] = 'categoria'; continue; }
          if (in_array($k,['marca','fabricante'])) { $map[$idx] = 'marca_nome'; continue; }
          if (in_array($k,['modelo','modelo_ativo'])) { $map[$idx] = 'modelo'; continue; }
          if (in_array($k,['tag','tag_patrimonial','patrimonio','patrim_nio'])) { $map[$idx] = 'tag_patrimonial'; continue; }
          if (in_array($k,['numero_serie','n_serie','num_serie','serial'])) { $map[$idx] = 'numero_serie'; continue; }
          if (in_array($k,['status','status_operacional'])) { $map[$idx] = 'status_txt'; continue; }
          if (in_array($k,['local','empresa','unidade','loja'])) { $map[$idx] = 'local_nome'; continue; }
          if (in_array($k,['fornecedor','vendor','fornec'])) { $map[$idx] = 'fornecedor_nome'; continue; }
          if (in_array($k,['nf','nf_numero','nota_fiscal'])) { $map[$idx] = 'nf_numero'; continue; }
          if (in_array($k,['data_compra','dt_compra','compra'])) { $map[$idx] = 'data_compra'; continue; }
          if (in_array($k,['garantia_ate','garantia','garantia_fim'])) { $map[$idx] = 'garantia_ate'; continue; }
          if (in_array($k,['custo','custo_aquisicao','valor_compra','valor'])) { $map[$idx] = 'custo_aquisicao'; continue; }
          if (in_array($k,['depreciacao','depreciacao_meses','depr_meses'])) { $map[$idx] = 'depreciacao_meses'; continue; }
          if (in_array($k,['centro_custo','centrocusto','ccusto'])) { $map[$idx] = 'centro_custo'; continue; }
          if (in_array($k,['rack','posicao_rack'])) { $map[$idx] = 'rack'; continue; }
          if (in_array($k,['u_inicio','inicio_uso','inicio_operacao'])) { $map[$idx] = 'u_inicio'; continue; }
          if (in_array($k,['responsavel','respons_vel','usuario','user'])) { $map[$idx] = 'responsavel'; continue; }
          if (in_array($k,['obs','observacoes','observacao','observações'])) { $map[$idx] = 'observacoes'; continue; }
        }

        foreach ($rawRows as $csvRow) {
          $line = [
            'nome'              => '',
            'categoria'         => '',
            'cat_id'            => '',
            'marca_id'          => '',
            'marca_nome'        => '',
            'modelo'            => '',
            'modelo_id'         => '',
            'tag_patrimonial'   => '',
            'numero_serie'      => '',
            'status_id'         => 2,
            'status_txt'        => '',
            'local_id'          => '',
            'local_nome'        => '',
            'fornecedor_id'     => '',
            'fornecedor_nome'   => '',
            'nf_numero'         => '',
            'data_compra'       => '',
            'garantia_ate'      => '',
            'custo_aquisicao'   => '',
            'depreciacao_meses' => '',
            'centro_custo'      => '',
            'rack'              => '',
            'u_inicio'          => '',
            'responsavel'       => '',
            'observacoes'       => '',
          ];

          foreach ($csvRow as $idx=>$val) {
            $val = trim((string)$val);
            $f = $map[$idx] ?? null;
            if (!$f) continue;
            $line[$f] = $val;
          }

          // Status
          if ($line['status_txt'] !== '') {
            $key = strtoupper(preg_replace('/\s+/', '_', trim($line['status_txt'])));
            if (isset($statusesMap[$key])) {
              $line['status_id'] = $statusesMap[$key];
            }
          }

          if ($line['nome'] === '') continue;
          $previewRows[] = $line;
        }

        if (!$previewRows) {
          $messages[] = ['tipo'=>'danger','msg'=>'Nenhuma linha válida encontrada (todas com nome em branco).'];
          $step = 'upload';
        }
      }
    }
  }
}

// ===== PASSO 3: IMPORT =====
if ($step === 'import' && $_SERVER['REQUEST_METHOD'] === 'POST') {

  $rows = $_POST['rows'] ?? [];
  if (!$rows || !is_array($rows)) {
    $messages[] = ['tipo'=>'danger','msg'=>'Nenhuma linha recebida para importação.'];
    $step = 'upload';
  } else {
    $importOk = 0;
    $importFail = 0;

    $sqlIns = "INSERT INTO moz_ativo (
                 nome, cat_id, marca_id, modelo_id,
                 tag_patrimonial, numero_serie,
                 status_id, local_id, fornecedor_id,
                 nf_numero, data_compra, garantia_ate,
                 custo_aquisicao, depreciacao_meses,
                 observacoes, ativo
               ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)";
    $st = $dbc->prepare($sqlIns);
    if (!$st) {
      $messages[] = ['tipo'=>'danger','msg'=>'Erro no prepare: '.$dbc->error];
      $step = 'upload';
    } else {

      foreach ($rows as $idx=>$r) {
        $nome = trim($r['nome'] ?? '');
        if ($nome === '') { $importFail++; continue; }

        // Categoria
        $cat_id  = (int)($r['cat_id'] ?? 0);
        $cat_nome = trim($r['categoria'] ?? '');
        if (!$cat_id && $cat_nome !== '') {
          if ($q = $dbc->prepare("SELECT id FROM moz_cat_ativo WHERE nome=? LIMIT 1")) {
            $q->bind_param('s',$cat_nome);
            $q->execute();
            $q->bind_result($cat_id);
            $q->fetch();
            $q->close();
          }
          if (!$cat_id) {
            if ($c = $dbc->prepare("INSERT INTO moz_cat_ativo (nome,ativo) VALUES (?,1)")) {
              $c->bind_param('s',$cat_nome);
              $c->execute();
              $cat_id = $c->insert_id;
              $c->close();
            }
          }
        }

        // Marca
        $marca_id = (int)($r['marca_id'] ?? 0);
        $marca_nome = trim($r['marca_nome'] ?? '');
        if (!$marca_id && $marca_nome !== '') {
          if ($q = $dbc->prepare("SELECT id FROM moz_marca WHERE nome=? LIMIT 1")) {
            $q->bind_param('s',$marca_nome);
            $q->execute();
            $q->bind_result($marca_id);
            $q->fetch();
            $q->close();
          }
        }

        // Modelo
        $modelo_id = (int)($r['modelo_id'] ?? 0);
        $modelo_nome = trim($r['modelo'] ?? '');
        if (!$modelo_id && $modelo_nome !== '') {
          if ($marca_id) {
            if ($q = $dbc->prepare("SELECT id FROM moz_modelo WHERE nome=? AND marca_id=? LIMIT 1")) {
              $q->bind_param('si',$modelo_nome,$marca_id);
              $q->execute();
              $q->bind_result($modelo_id);
              $q->fetch();
              $q->close();
            }
          } else {
            if ($q = $dbc->prepare("SELECT id FROM moz_modelo WHERE nome=? LIMIT 1")) {
              $q->bind_param('s',$modelo_nome);
              $q->execute();
              $q->bind_result($modelo_id);
              $q->fetch();
              $q->close();
            }
          }
        }

        // Local
        $local_id = (int)($r['local_id'] ?? 0);
        $local_nome = trim($r['local_nome'] ?? '');
        if (!$local_id && $local_nome !== '' && table_exists($dbc,'empresas')) {
          if ($q = $dbc->prepare("SELECT id FROM empresas WHERE nome_fantasia=? OR nome_empresarial=? LIMIT 1")) {
            $q->bind_param('ss',$local_nome,$local_nome);
            $q->execute();
            $q->bind_result($local_id);
            $q->fetch();
            $q->close();
          }
        }

        // Fornecedor
        $fornecedor_id = (int)($r['fornecedor_id'] ?? 0);
        $fornecedor_nome = trim($r['fornecedor_nome'] ?? '');
        if (!$fornecedor_id && $fornecedor_nome !== '' && $hasFornecedorTbl) {
          if ($q = $dbc->prepare("SELECT id FROM moz_fornecedor WHERE nome=? LIMIT 1")) {
            $q->bind_param('s',$fornecedor_nome);
            $q->execute();
            $q->bind_result($fornecedor_id);
            $q->fetch();
            $q->close();
          }
        }

        // Status
        $status_id = (int)($r['status_id'] ?? 0);
        if (!$status_id) $status_id = 2;

        // Campos simples
        $tag_patrimonial   = trim($r['tag_patrimonial'] ?? '');
        $numero_serie      = trim($r['numero_serie'] ?? '');
        $nf_numero         = trim($r['nf_numero'] ?? '');
        $data_compra       = trim($r['data_compra'] ?? '');
        $garantia_ate      = trim($r['garantia_ate'] ?? '');
        $custo_aquisicao   = trim($r['custo_aquisicao'] ?? '');
        $depreciacao_meses = trim($r['depreciacao_meses'] ?? '');
        $observacoes       = trim($r['observacoes'] ?? '');

        if ($custo_aquisicao !== '') {
          $custo_aquisicao = str_replace(',','.', $custo_aquisicao);
        }

        $cat_id_int        = $cat_id ?: 0;
        $marca_id_int      = $marca_id ?: 0;
        $modelo_id_int     = $modelo_id ?: 0;
        $status_id_int     = $status_id ?: 0;
        $local_id_int      = $local_id ?: 0;
        $fornecedor_id_int = $fornecedor_id ?: 0;
        $depr_int          = ($depreciacao_meses !== '' && is_numeric($depreciacao_meses)) ? (int)$depreciacao_meses : 0;

        $types = 'siiissiiissssis';
        if (!$st->bind_param(
          $types,
          $nome,
          $cat_id_int,
          $marca_id_int,
          $modelo_id_int,
          $tag_patrimonial,
          $numero_serie,
          $status_id_int,
          $local_id_int,
          $fornecedor_id_int,
          $nf_numero,
          $data_compra,
          $garantia_ate,
          $custo_aquisicao,
          $depr_int,
          $observacoes
        )) {
          $importFail++;
          continue;
        }

        if (!$st->execute()) {
          $importFail++;
        } else {
          $importOk++;
        }
      }

      $st->close();
      $messages[] = [
        'tipo'=>'info',
        'msg'=>"Importação concluída. Sucesso: {$importOk} | Falhas: {$importFail}"
      ];
      $step = 'done';
    }
  }
}

include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>

<div id="page-wrapper">
  <div class="container-fluid">

    <h3 class="page-header">Ativos — Importar CSV</h3>

    <?php foreach ($messages as $m): ?>
      <div class="alert alert-<?= h($m['tipo']) ?>"><?= h($m['msg']) ?></div>
    <?php endforeach; ?>

    <?php if ($step === 'upload'): ?>

      <form class="card" method="post" enctype="multipart/form-data">
        <input type="hidden" name="step" value="preview">

        <p class="subtitle">Passo 1 — Selecionar arquivo</p>
        <div class="grid cols-2">
          <div class="stack">
            <label>CSV (delimitador ; ou ,)</label>
            <input type="file" name="csv" accept=".csv,text/csv" required>
            <span class="hint">O arquivo deve conter cabeçalho na primeira linha.</span>
          </div>
          <div class="stack">
            <label>Modelo sugerido de cabeçalho</label>
            <textarea rows="7" readonly>nome;categoria;marca;modelo;tag_patrimonial;numero_serie;status;local;fornecedor;nf_numero;data_compra;garantia_ate;custo_aquisicao;depreciacao_meses;observacoes
Notebook João;Notebook;Dell;Latitude 5440;TAG-001;ABC123;EM_ESTOQUE;Matriz;Distribuidora X;12345;2024-01-10;2026-01-10;4500,00;36;Primeira carga
Switch Core;Switch;;;;;EM_OPERACAO;Sala TI;;;;;;</textarea>
            <span class="hint">
              Status aceitos: EM_OPERACAO, EM_ESTOQUE, EMPRESTADO, ALUGADO, EM_MANUTENCAO, BAIXADO.
            </span>
          </div>
        </div>

        <div class="row" style="justify-content:flex-end;gap:8px;margin-top:8px;">
          <a class="btn" href="ativos-listar.php">Voltar</a>
          <button class="btn primary" type="submit">Carregar</button>
        </div>
      </form>

    <?php elseif ($step === 'preview' && $previewRows): ?>

      <form class="card" method="post">
        <input type="hidden" name="step" value="import">

        <p class="subtitle">Passo 2 — Revisar e completar dados</p>
        <p class="hint">
          Confira os dados antes de importar.  
          Os campos de <strong>Categoria</strong>, <strong>Marca</strong>, <strong>Modelo</strong>,
          <strong>Local (Empresa)</strong>, <strong>Fornecedor</strong> e <strong>Status</strong> usam o
          cadastro atual do sistema (SELECT).  
          Se algo não casar, escolha manualmente no combo.
        </p>

        <div class="table-responsive" style="max-height:65vh;overflow:auto;border:1px solid #ddd;">
          <table class="table table-striped table-condensed">
            <thead>
              <tr>
                <th>Nome *</th>
                <th>Categoria (sistema)</th>
                <th>Marca (sistema)</th>
                <th>Modelo (sistema)</th>
                <th>Tag / Patrimônio</th>
                <th>Nº de série</th>
                <th>Status</th>
                <th>Local (Empresa)</th>
                <th>Fornecedor</th>
                <th>NF</th>
                <th>Data compra</th>
                <th>Garantia até</th>
                <th>Custo aquisição</th>
                <th>Depreciação (meses)</th>
                <th>Centro de custo</th>
                <th>Rack</th>
                <th>Início uso</th>
                <th>Responsável</th>
                <th>Observações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($previewRows as $i=>$line): ?>
                <tr>
                  <!-- Nome -->
                  <td>
                    <input type="text" class="form-control input-sm"
                           name="rows[<?= $i ?>][nome]"
                           value="<?= h($line['nome']) ?>" required>
                  </td>

                  <!-- Categoria (select + texto CSV) -->
                  <td>
                    <?php
                      $selCat = $line['cat_id'] ?? '';
                      if (!$selCat && $line['categoria'] !== '') {
                        foreach ($categorias as $c) {
                          if (strcasecmp(trim($c['nome']), trim($line['categoria'])) === 0) {
                            $selCat = $c['id'];
                            break;
                          }
                        }
                      }
                    ?>
                    <select class="form-control input-sm"
                            name="rows[<?= $i ?>][cat_id]">
                      <option value="">—</option>
                      <?php foreach ($categorias as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"
                          <?= (string)$selCat === (string)$c['id'] ? 'selected' : '' ?>>
                          <?= h($c['nome']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <input type="hidden"
                           name="rows[<?= $i ?>][categoria]"
                           value="<?= h($line['categoria']) ?>">
                    <?php if($line['categoria'] && !$selCat): ?>
                      <div class="hint">CSV: <?= h($line['categoria']) ?></div>
                    <?php endif; ?>
                  </td>

                  <!-- Marca (select) -->
                  <td>
                    <?php
                      $selMarca = $line['marca_id'] ?? '';
                      if (!$selMarca && $line['marca_nome'] !== '') {
                        foreach ($marcas as $m) {
                          if (strcasecmp(trim($m['nome']), trim($line['marca_nome'])) === 0) {
                            $selMarca = $m['id'];
                            break;
                          }
                        }
                      }
                    ?>
                    <select class="form-control input-sm"
                            name="rows[<?= $i ?>][marca_id]">
                      <option value="">—</option>
                      <?php foreach ($marcas as $m): ?>
                        <option value="<?= (int)$m['id'] ?>"
                          <?= (string)$selMarca === (string)$m['id'] ? 'selected' : '' ?>>
                          <?= h($m['nome']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <input type="hidden"
                           name="rows[<?= $i ?>][marca_nome]"
                           value="<?= h($line['marca_nome']) ?>">
                    <?php if($line['marca_nome'] && !$selMarca): ?>
                      <div class="hint">CSV: <?= h($line['marca_nome']) ?></div>
                    <?php endif; ?>
                  </td>

                  <!-- Modelo (select) -->
                  <td>
                    <?php
                      $selModelo = $line['modelo_id'] ?? '';
                      $modeloNomeCsv = trim($line['modelo']);
                      if (!$selModelo && $modeloNomeCsv !== '') {
                        // tenta casar modelo levando em conta marca
                        $selModeloTmp = '';
                        // 1ª passada: nome + mesma marca
                        if ($modeloNomeCsv !== '' && $selMarca) {
                          foreach ($modelos as $mod) {
                            if (strcasecmp(trim($mod['nome']), $modeloNomeCsv) === 0
                                && (int)$mod['marca_id'] === (int)$selMarca) {
                              $selModeloTmp = $mod['id'];
                              break;
                            }
                          }
                        }
                        // 2ª passada: só nome
                        if (!$selModeloTmp && $modeloNomeCsv !== '') {
                          foreach ($modelos as $mod) {
                            if (strcasecmp(trim($mod['nome']), $modeloNomeCsv) === 0) {
                              $selModeloTmp = $mod['id'];
                              break;
                            }
                          }
                        }
                        if ($selModeloTmp) $selModelo = $selModeloTmp;
                      }
                    ?>
                    <select class="form-control input-sm"
                            name="rows[<?= $i ?>][modelo_id]">
                      <option value="">—</option>
                      <?php foreach ($modelos as $mod): ?>
                        <option value="<?= (int)$mod['id'] ?>"
                          <?= (string)$selModelo === (string)$mod['id'] ? 'selected' : '' ?>>
                          <?= h($mod['nome']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <input type="hidden"
                           name="rows[<?= $i ?>][modelo]"
                           value="<?= h($line['modelo']) ?>">
                    <?php if($line['modelo'] && !$selModelo): ?>
                      <div class="hint">CSV: <?= h($line['modelo']) ?></div>
                    <?php endif; ?>
                  </td>

                  <!-- Tag -->
                  <td>
                    <input type="text" class="form-control input-sm"
                           name="rows[<?= $i ?>][tag_patrimonial]"
                           value="<?= h($line['tag_patrimonial']) ?>">
                  </td>

                  <!-- Série -->
                  <td>
                    <input type="text" class="form-control input-sm"
                           name="rows[<?= $i ?>][numero_serie]"
                           value="<?= h($line['numero_serie']) ?>">
                  </td>

                  <!-- Status -->
                  <td>
                    <?php
                      $stId = (int)($line['status_id'] ?? 2);
                      if (!$stId && $line['status_txt'] !== '') {
                        $key = strtoupper(preg_replace('/\s+/', '_', trim($line['status_txt'])));
                        if (isset($statusesMap[$key])) $stId = $statusesMap[$key];
                      }
                      if (!$stId) $stId = 2;
                    ?>
                    <select class="form-control input-sm"
                            name="rows[<?= $i ?>][status_id]">
                      <?php foreach ($statusLabels as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= (string)$stId===(string)$k?'selected':'' ?>>
                          <?= h($v) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>

                  <!-- Local (empresa) -->
                  <td>
                    <?php
                      $selLocal = $line['local_id'] ?? '';
                      if (!$selLocal && $line['local_nome'] !== '') {
                        foreach ($locais as $l) {
                          if (strcasecmp(trim($l['nome']), trim($line['local_nome'])) === 0) {
                            $selLocal = $l['id'];
                            break;
                          }
                        }
                      }
                    ?>
                    <select class="form-control input-sm"
                            name="rows[<?= $i ?>][local_id]">
                      <option value="">—</option>
                      <?php foreach ($locais as $l): ?>
                        <option value="<?= (int)$l['id'] ?>"
                          <?= (string)$selLocal===(string)$l['id']?'selected':'' ?>>
                          <?= h($l['nome']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <input type="hidden"
                           name="rows[<?= $i ?>][local_nome]"
                           value="<?= h($line['local_nome']) ?>">
                    <?php if($line['local_nome'] && !$selLocal): ?>
                      <div class="hint">CSV: <?= h($line['local_nome']) ?></div>
                    <?php endif; ?>
                  </td>

                  <!-- Fornecedor -->
                  <td>
                    <?php
                      $selForn = $line['fornecedor_id'] ?? '';
                      if (!$selForn && $line['fornecedor_nome'] !== '') {
                        foreach ($fornecedores as $f) {
                          if (strcasecmp(trim($f['nome']), trim($line['fornecedor_nome'])) === 0) {
                            $selForn = $f['id'];
                            break;
                          }
                        }
                      }
                    ?>
                    <select class="form-control input-sm"
                            name="rows[<?= $i ?>][fornecedor_id]">
                      <option value="">—</option>
                      <?php foreach ($fornecedores as $f): ?>
                        <option value="<?= (int)$f['id'] ?>"
                          <?= (string)$selForn===(string)$f['id']?'selected':'' ?>>
                          <?= h($f['nome']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <input type="hidden"
                           name="rows[<?= $i ?>][fornecedor_nome]"
                           value="<?= h($line['fornecedor_nome']) ?>">
                    <?php if($line['fornecedor_nome'] && !$selForn): ?>
                      <div class="hint">CSV: <?= h($line['fornecedor_nome']) ?></div>
                    <?php endif; ?>
                  </td>

                  <!-- NF -->
                  <td>
                    <input type="text" class="form-control input-sm"
                           name="rows[<?= $i ?>][nf_numero]"
                           value="<?= h($line['nf_numero']) ?>">
                  </td>

                  <!-- Data compra -->
                  <td>
                    <input type="date" class="form-control input-sm"
                           name="rows[<?= $i ?>][data_compra]"
                           value="<?= h($line['data_compra']) ?>">
                  </td>

                  <!-- Garantia -->
                  <td>
                    <input type="date" class="form-control input-sm"
                           name="rows[<?= $i ?>][garantia_ate]"
                           value="<?= h($line['garantia_ate']) ?>">
                  </td>

                  <!-- Custo -->
                  <td>
                    <input type="text" class="form-control input-sm"
                           name="rows[<?= $i ?>][custo_aquisicao]"
                           value="<?= h($line['custo_aquisicao']) ?>">
                  </td>

                  <!-- Depreciação -->
                  <td>
                    <input type="number" class="form-control input-sm"
                           name="rows[<?= $i ?>][depreciacao_meses]"
                           value="<?= h($line['depreciacao_meses']) ?>">
                  </td>

                  <!-- Centro de custo -->
                  <td>
                    <input type="text" class="form-control input-sm"
                           name="rows[<?= $i ?>][centro_custo]"
                           value="<?= h($line['centro_custo']) ?>">
                  </td>

                  <!-- Rack -->
                  <td>
                    <input type="text" class="form-control input-sm"
                           name="rows[<?= $i ?>][rack]"
                           value="<?= h($line['rack']) ?>">
                  </td>

                  <!-- Início uso -->
                  <td>
                    <input type="date" class="form-control input-sm"
                           name="rows[<?= $i ?>][u_inicio]"
                           value="<?= h($line['u_inicio']) ?>">
                  </td>

                  <!-- Responsável -->
                  <td>
                    <input type="text" class="form-control input-sm"
                           name="rows[<?= $i ?>][responsavel]"
                           value="<?= h($line['responsavel']) ?>">
                  </td>

                  <!-- Observações -->
                  <td>
                    <input type="text" class="form-control input-sm"
                           name="rows[<?= $i ?>][observacoes]"
                           value="<?= h($line['observacoes']) ?>">
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="row" style="justify-content:flex-end;gap:8px;margin-top:8px;">
          <a class="btn" href="ativos-importar.php">Cancelar</a>
          <button class="btn primary" type="submit">Importar agora</button>
        </div>
      </form>

    <?php elseif ($step === 'done'): ?>

      <div class="card">
        <p class="subtitle">Importação finalizada</p>
        <p>Você pode voltar para a lista de ativos ou iniciar uma nova importação.</p>
        <div class="row" style="gap:8px;">
          <a class="btn primary" href="ativos-listar.php">Ir para lista de ativos</a>
          <a class="btn" href="ativos-importar.php">Nova importação</a>
        </div>
      </div>

    <?php endif; ?>

  </div>
</div>

<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
