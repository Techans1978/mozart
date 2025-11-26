<?php
// Mostrar erros (opcional, só pra dev)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';

// Agora pode usar ROOT_PATH e BASE_URL para includes e links
require_once ROOT_PATH . '/includes/autenticacao.php';

require_once ROOT_PATH . '/config/connect.php';

include_once ROOT_PATH . '/includes/head.php';

include_once ROOT_PATH . '/includes/navbar.php';

?>

        <!-- Page Content -->
            <div id="page-wrapper">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-lg-12">
                            <h1 class="page-header"><?= APP_NAME ?></h1>
                        </div>
                        <!-- /.col-lg-12 -->
                    </div>
                    <div class="row">
                        <div class="col-lg-12"><!-- Content -->
                            <h1 class="page-header">    <h2>Cadastrar Usuário - <?= APP_NAME ?></h2></h1>
                            							    <form method="post">
									<div class="form-group">
										<label for="username">Username:</label>
										<input class="form-control" type="text" id="username" name="username" required>
									</div>

									<div class="form-group">
										<label for="username">Nome Completo:</label>
										<input class="form-control" type="text" id="username" name="username" required>
									</div>

									<div class="form-group">
										<label for="username">CPF:</label>
										<input class="form-control" type="text" id="username" name="username" required>
									</div>

									<div class="form-group">
										<label for="username">Cargo:</label>
										<input class="form-control" type="text" id="username" name="username" required>
									</div>

									<div class="form-group">
										<label for="username">E-mail:</label>
										<input class="form-control" type="text" id="username" name="username" required>
									</div>

									<div class="form-group">
										<label for="username">Telefone:</label>
										<input class="form-control" type="text" id="username" name="username" required>
									</div>

									<div class="form-group">
										<label for="username">Nivel de Acesso:</label>
										<input class="form-control" type="text" id="username" name="username" required>
									</div>

									<div class="form-group">
										<label for="username">Ativo:</label>
										<input class="form-control" type="text" id="username" name="username" required>
									</div>

									<div class="form-group">
										<label for="password">Senha:</label>
										<input class="form-control" type="password" id="password" name="password" required>
									</div>

									<div class="form-group">
										<label for="password">Confirmar Senha:</label>
										<input class="form-control" type="password" id="password" name="password" required>
									</div>

									<div class="form-group">
										<label for="password">Forçar alterar senha no primeiro acesso:</label>
										<input class="form-control" type="password" id="password" name="password" required>
									</div>

									<button type="submit">Registrar</button>
								</form>
                        </div>
                        <!-- /.col-lg-12 -->
                    </div>
                    <!-- /.row -->
                </div>
                <!-- /.container-fluid -->
            </div>
            <!-- /#page-wrapper -->
            <!-- /#page-wrapper -->

        </div>
        <!-- Page Content -->

<?php include_once ROOT_PATH . '/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . '/includes/footer.php'; ?>