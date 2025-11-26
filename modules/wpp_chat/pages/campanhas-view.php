<?php
// pages/campanhas-view.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once ROOT_PATH . '/system/includes/header.php';
require_once __DIR__ . '/../includes/wpp_guard.php';
require_once __DIR__ . '/../includes/wpp_campaigns.php';
require_once __DIR__ . '/../includes/wpp_client.php';

proteger_pagina();
wpp_guard_require_capability('wpp_campaigns_view');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$campanha = $id ? wpp_campanha_find($conn, $id) : null;
if (!$campanha) {
    echo '<div class="container-fluid moz-content"><div class="alert alert-danger">Campanha não encontrada.</div></div>';
    require_once ROOT_PATH . '/system/includes/footer.php';
    exit;
}

$destinatarios = wpp_campanha_destinatarios_find($conn, $campanha['id']);

?>
<div class="container-fluid moz-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Campanha #<?php echo (int)$campanha['id']; ?> – <?php echo htmlspecialchars($campanha['nome']); ?></h1>
    <div class="btn-group btn-group-sm">
      <a href="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/campanhas-list.php"
         class="btn btn-outline-secondary">Voltar</a>
      <a href="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/campanhas-form.php?id=<?php echo (int)$campanha['id']; ?>"
         class="btn btn-outline-primary">Editar</a>
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-md-4">
      <div class="card card-body">
        <h5 class="card-title mb-1">Resumo</h5>
        <p class="mb-1"><strong>Instância:</strong> <?php echo htmlspecialchars($campanha['instancia_nome']); ?></p>
        <p class="mb-1"><strong>Status:</strong>
          <span class="badge bg-secondary"><?php echo htmlspecialchars($campanha['status']); ?></span>
        </p>
        <p class="mb-1"><strong>Tipo:</strong> <?php echo htmlspecialchars($campanha['tipo_disparo']); ?></p>
        <p class="mb-1"><strong>Data agendada:</strong> <?php echo htmlspecialchars($campanha['data_agendada']); ?></p>
        <p class="mb-1"><strong>Criada em:</strong> <?php echo htmlspecialchars($campanha['created_at']); ?></p>
      </div>
    </div>
    <div class="col-md-8">
      <div class="card card-body">
        <h5 class="card-title mb-1">Ações</h5>
        <p class="text-muted">Disparo da campanha via WPPConnect.</p>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-success btn-sm"
                  id="btnDispararCampanha"
                  data-campanha-id="<?php echo (int)$campanha['id']; ?>">
            Disparar agora
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm"
                  id="btnTestarPrimeiro"
                  data-campanha-id="<?php echo (int)$campanha['id']; ?>">
            Enviar teste para primeiro número
          </button>
        </div>
        <div id="campanhaDisparoLog" class="mt-2 small text-monospace"></div>
      </div>
    </div>
  </div>

  <h5>Destinatários (<?php echo count($destinatarios); ?>)</h5>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Telefone</th>
          <th>Nome</th>
          <th>Status envio</th>
          <th>Envio em</th>
          <th>Mensagem resposta</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$destinatarios): ?>
          <tr><td colspan="6" class="text-muted text-center">Nenhum destinatário cadastrado.</td></tr>
        <?php else: foreach ($destinatarios as $d): ?>
          <tr>
            <td><?php echo (int)$d['id']; ?></td>
            <td><?php echo htmlspecialchars($d['contato_telefone']); ?></td>
            <td><?php echo htmlspecialchars($d['contato_nome']); ?></td>
            <td><?php echo htmlspecialchars($d['status_envio']); ?></td>
            <td><?php echo htmlspecialchars($d['enviado_em']); ?></td>
            <td><?php echo htmlspecialchars($d['mensagem_resposta']); ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="<?php echo BASE_URL; ?>/public/modules/wpp_chat/assets/js/wpp_chat.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    WPPChat.initCampanhaView();
  });
</script>

<?php require_once ROOT_PATH . '/system/includes/footer.php'; ?>
