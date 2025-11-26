<?php
require 'autenticacao.php';
proteger_pagina();

require 'conexao.php'; // conexão $conn (mysqli)

// ===== Helpers =====
function so_digitos($s){ return preg_replace('/\D+/', '', $s); }

/**
 * Valida se não há conflito pai/filho em uma lista de IDs,
 * usando a tabela de paths (closure table).
 */
function validaHierarquia(mysqli $conn, array $ids, string $tabelaPaths): ?string {
    $ids = array_map('intval', $ids);
    $ids = array_unique($ids);
    if (count($ids) <= 1) return null;

    $in = implode(',', $ids);
    $sql = "
      SELECT 1
      FROM {$tabelaPaths} p
      WHERE p.depth >= 1
        AND p.ancestor_id IN ({$in})
        AND p.descendant_id IN ({$in})
      LIMIT 1
    ";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        return "Não é permitido selecionar simultaneamente um item e seu pai/filho.";
    }
    return null;
}

function cadastrarUsuario(
    mysqli $conn,
    string $nome,
    string $cpf,
    string $cargo,
    string $email,
    string $senha,
    ?string $telefone,
    string $nivel_acesso,
    array $grupos_ids,
    array $perfis_ids
) {
    // Normalizações
    $cpf = so_digitos($cpf);
    $telefone = $telefone ? so_digitos($telefone) : null;

    // Verifica se email ou CPF já existem
    $sqlCheck = "SELECT id FROM usuarios WHERE email = ? OR cpf = ?";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bind_param("ss", $email, $cpf);
    $stmtCheck->execute();
    $stmtCheck->store_result();

    if ($stmtCheck->num_rows > 0) {
        $stmtCheck->close();
        return "Email ou CPF já cadastrado!";
    }
    $stmtCheck->close();

    // Valida hierarquia grupos/perfis
    if ($msg = validaHierarquia($conn, $grupos_ids, "grupos_paths")) {
        return $msg;
    }
    if ($msg = validaHierarquia($conn, $perfis_ids, "perfis_paths")) {
        return $msg;
    }

    // Hash da senha
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    // Inserir usuário
    $sqlInsert = "INSERT INTO usuarios (nome_completo, cpf, cargo, email, senha, telefone, nivel_acesso, ativo, data_cadastro, tentativas_login, bloqueado)
                  VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), 0, 0)";
    $stmt = $conn->prepare($sqlInsert);
    $stmt->bind_param("sssssss", $nome, $cpf, $cargo, $email, $senhaHash, $telefone, $nivel_acesso);

    if (!$stmt->execute()) {
        return "Erro ao cadastrar: " . $stmt->error;
    }
    $userId = $stmt->insert_id;
    $stmt->close();

    // Vincular grupos
    if (!empty($grupos_ids)) {
        $stmtG = $conn->prepare("INSERT INTO usuarios_grupos (usuario_id, grupo_id, is_primary) VALUES (?, ?, 0)");
        foreach ($grupos_ids as $gid) {
            $gid = (int)$gid;
            if ($gid > 0) {
                $stmtG->bind_param("ii", $userId, $gid);
                $stmtG->execute();
            }
        }
        $stmtG->close();
    }

    // Vincular perfis
    if (!empty($perfis_ids)) {
        $stmtP = $conn->prepare("INSERT INTO usuarios_perfis (usuario_id, perfil_id, is_primary) VALUES (?, ?, 0)");
        foreach ($perfis_ids as $pid) {
            $pid = (int)$pid;
            if ($pid > 0) {
                $stmtP->bind_param("ii", $userId, $pid);
                $stmtP->execute();
            }
        }
        $stmtP->close();
    }

    return true;
}

// ===== Execução =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome         = $_POST['nome_completo'] ?? '';
    $cpf          = $_POST['cpf'] ?? '';
    $cargo        = $_POST['cargo'] ?? '';
    $email        = $_POST['email'] ?? '';
    $senha        = $_POST['senha'] ?? '';
    $telefone     = $_POST['telefone'] ?? null;
    $nivel_acesso = $_POST['nivel_acesso'] ?? 'usuario';

    $grupos_ids = $_POST['grupos_ids'] ?? [];
    $perfis_ids = $_POST['perfis_ids'] ?? [];

    $resultado = cadastrarUsuario($conn, $nome, $cpf, $cargo, $email, $senha, $telefone, $nivel_acesso, $grupos_ids, $perfis_ids);

    if ($resultado === true) {
        echo "Usuário cadastrado com sucesso!";
    } else {
        echo $resultado;
    }
}
?>
