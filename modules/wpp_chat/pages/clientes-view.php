<?php
// pages/clientes-view.php
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
wpp_guard_require_capability('wpp_clientes_view');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cliente = $id ? wpp_cliente_find_by_id($conn, $id) : null;

if (!$cliente) {
    echo '<div class="container-fluid moz-content"><div class="alert alert-danger">Cliente não encontrado.</div></div>';
    require_once ROOT_PATH . '/system/includes/footer.php';
    exit;
}

$logs = wpp_cliente_logs($conn, $id);
?>
<div class="container-fluid moz-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
      Cliente #<?php echo (int)$cliente['id']; ?> – <?php echo htmlspecialchars($cliente['nome'] ?: $cliente['telefone']); ?>
    </h1>
    <div class="btn-group btn-group-sm">
      <a href="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/clientes-list.php"
         class="btn btn-outline-secondary">Voltar</a>
      <a href="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/clientes-form.php?id=<?php echo (int)$cliente['id']; ?>"
         class="btn btn-outline-primary">Editar</a>
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-md-4">
      <div class="card card-body">
        <h5 class="card-title mb-2">Dados do cliente</h5>
        <p class="mb-1"><strong>Telefone:</strong> <?php echo htmlspecialchars($cliente['telefone']); ?></p>
        <p class="mb-1"><strong>Nome:</strong> <?php echo htmlspecialchars($cliente['nome']); ?></p>
        <p class="mb-1"><strong>E-mail:</strong> <?php echo htmlspecialchars($cliente['email']); ?></p>
        <p class="mb-1"><strong>Documento:</strong> <?php echo htmlspecialchars($cliente['documento']); ?></p>
        <p class="mb-1"><strong>Tipo:</strong> <?php echo htmlspecialchars($cliente['tipo_cliente']); ?></p>
        <p class="mb-1"><strong>Origem:</strong> <?php echo htmlspecialchars($cliente['origem']); ?></p>
        <p class="mb-1"><strong>Tags:</strong> <?php echo htmlspecialchars($cliente['tags']); ?></p>
        <p class="mb-1"><strong>Último contato:</strong> <?php echo htmlspecialchars($cliente['ultimo_contato']); ?></p>
      </div>
    </div>
    <div class="col-md-8">
      <div class="card card-body">
        <h5 class="card-title mb-2">Observações</h5>
        <p class="mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($cliente['observacoes']); ?></p>
      </div>
    </div>
  </div>

  <h5 class="mb-2">Histórico de conversas</h5>

  <?php if (!$logs): ?>
    <div class="alert alert-info">Nenhuma conversa registrada para este cliente.</div>
  <?php else: ?>
    <?php foreach ($logs as $log): ?>
      <?php $conv = $log['conversa']; $msgs = $log['mensagens']; ?>
      <div class="card mb-3">
        <div class="card-header">
          Conversa #<?php echo (int)$conv['id']; ?>
          &mdash; Instância: <?php echo htmlspecialchars($conv['instancia_id']); ?>
          &mdash; Criada em: <?php echo htmlspecialchars($conv['created_at']); ?>
        </div>
        <div class="card-body wpp-chat-thread" style="max-height: 300px; overflow-y:auto;">
          <?php foreach ($msgs as $m): ?>
            <div class="wpp-chat-msg wpp-chat-msg-<?php echo $m['direction'] === 'out' ? 'out' : 'in'; ?>">
              <div class="wpp-chat-msg-bubble">
                <div class="wpp-chat-msg-text"><?php echo nl2br(htmlspecialchars($m['conteudo'])); ?></div>
                <div class="wpp-chat-msg-meta">
                  <small><?php echo htmlspecialchars($m['data_msg']); ?></small>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php require_once ROOT_PATH . '/system/includes/footer.php'; ?>
