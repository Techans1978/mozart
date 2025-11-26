<?php

require_once __DIR__ . '/wpp_clientes.php';
require_once __DIR__ . '/wpp_conversas.php';
require_once __DIR__ . '/wpp_campaigns.php';

/**
 * Valida e normaliza payload do Webhook.
 */
function wpp_webhook_parse_payload(): ?array {
    $raw = file_get_contents("php://input");
    if (!$raw) return null;

    $json = json_decode($raw, true);
    if (!is_array($json)) return null;

    // WPPConnect envia "messages"[]
    if (!isset($json['messages']) || !is_array($json['messages'])) {
        return null;
    }

    return $json;
}

/**
 * Anti-duplicação:
 * Retorna true se esta mensagem já foi processada.
 */
function wpp_webhook_is_duplicate(mysqli $conn, int $instancia_id, string $message_id): bool {
    $sql = "SELECT id FROM moz_wpp_incoming_dedup WHERE instancia_id = ? AND message_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $instancia_id, $message_id);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

/**
 * Marca a mensagem como processada para evitar duplicações.
 */
function wpp_webhook_register_message(mysqli $conn, int $instancia_id, string $message_id): void {
    $sql = "INSERT IGNORE INTO moz_wpp_incoming_dedup (instancia_id, message_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $instancia_id, $message_id);
    $stmt->execute();
}

/**
 * Enfileira um evento para processamento posterior.
 */
function wpp_webhook_enqueue(mysqli $conn, int $instancia_id, array $payload): void {
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

    $sql = "
      INSERT INTO moz_wpp_queue (instancia_id, event_type, payload_json)
      VALUES (?, 'msg_in', ?)
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $instancia_id, $json);
    $stmt->execute();
}

/**
 * Processa 1 item da fila (chamado por cron ou manual).
 */
function wpp_webhook_process_one(mysqli $conn): bool {
    $sql = "
      SELECT *
        FROM moz_wpp_queue
       WHERE status = 'pendente'
       ORDER BY id ASC
       LIMIT 1
    ";
    $res = $conn->query($sql);
    $item = $res->fetch_assoc();
    if (!$item) return false;

    $id = (int)$item['id'];

    $payload = json_decode($item['payload_json'], true);
    if (!$payload) {
        $conn->query("UPDATE moz_wpp_queue SET status='erro', updated_at=NOW() WHERE id={$id}");
        return false;
    }

    try {
        wpp_webhook_process_message($conn, $item['instancia_id'], $payload);
        $conn->query("UPDATE moz_wpp_queue SET status='processado', updated_at=NOW() WHERE id={$id}");
        return true;

    } catch (Throwable $e) {
        $conn->query("
          UPDATE moz_wpp_queue
             SET status='erro',
                 tentativas = tentativas + 1,
                 updated_at = NOW()
           WHERE id = {$id}
        ");
        return false;
    }
}

/**
 * Core: processa uma mensagem do WhatsApp.
 */
function wpp_webhook_process_message(mysqli $conn, int $instancia_id, array $payload): void {

    foreach ($payload['messages'] as $msg) {

        if ($msg['fromMe'] ?? false) continue; // ignorar mensagens que nós enviamos

        $message_id = $msg['id'] ?? '';
        if ($message_id === '') continue;

        if (wpp_webhook_is_duplicate($conn, $instancia_id, $message_id)) {
            continue;
        }

        // Agora marca como processada
        wpp_webhook_register_message($conn, $instancia_id, $message_id);

        $telefone = preg_replace('/\D+/', '', ($msg['sender']['id'] ?? ''));
        $nome     = trim($msg['sender']['pushName'] ?? '');

        // 1) Criar/encontrar conversa
        $conversa_id = wpp_conversa_find_or_create($conn, $instancia_id, $telefone, $nome);

        // 2) Vincular cliente
        $cliente_id = wpp_cliente_touch_from_incoming($conn, $telefone, $nome);

        // 3) Atualizar cliente na conversa
        $stmt = $conn->prepare("
          UPDATE moz_wpp_conversa
             SET cliente_id = ?
           WHERE id = ?
        ");
        $stmt->bind_param('ii', $cliente_id, $conversa_id);
        $stmt->execute();

        // 4) Registrar a mensagem
        $texto = trim($msg['text']['body'] ?? '');
        wpp_conversa_add_msg(
            $conn,
            $conversa_id,
            'in',
            $texto,
            'text',
            $message_id,
            $msg
        );
    }
}
