<?php
// pages/conversas-link-ticket.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once __DIR__ . '/../includes/wpp_guard.php';

proteger_pagina();
wpp_guard_require_capability('wpp_chats_view'); // ou outro mais específico se quiser

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/public/modules/wpp_chat/pages/conversas.php');
    exit;
}

$conversa_id = isset($_POST['conversa_id']) ? (int)$_POST['conversa_id'] : 0;
$tipo        = $_POST['tipo'] ?? '';
$ticket_id   = trim($_POST['ticket_id'] ?? '');

if ($conversa_id <= 0 || !in_array($tipo, ['helpdesk','glpi'], true)) {
    header('Location: ' . BASE_URL . '/public/modules/wpp_chat/pages/conversas.php');
    exit;
}

if ($ticket_id === '') {
    // Limpa o vínculo se veio vazio
    if ($tipo === 'helpdesk') {
        $stmt = $conn->prepare("UPDATE moz_wpp_conversa SET ticket_helpdesk_id = NULL WHERE id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE moz_wpp_conversa SET ticket_glpi_id = NULL WHERE id = ?");
    }
    $stmt->bind_param('i', $conversa_id);
    $stmt->execute();
} else {
    $ticketInt = (int)$ticket_id;
    if ($tipo === 'helpdesk') {
        $stmt = $conn->prepare("UPDATE moz_wpp_conversa SET ticket_helpdesk_id = ? WHERE id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE moz_wpp_conversa SET ticket_glpi_id = ? WHERE id = ?");
    }
    $stmt->bind_param('ii', $ticketInt, $conversa_id);
    $stmt->execute();
}

// Volta para o chat da conversa
header('Location: ' . BASE_URL . '/public/modules/wpp_chat/pages/conversas-chat.php?id=' . $conversa_id);
exit;
