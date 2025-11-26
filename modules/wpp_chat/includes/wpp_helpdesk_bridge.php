<?php
// includes/wpp_helpdesk_bridge.php

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../../../config.php';
}
require_once ROOT_PATH . '/system/config/connect.php';

/**
 * Apenas a "ponte": criar/atualizar ticket no helpdesk interno
 * Ajuste para o schema do seu módulo de helpdesk.
 */
function wpp_helpdesk_bridge_handle_incoming(
    mysqli $conn,
    int $conversa_id,
    string $telefone,
    string $mensagem,
    array $payload
): void {
    // Exemplo: criar ticket se conversa ainda não estiver vinculada
    $stmt = $conn->prepare("SELECT ticket_helpdesk_id FROM moz_wpp_conversa WHERE id = ?");
    $stmt->bind_param('i', $conversa_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $ticket_id = (int)($r['ticket_helpdesk_id'] ?? 0);

    if (!$ticket_id) {
        // criar ticket
        $titulo = 'WhatsApp de ' . $telefone;
        $sql = "
          INSERT INTO hd_ticket (titulo, descricao, origem, status, created_at)
          VALUES (?,?,?,?, NOW())
        ";
        $desc = "Mensagem inicial via WhatsApp:\n\n" . $mensagem;
        $origem = 'whatsapp';
        $status = 'aberto';
        $st = $conn->prepare($sql);
        $st->bind_param('ssss', $titulo, $desc, $origem, $status);
        $st->execute();
        $ticket_id = $st->insert_id;

        // vincular na conversa
        $st2 = $conn->prepare("UPDATE moz_wpp_conversa SET ticket_helpdesk_id = ? WHERE id = ?");
        $st2->bind_param('ii', $ticket_id, $conversa_id);
        $st2->execute();
    } else {
        // apenas inserir msg no histórico de mensagens do ticket
        $sql = "
          INSERT INTO hd_ticket_msg (ticket_id, mensagem, origem, created_at)
          VALUES (?,?, 'whatsapp', NOW())
        ";
        $st = $conn->prepare($sql);
        $st->bind_param('is', $ticket_id, $mensagem);
        $st->execute();
    }
}
