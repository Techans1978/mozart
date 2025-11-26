<?php
// public/api/hd/reports/export.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/connect.php';

$db = $conn ?? ($mysqli ?? null);
if (!$db || !($db instanceof mysqli)) { http_response_code(500); die('Sem conexão DB.'); }

$de   = isset($_GET['de'])   && $_GET['de']   !== '' ? $_GET['de']   : null;
$ate  = isset($_GET['ate'])  && $_GET['ate']  !== '' ? $_GET['ate']  : null;
$status = trim($_GET['status'] ?? '');
$prio   = trim($_GET['prio'] ?? '');
$grupo  = trim($_GET['grupo'] ?? '');
$loja   = trim($_GET['loja'] ?? '');

function has_col(mysqli $db,$t,$c){
  $s=$db->prepare("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
  $s->bind_param('ss',$t,$c); $s->execute(); $r=$s->get_result()->fetch_assoc(); return (int)$r['c']>0;
}

$cols = ['id','protocolo','assunto','status','prioridade','created_at'];
if (has_col($db,'hd_ticket','resolvido_at')) $cols[]='resolvido_at';
if (has_col($db,'hd_ticket','primeira_resposta_at')) $cols[]='primeira_resposta_at';
if (has_col($db,'hd_ticket','grupo_atual_id')) $cols[]='grupo_atual_id';
if (has_col($db,'hd_ticket','loja_id')) $cols[]='loja_id';

$w=[]; $types=''; $params=[];
if ($de){ $w[]="created_at >= ?"; $types.='s'; $params[]=$de.' 00:00:00'; }
if ($ate){ $w[]="created_at <= ?"; $types.='s'; $params[]=$ate.' 23:59:59'; }
if ($status!==''){ $w[]="status = ?"; $types.='s'; $params[]=$status; }
if ($prio!==''){ $w[]="prioridade = ?"; $types.='s'; $params[]=$prio; }
if ($grupo!==''){ if(has_col($db,'hd_ticket','grupo_atual_id')){ $w[]="grupo_atual_id = ?"; $types.='i'; $params[]=(int)$grupo; } }
if ($loja!==''){ if(has_col($db,'hd_ticket','loja_id')){ $w[]="loja_id = ?"; $types.='i'; $params[]=(int)$loja; } }

$where = $w ? ('WHERE '.implode(' AND ',$w)) : '';
$sql = "SELECT ".implode(',',$cols)." FROM hd_ticket $where ORDER BY id DESC LIMIT 50000";

$s=$db->prepare($sql); if($types) $s->bind_param($types, ...$params); $s->execute(); $res=$s->get_result();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="helpdesk_export.csv"');
$fp = fopen('php://output','w');
fputcsv($fp, $cols,';');
while($row=$res->fetch_assoc()){
  $out=[]; foreach($cols as $c){ $out[] = $row[$c] ?? ''; }
  fputcsv($fp, $out,';');
}
fclose($fp);
exit;
