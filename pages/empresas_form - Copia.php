<?php
// Mostrar erros (dev)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
// Autenticação e conexão
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . 'system//config/connect.php';

// Conexão
$dbc = isset($conn_glpi) ? $conn_glpi : (isset($conn) ? $conn : null);
if (!$dbc) { die('Sem conexão GLPI.'); }
@$dbc->set_charset('utf8mb4');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Carrega dados se for edição
$empresa = null;
if ($id > 0) {
  $stmt = $dbc->prepare("SELECT * FROM empresas WHERE id = ?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $empresa = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

// Cabeçalhos e navbar
include_once ROOT_PATH . 'system//includes/head.php';
include_once ROOT_PATH . 'system//includes/navbar.php';
?>
<div id="page-wrapper">
  <div class="container-fluid">

    <div class="row"><div class="col-lg-12">
      <h1 class="page-header"><?= htmlspecialchars(APP_NAME) ?></h1>
    </div></div>

    <div class="row"><div class="col-lg-12">
      <h2><?= $id ? "Atualizar Empresa #{$id}" : "Cadastrar Empresa" ?></h2>

      <form id="formEmpresa" method="post" action="<?= BASE_URL ?>/system/actions/empresas_save.php">
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="row">
          <div class="col-md-8">
            <div class="form-group">
              <label>Nome Empresarial*</label>
              <input type="text" class="form-control" name="nome_empresarial" required
                value="<?= htmlspecialchars($empresa['nome_empresarial'] ?? '') ?>">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label>Nome Fantasia</label>
              <input type="text" class="form-control" name="nome_fantasia"
                value="<?= htmlspecialchars($empresa['nome_fantasia'] ?? '') ?>">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label>CNPJ*</label>
              <input type="text" class="form-control" name="cnpj" id="cnpj" required
                placeholder="00.000.000/0000-00"
                value="<?= htmlspecialchars($empresa['cnpj'] ?? '') ?>">
              <p class="help-block">Somente números ou com máscara. Validaremos no servidor.</p>
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              <label>CEP</label>
              <input type="text" class="form-control" name="cep" id="cep"
                placeholder="00000-000"
                value="<?= htmlspecialchars($empresa['cep'] ?? '') ?>">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label>Logradouro</label>
              <input type="text" class="form-control" name="endereco_logradouro"
                value="<?= htmlspecialchars($empresa['endereco_logradouro'] ?? '') ?>">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-2">
            <div class="form-group">
              <label>Número</label>
              <input type="text" class="form-control" name="endereco_numero"
                value="<?= htmlspecialchars($empresa['endereco_numero'] ?? '') ?>">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label>Bairro</label>
              <input type="text" class="form-control" name="endereco_bairro"
                value="<?= htmlspecialchars($empresa['endereco_bairro'] ?? '') ?>">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label>Cidade</label>
              <input type="text" class="form-control" name="endereco_cidade"
                value="<?= htmlspecialchars($empresa['endereco_cidade'] ?? '') ?>">
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              <label>UF</label>
              <input type="text" class="form-control" name="endereco_uf" maxlength="2"
                value="<?= htmlspecialchars($empresa['endereco_uf'] ?? '') ?>">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label>Telefone</label>
              <input type="text" class="form-control" name="telefone" id="telefone"
                placeholder="(00) 00000-0000"
                value="<?= htmlspecialchars($empresa['telefone'] ?? '') ?>">
            </div>
          </div>
          <div class="col-md-8">
            <div class="form-group">
              <label>E-mail</label>
              <input type="email" class="form-control" name="email"
                value="<?= htmlspecialchars($empresa['email'] ?? '') ?>">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-3">
            <div class="form-group">
              <label>Data de Constituição</label>
              <input type="date" class="form-control" name="data_constituicao"
                value="<?= htmlspecialchars($empresa['data_constituicao'] ?? '') ?>">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label>Capital Social</label>
              <input type="text" class="form-control" name="capital_social" id="capital_social"
                placeholder="0,00" value="<?= htmlspecialchars($empresa['capital_social'] ?? '') ?>">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label>Tipo Jurídico</label>
              <select class="form-control" name="tipo_juridico">
                <?php
                $tipos = ['MEI','LTDA','SA','EIRELI','SS','Cooperativa','Associação','Fundação','Outro'];
                $sel = $empresa['tipo_juridico'] ?? '';
                foreach ($tipos as $t) {
                  $s = ($sel===$t)?'selected':'';
                  echo "<option value=\"".htmlspecialchars($t)."\" $s>$t</option>";
                }
                ?>
                <option value="" <?= $sel===''?'selected':'';?>>(não informado)</option>
              </select>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label>NIRE</label>
              <input type="text" class="form-control" name="nire"
                value="<?= htmlspecialchars($empresa['nire'] ?? '') ?>">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-3">
            <div class="form-group">
              <label>Início das Atividades</label>
              <input type="date" class="form-control" name="inicio_atividades"
                value="<?= htmlspecialchars($empresa['inicio_atividades'] ?? '') ?>">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label>Porte da Empresa</label>
              <select class="form-control" name="porte">
                <?php
                $portes = ['Microempresa','Pequena','Média','Grande','Outro'];
                $sel = $empresa['porte'] ?? '';
                foreach ($portes as $p) {
                  $s = ($sel===$p)?'selected':'';
                  echo "<option value=\"".htmlspecialchars($p)."\" $s>$p</option>";
                }
                ?>
                <option value="" <?= $sel===''?'selected':'';?>>(não informado)</option>
              </select>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label>Qualificação do Responsável</label>
              <input type="text" class="form-control" name="qualificacao_responsavel"
                value="<?= htmlspecialchars($empresa['qualificacao_responsavel'] ?? '') ?>">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label>Matriz ou Filial</label>
              <select class="form-control" name="matriz_filial">
                <?php
                $mfList = ['Matriz','Filial','Outra'];
                $sel = $empresa['matriz_filial'] ?? 'Matriz';
                foreach ($mfList as $m) {
                  $s = ($sel===$m)?'selected':'';
                  echo "<option value=\"$m\" $s>$m</option>";
                }
                ?>
              </select>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Objeto Social</label>
          <textarea class="form-control" name="objeto_social" rows="4"><?= htmlspecialchars($empresa['objeto_social'] ?? '') ?></textarea>
        </div>

        <div class="row">
          <div class="col-md-3">
            <div class="form-group">
              <label>Ativa no sistema?</label>
              <select name="ativo" class="form-control">
                <?php
                  $ativo = isset($empresa['ativo']) ? (int)$empresa['ativo'] : 1;
                ?>
                <option value="1" <?= $ativo===1?'selected':'';?>>Sim</option>
                <option value="0" <?= $ativo===0?'selected':'';?>>Não</option>
              </select>
            </div>
          </div>
        </div>

        <div class="form-group" style="margin-top:15px;">
          <button type="submit" class="btn btn-success">Salvar</button>
          <a href="<?= BASE_URL ?>/pages/empresas_listar.php" class="btn btn-default">Voltar</a>
        </div>

      </form>
    </div></div>

  </div>
</div>

<?php include_once ROOT_PATH . 'system//includes/code_footer.php'; ?>

<script>
// Máscara CNPJ (leve)
$('#cnpj').on('input', function(){
  let v = $(this).val().replace(/\D/g,'').slice(0,14);
  if (v.length === 14) v = v.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, "$1.$2.$3/$4-$5");
  $(this).val(v);
});
// CEP
$('#cep').on('input', function(){
  let v = $(this).val().replace(/\D/g,'').slice(0,8);
  if (v.length > 5) v = v.replace(/^(\d{5})(\d{0,3})$/, "$1-$2");
  $(this).val(v);
});
// Telefone
$('#telefone').on('input', function(){
  let v = $(this).val().replace(/\D/g,'').slice(0,11);
  if (v.length <= 10) v = v.replace(/(\d{2})(\d{4})(\d{0,4})/, "($1) $2-$3");
  else v = v.replace(/(\d{2})(\d{5})(\d{0,4})/, "($1) $2-$3");
  $(this).val(v);
});
// Capital social (virgula)
$('#capital_social').on('input', function(){
  let v = $(this).val().replace(/[^\d,]/g,'');
  $(this).val(v);
});
</script>

<?php include_once ROOT_PATH . 'system//includes/footer.php'; ?>
