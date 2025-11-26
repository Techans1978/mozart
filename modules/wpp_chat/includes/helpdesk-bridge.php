<?php
// public/modules/wpp_chat/includes/helpdesk-bridge.php

require_once __DIR__.'/../../../config.php';
require_once ROOT_PATH.'/system/config/connect.php';

/**
 * Local helper/helpdesk interno
 * Adapte para sua tabela hd_ticket / hd_mensagem etc.
 */

function moz_hd_open_ticket_from_wpp(mysqli $conn, $phone, $nome, $mensagem, $instance_id) {
    // TODO: ajustar tabela/colunas do seu helpdesk.
    // Exemplo mínimo:
    $stmt = $conn->prepare("
        INSERT INTO hd_ticket (titulo, descricao, origem, canal, telefone, nome_contato, status, created_at)
        VALUES (?, ?, 'wpp_chat', 'whatsapp', ?, ?, 'aberto', NOW())
    ");
    $titulo = 'WhatsApp de '.$nome;
    $stmt->bind_param('ssss', $titulo, $mensagem, $phone, $nome);
    $stmt->execute();
    $ticket_id = $stmt->insert_id;

    // Retorna ID para relacional na tabela de mensagens
    return $ticket_id;
}

function moz_hd_append_message_to_ticket(mysqli $conn, $ticket_id, $mensagem, $direcao) {
    // TODO: ajustar tabela de mensagens do helpdesk
    $stmt = $conn->prepare("
        INSERT INTO hd_ticket_msg (ticket_id, mensagem, direcao, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param('iss', $ticket_id, $mensagem, $direcao);
    $stmt->execute();
}
