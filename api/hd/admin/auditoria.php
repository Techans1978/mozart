<?php
// public/api/hd/admin/auditoria.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once ROOT_PATH . '/includes/hd_rbac.php';

proteger_pagina();
$user_id = $_SESSION['usuario_id'] ?? 0;
hd_require($conn, $user_id, 'audit.view');

$action = $_POST['action'] ?? 'list';
function ok($d){ echo json_encode(['success'=>true,'data'=>$d]); exit; }
function err($m){ echo json_encode(['success'=>false,'message'=>$m]); exit; }

if ($action==='list'){
  $table_name = trim($_POST['table_name'] ?? '');
  $record_id  = (int)($_POST['record_id'] ?? 0);
  $changed_by = (int)($_POST['changed_by'] ?? 0);
  $date_from  = $_POST['date_from'] ?? null;
  $date_to    = $_POST['date_to'] ?? null;

  $w = []; $p = []; $t = '';
  if ($table_name) { $w[]="table_name=?"; $p[]=$table_name; $t.='s'; }
  if ($record_id)  { $w[]="record_id=?";  $p[]=$record_id;  $t.='i'; }
  if ($changed_by) { $w[]="changed_by=?"; $p[]=$changed_by; $t.='i'; }
  if ($date_from)  { $w[]="changed_at >= ?"; $p[]=$date_from . " 00:00:00"; $t.='s'; }
  if ($date_to)    { $w[]="changed_at <= ?"; $p[]=$date_to   . " 23:59:59"; $t.='s'; }

  $sql = "SELECT id,table_name,record_id,field_name,old_value,new_value,changed_by,changed_at,ip_address,user_agent,note
            FROM hd_audit_log";
  if ($w) $sql .= " WHERE ".implode(' AND ', $w);
  $sql .= " ORDER BY changed_at DESC LIMIT 500";

  $stmt = $conn->prepare($sql);
  if(!$stmt) err($conn->error);
  if ($p) { $stmt->bind_param($t, ...$p); }
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  while($r=$res->fetch_assoc()) $rows[] = $r;
  $stmt->close();

  ok($rows);
}

err('Ação inválida');
