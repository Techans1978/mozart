<?php
// pages/niveis_save.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$mysqli = $mysqli ?? ($conn ?? null);
if (!$mysqli) die('Sem conexão DB');

$id             = (int)($_POST['id'] ?? 0);
$nome           = trim($_POST['nome'] ?? '');
$descricao      = trim($_POST['descricao'] ?? '');
$acesso_backend = isset($_POST['acesso_backend']) ? 1 : 0;
$acesso_frontend= isset($_POST['acesso_frontend'])? 1 : 0;
$ativo          = isset($_POST['ativo']) ? 1 : 0;
$mods           = $_POST['mods'] ?? [];

if ($nome===''){ $_SESSION['flash_error'] = 'Informe o nome.'; header('Location: niveis_form.php'.($id?"?id=$id":'')); exit; }

$mysqli->begin_transaction();
try {
  if ($id) {
    $st = $mysqli->prepare("UPDATE acl_levels SET nome=?, descricao=?, acesso_backend=?, acesso_frontend=?, ativo=? WHERE id=?");
    $st->bind_param('ssiiii', $nome,$descricao,$acesso_backend,$acesso_frontend,$ativo,$id);
    $st->execute();
  } else {
    $st = $mysqli->prepare("INSERT INTO acl_levels (nome, descricao, acesso_backend, acesso_frontend, ativo) VALUES (?,?,?,?,?)");
    $st->bind_param('ssiii', $nome,$descricao,$acesso_backend,$acesso_frontend,$ativo);
    $st->execute();
    $id = (int)$st->insert_id;
  }

  // zera e regrava permissões
  $del = $mysqli->prepare("DELETE FROM acl_permissions WHERE level_id=?");
  $del->bind_param('i',$id);
  $del->execute();

  if (is_array($mods)) {
    $ins = $mysqli->prepare("INSERT INTO acl_permissions (level_id, modulo, pode_ver, pode_criar, pode_editar, pode_excluir, pode_aprovar)
                             VALUES (?,?,?,?,?,?,?)");
    foreach ($mods as $k => $row) {
      $mod = trim($row['modulo'] ?? $k);
      if ($mod==='') continue;
      $pv = !empty($row['pode_ver'])?1:0;
      $pc = !empty($row['pode_criar'])?1:0;
      $pe = !empty($row['pode_editar'])?1:0;
      $px = !empty($row['pode_excluir'])?1:0;
      $pa = !empty($row['pode_aprovar'])?1:0;
      $ins->bind_param('isiiiii', $id,$mod,$pv,$pc,$pe,$px,$pa);
      $ins->execute();
    }
  }

  $mysqli->commit();
  $_SESSION['flash_ok'] = 'Nível salvo com sucesso.';
  header('Location: niveis_listar.php');
  exit;

} catch (Throwable $e){
  $mysqli->rollback();
  $_SESSION['flash_error'] = 'Erro ao salvar: '.$e->getMessage();
  header('Location: niveis_form.php'.($id?"?id=$id":''));
  exit;
}
?>