<?php
// public/modules/wpp_chat/includes/glpi-bridge.php

require_once __DIR__.'/helpers.php';

/**
 * Configurações (ajuste para seu ambiente)
 */
define('GLPI_API_URL',  'https://seu-glpi/api/');
define('GLPI_APP_TOKEN', 'SUA_APP_TOKEN');
define('GLPI_USER_TOKEN','SEU_USER_TOKEN');

function glpi_http($method, $endpoint, $body = null) {
    $url = rtrim(GLPI_API_URL, '/').'/'.ltrim($endpoint, '/');
    $headers = [
        'App-Token: '.GLPI_APP_TOKEN,
        'Authorization: user_token '.GLPI_USER_TOKEN,
        'Content-Type: application/json'
    ];
    return wpp_http_call($method, $url, $headers, $body);
}

/**
 * Cria um ticket GLPI simples, retornando o ID.
 */
function glpi_create_ticket_from_wpp($phone, $name, $message) {
    $body = [
        'input' => [
            'name'        => "WhatsApp de {$name} ({$phone})",
            'content'     => $message,
            'status'      => 1, // Novo
            'requesttypes_id' => 1 // ajuste
        ]
    ];
    $res = glpi_http('POST', 'Ticket', $body);
    if ($res['status'] >= 200 && $res['status'] < 300 && !empty($res['json']['id'])) {
        return $res['json']['id'];
    }
    return null;
}

/**
 * Anexa acompanhamento num ticket GLPI
 */
function glpi_add_followup_to_ticket($ticket_id, $message) {
    $body = [
        'input' => [
            'itemtype'   => 'Ticket',
            'items_id'   => (int)$ticket_id,
            'content'    => $message,
            'is_private' => 0
        ]
    ];
    return glpi_http('POST', 'ITILFollowup', $body);
}
