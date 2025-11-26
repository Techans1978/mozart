<?php
require_once __DIR__ . '/../config.php'; // Isso já tem session_start()

// Apenas redirecionar se a sessão já existir e a requisição não for POST
if (isset($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}


include_once ROOT_PATH . '/system/includes/head.php';
?>
    <body>

        <div class="container">
            <div class="row">
                <div class="col-md-4 col-md-offset-4">
                    <div class="login-panel panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">Acesso ao Painel</h3>
                        </div>
                        <div class="panel-body">
							<p><img class="logo-image d-none d-lg-inline-block" src="<?= BASE_URL ?>/data/media/media_sytem/images/grupo-abc-logo.png" alt="Grupo ABC" width="150px" height="auto"></p>
							<fieldset>
							    <form action="<?= BASE_URL ?>/system/actions/acessar-painel.php" method="post">
									<div class="form-group">
										<label for="username">Usuário:</label>
										<input class="form-control" type="text" id="username" name="username" required>
									</div>

									<div class="form-group">
                                        <label for="senha">Senha:</label>
                                        <div class="input-group">
                                            <input class="form-control" type="password" id="senha" name="senha" required>
                                            <span class="input-group-text" style="cursor:pointer;" onclick="toggleSenha()">👁️ Exibir senha</span>
                                        </div>
                                    </div>

									<button type="submit">Entrar</button>
                                    <p style="margin-top:25px;"><a href="<?= BASE_URL ?>/recuperar_senha.php">Recuperar Senha</a><p>
								</form>
							</fieldset>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <script>
    function toggleSenha() {
        var input = document.getElementById("senha");
        input.type = input.type === "password" ? "text" : "password";
    }
    </script>
<?php include_once ROOT_PATH . '/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>