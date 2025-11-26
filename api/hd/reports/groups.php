<?php
// public/api/hd/reports/groups.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/connect.php';

$db = $conn ?? ($mysqli ?? null);
if (!$db || !($db instanceof mysqli)) { echo json_encode(['success'=>false,'error'=>'Sem conexão DB.']); exit; }

function has_col(mysqli $db,$t,$c){
  $s=$db->prepare("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
  $s->bind_param('ss',$t,$c); $s->execute(); $r=$s->get_result()->fetch_assoc(); return (int)$r['c']>0;
}

$in = json_decode(file_get_contents('php://input'), true) ?: $_REQUEST;
$de = $in['de'] ?? null; $ate = $in['ate'] ?? null;

$gcol = has_col($db,'hd_ticket','grupo_atual_id') ? 'grupo_atual_id' : (has_col($db,'hd_ticket','grupo_id') ? 'grupo_id' : null);
if(!$gcol){ echo json_encode(['success'=>true,'data'=>[]]); exit; }

$w = []; $types=''; $params=[];
if ($de){ $w[]="created_at >= ?"; $types.='s'; $params[]=$de.' 00:00:00'; }
if ($ate){ $w[]="created_at <= ?"; $types.='s'; $params[]=$ate.' 23:59:59'; }
$where = $w ? ('WHERE '.implode(' AND ',$w)) : '';

$sql = "SELECT $gcol AS grupo_id, 
        SUM(status IN ('novo','aberto','pendente')) abertos,
        COUNT(*) criados
        FROM hd_ticket $where
        GROUP BY $gcol
        ORDER BY abertos DESC, criados DESC
        LIMIT 50";
$s=$db->prepare($sql); if($types) $s->bind_param($types, ...$params); $s->execute(); $r=$s->get_result();
$rows=[];
while($o=$r->fetch_assoc()){
  $o['grupo_id'] = (int)$o['grupo_id'];
  $o['abertos']  = (int)$o['abertos'];
  $o['criados']  = (int)$o['criados'];
  $o['nome']     = 'Grupo #'.$o['grupo_id']; // trocar quando houver tabela de grupos
  $rows[] = $o;
}
echo json_encode(['success'=>true,'data'=>$rows]);
