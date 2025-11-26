<?php
// pages/cadastrar_empresa.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

if (session_status()===PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$empresa_id = (int)($_GET['empresa_id'] ?? 0);

/** Base dos dados do form (todos campos do print/BD) */
$dados = [
  'nome_empresarial'       => '',
  'nome_fantasia'          => '',
  'apelido'                => '',          // NOVO
  'nome_interno'           => '',          // NOVO
  'cnpj'                   => '',
  'cep'                    => '',
  'endereco_logradouro'    => '',
  'endereco_numero'        => '',
  'endereco_bairro'        => '',
  'endereco_cidade'        => '',
  'endereco_uf'            => '',
  'telefone'               => '',
  'email'                  => '',
  'site'                   => '',
  'data_constituicao'      => '',
  'inicio_atividades'      => '',
  'capital_social'         => '',
  'porte'                  => '',
  'tipo_juridico'          => '',
  'nire'                   => '',
  'qualificacao_responsavel'=> '',
  'matriz_filial'          => 'Matriz',
  'ativo'                  => 1,
  'inscricao_estadual'     => '',
  'inscricao_municipal'    => '',
  'cnae_principal'         => '',
  'regime_tributario'      => '',
  'objeto_social'          => '',
  'codigos_integracao'     => []
];

/** Carrega do BD se edição */
if ($empresa_id>0){
  $sql = "SELECT
            id, nome_empresarial, nome_fantasia, apelido, nome_interno, cnpj, cep,
            endereco_logradouro, endereco_numero, endereco_bairro, endereco_cidade, endereco_uf,
            telefone, email, site, data_constituicao, inicio_atividades, capital_social, porte,
            tipo_juridico, nire, qualificacao_responsavel, matriz_filial, ativo,
            inscricao_estadual, inscricao_municipal, cnae_principal, regime_tributario, objeto_social,
            codigos_integracao
          FROM empresas WHERE id=?";
  $st = $conn->prepare($sql);
  $st->bind_param('i',$empresa_id);
  $st->execute();
  $r = $st->get_result()->fetch_assoc();
  $st->close();

  if ($r){
    foreach($dados as $k=>$v){
      if(array_key_exists($k,$r)) $dados[$k] = $r[$k];
    }
    if (!empty($r['codigos_integracao'])) {
      $decoded = json_decode($r['codigos_integracao'], true);
      if (is_array($decoded)) $dados['codigos_integracao'] = $decoded;
    }
  }
}

include_once ROOT_PATH . '/system/includes/head.php';
include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12">
      <h1 class="page-header">Cadastrar Empresa</h1>
    </div></div>

    <form method="post" action="<?= BASE_URL ?>/pages/empresas_save.php" onsubmit="return prepararCodigos()">
      <input type="hidden" name="id" value="<?= (int)$empresa_id ?>">
      <input type="hidden" id="codigos_integracao_json" name="codigos_integracao_json" value="[]">

      <div class="row">
        <div class="col-md-6">
          <div class="form-group">
            <label>Nome Empresarial *</label>
            <input type="text" class="form-control" name="nome_empresarial" required
                   value="<?= h($dados['nome_empresarial']) ?>">
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>Nome Fantasia</label>
            <input type="text" class="form-control" name="nome_fantasia"
                   value="<?= h($dados['nome_fantasia']) ?>">
          </div>
        </div>
      </div>

      <!-- Linha NOVA conforme pedido -->
      <div class="row">
        <div class="col-md-4">
          <div class="form-group">
            <label>Apelido</label>
            <input type="text" class="form-control" name="apelido"
                   value="<?= h($dados['apelido']) ?>">
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label>Nome Interno</label>
            <input type="text" class="form-control" name="nome_interno"
                   value="<?= h($dados['nome_interno']) ?>">
          </div>
        </div>
      </div>
      <!-- /Linha NOVA -->

      <div class="row">
        <div class="col-md-3">
          <div class="form-group">
            <label>CNPJ *</label>
            <input type="text" class="form-control" name="cnpj" placeholder="00.000.000/0000-00"
                   value="<?= h($dados['cnpj']) ?>">
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label>CEP</label>
            <input type="text" class="form-control" name="cep" placeholder="00000-000"
                   value="<?= h($dados['cep']) ?>">
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>Logradouro</label>
            <input type="text" class="form-control" name="endereco_logradouro"
                   value="<?= h($dados['endereco_logradouro']) ?>">
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-2">
          <div class="form-group">
            <label>Número</label>
            <input type="text" class="form-control" name="endereco_numero"
                   value="<?= h($dados['endereco_numero']) ?>">
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label>Bairro</label>
            <input type="text" class="form-control" name="endereco_bairro"
                   value="<?= h($dados['endereco_bairro']) ?>">
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label>Cidade</label>
            <input type="text" class="form-control" name="endereco_cidade"
                   value="<?= h($dados['endereco_cidade']) ?>">
          </div>
        </div>
        <div class="col-md-2">
          <div class="form-group">
            <label>UF</label>
            <input type="text" class="form-control" name="endereco_uf" maxlength="2"
                   value="<?= h($dados['endereco_uf']) ?>">
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-4">
          <div class="form-group">
            <label>Telefone</label>
            <input type="text" class="form-control" name="telefone" placeholder="(00) 00000-0000"
                   value="<?= h($dados['telefone']) ?>">
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label>E-mail</label>
            <input type="email" class="form-control" name="email"
                   value="<?= h($dados['email']) ?>">
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label>Site</label>
            <input type="url" class="form-control" name="site" placeholder="https://..."
                   value="<?= h($dados['site']) ?>">
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-3">
          <div class="form-group">
            <label>Data de Constituição</label>
            <input type="date" class="form-control" name="data_constituicao"
                   value="<?= h($dados['data_constituicao']) ?>">
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label>Início das Atividades</label>
            <input type="date" class="form-control" name="inicio_atividades"
                   value="<?= h($dados['inicio_atividades']) ?>">
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label>Capital Social</label>
            <input type="text" class="form-control" name="capital_social" placeholder="0,00"
                   value="<?= h($dados['capital_social']) ?>">
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label>Porte</label>
            <input type="text" class="form-control" name="porte"
                   value="<?= h($dados['porte']) ?>">
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-3">
          <div class="form-group">
            <label>Tipo Jurídico</label>
            <input type="text" class="form-control" name="tipo_juridico"
                   value="<?= h($dados['tipo_juridico']) ?>">
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label>NIRE</label>
            <input type="text" class="form-control" name="nire"
                   value="<?= h($dados['nire']) ?>">
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>Qualificação do Responsável</label>
            <input type="text" class="form-control" name="qualificacao_responsavel"
                   value="<?= h($dados['qualificacao_responsavel']) ?>">
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-3">
          <div class="form-group">
            <label>Matriz/Filial</label>
            <select class="form-control" name="matriz_filial">
              <?php
                $opts = ['Matriz'=>'Matriz','Filial'=>'Filial','Outros'=>'Outros'];
                foreach($opts as $val=>$lab){
                  $sel = ($dados['matriz_filial']===$val)?'selected':'';
                  echo "<option value=\"".h($val)."\" $sel>".h($lab)."</option>";
                }
              ?>
            </select>
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label>Ativo</label>
            <select class="form-control" name="ativo">
              <option value="1" <?= !empty($dados['ativo'])?'selected':''; ?>>Sim</option>
              <option value="0" <?= empty($dados['ativo'])?'selected':''; ?>>Não</option>
            </select>
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label>Inscrição Estadual</label>
            <input type="text" class="form-control" name="inscricao_estadual"
                   value="<?= h($dados['inscricao_estadual']) ?>">
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label>Inscrição Municipal</label>
            <input type="text" class="form-control" name="inscricao_municipal"
                   value="<?= h($dados['inscricao_municipal']) ?>">
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-6">
          <div class="form-group">
            <label>CNAE Principal</label>
            <input type="text" class="form-control" name="cnae_principal"
                   value="<?= h($dados['cnae_principal']) ?>">
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>Regime Tributário</label>
            <input type="text" class="form-control" name="regime_tributario"
                   value="<?= h($dados['regime_tributario']) ?>">
          </div>
        </div>
      </div>

      <div class="form-group">
        <label>Objeto Social</label>
        <textarea class="form-control" rows="4" name="objeto_social"><?= h($dados['objeto_social']) ?></textarea>
      </div>

      <!-- Códigos de Integração (múltiplos) -->
      <div class="panel panel-default">
        <div class="panel-heading"><strong>Códigos de Integração</strong></div>
        <div class="panel-body">
          <div id="codigosWrap"></div>
          <button class="btn btn-sm btn-default" type="button" onclick="addCodigo()">+ Adicionar código</button>
        </div>
      </div>

      <button class="btn btn-primary" type="submit">Salvar Empresa</button>
      <a class="btn btn-default" href="<?= BASE_URL ?>/pages/empresas_listar.php">Voltar</a>
    </form>

    <div class="text-center" style="margin-top:16px; opacity:.7;">
      <small>© 2025 Ti Sistemas e Digital - Grupo ABC. Todos os direitos reservados.</small>
    </div>
  </div>
</div>

<script>
var codigos = <?= json_encode(array_values($dados['codigos_integracao'] ?? [])) ?>;

function renderCodigos(){
  var wrap = document.getElementById('codigosWrap');
  wrap.innerHTML = '';
  codigos.forEach(function(v,idx){
    var row = document.createElement('div');
    row.className = 'form-inline';
    row.style.marginBottom = '6px';
    row.innerHTML =
      '<input type="text" class="form-control" style="min-width:280px" '+
      'oninput="codigos['+idx+']=this.value" value="'+(v?String(v).replace(/"/g,'&quot;'):'')+'"> '+
      '<button type="button" class="btn btn-danger btn-sm" onclick="delCodigo('+idx+')">Remover</button>';
    wrap.appendChild(row);
  });
}
function addCodigo(){ codigos.push(''); renderCodigos(); }
function delCodigo(i){ codigos.splice(i,1); renderCodigos(); }
function prepararCodigos(){
  document.getElementById('codigos_integracao_json').value = JSON.stringify(
    codigos.filter(function(v){ return String(v||'').trim()!==''; })
  );
  return true;
}
renderCodigos();
</script>

<?php include_once ROOT_PATH . '/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>
