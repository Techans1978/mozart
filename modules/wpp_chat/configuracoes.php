<?php
// public/modules/wpp_chat/configuracoes.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once ROOT_PATH . '/system/includes/header.php';
// require_once __DIR__ . '/includes/guard.php';

proteger_pagina();
// wpp_guard_require_capability('wpp_chats_view');

// helper para verificar se a tabela existe
function wpp_table_exists(mysqli $conn, string $tabela): bool {
    $tbl = $conn->real_escape_string($tabela);
    $res = $conn->query("SHOW TABLES LIKE '{$tbl}'");
    return $res && $res->num_rows > 0;
}

// Carrega instâncias para sugerir URLs de webhook
$instancias = [];
if (wpp_table_exists($conn, 'moz_wpp_instance')) {
    $sql = "SELECT id, nome, session_name FROM moz_wpp_instance ORDER BY nome";
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $instancias[] = $row;
        }
    }
}

// Monta base para o webhook (ajuste se o path no seu ambiente for diferente)
$webhookBase = rtrim(BASE_URL, '/') . '/public/modules/wpp_chat/webhook.php?session=';
?>
<div class="container-fluid moz-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">
      <i class="fa fa-cog"></i> Configurações – WPP Chat
    </h1>
  </div>

  <div class="row">
    <div class="col-lg-6 mb-3">
      <div class="card">
        <div class="card-header">
          Como configurar o WPP Chat
        </div>
        <div class="card-body">
          <p>
            As principais configurações do módulo WPP Chat estão distribuídas em:
          </p>
          <ul>
            <li><strong>Instâncias:</strong> cadastro de sessões/conexões do WPPConnect.</li>
            <li><strong>Campanhas:</strong> definição de mensagens e triggers por <em>tag</em>.</li>
            <li><strong>Scripts:</strong> fluxos de atendimento baseados em roteiros (quando em uso).</li>
          </ul>
          <p class="mb-1">
            A integração com o WPPConnect é feita por meio do endpoint:
          </p>
          <pre class="small mb-2"><?= htmlspecialchars($webhookBase) ?>NOME_DA_SESSION</pre>
          <p class="text-muted small mb-0">
            Substitua <code>NOME_DA_SESSION</code> pelo valor exato do campo
            <code>session_name</code> cadastrado em <strong>Instâncias</strong>.
          </p>
        </div>
      </div>
    </div>

    <div class="col-lg-6 mb-3">
      <div class="card">
        <div class="card-header">
          Instâncias cadastradas & URLs sugeridas
        </div>
        <div class="card-body">
          <?php if (!$instancias): ?>
            <p class="text-muted mb-0">
              Nenhuma instância cadastrada em <code>moz_wpp_instance</code>.
              Use o menu <strong>Instâncias</strong> para adicionar pelo menos uma.
            </p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-striped mb-0">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Session</th>
                    <th>Webhook (sugestão)</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($instancias as $inst): ?>
                    <tr>
                      <td><?= (int)$inst['id'] ?></td>
                      <td><?= htmlspecialchars($inst['nome'] ?? '') ?></td>
                      <td><?= htmlspecialchars($inst['session_name'] ?? '') ?></td>
                      <td class="small">
                        <code><?= htmlspecialchars($webhookBase . ($inst['session_name'] ?? '')) ?></code>
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

  <div class="row">
    <!-- Informação sobre a fila / CRON -->
    <div class="col-lg-12 mb-3">
      <div class="card">
        <div class="card-header">
          Processamento da fila (moz_wpp_queue)
        </div>
        <div class="card-body">
          <p class="mb-1">
            O processamento das mensagens recebidas via webhook é feito pela fila
            <code>moz_wpp_queue</code>, através do script:
          </p>
          <pre class="small mb-2">public/modules/wpp_chat/process_queue.php</pre>
          <p class="mb-1">
            Recomenda-se configurar uma entrada de CRON semelhante a:
          </p>
<pre class="small mb-0">* * * * * php /caminho/para/public/modules/wpp_chat/process_queue.php &gt;/dev/null 2&gt;&1</pre>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once ROOT_PATH . '/system/includes/footer.php'; ?>
