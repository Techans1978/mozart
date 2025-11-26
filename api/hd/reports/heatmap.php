<?php
// public/api/hd/reports/heatmap.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once ROOT_PATH . '/includes/hd_rbac.php';

proteger_pagina();
$user_id = $_SESSION['usuario_id'] ?? 0;
if (!hd_can($conn, $user_id, 'report.view')) {
  echo json_encode(['success'=>false,'error'=>'forbidden','message'=>'Sem permissão']); exit;
}

$start = $_POST['start'] ?? null;
$end   = $_POST['end'] ?? null;

if (!$start || !$end) {
  echo json_encode(['success'=>false,'message'=>'Período inválido']); exit;
}

/**
 * Assumindo tabela hd_ticket com colunas:
 *  - created_at DATETIME (criação do ticket)
 */
$matrix = array_fill(0,7, array_fill(0,24,0));
$max = 0;

$sql = "SELECT DAYOFWEEK(created_at) AS dow, HOUR(created_at) AS hr, COUNT(*) AS qt
          FROM hd_ticket
         WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
         GROUP BY DAYOFWEEK(created_at), HOUR(created_at)";
$stmt = $conn->prepare($sql);
if(!$stmt){ echo json_encode(['success'=>false,'message'=>$conn->error]); exit; }
$stmt->bind_param('ss', $start, $end);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  // MySQL: 1=Domingo ... 7=Sábado
  $d = (int)$row['dow'] - 1; if ($d<0) $d=0; if ($d>6) $d=6;
  $h = (int)$row['hr']; if ($h<0) $h=0; if ($h>23) $h=23;
  $q = (int)$row['qt'];
  $matrix[$d][$h] = $q;
  if ($q > $max) $max = $q;
}
$stmt->close();

echo json_encode(['success'=>true,'data'=>['max'=>$max,'matrix'=>$matrix]]);
