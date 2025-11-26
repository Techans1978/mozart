<?php
// public/api/hd/reports/compare.php
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
 * Assumindo colunas em hd_ticket:
 *  - created_at (aberto)
 *  - closed_at (fechado) nullable
 *  - first_response_at (para TTO) nullable
 * TTO e TTR em horas (médias)
 */

function period_stats(mysqli $conn, string $from, string $to) {
  $q = [];

  // Abertos no período
  $sql = "SELECT COUNT(*) c FROM hd_ticket WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)";
  $stmt = $conn->prepare($sql); $stmt->bind_param('ss',$from,$to); $stmt->execute(); $stmt->bind_result($c); $stmt->fetch(); $stmt->close();
  $q['opened'] = (int)$c;

  // Fechados no período
  $sql = "SELECT COUNT(*) c FROM hd_ticket WHERE closed_at IS NOT NULL AND closed_at >= ? AND closed_at < DATE_ADD(?, INTERVAL 1 DAY)";
  $stmt = $conn->prepare($sql); $stmt->bind_param('ss',$from,$to); $stmt->execute(); $stmt->bind_result($c); $stmt->fetch(); $stmt->close();
  $q['closed'] = (int)$c;

  // TTO médio (horas): first_response_at - created_at
  $sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, first_response_at))/3600.0 a
            FROM hd_ticket
           WHERE first_response_at IS NOT NULL AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)";
  $stmt = $conn->prepare($sql); $stmt->bind_param('ss',$from,$to); $stmt->execute(); $stmt->bind_result($a); $stmt->fetch(); $stmt->close();
  $q['tto_h'] = $a!==null ? round((float)$a,2) : null;

  // TTR médio (horas): closed_at - created_at
  $sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, closed_at))/3600.0 a
            FROM hd_ticket
           WHERE closed_at IS NOT NULL AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)";
  $stmt = $conn->prepare($sql); $stmt->bind_param('ss',$from,$to); $stmt->execute(); $stmt->bind_result($a); $stmt->fetch(); $stmt->close();
  $q['ttr_h'] = $a!==null ? round((float)$a,2) : null;

  // Backlog no final do período: tickets com created_at <= to e (closed_at IS NULL ou closed_at > to)
  $sql = "SELECT COUNT(*) c FROM hd_ticket
           WHERE created_at <= DATE_ADD(?, INTERVAL 1 DAY)
             AND (closed_at IS NULL OR closed_at > DATE_ADD(?, INTERVAL 1 DAY))";
  $stmt = $conn->prepare($sql); $stmt->bind_param('ss',$to,$to); $stmt->execute(); $stmt->bind_result($c); $stmt->fetch(); $stmt->close();
  $q['backlog_end'] = (int)$c;

  return $q;
}

// período anterior (mesma duração)
$dt_start = new DateTime($start);
$dt_end   = new DateTime($end);
$interval = $dt_start->diff($dt_end)->days + 1;

$prev_end = (clone $dt_start)->modify('-1 day');
$prev_start = (clone $prev_end)->modify('-'.($interval-1).' day');

$y_start = (clone $dt_start)->modify('-1 year');
$y_end   = (clone $dt_end)->modify('-1 year');

$cur = period_stats($conn, $start, $end);
$prv = period_stats($conn, $prev_start->format('Y-m-d'), $prev_end->format('Y-m-d'));
$yoy = period_stats($conn, $y_start->format('Y-m-d'), $y_end->format('Y-m-d'));

echo json_encode(['success'=>true,'data'=>[
  'current'=>$cur, 'prev'=>$prv, 'yoy'=>$yoy,
  'period'=>['start'=>$start,'end'=>$end],
  'prev_period'=>['start'=>$prev_start->format('Y-m-d'),'end'=>$prev_end->format('Y-m-d')],
  'yoy_period'=>['start'=>$y_start->format('Y-m-d'),'end'=>$y_end->format('Y-m-d')]
]]);
