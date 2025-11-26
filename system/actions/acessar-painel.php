<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . 'system/config/connect.php';

// Verifica se o usuário já está logado
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $senha = $_POST['senha'] ?? '';

    if (empty($username) || empty($senha)) {
        $erro = "Preencha todos os campos.";
    } else {
        $sql = "SELECT id, senha FROM usuarios WHERE username = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id, $senhaHash);
                $stmt->fetch();

                if (password_verify($senha, $senhaHash)) {
                    $_SESSION['user_id'] = $id;
                    $_SESSION['username'] = $username;

                    header("Location: " . BASE_URL . "/pages/dashboard.php");
                    exit();
                } else {
                    $erro = "Senha incorreta.";
                }
            } else {
                $erro = "Usuário não encontrado.";
            }

            $stmt->close();
        } else {
            $erro = "Erro na consulta: " . $conn->error;
        }
    }
}

$conn->close();
?>

<?php
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
							<p><img class="logo-image d-none d-lg-inline-block" src="<?= BASE_URL ?>/data/media/media_sytem/images/logo_mozart.png" alt="Grupo ABC" width="150px" height="auto"></p>
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
