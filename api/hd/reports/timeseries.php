<?php
// public/api/hd/reports/timeseries.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/connect.php';

$db = $conn ?? ($mysqli ?? null);
if (!$db || !($db instanceof mysqli)) { echo json_encode(['success'=>false,'error'=>'Sem conexão DB.']); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?: $_REQUEST;
$de   = isset($in['de'])   && $in['de']   !== '' ? $in['de']   : date('Y-m-d', strtotime('-29 days'));
$ate  = isset($in['ate'])  && $in['ate']  !== '' ? $in['ate']  : date('Y-m-d');

$rows=[];

// Criados por dia
$s=$db->prepare("SELECT DATE(created_at) d, COUNT(*) c FROM hd_ticket WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY d");
$s->bind_param('ss', $de.' 00:00:00', $ate.' 23:59:59'); $s->execute(); $r=$s->get_result();
$map_c=[]; while($o=$r->fetch_assoc()) $map_c[$o['d']] = (int)$o['c'];

// Resolvidos por dia (se houver coluna)
$has_res = $db->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hd_ticket' AND COLUMN_NAME='resolvido_at'")->fetch_row();
$map_r=[];
if($has_res){
  $s=$db->prepare("SELECT DATE(resolvido_at) d, COUNT(*) c FROM hd_ticket WHERE resolvido_at BETWEEN ? AND ? GROUP BY DATE(resolvido_at) ORDER BY d");
  $s->bind_param('ss', $de.' 00:00:00', $ate.' 23:59:59'); $s->execute(); $r=$s->get_result();
  while($o=$r->fetch_assoc()) $map_r[$o['d']] = (int)$o['c'];
}

// completa range
$start = new DateTime($de); $end = new DateTime($ate);
for($d=clone $start; $d <= $end; $d->modify('+1 day')){
  $key = $d->format('Y-m-d');
  $rows[] = ['dia'=>$key, 'criados'=>($map_c[$key]??0), 'resolvidos'=>($map_r[$key]??0)];
}

echo json_encode(['success'=>true,'data'=>$rows]);
