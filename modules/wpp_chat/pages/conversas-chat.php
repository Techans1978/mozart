<?php
// pages/conversas-chat.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once ROOT_PATH . '/system/includes/header.php';
require_once __DIR__ . '/../includes/wpp_guard.php';
require_once __DIR__ . '/../includes/wpp_conversas.php';
require_once __DIR__ . '/../includes/wpp_client.php';
require_once __DIR__ . '/../includes/wpp_clientes.php';

proteger_pagina();
wpp_guard_require_capability('wpp_chats_view');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$conversa = wpp_conversa_find($conn, $id);
if (!$conversa) {
    echo '<div class="container-fluid moz-content"><div class="alert alert-danger">Conversa não encontrada.</div></div>';
    require_once ROOT_PATH . '/system/includes/footer.php';
    exit;
}

// Mensagens da conversa
$msgs = wpp_conversa_msgs($conn, $id);

// ================== FICHA DO CLIENTE ==================
$cliente         = null;
$clienteLogs     = [];
$behaviorLogs    = [];

// 1) Se a conversa já tem cliente_id, busca direto
if (!empty($conversa['cliente_id'])) {
    $cliente = wpp_cliente_find_by_id($conn, (int)$conversa['cliente_id']);
    if ($cliente) {
        $clienteLogs  = wpp_cliente_logs($conn, (int)$cliente['id']);
        $behaviorLogs = wpp_cliente_behavior_logs($conn, (int)$cliente['id'], 20);
    }
}

// 2) Se ainda não tem cliente vinculado, tenta criar/vincular pelo telefone
if (!$cliente && !empty($conversa['contato_telefone'])) {
    $novoId = wpp_cliente_touch_from_incoming(
        $conn,
        $conversa['contato_telefone'],
        $conversa['contato_nome'] ?? ''
    );
    if ($novoId) {
        $cliente      = wpp_cliente_find_by_id($conn, $novoId);
        $clienteLogs  = $cliente ? wpp_cliente_logs($conn, (int)$cliente['id']) : [];
        $behaviorLogs = $cliente ? wpp_cliente_behavior_logs($conn, (int)$cliente['id'], 20) : [];
    }
}

// 3) Tenta enriquecer o nome usando wpp_contact (WhatsApp)
if ($cliente) {
    wpp_cliente_enrich_from_wpp_contact($conn, (int)$cliente['id'], $cliente['telefone'] ?? '');

    // Recarrega o cliente para refletir o novo nome
    $cliente = wpp_cliente_find_by_id($conn, (int)$cliente['id']) ?: $cliente;

    // Se atualizou o nome, refletir também no cabeçalho da conversa
    if (!empty($cliente['nome'])) {
        $conversa['contato_nome'] = $cliente['nome'];
    }
}
?>
<div class="container-fluid moz-content wpp-chat-page">

  <!-- Cabeçalho da conversa -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-0">
        <?php echo htmlspecialchars($conversa['contato_nome'] ?: $conversa['contato_telefone']); ?>
      </h1>
      <small class="text-muted">
        Instância: <?php echo htmlspecialchars($conversa['instancia_nome']); ?> —
        Telefone: <?php echo htmlspecialchars($conversa['contato_telefone']); ?>
      </small>
    </div>
    <a href="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/conversas.php"
       class="btn btn-outline-secondary btn-sm">Voltar</a>
  </div>

  <div class="row">
    <!-- COLUNA ESQUERDA: CHAT -->
    <div class="col-lg-8 mb-3">
      <div class="card">
        <div class="card-body wpp-chat-thread" id="wppChatThread"
             data-conversa-id="<?php echo (int)$conversa['id']; ?>">
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
        <div class="card-footer">
          <form id="wppChatSendForm" class="d-flex gap-2">
            <input type="hidden" name="conversa_id" value="<?php echo (int)$conversa['id']; ?>">
            <input type="text" name="texto" class="form-control" placeholder="Digite sua mensagem...">
            <button type="submit" class="btn btn-primary">Enviar</button>
          </form>
        </div>
      </div>
    </div>

    <!-- COLUNA DIREITA: FICHA DO CLIENTE / COMPORTAMENTO / TICKETS -->
    <div class="col-lg-4">
      <?php if ($cliente): ?>
        <!-- FICHA DO CLIENTE -->
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <strong>Ficha do Cliente</strong><br>
              <small class="text-muted">
                ID #<?php echo (int)$cliente['id']; ?>
              </small>
            </div>
            <div>
              <a href="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/clientes-view.php?id=<?php echo (int)$cliente['id']; ?>"
                 class="btn btn-sm btn-outline-primary">
                Ver completo
              </a>
            </div>
          </div>

          <div class="card-body small">
            <div class="mb-2">
              <div class="fw-semibold">
                <?php echo htmlspecialchars($cliente['nome'] ?: '[Sem nome]'); ?>
              </div>
              <div class="text-muted">
                <?php echo htmlspecialchars($cliente['telefone']); ?>
              </div>
            </div>

            <div class="mb-2">
              <div><strong>Tipo:</strong> <?php echo htmlspecialchars($cliente['tipo_cliente'] ?? ''); ?></div>
              <div><strong>Origem:</strong> <?php echo htmlspecialchars($cliente['origem'] ?? ''); ?></div>
            </div>

            <?php if (!empty($cliente['email'])): ?>
              <div class="mb-2">
                <strong>E-mail:</strong><br>
                <?php echo htmlspecialchars($cliente['email']); ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($cliente['documento'])): ?>
              <div class="mb-2">
                <strong>Documento:</strong><br>
                <?php echo htmlspecialchars($cliente['documento']); ?>
              </div>
            <?php endif; ?>

            <?php
              // Tags manuais
              $tagsManuais = [];
              if (!empty($cliente['tags'])) {
                  $tagsManuais = array_filter(array_map('trim', explode(',', $cliente['tags'])));
              }

              // Auto tags (comportamento)
              $tagsAuto = [];
              if (!empty($cliente['auto_tags'])) {
                  $tagsAuto = array_filter(array_map('trim', explode(',', $cliente['auto_tags'])));
              }
            ?>

            <?php if ($tagsManuais): ?>
              <div class="mb-2">
                <strong>Tags (manuais):</strong><br>
                <?php foreach ($tagsManuais as $tag): ?>
                  <span class="badge bg-secondary me-1 mb-1">
                    <?php echo htmlspecialchars($tag); ?>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if ($tagsAuto): ?>
              <div class="mb-2">
                <strong>Tags automáticas:</strong><br>
                <?php foreach ($tagsAuto as $tag): ?>
                  <span class="badge bg-info text-dark me-1 mb-1">
                    <?php echo htmlspecialchars($tag); ?>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($cliente['observacoes'])): ?>
              <div class="mb-2">
                <strong>Observações:</strong>
                <div class="border rounded p-2 bg-light" style="max-height: 140px; overflow-y:auto;">
                  <?php echo nl2br(htmlspecialchars($cliente['observacoes'])); ?>
                </div>
              </div>
            <?php endif; ?>

            <?php if (!empty($cliente['ultimo_contato'])): ?>
              <div class="mb-2 text-muted">
                <strong>Último contato:</strong>
                <?php echo htmlspecialchars($cliente['ultimo_contato']); ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- TICKETS RELACIONADOS -->
        <div class="card mb-3">
          <div class="card-header">
            <strong>Tickets relacionados</strong>
          </div>
          <div class="card-body small">

            <!-- Helpdesk interno -->
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span><strong>Helpdesk interno</strong></span>
                <?php if (!empty($conversa['ticket_helpdesk_id'])): ?>
                  <a href="<?php echo BASE_URL; ?>/public/modules/helpdesk/pages/ticket-view.php?id=<?php echo (int)$conversa['ticket_helpdesk_id']; ?>"
                     class="btn btn-xs btn-outline-primary" target="_blank">
                    Abrir
                  </a>
                <?php endif; ?>
              </div>
              <form action="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/conversas-link-ticket.php"
                    method="post" class="d-flex gap-2">
                <input type="hidden" name="conversa_id" value="<?php echo (int)$conversa['id']; ?>">
                <input type="hidden" name="tipo" value="helpdesk">
                <input type="text" name="ticket_id" class="form-control form-control-sm"
                       placeholder="ID do ticket"
                       value="<?php echo htmlspecialchars($conversa['ticket_helpdesk_id'] ?? ''); ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Vincular</button>
              </form>
              <small class="text-muted">
                Informe o ID do ticket já aberto no Helpdesk interno.
              </small>
            </div>

            <hr>

            <!-- GLPI -->
            <div>
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span><strong>GLPI</strong></span>
                <?php if (!empty($conversa['ticket_glpi_id'])): ?>
                  <!-- Ajuste a URL do GLPI abaixo para o seu ambiente -->
                  <a href="https://csc.superabconline.com.br/front/ticket.form.php?id=<?php echo (int)$conversa['ticket_glpi_id']; ?>"
                     class="btn btn-xs btn-outline-primary" target="_blank">
                    Abrir GLPI
                  </a>
                <?php endif; ?>
              </div>
              <form action="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/conversas-link-ticket.php"
                    method="post" class="d-flex gap-2">
                <input type="hidden" name="conversa_id" value="<?php echo (int)$conversa['id']; ?>">
                <input type="hidden" name="tipo" value="glpi">
                <input type="text" name="ticket_id" class="form-control form-control-sm"
                       placeholder="ID do ticket GLPI"
                       value="<?php echo htmlspecialchars($conversa['ticket_glpi_id'] ?? ''); ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Vincular</button>
              </form>
              <small class="text-muted">
                Informe o ID do ticket no GLPI (já existente).
              </small>
            </div>

          </div>
        </div>

        <?php if (!empty($behaviorLogs)): ?>
          <div class="card mb-3">
            <div class="card-header">
              <strong>Comportamento recente</strong>
            </div>
            <div class="card-body small" style="max-height: 220px; overflow-y:auto;">
              <ul class="list-unstyled mb-0">
                <?php foreach ($behaviorLogs as $ev): ?>
                  <li class="mb-2">
                    <div>
                      <strong><?php echo htmlspecialchars($ev['tipo_evento']); ?></strong>
                    </div>
                    <?php if (!empty($ev['descricao'])): ?>
                      <div><?php echo nl2br(htmlspecialchars($ev['descricao'])); ?></div>
                    <?php endif; ?>
                    <div class="text-muted">
                      <?php echo htmlspecialchars($ev['created_at']); ?>
                      <?php if (!empty($ev['conversa_id'])): ?>
                        — conversa #<?php echo (int)$ev['conversa_id']; ?>
                      <?php endif; ?>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($clienteLogs)): ?>
          <div class="card">
            <div class="card-header">
              <strong>Histórico de conversas</strong>
            </div>
            <div class="card-body small" style="max-height: 220px; overflow-y:auto;">
              <ul class="list-unstyled mb-0">
                <?php foreach (array_slice($clienteLogs, 0, 5) as $log):
                  $conv = $log['conversa'] ?? [];
                ?>
                  <li class="mb-2">
                    <div>
                      <strong>Conversa #<?php echo (int)($conv['id'] ?? 0); ?></strong>
                      <span class="text-muted">
                        (<?php echo htmlspecialchars($conv['status'] ?? ''); ?>)
                      </span>
                    </div>
                    <div class="text-muted">
                      <?php echo htmlspecialchars($conv['ultimo_msg_data'] ?? ($conv['created_at'] ?? '')); ?>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <div class="alert alert-warning">
          Nenhum cliente vinculado a esta conversa ainda.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="<?php echo BASE_URL; ?>/public/modules/wpp_chat/assets/js/wpp_chat.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function(){
    WPPChat.initChatPage();
  });
</script>

<?php require_once ROOT_PATH . '/system/includes/footer.php'; ?>
