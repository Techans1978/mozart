<?php
// pages/clientes-form.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once ROOT_PATH . '/system/includes/header.php';
require_once __DIR__ . '/../includes/wpp_guard.php';
require_once __DIR__ . '/../includes/wpp_clientes.php';

proteger_pagina();
wpp_guard_require_capability('wpp_clientes_edit');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cliente = $id ? wpp_cliente_find_by_id($conn, $id) : null;

$erro = '';
$ok   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id_salvo = wpp_cliente_save($conn, $_POST);
        $ok = 'Cliente salvo com sucesso.';
        $cliente = wpp_cliente_find_by_id($conn, $id_salvo);
        $id = $id_salvo;
    } catch (Throwable $e) {
        $erro = 'Erro ao salvar cliente: ' . $e->getMessage();
    }
}

$nome        = $cliente['nome'] ?? '';
$telefone    = $cliente['telefone'] ?? '';
$email       = $cliente['email'] ?? '';
$documento   = $cliente['documento'] ?? '';
$tipo_cli    = $cliente['tipo_cliente'] ?? '';
$origem      = $cliente['origem'] ?? 'manual';
$usuario_id  = $cliente['usuario_id'] ?? '';
$tags        = $cliente['tags'] ?? '';
$observacoes = $cliente['observacoes'] ?? '';
?>
<div class="container-fluid moz-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
      <?php echo $id ? 'Editar Cliente' : 'Novo Cliente'; ?>
    </h1>
    <a href="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/clientes-list.php"
       class="btn btn-outline-secondary btn-sm">Voltar</a>
  </div>

  <?php if ($erro): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
  <?php endif; ?>

  <?php if ($ok): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($ok); ?></div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">

    <div class="row">
      <div class="col-md-4 mb-3">
        <label class="form-label">Telefone (WhatsApp) *</label>
        <input type="text" name="telefone" class="form-control" required
               value="<?php echo htmlspecialchars($telefone); ?>">
        <small class="text-muted">Apenas números ou com máscara, será normalizado.</small>
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label">Nome</label>
        <input type="text" name="nome" class="form-control"
               value="<?php echo htmlspecialchars($nome); ?>">
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label">E-mail</label>
        <input type="email" name="email" class="form-control"
               value="<?php echo htmlspecialchars($email); ?>">
      </div>
    </div>

    <div class="row">
      <div class="col-md-4 mb-3">
        <label class="form-label">Documento (CPF/CNPJ)</label>
        <input type="text" name="documento" class="form-control"
               value="<?php echo htmlspecialchars($documento); ?>">
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label">Tipo de cliente</label>
        <input type="text" name="tipo_cliente" class="form-control"
               value="<?php echo htmlspecialchars($tipo_cli); ?>">
        <small class="text-muted">Ex.: PF, PJ, fornecedor, colaborador...</small>
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label">Origem</label>
        <input type="text" name="origem" class="form-control"
               value="<?php echo htmlspecialchars($origem); ?>">
      </div>
    </div>

    <div class="row">
      <div class="col-md-4 mb-3">
        <label class="form-label">Vincular a usuário do sistema (opcional)</label>
        <input type="number" name="usuario_id" class="form-control"
               value="<?php echo htmlspecialchars($usuario_id); ?>">
        <small class="text-muted">Pode virar select depois (usuarios.id).</small>
      </div>
      <div class="col-md-8 mb-3">
        <label class="form-label">Tags</label>
        <input type="text" name="tags" class="form-control"
               value="<?php echo htmlspecialchars($tags); ?>">
        <small class="text-muted">Use vírgulas: vip,loja01,cliente_app...</small>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Observações</label>
      <textarea name="observacoes" class="form-control" rows="4"><?php
        echo htmlspecialchars($observacoes);
      ?></textarea>
    </div>

    <button type="submit" class="btn btn-primary">Salvar cliente</button>
  </form>
</div>
<?php require_once ROOT_PATH . '/system/includes/footer.php'; ?>
