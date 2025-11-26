<?php
// pages/ajax/conversas-poll.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once __DIR__ . '/../../includes/wpp_guard.php';
require_once __DIR__ . '/../../includes/wpp_conversas.php';

proteger_pagina();
wpp_guard_require_capability('wpp_chats_view');

$conversa_id = isset($_GET['conversa_id']) ? (int)$_GET['conversa_id'] : 0;
if (!$conversa_id) {
    echo json_encode(['ok' => false, 'msg' => 'Conversa inválida.']);
    exit;
}
$msgs = wpp_conversa_msgs($conn, $conversa_id);
echo json_encode(['ok' => true, 'msgs' => $msgs]);
