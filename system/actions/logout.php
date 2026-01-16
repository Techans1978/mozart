<?php
require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/connect.php'; // $conn
require_once ROOT_PATH . '/system/lib/auditoria.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Guarda dados antes de destruir a sessão
$userId   = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['username'] ?? ($_SESSION['usuario'] ?? null);

// Auditoria: LOGOUT (antes do destroy)
if ($userId) {
  auditoria_log($conn, [
    'action'    => 'LOGOUT',
    'module'    => 'system',
    'entity'    => 'usuarios',
    'entity_id' => (string)$userId,
    'details'   => [
      'username' => $userName
    ],
    'severity'  => 'info'
  ]);
}

// Limpa e destrói a sessão
$_SESSION = [];
session_unset();
session_destroy();

header('Location: ' . BASE_URL . '/pages/login.php');
exit;
