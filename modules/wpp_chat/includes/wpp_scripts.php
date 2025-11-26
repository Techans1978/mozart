<?php
// includes/wpp_scripts.php

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../../../config.php';
}

require_once ROOT_PATH . '/system/config/connect.php';

function wpp_script_find_all(mysqli $conn): array {
    $res = $conn->query("SELECT * FROM moz_wpp_script ORDER BY ativo DESC, nome ASC");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function wpp_script_find(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare("SELECT * FROM moz_wpp_script WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    return $r ?: null;
}

function wpp_script_save(mysqli $conn, array $data, int $user_id): int {
    $id          = isset($data['id']) ? (int)$data['id'] : 0;
    $nome        = trim($data['nome'] ?? '');
    $descricao   = trim($data['descricao'] ?? '');
    $xml         = trim($data['xml_definicao'] ?? '');
    $ativo       = isset($data['ativo']) ? 1 : 0;

    if ($id > 0) {
        $sql = "
          UPDATE moz_wpp_script
             SET nome = ?, descricao = ?, xml_definicao = ?, ativo = ?, updated_at = NOW()
           WHERE id = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssii', $nome, $descricao, $xml, $ativo, $id);
        $stmt->execute();
        return $id;
    } else {
        $sql = "
          INSERT INTO moz_wpp_script
              (nome, descricao, xml_definicao, ativo, criado_por, created_at, updated_at)
          VALUES (?,?,?,?,?, NOW(), NOW())
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssii', $nome, $descricao, $xml, $ativo, $user_id);
        $stmt->execute();
        return $stmt->insert_id;
    }
}
