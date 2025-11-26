<?php
// Mostrar erros (opcional, só pra dev)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php'; // se estiver na mesma pasta

// Agora pode usar ROOT_PATH e BASE_URL para includes e links
require_once __DIR__ . '/system/config/autenticacao.php';

// Exemplo de uso para redirecionar usuário logado
if (isset($_SESSION['usuario_id'])) {
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit;
} else {
    header("Location: " . BASE_URL . "/pages/login.php");
    exit;
}
?>