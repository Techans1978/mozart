<?php
// system/actions/acessar-painel.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/connect.php';

// Garante sessão ativa (login não inclui autenticacao.php)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Auditoria disponível
require_once ROOT_PATH . '/system/lib/auditoria.php';

// Se já está logado, manda pro dashboard
if (!empty($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

$erro     = '';
$username = '';

// CSRF simples (só para evitar post indevido)
if (empty($_SESSION['_csrf_login'])) {
    $_SESSION['_csrf_login'] = bin2hex(random_bytes(16));
}

/**
 * Carrega roles/permissões RBAC do usuário e salva na sessão.
 */
function mozart_carregar_permissoes_usuario(mysqli $conn, int $userId): void
{
    $roles = [];
    $perms = [];

    // Se suas tabelas RBAC ainda não existirem, apenas não carrega nada (não quebra)
    $check = $conn->query("SHOW TABLES LIKE 'rbac_user_role'");
    if (!$check || $check->num_rows === 0) {
        $_SESSION['user_roles']     = [];
        $_SESSION['user_perms']     = [];
        $_SESSION['user_perms_map'] = [];
        return;
    }

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

    $_SESSION['user_roles']     = $roles;
    $_SESSION['user_perms']     = array_keys($perms);
    $_SESSION['user_perms_map'] = $perms;
}

// Processa o POST do login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $senha    = (string)($_POST['senha'] ?? '');
    $csrf     = (string)($_POST['_csrf'] ?? '');

    if (!hash_equals($_SESSION['_csrf_login'] ?? '', $csrf)) {
        $erro = "Sessão expirada. Recarregue a página e tente novamente.";
    } elseif ($username === '' || $senha === '') {
        $erro = "Preencha todos os campos.";
    } else {

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

                    // Auditoria: LOGIN_FAIL (senha incorreta)
                    auditoria_log($conn, [
                        'action'    => 'LOGIN_FAIL',
                        'module'    => 'system',
                        'entity'    => 'usuarios',
                        'entity_id' => (string)$id,
                        'details'   => [
                            'username_informado' => $username,
                            'motivo' => 'senha_invalida'
                        ],
                        'severity'  => 'warn'
                    ]);
                } else {
                    // LOGIN OK
                    $_SESSION['user_id']        = (int)$id;
                    $_SESSION['username']       = $dbUsername;
                    $_SESSION['nome_completo']  = $nomeCompleto;
                    $_SESSION['email']          = $email;
                    $_SESSION['nivel_acesso']   = $nivelAcesso ?: 'usuario';

                    mozart_carregar_permissoes_usuario($conn, (int)$id);

                    $_SESSION['is_superadmin'] = ($nivelAcesso === 'bigboss');

                    // Atualiza último login / IP (se existir colunas; não derruba se não existir)
                    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                    @$conn->query("SET @__moz_ignore=1"); // no-op
                    if ($up = @$conn->prepare("UPDATE usuarios SET ultimo_login = NOW(), ultimo_ip = ? WHERE id = ?")) {
                        $up->bind_param('si', $ip, $id);
                        @$up->execute();
                        $up->close();
                    }

                    // Auditoria: LOGIN
                    auditoria_log($conn, [
                        'action'    => 'LOGIN',
                        'module'    => 'system',
                        'entity'    => 'usuarios',
                        'entity_id' => (string)$id,
                        'details'   => [
                            'username' => $dbUsername,
                            'ip'       => $ip
                        ],
                        'severity'  => 'info'
                    ]);

                    header("Location: " . BASE_URL . "/pages/dashboard.php");
                    exit();
                }
            } else {
                $erro = "Usuário ou senha inválidos.";

                // Auditoria: LOGIN_FAIL (usuário inexistente) - grava sem entity_id
                auditoria_log($conn, [
                    'action'   => 'LOGIN_FAIL',
                    'module'   => 'system',
                    'entity'   => 'usuarios',
                    'entity_id'=> null,
                    'details'  => [
                        'username_informado' => $username,
                        'motivo' => 'usuario_inexistente'
                    ],
                    'severity' => 'warn'
                ]);
            }

            $stmt->close();
        } else {
            $erro = "Erro na consulta: " . $conn->error;

            auditoria_log($conn, [
                'action'   => 'ERROR',
                'module'   => 'system',
                'entity'   => 'login',
                'details'  => $erro,
                'severity' => 'error'
            ]);
        }
    }
}

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

          <p style="text-align:center;">
            <img class="logo-image d-none d-lg-inline-block"
                 src="<?= h(BASE_URL) ?>/data/media/media_sytem/images/logo_mozart.png"
                 alt="Mozart"
                 width="150" height="auto">
          </p>

          <?php if ($erro): ?>
            <div class="alert alert-danger" style="margin-bottom: 15px;">
              <?= h($erro) ?>
            </div>
          <?php endif; ?>

          <form method="post" action="<?= h(BASE_URL) ?>/system/actions/acessar-painel.php">
            <input type="hidden" name="_csrf" value="<?= h($_SESSION['_csrf_login']) ?>">

            <div class="form-group">
              <label for="username">Usuário</label>
              <input type="text" class="form-control" id="username" name="username"
                     value="<?= h($username) ?>" autocomplete="username" required>
            </div>

            <div class="form-group">
              <label for="senha">Senha</label>
              <input type="password" class="form-control" id="senha" name="senha"
                     autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn btn-lg btn-success btn-block">Entrar</button>

            <div style="margin-top:10px; text-align:center;">
              <a href="<?= h(BASE_URL) ?>/pages/esqueci_senha.php">Esqueci minha senha</a>
            </div>
          </form>

        </div>
      </div>
    </div>
  </div>
</div>

<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>
</body>
</html>
