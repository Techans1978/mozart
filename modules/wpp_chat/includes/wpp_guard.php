<?php
// public/modules/wpp_chat/includes/guard.php

require_once __DIR__.'/../../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
require_once __DIR__.'/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

proteger_pagina(); // padrão Mozart

/**
 * Ajuste essa função de acordo com o seu modelo de RBAC.
 * Aqui estou assumindo um $_SESSION['nivel_acesso'] numérico.
 */
function wpp_require_role($min_level = 1) {
    if (!isset($_SESSION['nivel_acesso']) || $_SESSION['nivel_acesso'] < $min_level) {
        http_response_code(403);
        echo "<h1>Acesso negado</h1><p>Você não tem permissão para acessar este módulo.</p>";
        exit;
    }
}

// Exemplo: 2 = gestor, 3 = admin
wpp_require_role(2);
