<?php
require_once __DIR__ . '/../../config.php';

// Garante que a sessão esteja iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpa e destrói a sessão
$_SESSION = [];
session_unset();
session_destroy();

// Redireciona para o login
header('Location: ' . BASE_URL . '/pages/login.php');
exit;
