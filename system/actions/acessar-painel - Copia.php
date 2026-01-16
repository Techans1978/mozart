<?php
// system/actions/acessar-painel.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . 'system/config/connect.php';

// Garante sessão ativa (login não inclui autenticacao.php)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Se já está logado, manda pro dashboard
if (!empty($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

require_once ROOT_PATH . '/system/lib/auditoria.php';

$erro     = '';
$username = '';

/**
 * Carrega roles/permissões RBAC do usuário e salva na sessão.
 * Usa as tabelas:
 *  - rbac_user_role (user_id, role_id)
 *  - rbac_role      (id, name, ativo)
 *  - rbac_role_perm (role_id, perm_id)
 *  - rbac_perm      (id, code)
 */
function mozart_carregar_permissoes_usuario(mysqli $conn, int $userId): void
{
    $roles = [];
    $perms = [];

    $sql = "
        SELECT
            r.id      AS role_id,
            r.name    AS role_name,
            p.code    AS perm_code
        FROM rbac_user_role ur
        JOIN rbac_role r
             ON r.id = ur.role_id
        LEFT JOIN rbac_role_perm rp
             ON rp.role_id = r.id
        LEFT JOIN rbac_perm p
             ON p.id = rp.perm_id
        WHERE ur.user_id = ?
    ";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            if (!empty($row['role_id'])) {
                $roles[$row['role_id']] = $row['role_name'] ?? ('role#' . $row['role_id']);
            }
            if (!empty($row['perm_code'])) {
                $perms[$row['perm_code']] = true; // set para evitar duplicados
            }
        }
        $stmt->close();
    }

    $_SESSION['user_roles']     = $roles;               // [role_id => nome]
    $_SESSION['user_perms']     = array_keys($perms);   // ['helpdesk.tickets.read', ...]
    $_SESSION['user_perms_map'] = $perms;               // ['helpdesk.tickets.read' => true, ...]
}

// Processa o POST do login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $senha    = $_POST['senha'] ?? '';

    if ($username === '' || $senha === '') {
        $erro = "Preencha todos os campos.";
    } else {
        // Ajuste os campos abaixo para o que você realmente tem na tabela `usuarios`
        $sql = "
            SELECT
                id,
                username,
                senha,
                nome_completo,
                email,
                nivel_acesso
            FROM usuarios
            WHERE username = ?
            LIMIT 1
        ";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result(
                    $id,
                    $dbUsername,
                    $senhaHash,
                    $nomeCompleto,
                    $email,
                    $nivelAcesso
                );
                $stmt->fetch();

                if (!password_verify($senha, $senhaHash)) {
                    $erro = "Usuário ou senha inválidos.";
                } else {
                    // LOGIN OK

                    // 1) Dados básicos
                    $_SESSION['user_id']        = (int)$id;
                    $_SESSION['username']       = $dbUsername;
                    $_SESSION['nome_completo']  = $nomeCompleto;
                    $_SESSION['email']          = $email;
                    $_SESSION['nivel_acesso']   = $nivelAcesso ?: 'usuario';

                    // 2) Carregar RBAC (roles + perms) na sessão
                    mozart_carregar_permissoes_usuario($conn, (int)$id);

                    // 3) Flag de superadmin (se quiser usar na usuario_tem_capabilities)
                    $_SESSION['is_superadmin'] = ($nivelAcesso === 'bigboss');

                    // 4) (Opcional) atualizar último login / IP, se tiver colunas
                    //    Comente este bloco se a tabela não tiver esses campos
                    if ($up = $conn->prepare("UPDATE usuarios SET ultimo_login = NOW(), ultimo_ip = ? WHERE id = ?")) {
                        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                        $up->bind_param('si', $ip, $id);
                        $up->execute();
                        $up->close();
                    }

                    // 5) Redireciona para o dashboard
                    header("Location: " . BASE_URL . "/pages/dashboard.php");
                    exit();
                }
            } else {
                $erro = "Usuário ou senha inválidos.";
            }

            $stmt->close();
        } else {
            $erro = "Erro na consulta: " . $conn->error;
        }
    }
}

// Pode fechar a conexão se quiser (não é obrigatório aqui)
// $conn->close();

include_once ROOT_PATH . '/system/includes/head.php';
?>
<body>
    <div class="container">
        <div class="row">
            <div class="col-md-4 col-md-offset-4">
                <div class="login-panel panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Acesso Administrador</h3>
                    </div>
                    <div class="panel-body">
                        <p>
                            <img class="logo-image d-none d-lg-inline-block"
                                 src="<?= BASE_URL ?>/data/media/media_sytem/images/logo_mozart.png"
                                 alt="Mozart"
                                 width="150px"
                                 height="auto">
                        </p>
                        <fieldset>
                            <?php if ($erro): ?>
                                <div style="color:red; margin-bottom:15px;">
                                    <?= htmlspecialchars($erro) ?>
                                </div>
                                <a href="javascript:history.back()">← Voltar para o login</a>
                            <?php endif; ?>
                        </fieldset>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
    include_once ROOT_PATH . '/system/includes/footer.php';
?>
