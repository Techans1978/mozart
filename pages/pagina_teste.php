<?php
// pages/pagina_teste.php

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se você usa essa função para proteger a área logada:
if (function_exists('proteger_pagina')) {
    proteger_pagina();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Página de Teste 1 - <?= APP_NAME ?></title>

    <!-- Coloque aqui seus CSS/JS globais se não tiver um include próprio -->
    <!-- Ex.: include __DIR__.'/includes/head_assets.php'; -->
<?php include __DIR__ . '/navbar.php'; ?>

<div id="page-wrapper">
    <div class="container-fluid">

        <h1 class="page-header">Página de Teste 1</h1>

        <p>Esta é apenas uma página de teste para validar o novo sistema de menus.</p>

        <div class="panel panel-default">
            <div class="panel-heading">
                Conteúdo de Teste
            </div>
            <div class="panel-body">
                <p>Se você está vendo este conteúdo ao clicar em <strong>Menu Teste &gt; Página de Teste 1</strong>, está tudo certo 🎯</p>
            </div>
        </div>

    </div>
</div>

<?php
// Se você tiver um footer padrão, inclua aqui:
// include __DIR__ . '/footer.php';
?>
</body>
</html>
