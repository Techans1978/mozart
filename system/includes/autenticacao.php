<?php
// NUNCA deixe espaços/linhas acima deste <?php

// Inicia sessão só se ainda não estiver ativa
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Se você já inicia a sessão no config.php, pode até REMOVER o bloco acima.
 * O importante é: nenhum echo/print/var_dump aqui!
 */

function proteger_pagina() {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/pages/login.php');
        exit;
    }
}