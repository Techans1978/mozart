<?php
// includes/wpp_conversas.php

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../../../config.php';
}
require_once ROOT_PATH . '/system/config/connect.php';
require_once __DIR__ . '/wpp_clientes.php';
require_once __DIR__ . '/wpp_campaigns.php';

/**
 * Busca conversa existente ou cria uma nova para (instância + telefone).
 */
function wpp_conversa_find_or_create(
    mysqli $conn,
    int $instancia_id,
    string $telefone,
    string $nome = ''
): int {
    $telefone = preg_replace('/\D+/', '', $telefone);

    // Tenta achar uma conversa aberta para esse telefone
    $stmt = $conn->prepare("
        SELECT id
          FROM moz_wpp_conversa
         WHERE instancia_id = ?
           AND contato_telefone = ?
         ORDER BY updated_at DESC, id DESC
         LIMIT 1
    ");
    $stmt->bind_param('is', $instancia_id, $telefone);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res && !empty($res['id'])) {
        return (int)$res['id'];
    }

    // Cria nova conversa
    $sql = "
      INSERT INTO moz_wpp_conversa
        (instancia_id, contato_telefone, contato_nome, status,
         ultimo_msg, ultimo_msg_data, cliente_id,
         created_at, updated_at)
      VALUES (?, ?, ?, 'aberta', NULL, NULL, NULL, NOW(), NOW())
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iss', $instancia_id, $telefone, $nome);
    $stmt->execute();

    return (int)$stmt->insert_id;
}

/**
 * Busca uma conversa específica.
 */
function wpp_conversa_find(mysqli $conn, int $id): ?array {
    $sql = "
      SELECT c.*, i.nome AS instancia_nome
        FROM moz_wpp_conversa c
   LEFT JOIN moz_wpp_instance i ON i.id = c.instancia_id
       WHERE c.id = ?
       LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return $res ?: null;
}

/**
 * Lista conversas com filtros básicos.
 */
function wpp_conversa_list(mysqli $conn, array $filtros = []): array {
    $where  = [];
    $params = [];
    $types  = '';

    if (!empty($filtros['q'])) {
        $where[] = '(c.contato_telefone LIKE ? OR c.contato_nome LIKE ?)';
        $like = '%'.$filtros['q'].'%';
        $params[] = $like;
        $params[] = $like;
        $types   .= 'ss';
    }

    if (!empty($filtros['status'])) {
        $where[] = 'c.status = ?';
        $params[] = $filtros['status'];
        $types   .= 's';
    }

    $sql = "
      SELECT c.*, i.nome AS instancia_nome
        FROM moz_wpp_conversa c
   LEFT JOIN moz_wpp_instance i ON i.id = c.instancia_id
    ";
    if ($where) {
        $sql .= ' WHERE '.implode(' AND ', $where);
    }
    $sql .= ' ORDER BY c.updated_at DESC, c.id DESC LIMIT 200';

    if ($params) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }

    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Registra mensagem (in/out) em uma conversa.
 * Aqui também disparamos o motor de auto_tags.
 * Anti-duplicidade: se vier o mesmo message_id_wpp para a mesma conversa, não grava de novo.
 */
function wpp_conversa_add_msg(
    mysqli $conn,
    int $conversa_id,
    string $direction,
    string $conteudo,
    string $tipo = 'text',
    ?string $wpp_msg_id = null,
    $raw = null
): int {
    $direction = $direction === 'out' ? 'out' : 'in';
    $tipo      = $tipo ?: 'text';

    // Se tiver ID externo, verifica se já existe
    if ($wpp_msg_id) {
        $stmtChk = $conn->prepare("
          SELECT id
            FROM moz_wpp_mensagem
           WHERE conversa_id = ?
             AND mensagem_id_wpp = ?
           LIMIT 1
        ");
        $stmtChk->bind_param('is', $conversa_id, $wpp_msg_id);
        $stmtChk->execute();
        $existe = $stmtChk->get_result()->fetch_assoc();
        if ($existe) {
            // Já temos essa mensagem, só retorna o id encontrado
            return (int)$existe['id'];
        }
    }

    $rawJson = $raw ? json_encode($raw) : null;

    // Insere mensagem
    $sql = "
      INSERT INTO moz_wpp_mensagem
        (conversa_id, direction, conteudo, tipo,
         mensagem_id_wpp, status, data_msg, raw_json, created_at)
      VALUES (?,?,?,?, 'ok', NOW(), ?, NOW())
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'isssss',
        $conversa_id,
        $direction,
        $conteudo,
        $tipo,
        $wpp_msg_id,
        $rawJson
    );
    $stmt->execute();
    $msgId = (int)$stmt->insert_id;

    // Atualiza resumo da conversa
    $sql2 = "
      UPDATE moz_wpp_conversa
         SET ultimo_msg = ?,
             ultimo_msg_data = NOW(),
             updated_at = NOW()
       WHERE id = ?
    ";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param('si', $conteudo, $conversa_id);
    $stmt2->execute();

    // Dispara análise de comportamento / auto_tags
    wpp_behavior_on_new_message($conn, $conversa_id, $direction, $conteudo);

    return $msgId;
}


/**
 * Motor simples de regras de comportamento.
 * - recorrente: cliente com 3+ conversas
 * - engajado: 10+ mensagens na conversa
 * - interesse_comercial: palavras-chave em mensagens de entrada
 */
function wpp_behavior_on_new_message(
    mysqli $conn,
    int $conversa_id,
    string $direction,
    string $conteudo
): void {
    // Descobre cliente ligado à conversa
    $stmt = $conn->prepare("
      SELECT cliente_id
        FROM moz_wpp_conversa
       WHERE id = ?
       LIMIT 1
    ");
    $stmt->bind_param('i', $conversa_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    $cliente_id = !empty($res['cliente_id']) ? (int)$res['cliente_id'] : 0;
    if ($cliente_id <= 0) {
        return;
    }

    // Regra 1: cliente recorrente (3+ conversas)
    $stmt2 = $conn->prepare("
      SELECT COUNT(*) AS total
        FROM moz_wpp_conversa
       WHERE cliente_id = ?
    ");
    $stmt2->bind_param('i', $cliente_id);
    $stmt2->execute();
    $r2 = $stmt2->get_result()->fetch_assoc();
    $totalConversas = (int)($r2['total'] ?? 0);

    if ($totalConversas >= 3) {
        wpp_cliente_add_auto_tag($conn, $cliente_id, 'recorrente', $conversa_id);
    }

    // Regra 2: conversa engajada (10+ mensagens)
    $stmt3 = $conn->prepare("
      SELECT COUNT(*) AS total
        FROM moz_wpp_mensagem
       WHERE conversa_id = ?
    ");
    $stmt3->bind_param('i', $conversa_id);
    $stmt3->execute();
    $r3 = $stmt3->get_result()->fetch_assoc();
    $totalMsgs = (int)($r3['total'] ?? 0);

    if ($totalMsgs >= 10) {
        wpp_cliente_add_auto_tag($conn, $cliente_id, 'engajado', $conversa_id);
    }

    // Regra 3: interesse comercial por palavras-chave (apenas mensagens de entrada)
    if ($direction === 'in') {
        $texto = strtolower($conteudo);
        $keywords = [
            'preço', 'preco',
            'promoção', 'promocao',
            'desconto',
            'oferta',
            'orçamento', 'orcamento'
        ];
        $temInteresse = false;
        foreach ($keywords as $kw) {
            if (strpos($texto, $kw) !== false) {
                $temInteresse = true;
                break;
            }
        }
        if ($temInteresse) {
            // Marca auto_tag
            wpp_cliente_add_auto_tag($conn, $cliente_id, 'interesse_comercial', $conversa_id);

            // Integra com campanhas que usam esse gatilho
            wpp_campanhas_vincular_cliente_por_tag($conn, $cliente_id, 'interesse_comercial');
        }
    }
}


/**
 * Lista mensagens da conversa.
 */
function wpp_conversa_msgs(mysqli $conn, int $conversa_id): array {
    $stmt = $conn->prepare("
      SELECT *
        FROM moz_wpp_mensagem
       WHERE conversa_id = ?
       ORDER BY data_msg ASC, id ASC
       LIMIT 1000
    ");
    $stmt->bind_param('i', $conversa_id);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->fetch_all(MYSQLI_ASSOC);
}
