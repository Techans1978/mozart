<?php
// pages/ajax/campanha-disparar.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once __DIR__ . '/../../includes/wpp_guard.php';
require_once __DIR__ . '/../../includes/wpp_campaigns.php';
require_once __DIR__ . '/../../includes/wpp_client.php';

proteger_pagina();
wpp_guard_require_capability('wpp_campaigns_send');

$campanha_id = isset($_POST['campanha_id']) ? (int)$_POST['campanha_id'] : 0;
$modo        = $_POST['modo'] ?? 'todos'; // 'todos' ou 'teste'

if (!$campanha_id) {
    echo json_encode(['ok' => false, 'msg' => 'Campanha inválida.']);
    exit;
}

$campanha = wpp_campanha_find($conn, $campanha_id);
if (!$campanha) {
    echo json_encode(['ok' => false, 'msg' => 'Campanha não encontrada.']);
    exit;
}

$destinatarios = wpp_campanha_destinatarios_find($conn, $campanha_id);
if (!$destinatarios) {
    echo json_encode(['ok' => false, 'msg' => 'Nenhum destinatário para disparar.']);
    exit;
}

// Carrega instancia WPP
$inst_id = (int)$campanha['instancia_id'];
$res = $conn->prepare("SELECT * FROM moz_wpp_instance WHERE id = ?");
$res->bind_param('i', $inst_id);
$res->execute();
$inst = $res->get_result()->fetch_assoc();
if (!$inst) {
    echo json_encode(['ok' => false, 'msg' => 'Instância WPP não encontrada.']);
    exit;
}

$client = new WPPClient($inst['server_url'], (int)$inst['server_port'], $inst['server_secret'], $inst['session_name']);

$enviados = 0;
$max = ($modo === 'teste') ? 1 : count($destinatarios);

foreach ($destinatarios as $idx => $d) {
    if ($idx >= $max) break;

    $telefone = $d['contato_telefone'];
    $nome     = $d['contato_nome'];
    $varsJson = $d['variaveis_json'] ?: '{}';
    $vars     = json_decode($varsJson, true) ?: [];

    // Montar mensagem base da campanha.
    // Aqui você pode trocar por template + merge de variáveis.
    $mensagem = $campanha['descricao'] ?: ('Olá ' . $nome . ', tudo bem?');

    // Envia via WPPConnect
    $resp = $client->sendText($telefone, $mensagem);

    // Atualizar status
    $statusEnvio = !empty($resp['status']) ? $resp['status'] : 'enviado';
    $sql = "
        UPDATE moz_wpp_campanha_destinatario
           SET status_envio = ?, enviado_em = NOW(), mensagem_resposta = ?
         WHERE id = ?
    ";
    $stmtUp = $conn->prepare($sql);
    $respRaw = substr(json_encode($resp), 0, 500);
    $stmtUp->bind_param('ssi', $statusEnvio, $respRaw, $d['id']);
    $stmtUp->execute();

    $enviados++;
}

if ($modo === 'todos') {
    wpp_campanha_update_status($conn, $campanha_id, 'concluida');
}

echo json_encode([
    'ok'   => true,
    'msg'  => "Mensagens enviadas: $enviados",
    'mode' => $modo
]);
