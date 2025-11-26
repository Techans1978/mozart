<?php
// public/api/hd/reports/schedule.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once ROOT_PATH . '/includes/hd_rbac.php';

proteger_pagina();
$user_id = $_SESSION['usuario_id'] ?? 0;
hd_require($conn, $user_id, 'report.manage');

$action = $_POST['action'] ?? 'list';

function json_ok($data){ echo json_encode(['success'=>true,'data'=>$data]); exit; }
function json_err($msg){ echo json_encode(['success'=>false,'message'=>$msg]); exit; }

if ($action === 'create') {
  $name = trim($_POST['name'] ?? '');
  $frequency = $_POST['frequency'] ?? '';
  $recipients = trim($_POST['recipients'] ?? '');
  $range = $_POST['range'] ?? 'last_7_days';

  if (!$name || !in_array($frequency,['daily','weekly','monthly']) || !$recipients) json_err('Dados inválidos');

  $params = json_encode(['range'=>$range, 'include_heatmap'=>true], JSON_UNESCAPED_UNICODE);

  // calcula next_run simples (amanhã 08:00, próxima segunda 08:00, 1º dia mês 08:00)
  $tz = new DateTimeZone(date_default_timezone_get());
  $now = new DateTime('now', $tz);
  $next = new DateTime('tomorrow 08:00', $tz);
  if ($frequency==='weekly') {
    $next = new DateTime('next monday 08:00', $tz);
  } elseif ($frequency==='monthly') {
    $d = new DateTime('first day of next month 08:00', $tz);
    $next = $d;
  }

  $sql = "INSERT INTO hd_report_schedule (name, frequency, params, recipients, is_active, next_run, created_by)
          VALUES (?,?,?,?,1,?,?)";
  $stmt = $conn->prepare($sql);
  if(!$stmt) json_err($conn->error);
  $nr = $next->format('Y-m-d H:i:s');
  $stmt->bind_param('sssssi', $name,$frequency,$params,$recipients,$nr,$user_id);
  $ok = $stmt->execute();
  $stmt->close();
  if(!$ok) json_err('Erro ao salvar');

  json_ok(['id'=>$conn->insert_id]);
}

if ($action === 'list') {
  $rows = [];
  $res = $conn->query("SELECT id,name,frequency,params,recipients,is_active,next_run,last_run FROM hd_report_schedule ORDER BY id DESC");
  while ($r = $res->fetch_assoc()) $rows[] = $r;
  json_ok($rows);
}

if ($action === 'toggle') {
  $id = (int)($_POST['id'] ?? 0);
  $active = (int)($_POST['is_active'] ?? 0);
  if (!$id) json_err('ID inválido');
  $stmt = $conn->prepare("UPDATE hd_report_schedule SET is_active=? WHERE id=?");
  $stmt->bind_param('ii', $active, $id);
  $ok = $stmt->execute(); $stmt->close();
  if(!$ok) json_err('Erro ao atualizar');
  json_ok(true);
}

if ($action === 'delete') {
  $id = (int)($_POST['id'] ?? 0);
  if (!$id) json_err('ID inválido');
  $stmt = $conn->prepare("DELETE FROM hd_report_schedule WHERE id=?");
  $stmt->bind_param('i', $id);
  $ok = $stmt->execute(); $stmt->close();
  if(!$ok) json_err('Erro ao excluir');
  json_ok(true);
}

json_err('Ação inválida');
