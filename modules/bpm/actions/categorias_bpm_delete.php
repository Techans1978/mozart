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

try{
  // só permite excluir se não tiver filhos
  $rs = $conn->query("SELECT 1 FROM bpm_categorias_paths WHERE ancestor_id=$id AND depth>0 LIMIT 1");
  if ($rs && $rs->num_rows>0){
    $_SESSION['__flash']=['m'=>'Não é possível excluir: existem subcategorias.'];
    header('Location: ../categorias_bpm_listar.php'); exit;
  }

  $conn->begin_transaction();
  $conn->query("DELETE FROM bpm_categorias_paths WHERE descendant_id=$id"); // remove caminhos do nó
  $conn->query("DELETE FROM bpm_categorias WHERE id=$id");
  $conn->commit();
  $_SESSION['__flash']=['m'=>'Categoria excluída.'];
} catch(Exception $e){
  $conn->rollback();
  $_SESSION['__flash']=['m'=>'Erro ao excluir: '.$e->getMessage()];
}
header('Location: ../categorias_bpm_listar.php');
