<?php
// public/modules/wpp_chat/dashboard.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once ROOT_PATH . '/system/includes/head.php';
// require_once __DIR__ . '/includes/guard.php';

proteger_pagina();
// wpp_guard_require_capability('wpp_chats_view');

/**
 * Helpers locais
 */
function wpp_table_exists(mysqli $conn, string $tabela): bool {
    $tbl = $conn->real_escape_string($tabela);
    $res = $conn->query("SHOW TABLES LIKE '{$tbl}'");
    return $res && $res->num_rows > 0;
}

function wpp_count_rows(mysqli $conn, string $tabela, string $where = ''): int {
    if (!wpp_table_exists($conn, $tabela)) return 0;
    $sql = "SELECT COUNT(*) AS c FROM {$tabela}";
    if ($where !== '') {
        $sql .= " WHERE {$where}";
    }
    $res = $conn->query($sql);
    if (!$res) return 0;
    $row = $res->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

// Contadores principais
$totInstancias  = wpp_count_rows($conn, 'moz_wpp_instance');
$totConversas   = wpp_count_rows($conn, 'moz_wpp_conversa');
$totClientes    = wpp_count_rows($conn, 'moz_wpp_cliente');
$totCampanhas   = wpp_count_rows($conn, 'moz_wpp_campanha');
$totPendentes   = wpp_table_exists($conn, 'moz_wpp_queue')
    ? wpp_count_rows($conn, 'moz_wpp_queue', "status = 'pendente'")
    : 0;

// Últimas conversas (para um "recentes" simples)
$ultimasConversas = [];
if (wpp_table_exists($conn, 'moz_wpp_conversa')) {
    $sql = "
      SELECT c.id, c.contato_telefone, c.contato_nome, c.status, c.ultimo_msg_data, i.session_name AS instancia_nome
        FROM moz_wpp_conversa c
        LEFT JOIN moz_wpp_instance i ON i.id = c.instancia_id
       ORDER BY c.ultimo_msg_data DESC, c.created_at DESC
       LIMIT 10
    ";
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $ultimasConversas[] = $row;
        }
    }
}
?>
<div class="container-fluid moz-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">
      <i class="fa fa-whatsapp text-success"></i> WPP Chat – Dashboard
    </h1>
  </div>

  <div class="row">
    <!-- Cards de resumo -->
    <div class="col-md-3 col-sm-6 mb-3">
      <div class="card text-bg-light">
        <div class="card-body">
          <div class="text-muted small">Instâncias ativas</div>
          <div class="h3 mb-0"><?= (int)$totInstancias ?></div>
        </div>
      </div>
    </div>

    <div class="col-md-3 col-sm-6 mb-3">
      <div class="card text-bg-light">
        <div class="card-body">
          <div class="text-muted small">Conversas</div>
          <div class="h3 mb-0"><?= (int)$totConversas ?></div>
        </div>
      </div>
    </div>

    <div class="col-md-3 col-sm-6 mb-3">
      <div class="card text-bg-light">
        <div class="card-body">
          <div class="text-muted small">Clientes (CRM)</div>
          <div class="h3 mb-0"><?= (int)$totClientes ?></div>
        </div>
      </div>
    </div>

    <div class="col-md-3 col-sm-6 mb-3">
      <div class="card text-bg-light">
        <div class="card-body">
          <div class="text-muted small">Campanhas</div>
          <div class="h3 mb-0"><?= (int)$totCampanhas ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <!-- Fila / Webhook -->
    <div class="col-md-4 mb-3">
      <div class="card">
        <div class="card-header">
          Webhook & Fila
        </div>
        <div class="card-body">
          <?php if (wpp_table_exists($conn, 'moz_wpp_queue')): ?>
            <p class="mb-1">
              <strong>Eventos pendentes na fila:</strong>
              <?= (int)$totPendentes ?>
            </p>
            <p class="text-muted small mb-0">
              O processamento é feito pelo script
              <code>process_queue.php</code> (via CRON).
            </p>
          <?php else: ?>
            <p class="text-muted mb-0">
              Tabela <code>moz_wpp_queue</code> ainda não criada ou não encontrada.
            </p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Últimas conversas -->
    <div class="col-md-8 mb-3">
      <div class="card">
        <div class="card-header">
          Últimas conversas
        </div>
        <div class="card-body">
          <?php if (!$ultimasConversas): ?>
            <p class="text-muted mb-0">Nenhuma conversa encontrada.</p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-striped mb-0">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Contato</th>
                    <th>Instância</th>
                    <th>Status</th>
                    <th>Última mensagem</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($ultimasConversas as $c): ?>
                    <tr>
                      <td><?= (int)$c['id'] ?></td>
                      <td>
                        <?= htmlspecialchars($c['contato_nome'] ?: $c['contato_telefone']) ?><br>
                        <small class="text-muted"><?= htmlspecialchars($c['contato_telefone']) ?></small>
                      </td>
                      <td><?= htmlspecialchars($c['instancia_nome'] ?? '-') ?></td>
                      <td><?= htmlspecialchars($c['status'] ?? '-') ?></td>
                      <td><?= htmlspecialchars($c['ultimo_msg_data'] ?? '-') ?></td>
                      <td class="text-end">
                        <a class="btn btn-xs btn-outline-primary"
                           href="<?= BASE_URL ?>/public/modules/wpp_chat/conversas-chat.php?id=<?= (int)$c['id'] ?>">
                          Abrir
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once ROOT_PATH . '/system/includes/footer.php'; ?>
