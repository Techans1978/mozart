<?php
// pages/scripts-builder.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once ROOT_PATH . '/system/includes/header.php';
require_once __DIR__ . '/../includes/wpp_guard.php';
require_once __DIR__ . '/../includes/wpp_scripts.php';

proteger_pagina();
wpp_guard_require_capability('wpp_scripts_edit');

$user_id = $_SESSION['user_id'] ?? 0;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$script = $id ? wpp_script_find($conn, $id) : null;

$erro = '';
$ok   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id_salvo = wpp_script_save($conn, $_POST, $user_id);
        $ok = 'Script salvo com sucesso.';
        $script = wpp_script_find($conn, $id_salvo);
        $id = $id_salvo;
    } catch (Throwable $e) {
        $erro = 'Erro ao salvar script: ' . $e->getMessage();
    }
}

$nome        = $script['nome'] ?? '';
$descricao   = $script['descricao'] ?? '';
$xml         = $script['xml_definicao'] ?? '';
$ativo       = (int)($script['ativo'] ?? 1);

?>
<div class="container-fluid moz-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
      <?php echo $id ? 'Editar Script' : 'Novo Script'; ?>
    </h1>
    <a href="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/scripts-list.php"
       class="btn btn-outline-secondary btn-sm">Voltar</a>
  </div>

  <?php if ($erro): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
  <?php endif; ?>

  <?php if ($ok): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($ok); ?></div>
  <?php endif; ?>

  <form method="post" id="formScript">
    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">

    <div class="mb-3">
      <label class="form-label">Nome do script *</label>
      <input type="text" name="nome" class="form-control" required
             value="<?php echo htmlspecialchars($nome); ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Descrição</label>
      <textarea name="descricao" class="form-control" rows="2"><?php
        echo htmlspecialchars($descricao);
      ?></textarea>
    </div>

    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" value="1" id="chkAtivo" name="ativo"
        <?php if($ativo) echo 'checked'; ?>>
      <label class="form-check-label" for="chkAtivo">
        Script ativo
      </label>
    </div>

    <hr>

    <div class="row">
      <div class="col-md-6 mb-3">
        <h5 class="h6">Construtor visual</h5>
        <div id="scriptBuilderQuestions" class="mb-2"></div>
        <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddQuest">
          + Adicionar pergunta
        </button>
      </div>
      <div class="col-md-6 mb-3">
        <h5 class="h6">XML do script</h5>
        <textarea name="xml_definicao" id="xml_definicao" class="form-control" rows="14"><?php
          echo htmlspecialchars($xml);
        ?></textarea>
        <small class="text-muted">
          Formato padrão:
          <code>&lt;script&gt;&lt;quest&gt;Pergunta&lt;/quest&gt;&lt;answer&gt;&lt;/answer&gt;...&lt;/script&gt;</code>
        </small>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Salvar script</button>
  </form>
</div>

<script src="<?php echo BASE_URL; ?>/public/modules/wpp_chat/assets/js/wpp_chat.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function(){
    WPPChat.initScriptBuilder(<?php echo json_encode($xml); ?>);
  });
</script>

<?php require_once ROOT_PATH . '/system/includes/footer.php'; ?>
