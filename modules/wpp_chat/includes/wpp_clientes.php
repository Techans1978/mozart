<?php
// includes/wpp_clientes.php

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../../../config.php';
}
require_once ROOT_PATH . '/system/config/connect.php';

/**
 * Normaliza telefone: deixa só dígitos.
 */
function wpp_normalizar_telefone(string $tel): string {
    return preg_replace('/\D+/', '', $tel);
}

function wpp_cliente_find_by_id(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare("SELECT * FROM moz_wpp_cliente WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    return $r ?: null;
}

function wpp_cliente_find_by_tel(mysqli $conn, string $telefone): ?array {
    $telefone = wpp_normalizar_telefone($telefone);
    $stmt = $conn->prepare("SELECT * FROM moz_wpp_cliente WHERE telefone = ? LIMIT 1");
    $stmt->bind_param('s', $telefone);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    return $r ?: null;
}

/**
 * Cria/atualiza cliente a partir de um contato que chegou pelo WhatsApp.
 * Se já existir, apenas atualiza ultimo_contato e nome se estiver vazio.
 */
function wpp_cliente_touch_from_incoming(
    mysqli $conn,
    string $telefone,
    string $nome = ''
): int {
    $telefone = wpp_normalizar_telefone($telefone);
    $nome = trim($nome);

    $existente = wpp_cliente_find_by_tel($conn, $telefone);
    if ($existente) {
        $sql = "UPDATE moz_wpp_cliente
                   SET ultimo_contato = NOW(),
                       nome = IF(nome IS NULL OR nome = '', ?, nome)
                 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $nome, $existente['id']);
        $stmt->execute();
        return (int)$existente['id'];
    }

    $sql = "
      INSERT INTO moz_wpp_cliente
        (telefone, nome, origem, ultimo_contato, created_at, updated_at)
      VALUES (?,?, 'whatsapp', NOW(), NOW(), NOW())
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $telefone, $nome);
    $stmt->execute();
    return $stmt->insert_id;
}

/**
 * Grava/edita cliente via formulário.
 */
function wpp_cliente_save(mysqli $conn, array $data): int {
    $id          = isset($data['id']) ? (int)$data['id'] : 0;
    $telefone    = wpp_normalizar_telefone($data['telefone'] ?? '');
    $nome        = trim($data['nome'] ?? '');
    $email       = trim($data['email'] ?? '');
    $documento   = trim($data['documento'] ?? '');
    $tipo_cli    = trim($data['tipo_cliente'] ?? '');
    $origem      = trim($data['origem'] ?? 'manual');
    $usuario_id  = !empty($data['usuario_id']) ? (int)$data['usuario_id'] : null;
    $tags        = trim($data['tags'] ?? '');
    $observacoes = trim($data['observacoes'] ?? '');

    if ($id > 0) {
        $sql = "
          UPDATE moz_wpp_cliente
             SET telefone = ?, nome = ?, email = ?, documento = ?, tipo_cliente = ?,
                 origem = ?, usuario_id = ?, tags = ?, observacoes = ?, updated_at = NOW()
           WHERE id = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'ssssssissi',
            $telefone, $nome, $email, $documento, $tipo_cli,
            $origem, $usuario_id, $tags, $observacoes, $id
        );
        $stmt->execute();
        return $id;
    } else {
        $sql = "
          INSERT INTO moz_wpp_cliente
              (telefone, nome, email, documento, tipo_cliente, origem,
               usuario_id, tags, observacoes, ultimo_contato, created_at, updated_at)
          VALUES (?,?,?,?,?,?,?,?,?, NOW(), NOW(), NOW())
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'ssssssiss',
            $telefone, $nome, $email, $documento, $tipo_cli,
            $origem, $usuario_id, $tags, $observacoes
        );
        $stmt->execute();
        return $stmt->insert_id;
    }
}

/**
 * Lista de clientes para tela de pesquisa.
 */
function wpp_cliente_list(mysqli $conn, array $filtros = []): array {
    $where = [];
    $params = [];
    $types  = '';

    if (!empty($filtros['q'])) {
        $where[] = '(c.telefone LIKE ? OR c.nome LIKE ? OR c.tags LIKE ?)';
        $like = '%'.$filtros['q'].'%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types   .= 'sss';
    }
    if (!empty($filtros['tipo_cliente'])) {
        $where[] = 'c.tipo_cliente = ?';
        $params[] = $filtros['tipo_cliente'];
        $types   .= 's';
    }

    $sql = "SELECT c.* FROM moz_wpp_cliente c";
    if ($where) {
        $sql .= ' WHERE '.implode(' AND ', $where);
    }
    $sql .= ' ORDER BY c.ultimo_contato DESC, c.nome ASC LIMIT 500';

    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->fetch_all(MYSQLI_ASSOC);
}

/**
 * Retorna todas as conversas e mensagens de um cliente (log completo).
 */
function wpp_cliente_logs(mysqli $conn, int $cliente_id): array {
    // Conversas
    $sqlConv = "
      SELECT c.*
      FROM moz_wpp_conversa c
      WHERE c.cliente_id = ?
      ORDER BY c.created_at DESC
    ";
    $stmtC = $conn->prepare($sqlConv);
    $stmtC->bind_param('i', $cliente_id);
    $stmtC->execute();
    $conversas = $stmtC->get_result()->fetch_all(MYSQLI_ASSOC);

    // Mensagens (opcional, agrupando por conversa_id)
    $logs = [];
    foreach ($conversas as $c) {
        $cid = (int)$c['id'];
        $stmtM = $conn->prepare("
          SELECT * FROM moz_wpp_mensagem
          WHERE conversa_id = ?
          ORDER BY data_msg ASC, id ASC
        ");
        $stmtM->bind_param('i', $cid);
        $stmtM->execute();
        $msgs = $stmtM->get_result()->fetch_all(MYSQLI_ASSOC);

        $logs[] = [
            'conversa'  => $c,
            'mensagens' => $msgs,
        ];
    }
    return $logs;
}

/**
 * Registra um evento de comportamento do cliente.
 */
function wpp_cliente_register_event(
    mysqli $conn,
    int $cliente_id,
    string $tipo,
    string $descricao = '',
    int $conversa_id = 0
): void {
    $sql = "
      INSERT INTO moz_wpp_behavior_log
        (cliente_id, conversa_id, tipo_evento, descricao, created_at)
      VALUES (?,?,?,?, NOW())
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiss', $cliente_id, $conversa_id, $tipo, $descricao);
    $stmt->execute();
}

/**
 * Adiciona uma tag automática ao cliente (não duplica) e registra log.
 */
function wpp_cliente_add_auto_tag(
    mysqli $conn,
    int $cliente_id,
    string $tag,
    int $conversa_id = 0
): void {
    $tag = trim(strtolower($tag));
    if ($tag === '') {
        return;
    }

    // Busca tags atuais
    $stmt = $conn->prepare("
      SELECT auto_tags
        FROM moz_wpp_cliente
       WHERE id = ?
       LIMIT 1
    ");
    $stmt->bind_param('i', $cliente_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if (!$res) {
        return;
    }

    $atual = $res['auto_tags'] ?? '';
    $lista = array_filter(array_map('trim', explode(',', (string)$atual)));

    // Já tem essa tag?
    if (in_array($tag, $lista, true)) {
        return;
    }

    $lista[] = $tag;
    $novo = implode(',', $lista);

    $stmtU = $conn->prepare("
      UPDATE moz_wpp_cliente
         SET auto_tags = ?
       WHERE id = ?
    ");
    $stmtU->bind_param('si', $novo, $cliente_id);
    $stmtU->execute();

    // Loga o evento
    wpp_cliente_register_event(
        $conn,
        $cliente_id,
        'tag_'.$tag,
        'Tag automática aplicada: '.$tag,
        $conversa_id
    );
}

/**
 * Tenta enriquecer o nome do cliente usando a tabela wpp_contact.
 * Usa o telefone para achar o contato no WhatsApp.
 */
function wpp_cliente_enrich_from_wpp_contact(
    mysqli $conn,
    int $cliente_id,
    string $telefone
): void {
    $telefoneNum = wpp_normalizar_telefone($telefone);

    // Busca o cliente atual
    $stmt = $conn->prepare("
      SELECT nome, telefone
        FROM moz_wpp_cliente
       WHERE id = ?
       LIMIT 1
    ");
    $stmt->bind_param('i', $cliente_id);
    $stmt->execute();
    $cli = $stmt->get_result()->fetch_assoc();

    if (!$cli) {
        return;
    }

    $nomeAtual     = trim((string)$cli['nome']);
    $telefoneCli   = wpp_normalizar_telefone($cli['telefone'] ?? '');

    // Se já tem nome razoável, não força nada
    if ($nomeAtual !== '' && $nomeAtual !== $telefoneCli) {
        return;
    }

    // Busca nome no wpp_contact pelo telefone
    $stmt2 = $conn->prepare("
      SELECT name
        FROM wpp_contact
       WHERE phone = ?
         AND name IS NOT NULL
         AND name <> ''
       ORDER BY id DESC
       LIMIT 1
    ");
    $stmt2->bind_param('s', $telefoneNum);
    $stmt2->execute();
    $c = $stmt2->get_result()->fetch_assoc();

    if (!$c || trim((string)$c['name']) === '') {
        return;
    }

    $novoNome = trim($c['name']);

    // Atualiza o cliente
    $stmtU = $conn->prepare("
      UPDATE moz_wpp_cliente
         SET nome = ?
       WHERE id = ?
    ");
    $stmtU->bind_param('si', $novoNome, $cliente_id);
    $stmtU->execute();

    // Atualiza também o nome nas conversas ligadas a esse cliente
    $stmtC = $conn->prepare("
      UPDATE moz_wpp_conversa
         SET contato_nome = ?
       WHERE cliente_id = ?
    ");
    $stmtC->bind_param('si', $novoNome, $cliente_id);
    $stmtC->execute();
}

/**
 * Mescla dois clientes:
 * - $principal_id: será mantido
 * - $secundario_id: será "absorvido"
 *
 * Regras:
 * - Escolhe valores não vazios do principal; se estiver vazio, usa do secundário
 * - Une tags e auto_tags sem duplicar
 * - Concatena observações
 * - Usa o último contato mais recente
 * - Move conversas e logs do secundário para o principal
 * - Marca o secundário como mesclado (mesclado_para_id, mesclado_em)
 */
function wpp_cliente_merge(
    mysqli $conn,
    int $principal_id,
    int $secundario_id
): bool {
    if ($principal_id <= 0 || $secundario_id <= 0 || $principal_id === $secundario_id) {
        return false;
    }

    // Carrega os dois clientes
    $stmt = $conn->prepare("
      SELECT *
        FROM moz_wpp_cliente
       WHERE id IN (?, ?)
    ");
    $stmt->bind_param('ii', $principal_id, $secundario_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $c1 = null;
    $c2 = null;
    while ($row = $res->fetch_assoc()) {
        if ((int)$row['id'] === $principal_id) {
            $c1 = $row;
        } elseif ((int)$row['id'] === $secundario_id) {
            $c2 = $row;
        }
    }

    if (!$c1 || !$c2) {
        return false;
    }

    $conn->begin_transaction();

    try {
        // Snapshots para log
        $snapPrincipal = json_encode($c1);
        $snapSecund    = json_encode($c2);

        // Função helper local
        $pick = function($a, $b) {
            $a = is_string($a) ? trim($a) : $a;
            $b = is_string($b) ? trim($b) : $b;
            return ($a === '' || $a === null) ? $b : $a;
        };

        // Campos básicos
        $telefone     = $pick($c1['telefone'],     $c2['telefone']);
        $nome         = $pick($c1['nome'],         $c2['nome']);
        $email        = $pick($c1['email'],        $c2['email']);
        $documento    = $pick($c1['documento'],    $c2['documento']);
        $tipo_cliente = $pick($c1['tipo_cliente'], $c2['tipo_cliente']);
        $origem       = $pick($c1['origem'],       $c2['origem']);
        $usuario_id   = $c1['usuario_id'] ?: $c2['usuario_id'];

        // Tags manuais: une e remove duplicados
        $tags1 = array_filter(array_map('trim', explode(',', (string)$c1['tags'])));
        $tags2 = array_filter(array_map('trim', explode(',', (string)$c2['tags'])));
        $tagsMerge = array_unique(array_merge($tags1, $tags2));
        $tagsStr   = $tagsMerge ? implode(',', $tagsMerge) : null;

        // Auto tags: une e remove duplicados
        $a1 = array_filter(array_map('trim', explode(',', (string)$c1['auto_tags'])));
        $a2 = array_filter(array_map('trim', explode(',', (string)$c2['auto_tags'])));
        $autoMerge = array_unique(array_merge($a1, $a2));
        $autoStr   = $autoMerge ? implode(',', $autoMerge) : null;

        // Observações: concatena
        $obs1 = trim((string)$c1['observacoes']);
        $obs2 = trim((string)$c2['observacoes']);
        if ($obs1 !== '' && $obs2 !== '') {
            $observacoes = $obs1."\n\n--- Mescla com cliente #{$secundario_id} ---\n".$obs2;
        } else {
            $observacoes = $obs1 !== '' ? $obs1 : $obs2;
        }

        // Último contato: pega o mais recente
        $u1 = $c1['ultimo_contato'] ?? null;
        $u2 = $c2['ultimo_contato'] ?? null;
        if ($u1 && $u2) {
            $ultimo_contato = (strtotime($u1) >= strtotime($u2)) ? $u1 : $u2;
        } else {
            $ultimo_contato = $u1 ?: $u2;
        }

        // Atualiza o cliente principal
        $stmtU = $conn->prepare("
          UPDATE moz_wpp_cliente
             SET telefone      = ?,
                 nome          = ?,
                 email         = ?,
                 documento     = ?,
                 tipo_cliente  = ?,
                 origem        = ?,
                 usuario_id    = ?,
                 tags          = ?,
                 auto_tags     = ?,
                 observacoes   = ?,
                 ultimo_contato = ?,
                 updated_at    = NOW()
           WHERE id = ?
        ");
        $stmtU->bind_param(
            'sssssssisssi',
            $telefone,
            $nome,
            $email,
            $documento,
            $tipo_cliente,
            $origem,
            $usuario_id,
            $tagsStr,
            $autoStr,
            $observacoes,
            $ultimo_contato,
            $principal_id
        );
        $stmtU->execute();

        // Move conversas do secundário para o principal
        $stmtConv = $conn->prepare("
          UPDATE moz_wpp_conversa
             SET cliente_id = ?
           WHERE cliente_id = ?
        ");
        $stmtConv->bind_param('ii', $principal_id, $secundario_id);
        $stmtConv->execute();

        // Move logs de comportamento
        $stmtBeh = $conn->prepare("
          UPDATE moz_wpp_behavior_log
             SET cliente_id = ?
           WHERE cliente_id = ?
        ");
        $stmtBeh->bind_param('ii', $principal_id, $secundario_id);
        $stmtBeh->execute();

        // Marca o cliente secundário como mesclado
        $stmtM = $conn->prepare("
          UPDATE moz_wpp_cliente
             SET mesclado_em = NOW(),
                 mesclado_para_id = ?
           WHERE id = ?
        ");
        $stmtM->bind_param('ii', $principal_id, $secundario_id);
        $stmtM->execute();

        // Log de mescla
        $stmtLog = $conn->prepare("
          INSERT INTO moz_wpp_merge_log
            (cliente_principal_id, cliente_mesclado_id,
             snapshot_principal, snapshot_mesclado, created_at)
          VALUES (?,?,?,?, NOW())
        ");
        $stmtLog->bind_param(
            'iiss',
            $principal_id,
            $secundario_id,
            $snapPrincipal,
            $snapSecund
        );
        $stmtLog->execute();

        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        // Em produção, talvez logar o erro
        return false;
    }
}
