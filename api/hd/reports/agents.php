<?php
// public/api/hd/reports/agents.php
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
$de   = isset($in['de'])   && $in['de']   !== '' ? $in['de']   : null;
$ate  = isset($in['ate'])  && $in['ate']  !== '' ? $in['ate']  : null;

$acol = has_col($db,'hd_ticket','agente_atual_id') ? 'agente_atual_id' : null;

$rows=[];

if($acol){
  // Com coluna de agente atual
  // Em posse agora
  $q = "SELECT $acol AS agente_id, SUM(status IN ('novo','aberto','pendente')) AS posse
        FROM hd_ticket
        GROUP BY $acol
        HAVING agente_id IS NOT NULL
        ORDER BY posse DESC
        LIMIT 100";
  $r=$db->query($q); $posse=[];
  while($o=$r->fetch_assoc()){ $posse[(int)$o['agente_id']] = (int)$o['posse']; }

  // Resolvidos no período + TTR médio
  $w=[]; $types=''; $params=[];
  if ($de){ $w[]="resolvido_at >= ?"; $types.='s'; $params[]=$de.' 00:00:00'; }
  if ($ate){ $w[]="resolvido_at <= ?"; $types.='s'; $params[]=$ate.' 23:59:59'; }
  $wh = $w ? ('WHERE '.implode(' AND ',$w)) : '';
  $sql = "SELECT $acol AS agente_id, COUNT(*) resolvidos, AVG(TIMESTAMPDIFF(MINUTE, created_at, resolvido_at)) ttr
          FROM hd_ticket $wh
          AND $acol IS NOT NULL AND resolvido_at IS NOT NULL
          GROUP BY $acol
          ORDER BY resolvidos DESC
          LIMIT 100";
  $sql = str_replace('WHERE AND','WHERE',$sql);
  $s=$db->prepare($sql); if($types) $s->bind_param($types, ...$params); $s->execute(); $r=$s->get_result();
  while($o=$r->fetch_assoc()){
    $aid = (int)$o['agente_id'];
    $rows[] = [
      'agente_id'=>$aid,
      'nome'=>'Agente #'.$aid,
      'posse'=>$posse[$aid] ?? 0,
      'resolvidos'=>(int)$o['resolvidos'],
      'ttr_avg_min'=> $o['ttr']!==null ? (int)round($o['ttr']) : null
    ];
  }
} else {
  // Fallback via logs: conta ações por autor "agente:ID"
  $w=[]; $types=''; $params=[];
  if ($de){ $w[]="created_at >= ?"; $types.='s'; $params[]=$de.' 00:00:00'; }
  if ($ate){ $w[]="created_at <= ?"; $types.='s'; $params[]=$ate.' 23:59:59'; }
  $wh = $w ? ('WHERE '.implode(' AND ',$w)) : '';

  $sql="SELECT SUBSTRING_INDEX(autor,':',-1) idtxt, COUNT(*) acoes
        FROM hd_ticket_log $wh
        AND autor LIKE 'agente:%'
        GROUP BY SUBSTRING_INDEX(autor,':',-1)
        ORDER BY acoes DESC LIMIT 100";
  $sql=str_replace('WHERE AND','WHERE',$sql);
  $s=$db->prepare($sql); if($types) $s->bind_param($types, ...$params); $s->execute(); $r=$s->get_result();
  while($o=$r->fetch_assoc()){
    $aid = (int)$o['idtxt'];
    $rows[] = ['agente_id'=>$aid,'nome'=>'Agente #'.$aid,'posse'=>null,'resolvidos'=>null,'ttr_avg_min'=>null];
  }
}

echo json_encode(['success'=>true,'data'=>$rows]);
