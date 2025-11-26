<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (!isset($conn) && isset($mysqli)) { $conn = $mysqli; }
if (!($conn instanceof mysqli)) { die('Conexão MySQLi $conn não encontrada.'); }
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }


$id = (int)($_GET['id'] ?? 0);
if ($id<=0){ header('Location: ../categorias_bpm_listar.php'); exit; }

$conn->query("UPDATE bpm_categorias SET ativo = IF(ativo=1,0,1) WHERE id=$id");
$_SESSION['__flash']=['m'=>'Categoria atualizada.'];
header('Location: ../categorias_bpm_listar.php');
