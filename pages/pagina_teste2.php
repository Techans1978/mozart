<?php
// pages/pagina_teste2.php

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (function_exists('proteger_pagina')) {
    proteger_pagina();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Página de Teste 2 - <?= APP_NAME ?></title>
<?php include __DIR__ . '/navbar.php'; ?>

<div id="page-wrapper">
    <div class="container-fluid">

        <h1 class="page-header">Página de Teste 2</h1>

        <p>Mais uma página simples só para testar o segundo item do menu.</p>

        <div class="panel panel-info">
            <div class="panel-heading">
                Teste de Navegação
            </div>
            <div class="panel-body">
                <p>Clique entre <strong>Página de Teste 1</strong> e <strong>Página de Teste 2</strong> no menu lateral
                   para conferir se o <em>active</em> está funcionando como esperado.</p>
            </div>
        </div>

    </div>
</div>

<?php
// Footer padrão, se tiver
// include __DIR__ . '/footer.php';
?>
</body>
</html>
