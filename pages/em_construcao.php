<?php
// Mostrar erros (opcional, só pra dev)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';

// Agora pode usar ROOT_PATH e BASE_URL para includes e links
require_once ROOT_PATH . '/includes/autenticacao.php';

require_once ROOT_PATH . '/config/connect_glpi.php';

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
                            <h1 class="page-header">    <h2>EM CONSTRUÇÃO</h2></h1>
                            Conteúdo em construção aguarde, volte em breve.
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