<?php
// public/api/hd/reports/summary.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/connect.php';

$db = $conn ?? ($mysqli ?? null);
if (!$db || !($db instanceof mysqli)) { echo json_encode(['success'=>false,'error'=>'Sem conexão DB.']); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?: $_REQUEST;
$de   = isset($in['de'])   && $in['de']   !== '' ? $in['de']   : null;
$ate  = isset($in['ate'])  && $in['ate']  !== '' ? $in['ate']  : null;
$status = trim($in['status'] ?? '');
$prio   = trim($in['prio'] ?? '');
$grupo  = trim($in['grupo'] ?? '');
$loja   = trim($in['loja'] ?? '');

function has_col(mysqli $db,$t,$c){
  $s=$db->prepare("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
  $s->bind_param('ss',$t,$c); $s->execute(); $r=$s->get_result()->fetch_assoc(); return (int)$r['c']>0;
}

$w = [];
$types=''; $params=[];
if ($de){ $w[]="t.created_at >= ?"; $types.='s'; $params[]=$de.' 00:00:00'; }
if ($ate){ $w[]="t.created_at <= ?"; $types.='s'; $params[]=$ate.' 23:59:59'; }
if ($status!==''){ $w[]="t.status = ?"; $types.='s'; $params[]=$status; }
if ($prio!==''){ $w[]="t.prioridade = ?"; $types.='s'; $params[]=$prio; }
if ($grupo!==''){ $col = has_col($db,'hd_ticket','grupo_atual_id') ? 'grupo_atual_id' : (has_col($db,'hd_ticket','grupo_id') ? 'grupo_id' : null); if($col){ $w[]="t.$col = ?"; $types.='i'; $params[]=(int)$grupo; } }
if ($loja!==''){ if(has_col($db,'hd_ticket','loja_id')){ $w[]="t.loja_id = ?"; $types.='i'; $params[]=(int)$loja; } }
$where = $w ? ('WHERE '.implode(' AND ',$w)) : '';

$sql_total = "SELECT COUNT(*) c FROM hd_ticket t $where";
$s=$db->prepare($sql_total); if($types) $s->bind_param($types, ...$params); $s->execute(); $r=$s->get_result(); $total=(int)($r->fetch_assoc()['c']??0);

// abertos agora (independe do período)
$sql_abertos = "SELECT COUNT(*) c FROM hd_ticket WHERE status IN ('novo','aberto','pendente')";
$r = $db->query($sql_abertos); $abertos = (int)($r->fetch_assoc()['c']??0);

// resolvidos no período (se filtro de período)
$wr = [];
$typesR=''; $paramsR=[];
if ($de){ $wr[]="resolvido_at >= ?"; $typesR.='s'; $paramsR[]=$de.' 00:00:00'; }
if ($ate){ $wr[]="resolvido_at <= ?"; $typesR.='s'; $paramsR[]=$ate.' 23:59:59'; }
if ($wr){
  $sql_res = "SELECT COUNT(*) c FROM hd_ticket WHERE ".implode(' AND ',$wr);
  $sr=$db->prepare($sql_res); if($typesR) $sr->bind_param($typesR, ...$paramsR); $sr->execute(); $rr=$sr->get_result(); $resolvidos=(int)($rr->fetch_assoc()['c']??0);
} else {
  $resolvidos = 0;
}

// prioridades no período
$sql_prior = "SELECT prioridade, COUNT(*) c FROM hd_ticket t $where GROUP BY prioridade";
$s=$db->prepare($sql_prior); if($types) $s->bind_param($types, ...$params); $s->execute(); $r=$s->get_result();
$prior=[]; while($o=$r->fetch_assoc()){ $k = $o['prioridade']?:'(vazio)'; $prior[$k]=(int)$o['c']; }

// TTO/TTR médios (min)
$tem_tto = has_col($db,'hd_ticket','primeira_resposta_at');
$tem_ttr = has_col($db,'hd_ticket','resolvido_at');

$tto_avg=null; $ttr_avg=null;
if($tem_tto){
  $sql="SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, primeira_resposta_at)) m FROM hd_ticket t $where AND primeira_resposta_at IS NOT NULL";
  $sql = str_replace('WHERE AND','WHERE',$sql);
  $s=$db->prepare($sql); if($types) $s->bind_param($types, ...$params); $s->execute(); $r=$s->get_result(); $tto_avg = $r->fetch_assoc()['m']; if($tto_avg!==null) $tto_avg=(int)round($tto_avg);
}
if($tem_ttr){
  $sql="SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, resolvido_at)) m FROM hd_ticket t $where AND resolvido_at IS NOT NULL";
  $sql = str_replace('WHERE AND','WHERE',$sql);
  $s=$db->prepare($sql); if($types) $s->bind_param($types, ...$params); $s->execute(); $r=$s->get_result(); $ttr_avg = $r->fetch_assoc()['m']; if($ttr_avg!==null) $ttr_avg=(int)round($ttr_avg);
}

// SLA rompimentos (lê logs "sistema:sla")
$wlog = [];
$typesL=''; $paramsL=[];
if ($de){ $wlog[]="created_at >= ?"; $typesL.='s'; $paramsL[]=$de.' 00:00:00'; }
if ($ate){ $wlog[]="created_at <= ?"; $typesL.='s'; $paramsL[]=$ate.' 23:59:59'; }
$wlog[]="autor='sistema:sla'";
$lw = 'WHERE '.implode(' AND ',$wlog);
$sql="SELECT SUM(texto LIKE '%SLA TTO%') tto, SUM(texto LIKE '%SLA TTR%') ttr, COUNT(*) total FROM hd_ticket_log $lw";
$s=$db->prepare($sql); if($typesL) $s->bind_param($typesL, ...$paramsL); $s->execute(); $r=$s->get_result(); $o=$r->fetch_assoc();
$sla_total = (int)($o['total']??0); $sla_tto=(int)($o['tto']??0); $sla_ttr=(int)($o['ttr']??0);
$rate = function($q,$base){ if($base<=0) return '0%'; $v=($q/$base)*100; return number_format($v,1,',','.').'%'; };

$out = [
  'periodo' => ($de?$de:'…').' – '.($ate?$ate:'…'),
  'total' => $total,
  'abertos' => $abertos,
  'resolvidos' => $resolvidos,
  'prioridades' => $prior,
  'tto_avg_min' => $tto_avg,
  'ttr_avg_min' => $ttr_avg,
  'sla' => [
    'total' => $sla_total,
    'TTO' => ['qtd'=>$sla_tto, 'rate'=>$rate($sla_tto, max(1,$total))],
    'TTR' => ['qtd'=>$sla_ttr, 'rate'=>$rate($sla_ttr, max(1,$total))]
  ]
];

echo json_encode(['success'=>true,'data'=>$out]);
