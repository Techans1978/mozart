<?php
// Mostrar erros (dev)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
// Autenticação e conexão
require_once ROOT_PATH . '/system/config/autenticacao.php';

// Cabeçalhos e navbar
include_once ROOT_PATH . 'system//includes/head.php';
include_once ROOT_PATH . 'system//includes/navbar.php';
?>
<!-- Page Content -->
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>
    <div class="row">
      <div class="col-lg-12">
        <h2>Requisição Não Encontrada - <?= APP_NAME ?></h2>
     </div>
    </div>
  </div>
</div>
<?php include_once ROOT_PATH . 'system//includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . 'system//includes/footer.php'; ?>
