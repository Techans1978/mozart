<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

// Garante que as funções multibyte usem UTF-8
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

if (!$conn instanceof mysqli) {
  die('Sem conexão com o banco.');
}
$dbc = $conn;

/* ================= Helpers ================= */

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function sniff_delimiter($path){
  $sample = file_get_contents($path, false, null, 0, 4096);
  $sc = substr_count($sample, ';');
  $cc = substr_count($sample, ',');
  return ($sc >= $cc) ? ';' : ',';
}

/** Normaliza chave de comparação (para nomes) */
function norm_key($v){
  $v = trim((string)$v);
  $v = mb_strtolower($v,'UTF-8');
  // remove espaços duplicados
  $v = preg_replace('/\s+/u', ' ', $v);
  return $v;
}

/** Converte string de data flexível para YYYY-MM-DD ou null */
function parse_date_flex($v){
  $v = trim((string)$v);
  if ($v==='') return null;

  // já vem YYYY-MM-DD
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;

  // DD/MM/YYYY ou DD-MM-YYYY
  if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $v, $m)) {
    $d = str_pad($m[1],2,'0',STR_PAD_LEFT);
    $M = str_pad($m[2],2,'0',STR_PAD_LEFT);
    $Y = $m[3];
    return "$Y-$M-$d";
  }

  // tenta strtotime
  $ts = strtotime($v);
  if ($ts!==false) return date('Y-m-d',$ts);

  return null;
}

/** Converte decimal com vírgula/ponto para string no padrão decimal MySQL ou null */
function parse_decimal($v){
  $v = trim((string)$v);
  if ($v==='') return null;
  // remove separador de milhar
  $v = str_replace(['.',' '], '', $v);
  // vírgula para ponto
  $v = str_replace(',', '.', $v);
  if (!is_numeric($v)) return null;
  return $v;
}

/* ================= Campos ================= */

$step = $_POST['step'] ?? 'upload';

/**
 * Campos "conceituais" do CSV / tela de preview.
 * Estes são todos texto na tela; depois, na importação,
 * mapeamos para IDs (categoria/marca/modelo/fornecedor/local/status).
 */
$fields = [
  'nome',
  'categoria',
  'marca',
  'modelo',
  'tag_patrimonial',
  'numero_serie',
  'status',
  'local',
  'fornecedor',
  'nf_numero',
  'data_compra',
  'garantia_ate',
  'custo_aquisicao',
  'depreciacao_meses',
  'centro_custo',
  'responsavel',
  'observacoes'
];

/* ================= Carrega dados auxiliares (datalists) ================= */

/* Categorias */
$cats = [];
if ($r = $dbc->query("SELECT id, nome FROM moz_cat_ativo WHERE ativo=1 ORDER BY nome")) {
  while($x = $r->fetch_assoc()){
    $cats[] = $x;
  }
}

/* Marcas */
$marcas = [];
if ($r = $dbc->query("SELECT id, nome FROM moz_marca WHERE ativo=1 ORDER BY nome")) {
  while($x = $r->fetch_assoc()){
    $marcas[] = $x;
  }
}

/* Modelos */
$modelos = [];
if ($r = $dbc->query("SELECT id, nome, marca_id FROM moz_modelo WHERE ativo=1 ORDER BY nome")) {
  while($x = $r->fetch_assoc()){
    $modelos[] = $x;
  }
}

/* Fornecedores */
$fornecedores = [];
if ($r = $dbc->query("SELECT id, nome FROM moz_fornecedor WHERE ativo=1 ORDER BY nome")) {
  while($x = $r->fetch_assoc()){
    $fornecedores[] = $x;
  }
}

/* Locais = empresas */
$empresas = [];
if ($r = $dbc->query("SELECT id, nome_empresarial, nome_fantasia, apelido
                        FROM empresas
                       WHERE 1=1
                    ORDER BY nome_empresarial ASC, nome_fantasia ASC, apelido ASC")) {
  while($x = $r->fetch_assoc()){
    $label = trim(
      ($x['nome_fantasia'] ?: $x['nome_empresarial'] ?: '(sem nome)')
      . ( $x['apelido'] ? (' - '.$x['apelido']) : '' )
    );
    $x['label'] = $label;
    $empresas[] = $x;
  }
}

/* Status dos ativos */
$statusRows = [];
if ($dbc->query("SHOW TABLES LIKE 'moz_status_ativo'")->num_rows){
  if ($r = $dbc->query("SELECT id, nome FROM moz_status_ativo ORDER BY id")) {
    while($x=$r->fetch_assoc()){
      $statusRows[] = $x;
    }
  }
} else {
  // fallback: mesmo mapeamento do import antigo
  $statusRows = [
    ['id'=>1,'nome'=>'EM_OPERACAO'],
    ['id'=>2,'nome'=>'EM_ESTOQUE'],
    ['id'=>3,'nome'=>'EMPRESTADO'],
    ['id'=>4,'nome'=>'ALUGADO'],
    ['id'=>5,'nome'=>'EM_MANUTENCAO'],
    ['id'=>6,'nome'=>'BAIXADO'],
  ];
}

/* ================= Início do HTML ================= */

include_once ROOT_PATH.'system/includes/head.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.1" rel="stylesheet">
<?php include_once ROOT_PATH.'system/includes/navbar.php'; ?>

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

  <session class="bpm"><div class="container">
    <header class="toolbar">
      <h1>Ativos — Importar CSV</h1>
      <div class="actions">
        <a class="btn" href="ativos-listar.php">Listar ativos</a>
        <a class="btn" href="ativos-form.php">Novo ativo</a>
      </div>
    </header>

<?php if ($step === 'upload'): ?>

    <form class="card" method="post" enctype="multipart/form-data">
      <p class="subtitle">Passo 1 — Enviar arquivo CSV</p>
      <div class="stack">
        <label>Selecione o arquivo CSV</label>
        <input type="hidden" name="step" value="preview">
        <input type="file" name="csv" accept=".csv,text/csv" required>
        <span class="hint">
          Use um arquivo CSV com cabeçalho. Delimitador “;” ou “,”.
        </span>
      </div>

      <div class="divider"></div>
      <details class="stack">
        <summary>Modelo sugerido de cabeçalho</summary>
        <textarea rows="5" readonly style="width:100%;font-family:monospace;">
nome;categoria;marca;modelo;tag_patrimonial;numero_serie;status;local;fornecedor;nf_numero;data_compra;garantia_ate;custo_aquisicao;depreciacao_meses;centro_custo;responsavel;observacoes
Notebook João;Notebook;Dell;Latitude 5440;TAG-001;ABC123;EM_ESTOQUE;Loja 01 - Centro;Fornecedor A;12345;2024-01-10;2026-01-10;4500,00;36;TI;João da Silva;Primeira carga
        </textarea>
        <span class="hint">
          Datas podem ser YYYY-MM-DD ou DD/MM/YYYY.  
          Status aceitos: EM_OPERACAO, EM_ESTOQUE, EMPRESTADO, ALUGADO, EM_MANUTENCAO, BAIXADO.
        </span>
      </details>

      <div class="row" style="justify-content:flex-end;gap:8px;margin-top:12px">
        <a class="btn" href="ativos-listar.php">Voltar</a>
        <button class="btn primary" type="submit">Carregar</button>
      </div>
    </form>

<?php elseif ($step === 'preview'): ?>

<?php
  if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
    echo '<div class="card"><div class="alert alert-danger">Arquivo inválido.</div></div>';
  } else {
    // Salva temporário
    $tmpdir  = sys_get_temp_dir();
    $tmpfile = tempnam($tmpdir, 'ativos_csv_');
    move_uploaded_file($_FILES['csv']['tmp_name'], $tmpfile);

    $delim = sniff_delimiter($tmpfile);
    $fh    = fopen($tmpfile, 'r');
    $header = null;
    $rows   = [];

    while(($r = fgetcsv($fh, 0, $delim)) !== false){
  // converte todo o array da linha para UTF-8 (caso venha em ISO-8859-1/Windows-1252)
  foreach ($r as &$v) {
    $v = mb_convert_encoding($v, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
  }
  unset($v);

  if ($header === null) {
    if (isset($r[0])) {
      // remove BOM se houver
      $r[0] = preg_replace('/^\xEF\xBB\xBF/', '', $r[0]);
    }
    $header = $r;
    continue;
  }
  if (count(array_filter($r, fn($x)=>trim((string)$x) !== '')) === 0) continue;
  $rows[] = $r;
}

    fclose($fh);

    $_SESSION['ativos_import_tmp'] = [
      'tmpfile' => $tmpfile,
      'delim'   => $delim,
      'header'  => $header,
      'rows'    => $rows
    ];

    if (!$header || !$rows) {
      echo '<div class="card"><div class="alert alert-warning">Não foi possível ler o cabeçalho ou não há linhas válidas no CSV.</div>';
      echo '<a class="btn" href="ativos-importar.php">Tentar novamente</a></div>';
    } else {
?>

    <div class="card">
      <p class="subtitle">Passo 2 — Revisar / completar antes de importar</p>
      <p class="hint">
        Revise os dados abaixo. Você pode editar qualquer célula.  
        Para <strong>Categoria, Marca, Modelo, Local, Fornecedor e Status</strong>, use as sugestões da lista ou digite um novo valor.
        Caso não exista no cadastro, será criado automaticamente na importação (exceto Local, que não cria empresa nova).
      </p>

      <form method="post">
        <input type="hidden" name="step" value="import">

        <div style="max-height:75vh;overflow:auto;border:1px solid #ddd;border-radius:8px;">

          <table class="table table-striped table-condensed" style="width:100%;border-collapse:collapse;">
            <thead style="position:sticky;top:0;background:#f7f7f7;">
              <tr>
                <?php foreach ($fields as $f): ?>
                  <th style="padding:6px 10px;border-bottom:1px solid #ddd;font-size:13px;white-space:nowrap;">
  <?= h($f) ?>
</th>

                <?php endforeach; ?>
              </tr>
            </thead>
<tbody>
  <?php
  // Mapa: coluna do CSV -> campo interno
  $map = [];
  foreach ($header as $idx=>$col) {
    $k = mb_strtolower(preg_replace('/[^a-z0-9_]+/i','_', $col),'UTF-8');
    $map[$idx] = null;
    foreach ($fields as $f) {
      if ($k === $f) { $map[$idx] = $f; break; }
      // aliases comuns
      if ($k==='categoria_ativo' && $f==='categoria') $map[$idx]=$f;
      if ($k==='tag' && $f==='tag_patrimonial') $map[$idx]=$f;
      if ($k==='serie' && $f==='numero_serie') $map[$idx]=$f;
    }
  }

  foreach ($rows as $i=>$csvRow):
    $line = array_fill_keys($fields, '');
    foreach ($csvRow as $cidx=>$val) {
      $f = $map[$cidx] ?? null;
      if ($f) $line[$f] = $val;
    }
  ?>
  <tr>
    <?php foreach ($fields as $f): ?>
      <td style="padding:3px 5px;border-bottom:1px solid #eee;">
  <?php
    $v = $line[$f];
    $name = "rows[$i][".h($f)."]";
    // Campos com sugestões (datalist)
    if (in_array($f,['categoria','marca','modelo','local','fornecedor','status'])):
  ?>
    <input type="text"
           name="<?= $name ?>"
           value="<?= h($v) ?>"
           list="dl_<?= h($f) ?>"
           style="width:100%;font-size:13px;padding:4px 6px;">
  <?php else: ?>
    <input type="text"
           name="<?= $name ?>"
           value="<?= h($v) ?>"
           style="width:100%;font-size:13px;padding:4px 6px;">
  <?php endif; ?>
</td>

    <?php endforeach; ?>
  </tr>
  <?php endforeach; ?>
</tbody>

          </table>
        </div>

        <div class="row" style="justify-content:flex-end;gap:8px;margin-top:10px;">
          <a class="btn" href="ativos-importar.php">Cancelar</a>
          <button class="btn primary" type="submit">Importar agora</button>
        </div>
      </form>

      <!-- DATALISTS -->
      <datalist id="dl_categoria">
        <?php foreach($cats as $c): ?>
          <option value="<?= h($c['nome']) ?>"></option>
        <?php endforeach; ?>
      </datalist>

      <datalist id="dl_marca">
        <?php foreach($marcas as $m): ?>
          <option value="<?= h($m['nome']) ?>"></option>
        <?php endforeach; ?>
      </datalist>

      <datalist id="dl_modelo">
        <?php foreach($modelos as $mo): ?>
          <option value="<?= h($mo['nome']) ?>"></option>
        <?php endforeach; ?>
      </datalist>

      <datalist id="dl_fornecedor">
        <?php foreach($fornecedores as $f): ?>
          <option value="<?= h($f['nome']) ?>"></option>
        <?php endforeach; ?>
      </datalist>

      <datalist id="dl_local">
        <?php foreach($empresas as $e): ?>
          <option value="<?= h($e['label']) ?>"></option>
        <?php endforeach; ?>
      </datalist>

      <datalist id="dl_status">
        <?php foreach($statusRows as $srow): ?>
          <option value="<?= h($srow['nome']) ?>"></option>
        <?php endforeach; ?>
      </datalist>
    </div>

<?php
    } // header/rows ok
  } // file ok
?>

<?php elseif ($step === 'import'): ?>

<?php
  $rows = $_POST['rows'] ?? [];
  if (!$rows) {
    echo '<div class="card"><div class="alert alert-warning">Nada para importar.</div><a class="btn" href="ativos-importar.php">Voltar</a></div>';
  } else {

    // Carrega mapas existentes para evitar consultas repetidas
    $mapCat = [];      // norm(nome) -> id
    $mapMarca = [];    // norm(nome) -> id
    $mapForn = [];     // norm(nome) -> id
    $mapStatus = [];   // norm(nome) -> id
    $mapEmp = [];      // norm(label) / apelido / fantasia / empresarial -> id
    $mapModelo = [];   // marca_id: [norm(nome)->id]

    foreach ($cats as $c) {
      $mapCat[norm_key($c['nome'])] = (int)$c['id'];
    }
    foreach ($marcas as $m) {
      $mapMarca[norm_key($m['nome'])] = (int)$m['id'];
    }
    foreach ($fornecedores as $f) {
      $mapForn[norm_key($f['nome'])] = (int)$f['id'];
    }
    foreach ($statusRows as $s) {
      $mapStatus[norm_key($s['nome'])] = (int)$s['id'];
    }
    foreach ($empresas as $e) {
      $id = (int)$e['id'];
      $label = $e['label'];
      $mapEmp[norm_key($label)] = $id;
      if ($e['apelido'])          $mapEmp[norm_key($e['apelido'])] = $id;
      if ($e['nome_fantasia'])    $mapEmp[norm_key($e['nome_fantasia'])] = $id;
      if ($e['nome_empresarial']) $mapEmp[norm_key($e['nome_empresarial'])] = $id;
    }
    foreach ($modelos as $mo) {
      $mid = (int)$mo['marca_id'];
      if (!isset($mapModelo[$mid])) $mapModelo[$mid] = [];
      $mapModelo[$mid][norm_key($mo['nome'])] = (int)$mo['id'];
    }

    // Helpers de criação on-demand
    $create_errors = [];

    $getCatId = function(string $nome) use ($dbc, &$mapCat, &$create_errors){
      $nome = trim($nome);
      if ($nome==='') return null;
      $k = norm_key($nome);
      if (isset($mapCat[$k])) return $mapCat[$k];

      // tenta buscar direto
      $st = $dbc->prepare("SELECT id FROM moz_cat_ativo WHERE nome=? LIMIT 1");
      if ($st){
        $st->bind_param('s',$nome);
        $st->execute();
        $st->bind_result($id);
        if ($st->fetch()){
          $st->close();
          $mapCat[$k] = (int)$id;
          return (int)$id;
        }
        $st->close();
      }

      // cria nova categoria
      $st = $dbc->prepare("INSERT INTO moz_cat_ativo (nome, pai_id, ativo) VALUES (?, NULL, 1)");
      if (!$st){
        $create_errors[] = "Erro ao preparar insert de categoria '{$nome}': ".$dbc->error;
        return null;
      }
      $st->bind_param('s',$nome);
      if(!$st->execute()){
        $create_errors[] = "Erro ao criar categoria '{$nome}': ".$st->error;
        $st->close();
        return null;
      }
      $id = (int)$st->insert_id;
      $st->close();
      $mapCat[$k] = $id;
      return $id;
    };

    $getMarcaId = function(string $nome) use ($dbc, &$mapMarca, &$create_errors){
      $nome = trim($nome);
      if ($nome==='') return null;
      $k = norm_key($nome);
      if (isset($mapMarca[$k])) return $mapMarca[$k];

      $st = $dbc->prepare("SELECT id FROM moz_marca WHERE nome=? LIMIT 1");
      if ($st){
        $st->bind_param('s',$nome);
        $st->execute();
        $st->bind_result($id);
        if ($st->fetch()){
          $st->close();
          $mapMarca[$k]=(int)$id;
          return (int)$id;
        }
        $st->close();
      }

      // cria nova marca
      $st = $dbc->prepare("INSERT INTO moz_marca (nome, ativo) VALUES (?,1)");
      if(!$st){
        $create_errors[] = "Erro ao preparar insert de marca '{$nome}': ".$dbc->error;
        return null;
      }
      $st->bind_param('s',$nome);
      if(!$st->execute()){
        $create_errors[] = "Erro ao criar marca '{$nome}': ".$st->error;
        $st->close();
        return null;
      }
      $id = (int)$st->insert_id;
      $st->close();
      $mapMarca[$k] = $id;
      return $id;
    };

    $getFornecedorId = function(string $nome) use ($dbc, &$mapForn, &$create_errors){
      $nome = trim($nome);
      if ($nome==='') return null;
      $k = norm_key($nome);
      if (isset($mapForn[$k])) return $mapForn[$k];

      $st = $dbc->prepare("SELECT id FROM moz_fornecedor WHERE nome=? LIMIT 1");
      if ($st){
        $st->bind_param('s',$nome);
        $st->execute();
        $st->bind_result($id);
        if ($st->fetch()){
          $st->close();
          $mapForn[$k]=(int)$id;
          return (int)$id;
        }
        $st->close();
      }

      // cria novo fornecedor (mínimo: nome, ativo=1)
      $st = $dbc->prepare("INSERT INTO moz_fornecedor (nome, ativo) VALUES (?,1)");
      if(!$st){
        $create_errors[] = "Erro ao preparar insert de fornecedor '{$nome}': ".$dbc->error;
        return null;
      }
      $st->bind_param('s',$nome);
      if(!$st->execute()){
        $create_errors[] = "Erro ao criar fornecedor '{$nome}': ".$st->error;
        $st->close();
        return null;
      }
      $id = (int)$st->insert_id;
      $st->close();
      $mapForn[$k] = $id;
      return $id;
    };

    $getModeloId = function(string $nome, ?int $marca_id, ?int $cat_id) use ($dbc, &$mapModelo, &$create_errors){
      $nome = trim($nome);
      if ($nome==='') return null;
      if (!$marca_id) {
        // sem marca não temos chave única; tentamos sem cache
        $st = $dbc->prepare("SELECT id FROM moz_modelo WHERE nome=? LIMIT 1");
        if ($st){
          $st->bind_param('s',$nome);
          $st->execute();
          $st->bind_result($id);
          if ($st->fetch()){
            $st->close();
            return (int)$id;
          }
          $st->close();
        }
        // tenta criar mesmo sem marca (marca_id pode ser NULL?), mas na tabela está NOT NULL -> então precisamos de marca_id
        return null;
      }

      $k = norm_key($nome);
      if (!isset($mapModelo[$marca_id])) $mapModelo[$marca_id]=[];
      if (isset($mapModelo[$marca_id][$k])) return $mapModelo[$marca_id][$k];

      $st = $dbc->prepare("SELECT id FROM moz_modelo WHERE nome=? AND marca_id=? LIMIT 1");
      if ($st){
        $st->bind_param('si',$nome,$marca_id);
        $st->execute();
        $st->bind_result($id);
        if ($st->fetch()){
          $st->close();
          $mapModelo[$marca_id][$k]=(int)$id;
          return (int)$id;
        }
        $st->close();
      }

      // cria novo modelo
      // cat_id é NOT NULL, então se não veio tentamos 0 ou alguma categoria genérica, mas ideal é ter algo
      if (!$cat_id) $cat_id = 1; // fallback simples (ajuste se quiser outra categoria padrão)
      $st = $dbc->prepare("INSERT INTO moz_modelo (cat_id, marca_id, nome, ativo) VALUES (?,?,?,1)");
      if(!$st){
        $create_errors[] = "Erro ao preparar insert de modelo '{$nome}': ".$dbc->error;
        return null;
      }
      $st->bind_param('iis',$cat_id,$marca_id,$nome);
      if(!$st->execute()){
        $create_errors[] = "Erro ao criar modelo '{$nome}': ".$st->error;
        $st->close();
        return null;
      }
      $id = (int)$st->insert_id;
      $st->close();
      $mapModelo[$marca_id][$k] = $id;
      return $id;
    };

    $getLocalId = function(string $v) use (&$mapEmp){
      $v = trim($v);
      if ($v==='') return null;
      // se for só número, assume ID
      if (ctype_digit($v)) return (int)$v;
      $k = norm_key($v);
      return $mapEmp[$k] ?? null;
    };

    $getStatusId = function(string $v) use (&$mapStatus){
      $v = trim($v);
      if ($v==='') return 2; // EM_ESTOQUE padrão
      if (ctype_digit($v) && isset(array_flip($mapStatus)[(int)$v])) {
        return (int)$v;
      }
      $k = norm_key(str_replace(' ', '_', strtoupper($v)));
      return $mapStatus[$k] ?? 2;
    };

    $sql = "INSERT INTO moz_ativo (
              nome, cat_id, marca_id, modelo_id,
              tag_patrimonial, numero_serie,
              status_id, local_id, fornecedor_id,
              nf_numero, data_compra, garantia_ate,
              custo_aquisicao, depreciacao_meses,
              centro_custo, responsavel, observacoes,
              ativo
            ) VALUES (
              ?, ?, ?, ?,
              ?, ?,
              ?, ?, ?,
              ?, ?, ?,
              ?, ?,
              ?, ?, ?,
              1
            )";

    $st = $dbc->prepare($sql);
    if (!$st) {
      echo '<div class="card"><div class="alert alert-danger">Erro ao preparar INSERT de ativo: '.h($dbc->error).'</div></div>';
    } else {
      $ok=0; $fail=0; $msgs=[];

      foreach ($rows as $i=>$r) {
        // coleta valores texto
        $linha = [];
        foreach ($fields as $f) {
          $linha[$f] = isset($r[$f]) ? trim((string)$r[$f]) : '';
        }

        if ($linha['nome']==='') {
          $fail++;
          $msgs[] = "Linha ".($i+1).": ignorada (nome em branco).";
          continue;
        }

        // resolve IDs e normalizações
        $cat_id   = $getCatId($linha['categoria']);
        $marca_id = $getMarcaId($linha['marca']);
        $forn_id  = $getFornecedorId($linha['fornecedor']);
        $status_id= $getStatusId($linha['status']);
        $local_id = $getLocalId($linha['local']);
        $modelo_id= $getModeloId($linha['modelo'], $marca_id, $cat_id);

        $nf_numero     = $linha['nf_numero'] ?: null;
        $data_compra   = parse_date_flex($linha['data_compra']);
        $garantia_ate  = parse_date_flex($linha['garantia_ate']);
        $custo         = parse_decimal($linha['custo_aquisicao']);
        $depr_meses    = ($linha['depreciacao_meses']!=='') ? (int)$linha['depreciacao_meses'] : null;
        $centro_custo  = $linha['centro_custo'] ?: null;
        $responsavel   = $linha['responsavel'] ?: null;
        $obs           = $linha['observacoes'] ?: null;
        $tag           = $linha['tag_patrimonial'] ?: null;
        $num_serie     = $linha['numero_serie'] ?: null;

        // bind
        $types = 'siiissiiissssisss';
        $st->bind_param(
          $types,
          $linha['nome'],     // s
          $cat_id,            // i
          $marca_id,          // i
          $modelo_id,         // i
          $tag,               // s
          $num_serie,         // s
          $status_id,         // i
          $local_id,          // i
          $forn_id,           // i
          $nf_numero,         // s
          $data_compra,       // s
          $garantia_ate,      // s
          $custo,             // s
          $depr_meses,        // i
          $centro_custo,      // s
          $responsavel,       // s
          $obs                // s
        );

        if (!$st->execute()) {
          $fail++;
          $msgs[] = "Linha ".($i+1).": erro ao inserir ativo '".h($linha['nome'])."': ".$st->error;
        } else {
          $ok++;
          $msgs[] = "Linha ".($i+1).": importado ativo '".h($linha['nome'])."' (ID ".$st->insert_id.")";
        }
      }

      $st->close();

      echo '<div class="card">';
      echo '<p class="subtitle">Resultado da importação</p>';
      echo '<div class="stack">';
      echo '<div class="alert alert-info">Sucesso: '.$ok.' | Falhas: '.$fail.'</div>';
      if ($create_errors) {
        echo '<div class="alert alert-warning"><strong>Atenção:</strong><br>'.implode('<br>', array_map('h',$create_errors)).'</div>';
      }
      echo '<ul>';
      foreach ($msgs as $m) {
        echo '<li>'.h($m).'</li>';
      }
      echo '</ul>';
      echo '<div class="row" style="justify-content:flex-end;gap:8px;margin-top:10px;">';
      echo '<a class="btn" href="ativos-listar.php">Ir para lista de ativos</a>';
      echo '<a class="btn" href="ativos-importar.php">Nova importação</a>';
      echo '</div>';
      echo '</div></div>';
    }
  }
?>

<?php endif; // step ?>

  </div></session>
</div></div>

<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
