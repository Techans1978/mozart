<?php
ini_set('display_errors',0);
ini_set('display_startup_errors',0);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once __DIR__ . '/includes/wpp_webhook.php';

// 1 — Descobrir a instância
$session = $_GET['session'] ?? ($_GET['instance'] ?? '');
if (!$session) {
    http_response_code(400);
    echo "Missing session parameter";
    exit;
}

$stmt = $conn->prepare("SELECT id FROM moz_wpp_instance WHERE session_name = ? LIMIT 1");
$stmt->bind_param('s', $session);
$stmt->execute();
$inst = $stmt->get_result()->fetch_assoc();
if (!$inst) {
    http_response_code(404);
    echo "Invalid session";
    exit;
}
$instancia_id = (int)$inst['id'];

// 2 — Ler payload
$payload = wpp_webhook_parse_payload();
if (!$payload) {
    http_response_code(200);
    echo "ignored";
    exit;
}

// 3 — ENFILEIRA para processar depois
wpp_webhook_enqueue($conn, $instancia_id, $payload);

// 4 — Retorno imediato (WPPConnect exige 200 rápido)
http_response_code(200);
echo "ok";
