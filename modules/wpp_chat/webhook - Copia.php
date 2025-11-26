<?php
// webhook.php - endpoint para WPPConnect
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/wpp_conversas.php';
require_once __DIR__ . '/includes/wpp_helpdesk_bridge.php';
require_once __DIR__ . '/includes/wpp_glpi_bridge.php';
require_once __DIR__ . '/includes/wpp_clientes.php';
...
if ($event === 'message') {
    $from    = $data['from'] ?? '';
    $bodyMsg = $data['body'] ?? '';
    $msgId   = $data['id']['_serialized'] ?? ($data['id'] ?? '');
    $nome    = $data['sender']['pushname'] ?? ''; // depende do formato do seu WPPConnect

    // Descobrir instancia_id pela session_name
    $stmt = $conn->prepare("SELECT id FROM moz_wpp_instance WHERE session_name = ? LIMIT 1");
    $stmt->bind_param('s', $session);
    $stmt->execute();
    $inst = $stmt->get_result()->fetch_assoc();
    if (!$inst) {
        http_response_code(200);
        echo 'Instance not mapped';
        exit;
    }
    $instancia_id = (int)$inst['id'];

    // Cria / atualiza cliente
    $cliente_id = wpp_cliente_touch_from_incoming($conn, $from, $nome);

    // Cria / recupera conversa
    $conversa_id = wpp_conversa_find_or_create($conn, $instancia_id, $from, $nome);

    // Opcional: vincular conversa ao cliente_id se tiver coluna
    if ($cliente_id) {
        $stmtC = $conn->prepare("UPDATE moz_wpp_conversa SET cliente_id = ? WHERE id = ?");
        $stmtC->bind_param('ii', $cliente_id, $conversa_id);
        $stmtC->execute();
    }

    // Salva mensagem
    wpp_conversa_add_msg($conn, $conversa_id, 'in', $bodyMsg, 'text', $msgId, $payload);

    // Bridge para Helpdesk interno / GLPI
    wpp_helpdesk_bridge_handle_incoming($conn, $conversa_id, $from, $bodyMsg, $payload);
    wpp_glpi_bridge_handle_incoming($conn, $conversa_id, $from, $bodyMsg, $payload);

    http_response_code(200);
    echo 'OK';
    exit;
}
