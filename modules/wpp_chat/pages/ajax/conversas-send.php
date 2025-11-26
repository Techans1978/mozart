<?php
// pages/ajax/conversas-send.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once __DIR__ . '/../../includes/wpp_guard.php';
require_once __DIR__ . '/../../includes/wpp_conversas.php';
require_once __DIR__ . '/../../includes/wpp_client.php';

proteger_pagina();
wpp_guard_require_capability('wpp_chats_send');

$conversa_id = isset($_POST['conversa_id']) ? (int)$_POST['conversa_id'] : 0;
$texto       = trim($_POST['texto'] ?? '');

if (!$conversa_id || $texto === '') {
    echo json_encode(['ok' => false, 'msg' => 'Dados inválidos.']);
    exit;
}

$conversa = wpp_conversa_find($conn, $conversa_id);
if (!$conversa) {
    echo json_encode(['ok' => false, 'msg' => 'Conversa não encontrada.']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM moz_wpp_instance WHERE id = ?");
$stmt->bind_param('i', $conversa['instancia_id']);
$stmt->execute();
$inst = $stmt->get_result()->fetch_assoc();
if (!$inst) {
    echo json_encode(['ok' => false, 'msg' => 'Instância WPP não encontrada.']);
    exit;
}

$client = new WPPClient($inst['server_url'], (int)$inst['server_port'], $inst['server_secret'], $inst['session_name']);
$resp   = $client->sendText($conversa['contato_telefone'], $texto);
$wppId  = !empty($resp['data']['key']['id']) ? $resp['data']['key']['id'] : null;

wpp_conversa_add_msg($conn, $conversa_id, 'out', $texto, 'text', $wppId, $resp);

echo json_encode(['ok' => true, 'msg' => 'Enviado.']);
