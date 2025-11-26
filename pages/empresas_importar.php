<?php
// pages/empresas_importar.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();

include_once ROOT_PATH . '/system/includes/head.php';
include_once ROOT_PATH . '/system/includes/navbar.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function so_digitos($s){ return preg_replace('/\D+/', '', (string)$s); }
function parse_date($v){
  $v = trim((string)$v);
  if ($v==='') return '';
  // YYYY-MM-DD
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)) return $v;
  // DD/MM/YYYY
  if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/',$v,$m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
  return $v; // tenta como veio
}
function to_bool_int($v){
  $v = mb_strtolower(trim((string)$v),'UTF-8');
  if ($v==='' ) return 1; // default ativo
  if (in_array($v, ['1','sim','s','yes','y','true','ativo'])) return 1;
  if (in_array($v, ['0','nao','não','n','no','false','inativo'])) return 0;
  return (int)$v;
}
function sniff_delimiter($path){
  $sample = file_get_contents($path, false, null, 0, 4096);
  $sc = substr_count($sample, ';');
  $cc = substr_count($sample, ',');
  return ($sc >= $cc) ? ';' : ',';
}

$step = $_POST['step'] ?? 'upload';

// Campos suportados para import (ordem amigável)
$fields = [
  'nome_empresarial','nome_fantasia','apelido','nome_interno','cnpj',
  'cep','endereco_logradouro','endereco_numero','endereco_bairro','endereco_cidade','endereco_uf',
  'telefone','email','site',
  'data_constituicao','inicio_atividades','capital_social','porte',
  'tipo_juridico','nire','qualificacao_responsavel','matriz_filial','ativo',
  'inscricao_estadual','inscricao_municipal','cnae_principal','regime_tributario','objeto_social'
];

?>
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12">
      <h1 class="page-header">Importar Lista de Empresas</h1>
    </div></div>

<?php if ($step==='upload'): ?>

  <div class="panel panel-default">
    <div class="panel-heading"><strong>Passo 1:</strong> Enviar arquivo CSV</div>
    <div class="panel-body">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="step" value="preview">
        <div class="form-group">
          <label>Selecione o arquivo CSV</label>
          <input type="file" name="csv" class="form-control" accept=".csv,text/csv" required>
          <p class="help-block">Use um arquivo CSV com cabeçalho. Delimitador “;” ou “,”.</p>
        </div>
        <button class="btn btn-primary" type="submit">Carregar</button>
        <a class="btn btn-default" href="<?= BASE_URL ?>/pages/empresas_listar.php">Voltar</a>
      </form>
    </div>
  </div>

<?php elseif ($step==='preview'):
  if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
    echo '<div class="alert alert-danger">Arquivo inválido.</div>';
  } else {
    // Salva temporário em sessão
    $tmpdir = sys_get_temp_dir();
    $tmpfile = tempnam($tmpdir, 'emp_csv_');
    move_uploaded_file($_FILES['csv']['tmp_name'], $tmpfile);

    $delim = sniff_delimiter($tmpfile);
    $fh = fopen($tmpfile, 'r');
    $rows = [];
    $header = null;
    while (($r = fgetcsv($fh, 0, $delim)) !== false) {
      // remove BOM
      if ($header===null) {
        if (isset($r[0])) $r[0] = preg_replace('/^\xEF\xBB\xBF/', '', $r[0]);
        $header = $r;
        continue;
      }
      if (count(array_filter($r, fn($x)=>trim((string)$x) !== ''))===0) continue;
      $rows[] = $r;
    }
    fclose($fh);

    // guarda em sessão
    $_SESSION['emp_import_tmp'] = [
      'tmpfile' => $tmpfile,
      'delim'   => $delim,
      'header'  => $header,
      'rows'    => $rows
    ];

    if (!$header || !$rows) {
      echo '<div class="alert alert-warning">Não foi possível ler cabeçalho ou não há linhas válidas no CSV.</div>';
      echo '<a class="btn btn-default" href="'.BASE_URL.'/pages/empresas_importar.php">Tentar novamente</a>';
    } else {
?>
  <div class="panel panel-default">
    <div class="panel-heading"><strong>Passo 2:</strong> Completar / editar antes de importar</div>
    <div class="panel-body">
      <form method="post">
        <input type="hidden" name="step" value="import">

        <div class="alert alert-info">
          Revise os dados abaixo. Você pode editar qualquer célula. Campos não presentes no CSV ficam em branco para completar.
        </div>

        <div class="table-responsive" style="max-height:60vh; overflow:auto; border:1px solid #ddd;">
          <table class="table table-striped table-condensed">
            <thead>
              <tr>
                <?php foreach ($fields as $f): ?>
                  <th><?= h($f) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php
              // Mapa (header → field) por similaridade simples
              $map = [];
              foreach ($header as $idx=>$col) {
                $k = mb_strtolower(preg_replace('/[^a-z0-9_]+/i','_', $col),'UTF-8');
                $map[$idx] = null;
                foreach ($fields as $f) {
                  if ($k === $f) { $map[$idx] = $f; break; }
                  // matches comuns
                  if ($k==='razao_social' && $f==='nome_empresarial') $map[$idx]=$f;
                  if ($k==='fantasia' && $f==='nome_fantasia') $map[$idx]=$f;
                }
              }

              foreach ($rows as $i=>$csvRow):
                // inicia linha vazia
                $line = array_fill_keys($fields, '');
                // preenche o que souber
                foreach ($csvRow as $cidx=>$val) {
                  $f = $map[$cidx] ?? null;
                  if ($f) $line[$f] = $val;
                }
              ?>
              <tr>
                <?php foreach ($fields as $f): ?>
                  <td>
                    <input type="text" class="form-control input-sm" name="rows[<?= $i ?>][<?= h($f) ?>]"
                           value="<?= h($line[$f]) ?>">
                  </td>
                <?php endforeach; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="text-right" style="margin-top:12px;">
          <button class="btn btn-success" type="submit">Importar agora</button>
          <a class="btn btn-default" href="<?= BASE_URL ?>/pages/empresas_importar.php">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
<?php
    } // endif header/rows
  } // endif file ok
?>

<?php elseif ($step==='import'):
  $rows = $_POST['rows'] ?? [];
  if (!$rows) {
    echo '<div class="alert alert-warning">Nada para importar.</div>';
    echo '<a class="btn btn-default" href="'.BASE_URL.'/pages/empresas_importar.php">Voltar</a>';
  } else {
    $ok=0; $fail=0; $msgs=[];
    foreach ($rows as $r) {
      // Normalizações
      $dados = [];
      foreach ($fields as $f) {
        $v = isset($r[$f]) ? trim((string)$r[$f]) : '';
        switch ($f) {
          case 'cnpj': $v = so_digitos($v); break;
          case 'cep':  $v = so_digitos($v); break;
          case 'endereco_uf': $v = strtoupper(substr($v,0,2)); break;
          case 'data_constituicao':
          case 'inicio_atividades': $v = parse_date($v); break;
          case 'ativo': $v = to_bool_int($v); break;
        }
        $dados[$f] = $v;
      }
      if ($dados['nome_empresarial']==='') { $dados['nome_empresarial']=''; } // deixa vazio se não veio
      // INSERT
      // valor padrão para o JSON de integrações
$codigos_integracao = '[]';

$sql = "INSERT INTO empresas (
          nome_empresarial, nome_fantasia, apelido, nome_interno, cnpj,
          cep, endereco_logradouro, endereco_numero, endereco_bairro,
          endereco_cidade, endereco_uf, telefone, email, site,
          data_constituicao, inicio_atividades, capital_social, porte,
          tipo_juridico, nire, qualificacao_responsavel, matriz_filial, ativo,
          inscricao_estadual, inscricao_municipal, cnae_principal, regime_tributario, objeto_social,
          codigos_integracao, created_at, updated_at
        ) VALUES (
          ?, ?, ?, ?, ?,
          ?, ?, ?, ?,
          ?, ?, ?, ?, ?,
          ?, ?, ?, ?,
          ?, ?, ?, ?, ?,
          ?, ?, ?, ?, ?,
          ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
        )";

$st = $conn->prepare($sql);
if (!$st) { $fail++; $msgs[] = 'Prepare: '.$conn->error; continue; }

/*
 * Tipos:
 *  - 22 strings iniciais
 *  - 1 inteiro (ativo)
 *  - 6 strings finais (insc_estadual, insc_municipal, cnae, regime, objeto, codigos_integracao)
 *
 * Total de parâmetros: 29
 */
$types = 'ssssssssssssssssssssssissssss'; // 22 's' + 'i' + 6 's'

$st->bind_param(
  $types,
  $dados['nome_empresarial'], $dados['nome_fantasia'], $dados['apelido'], $dados['nome_interno'], $dados['cnpj'],
  $dados['cep'], $dados['endereco_logradouro'], $dados['endereco_numero'], $dados['endereco_bairro'],
  $dados['endereco_cidade'], $dados['endereco_uf'], $dados['telefone'], $dados['email'], $dados['site'],
  $dados['data_constituicao'], $dados['inicio_atividades'], $dados['capital_social'], $dados['porte'],
  $dados['tipo_juridico'], $dados['nire'], $dados['qualificacao_responsavel'], $dados['matriz_filial'], $dados['ativo'],
  $dados['inscricao_estadual'], $dados['inscricao_municipal'], $dados['cnae_principal'], $dados['regime_tributario'], $dados['objeto_social'],
  $codigos_integracao
);

if (!$st->execute()) { $fail++; $msgs[] = 'Insert: '.$st->error; }
else { $ok++; }
$st->close();

    }

    echo '<div class="alert alert-info"><strong>Importação concluída.</strong><br>Sucesso: '.$ok.' | Falhas: '.$fail.'</div>';
    if ($msgs) {
      echo '<div class="panel panel-default"><div class="panel-heading">Mensagens</div><div class="panel-body"><pre style="white-space:pre-wrap">';
      foreach ($msgs as $m) echo h($m)."\n";
      echo '</pre></div></div>';
    }
    echo '<a class="btn btn-primary" href="'.BASE_URL.'/pages/empresas_listar.php">Ir para lista</a> ';
    echo '<a class="btn btn-default" href="'.BASE_URL.'/pages/empresas_importar.php">Nova importação</a>';
  }
?>

<?php endif; ?>

  </div>
</div>

<?php include_once ROOT_PATH . '/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>
