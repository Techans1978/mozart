<?php
// public/api/hd/admin/rbac.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once ROOT_PATH . '/includes/hd_rbac.php';

proteger_pagina();
$user_id = $_SESSION['usuario_id'] ?? 0;
hd_require($conn, $user_id, 'rbac.manage');

$action = $_POST['action'] ?? 'list_all';

function ok($d){ echo json_encode(['success'=>true,'data'=>$d]); exit; }
function err($m){ echo json_encode(['success'=>false,'message'=>$m]); exit; }

if ($action==='create_role'){
  $name = trim($_POST['name'] ?? '');
  $description = trim($_POST['description'] ?? '');
  if(!$name) err('Nome obrigatório');
  $stmt = $conn->prepare("INSERT INTO hd_role (name, description) VALUES (?,?)");
  $stmt->bind_param('ss',$name,$description);
  if(!$stmt->execute()){ err('Erro: '.$conn->error); }
  $id = $conn->insert_id;
  $stmt->close();
  ok(['id'=>$id]);
}

if ($action==='delete_role'){
  $role_id = (int)($_POST['role_id'] ?? 0);
  if(!$role_id) err('Role inválida');
  $stmt = $conn->prepare("DELETE FROM hd_role WHERE id=?");
  $stmt->bind_param('i',$role_id);
  $ok = $stmt->execute(); $stmt->close();
  if(!$ok) err('Erro ao excluir');
  ok(true);
}

if ($action==='grant_perm' || $action==='revoke_perm'){
  $role_id = (int)($_POST['role_id'] ?? 0);
  $perm_code = trim($_POST['perm_code'] ?? '');
  if(!$role_id || !$perm_code) err('Dados inválidos');

  // Descobrir perm_id
  $perm_id = null;
  $stmt = $conn->prepare("SELECT id FROM hd_permission WHERE code=?");
  $stmt->bind_param('s',$perm_code); $stmt->execute(); $stmt->bind_result($perm_id); $stmt->fetch(); $stmt->close();
  if(!$perm_id) err('Permissão não encontrada');

  if ($action==='grant_perm'){
    $stmt = $conn->prepare("INSERT IGNORE INTO hd_role_perm (role_id,perm_id) VALUES (?,?)");
    $stmt->bind_param('ii',$role_id,$perm_id);
    $ok = $stmt->execute(); $stmt->close();
    if(!$ok) err('Erro');
    ok(true);
  } else {
    $stmt = $conn->prepare("DELETE FROM hd_role_perm WHERE role_id=? AND perm_id=?");
    $stmt->bind_param('ii',$role_id,$perm_id);
    $ok = $stmt->execute(); $stmt->close();
    if(!$ok) err('Erro');
    ok(true);
  }
}

if ($action==='assign_user_role' || $action==='revoke_user_role'){
  $rid = (int)($_POST['role_id'] ?? 0);
  $uid = (int)($_POST['user_id'] ?? 0);
  if(!$rid || !$uid) err('Dados inválidos');

  if ($action==='assign_user_role'){
    $stmt = $conn->prepare("INSERT IGNORE INTO hd_user_role (user_id,role_id) VALUES (?,?)");
    $stmt->bind_param('ii',$uid,$rid);
    $ok = $stmt->execute(); $stmt->close();
    if(!$ok) err('Erro');
    ok(true);
  } else {
    $stmt = $conn->prepare("DELETE FROM hd_user_role WHERE user_id=? AND role_id=?");
    $stmt->bind_param('ii',$uid,$rid);
    $ok = $stmt->execute(); $stmt->close();
    if(!$ok) err('Erro');
    ok(true);
  }
}

if ($action==='list_all'){
  // roles + perms
  $roles = [];
  $res = $conn->query("SELECT id,name,description FROM hd_role ORDER BY id DESC");
  while ($r = $res->fetch_assoc()) $roles[$r['id']] = ['id'=>$r['id'],'name'=>$r['name'],'description'=>$r['description'],'perms'=>[]];

  if ($roles){
    $ids = implode(',', array_map('intval', array_keys($roles)));
    $q = $conn->query("SELECT rp.role_id, p.code FROM hd_role_perm rp JOIN hd_permission p ON p.id=rp.perm_id WHERE rp.role_id IN ($ids)");
    while ($x=$q->fetch_assoc()){
      $roles[(int)$x['role_id']]['perms'][] = ['code'=>$x['code']];
    }
  }

  // users_by_role
  $users_by_role = [];
  $q = $conn->query("SELECT role_id, user_id FROM hd_user_role ORDER BY role_id, user_id");
  while ($x=$q->fetch_assoc()){
    $rid = (int)$x['role_id'];
    if(!isset($users_by_role[$rid])) $users_by_role[$rid] = [];
    $users_by_role[$rid][] = (int)$x['user_id'];
  }

  ok(['roles'=>array_values($roles), 'users_by_role'=>$users_by_role]);
}

err('Ação inválida');
