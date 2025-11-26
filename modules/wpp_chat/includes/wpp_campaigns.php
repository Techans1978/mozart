<?php
// includes/wpp_campaigns.php

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../../../config.php';
}

require_once ROOT_PATH . '/system/config/connect.php';
require_once __DIR__ . '/wpp_helpers.php';

function wpp_campanha_find_all(mysqli $conn, array $filtros = []): array {
    $where = [];
    $params = [];
    $types  = '';

    if (!empty($filtros['q'])) {
        $where[] = '(c.nome LIKE ? OR c.descricao LIKE ?)';
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
        FROM moz_wpp_campanha c
        LEFT JOIN moz_wpp_instance i ON i.id = c.instancia_id
    ";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY c.created_at DESC';

    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->fetch_all(MYSQLI_ASSOC);
}

function wpp_campanha_find(mysqli $conn, int $id): ?array {
    $sql = "
        SELECT c.*, i.nome AS instancia_nome
        FROM moz_wpp_campanha c
        LEFT JOIN moz_wpp_instance i ON i.id = c.instancia_id
        WHERE c.id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    return $r ?: null;
}

function wpp_campanha_save(mysqli $conn, array $data, int $usuario_id): int {
    $id           = isset($data['id']) ? (int)$data['id'] : 0;
    $nome         = trim($data['nome'] ?? '');
    $descricao    = trim($data['descricao'] ?? '');
    $instancia_id = (int)($data['instancia_id'] ?? 0);
    $status       = $data['status'] ?? 'rascunho';
    $tipo_disparo = $data['tipo_disparo'] ?? 'imediato';
    $data_agendada = $data['data_agendada'] ?: null;
    $script_id    = !empty($data['script_id']) ? (int)$data['script_id'] : null;

    // NOVO: campos de gatilho
    $trigger_tag   = trim($data['trigger_tag'] ?? '');
    $trigger_ativo = !empty($data['trigger_ativo']) ? 1 : 0;

    if ($id > 0) {
        $sql = "
            UPDATE moz_wpp_campanha
               SET nome = ?, descricao = ?, instancia_id = ?, status = ?, tipo_disparo = ?,
                   data_agendada = ?, script_id = ?, trigger_tag = ?, trigger_ativo = ?, updated_at = NOW()
             WHERE id = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'ssisssisii',
            $nome, $descricao, $instancia_id, $status, $tipo_disparo,
            $data_agendada, $script_id, $trigger_tag, $trigger_ativo, $id
        );
        $stmt->execute();
        return $id;
    } else {
        $sql = "
            INSERT INTO moz_wpp_campanha
                (nome, descricao, instancia_id, status, tipo_disparo, data_agendada,
                 script_id, trigger_tag, trigger_ativo,
                 criado_por, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?, ?, ?, ?, NOW(), NOW())
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'ssisssisi i',
            $nome, $descricao, $instancia_id, $status, $tipo_disparo,
            $data_agendada, $script_id, $trigger_tag, $trigger_ativo, $usuario_id
        );
        // Tirar o espaço no 'isi i' se der erro de sintaxe: 'ssisssisii'
        $stmt->execute();
        return (int)$stmt->insert_id;
    }
}


function wpp_campanha_destinatarios_find(mysqli $conn, int $campanha_id): array {
    $sql = "
        SELECT d.*
        FROM moz_wpp_campanha_destinatario d
        WHERE d.campanha_id = ?
        ORDER BY d.id ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $campanha_id);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->fetch_all(MYSQLI_ASSOC);
}

function wpp_campanha_destinatarios_importar_from_text(mysqli $conn, int $campanha_id, string $texto): int {
    // texto no formato:
    // 5531999999999;Fulano;{"cidade":"Contagem"}
    // 5531888888888;Beltrano;{"cidade":"BH"}
    $linhas = preg_split('/\r\n|\n|\r/', trim($texto));
    $count  = 0;

    $sql = "
        INSERT INTO moz_wpp_campanha_destinatario
            (campanha_id, contato_nome, contato_telefone, variaveis_json, status_envio)
        VALUES (?,?,?,?, 'pendente')
    ";
    $stmt = $conn->prepare($sql);

    foreach ($linhas as $l) {
        $l = trim($l);
        if ($l === '') continue;
        $partes = explode(';', $l);
        $telefone = trim($partes[0] ?? '');
        $nome     = trim($partes[1] ?? '');
        $vars     = trim($partes[2] ?? '');
        if ($vars === '') $vars = '{}';

        $stmt->bind_param('isss', $campanha_id, $nome, $telefone, $vars);
        $stmt->execute();
        $count++;
    }

    return $count;
}

function wpp_campanha_update_status(mysqli $conn, int $campanha_id, string $status): void {
    $sql = "UPDATE moz_wpp_campanha SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $status, $campanha_id);
    $stmt->execute();
}

/**
 * A partir de uma tag, inclui o cliente como destinatário
 * em todas as campanhas que tiverem trigger_ativo=1 e trigger_tag = tag.
 */
function wpp_campanhas_vincular_cliente_por_tag(
    mysqli $conn,
    int $cliente_id,
    string $tag
): void {
    $tag = trim($tag);
    if ($tag === '') {
        return;
    }

    // Busca dados do cliente
    $stmtCli = $conn->prepare("
      SELECT nome, telefone
        FROM moz_wpp_cliente
       WHERE id = ?
       LIMIT 1
    ");
    $stmtCli->bind_param('i', $cliente_id);
    $stmtCli->execute();
    $cli = $stmtCli->get_result()->fetch_assoc();

    if (!$cli) {
        return;
    }

    $nome     = $cli['nome'] ?? '';
    $telefone = $cli['telefone'] ?? '';
    if (trim($telefone) === '') {
        return;
    }

    // Campanhas configuradas com esse gatilho
    $stmtCamp = $conn->prepare("
      SELECT id
        FROM moz_wpp_campanha
       WHERE trigger_ativo = 1
         AND trigger_tag = ?
    ");
    $stmtCamp->bind_param('s', $tag);
    $stmtCamp->execute();
    $resCamp = $stmtCamp->get_result();
    $campanhas = $resCamp ? $resCamp->fetch_all(MYSQLI_ASSOC) : [];

    if (!$campanhas) {
        return;
    }

    // Prepara insert de destinatário, evitando duplicar por (campanha + telefone)
    $sqlCheck = "
      SELECT id
        FROM moz_wpp_campanha_destinatario
       WHERE campanha_id = ?
         AND contato_telefone = ?
       LIMIT 1
    ";
    $stmtCheck = $conn->prepare($sqlCheck);

    $sqlIns = "
      INSERT INTO moz_wpp_campanha_destinatario
        (campanha_id, contato_nome, contato_telefone, variaveis_json, status_envio)
      VALUES (?,?,?,?, 'pendente')
    ";
    $stmtIns = $conn->prepare($sqlIns);

    foreach ($campanhas as $c) {
        $cid = (int)$c['id'];

        // Já existe?
        $stmtCheck->bind_param('is', $cid, $telefone);
        $stmtCheck->execute();
        $existe = $stmtCheck->get_result()->fetch_assoc();
        if ($existe) {
            continue;
        }

        $varsJson = '{}'; // pode evoluir depois com variáveis dinâmicas
        $stmtIns->bind_param('isss', $cid, $nome, $telefone, $varsJson);
        $stmtIns->execute();
    }
}
