<?php
// includes/wpp_glpi_bridge.php

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../../../config.php';
}
require_once ROOT_PATH . '/system/config/connect.php';

/**
 * Ponte simples com GLPI via API HTTP.
 * Ajuste URL, token e mapeamento conforme seu ambiente.
 */
function wpp_glpi_bridge_handle_incoming(
    mysqli $conn,
    int $conversa_id,
    string $telefone,
    string $mensagem,
    array $payload
): void {
    // Config GLPI
    $glpi_url   = getenv('GLPI_URL')   ?: 'https://seu-glpi/api/';
    $glpi_token = getenv('GLPI_TOKEN') ?: '';

    if (!$glpi_token) {
        return; // Sem integração configurada
    }

    // Verificar se já tem ticket GLPI associado
    $stmt = $conn->prepare("SELECT ticket_glpi_id FROM moz_wpp_conversa WHERE id = ?");
    $stmt->bind_param('i', $conversa_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $ticket_id = (int)($r['ticket_glpi_id'] ?? 0);

    if (!$ticket_id) {
        // Criar ticket no GLPI (exemplo usando cURL)
        $titulo = 'WhatsApp de ' . $telefone;
        $dataTicket = [
            'input' => [
                'name'         => $titulo,
                'content'      => $mensagem,
                'requesttypes_id' => 1,
                'status'       => 1
            ]
        ];
        $ch = curl_init($glpi_url . 'Ticket/');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'App-Token: ' . $glpi_token
            ],
            CURLOPT_POSTFIELDS => json_encode($dataTicket)
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        // Você deve parsear $resp e pegar o ID retornado
        // Aqui deixo genérico:
        $ticket_id = 0; // TODO: extrair do $resp

        // vincular
        $st2 = $conn->prepare("UPDATE moz_wpp_conversa SET ticket_glpi_id = ? WHERE id = ?");
        $st2->bind_param('ii', $ticket_id, $conversa_id);
        $st2->execute();
    } else {
        // Atualizar com uma nova followup
        // Exemplo simplificado, ajuste ao endpoint Followup do GLPI
    }
}
