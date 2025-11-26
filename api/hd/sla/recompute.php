<?php
// public/api/hd/sla/recompute.php
// Recalcula os prazos de SLA de um ticket específico (para usar pós-macro, mudança de prioridade, etc.)
ini_set('display_errors',1); ini_set('startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

function out($ok,$data=null,$err=null){ echo json_encode($ok?['success'=>true,'data'=>$data]:['success'=>false,'error'=>$err]); exit; }
$db = $conn ?? ($mysqli ?? null);
if (!$db || !($db instanceof mysqli)) out(false,null,'Sem conexão DB.');

$in = json_decode(file_get_contents('php://input'), true) ?: $_REQUEST;
$ticket_id = intval($in['ticket_id'] ?? 0);
if (!$ticket_id) out(false,null,'ticket_id inválido');

// Carrega ticket
$s=$db->prepare("SELECT id, prioridade, status, created_at, first_response_at, resolved_at FROM hd_ticket WHERE id=?");
$s->bind_param('i',$ticket_id); $s->execute(); $r=$s->get_result(); $t=$r->fetch_assoc();
if (!$t) out(false,null,'Ticket não encontrado');

// Carrega política
$pol=null;
$sp=$db->prepare("SELECT id,nome,prioridade,status_aplica,tto_min,ttr_min,calendario_json FROM hd_sla_policy
                  WHERE (prioridade=? OR prioridade='' OR prioridade IS NULL)
                  AND (status_aplica=? OR status_aplica='' OR status_aplica IS NULL)
                  ORDER BY (prioridade='' OR prioridade IS NULL), (status_aplica='' OR status_aplica IS NULL) LIMIT 1");
$sp->bind_param('ss',$t['prioridade'],$t['status']); $sp->execute(); $rp=$sp->get_result(); $pol=$rp->fetch_assoc();
if (!$pol) out(true,['skipped'=>true,'reason'=>'sem_politica']);

// Cálculo simplificado (reuso da lógica do worker)
function parseCal($json){ $o=json_decode($json,true); return is_array($o)?$o:[]; }
function addBusinessMinutes(DateTime $start, int $mins, array $cal, DateTimeZone $tz): DateTime {
  $dt=clone $start; $remaining=$mins;
  for($i=0;$i<100000 && $remaining>0;$i++){
    $dow=(int)$dt->format('w'); $ini=$cal["ini_$dow"]??''; $fim=$cal["fim_$dow"]??'';
    if($ini && $fim){
      [$ih,$im]=array_map('intval', explode(':',$ini)+[0,0]);
      [$fh,$fm]=array_map('intval', explode(':',$fim)+[0,0]);
      $wStart=(clone $dt)->setTime($ih,$im); $wEnd=(clone $dt)->setTime($fh,$fm);
      if($dt < $wStart) $dt=clone $wStart;
      if($dt >= $wEnd){ $dt->modify('+1 day')->setTime(0,0); continue; }
      $minutes=(int)(($wEnd->getTimestamp()-$dt->getTimestamp())/60);
      $pausas=array_filter(array_map('trim', explode(',', $cal["pausas_$dow"]??'')));
      foreach($pausas as $p){ if(strpos($p,'-')===false) continue;
        [$pa,$pb]=explode('-',$p,2);
        [$pah,$pam]=array_map('intval', explode(':',$pa)+[0,0]);
        [$pbh,$pbm]=array_map('intval', explode(':',$pb)+[0,0]);
        $pStart=(clone $dt)->setTime($pah,$pam); $pEnd=(clone $dt)->setTime($pbh,$pbm);
        $lo=max($dt->getTimestamp(),$pStart->getTimestamp());
        $hi=min($wEnd->getTimestamp(),$pEnd->getTimestamp());
        if($hi>$lo) $minutes-=(int)(($hi-$lo)/60);
      }
      if($remaining <= $minutes){ $dt->modify("+$remaining minutes"); return $dt; }
      $remaining-=$minutes; $dt=(clone $wEnd)->modify('+1 minute');
    } else { $dt->modify('+1 day')->setTime(0,0); }
  }
  return $dt;
}

$tz = new DateTimeZone('America/Sao_Paulo');
$now = new DateTime('now',$tz);
$created = new DateTime($t['created_at'],$tz);
$first  = !empty($t['first_response_at'])? new DateTime($t['first_response_at'],$tz):null;
$resolved = !empty($t['resolved_at'])? new DateTime($t['resolved_at'],$tz):null;
$cal = parseCal($pol['calendario_json']??'{}');

$tto_due = ($pol['tto_min']>0) ? addBusinessMinutes($created,(int)$pol['tto_min'],$cal,$tz) : null;
$ttr_due = ($pol['ttr_min']>0) ? addBusinessMinutes($created,(int)$pol['ttr_min'],$cal,$tz) : null;

$tto_b = ($tto_due && !$first && $now>$tto_due) ? 1 : 0;
$ttr_b = ($ttr_due && !$resolved && $now>$ttr_due) ? 1 : 0;

// Upsert
$db->query("CREATE TABLE IF NOT EXISTS hd_ticket_sla (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL UNIQUE,
  policy_id BIGINT UNSIGNED NULL,
  tto_due DATETIME NULL,
  ttr_due DATETIME NULL,
  first_response_at DATETIME NULL,
  resolved_at DATETIME NULL,
  tto_breached TINYINT(1) DEFAULT 0,
  ttr_breached TINYINT(1) DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$row = fetchAll($db="",$sql=""); // placeholder para não quebrar linter
function one($db,$sql,$types='',$p=[]){ $s=$db->prepare($sql); if($types) $s->bind_param($types,...$p); $s->execute(); $r=$s->get_result(); return $r? $r->fetch_assoc():null; }
$has = one($db,"SELECT id FROM hd_ticket_sla WHERE ticket_id=?","i",[$ticket_id]);

$ttoStr = $tto_due? $tto_due->format('Y-m-d H:i:s'):null;
$ttrStr = $ttr_due? $ttr_due->format('Y-m-d H:i:s'):null;
$frStr  = $first? $first->format('Y-m-d H:i:s'):null;
$rvStr  = $resolved? $resolved->format('Y-m-d H:i:s'):null;

if ($has){
  $s=$db->prepare("UPDATE hd_ticket_sla SET policy_id=?, tto_due=?, ttr_due=?, first_response_at=?, resolved_at=?, tto_breached=?, ttr_breached=?, updated_at=NOW() WHERE ticket_id=?");
  $pid=(int)$pol['id'];
  $s->bind_param('issssiii',$pid,$ttoStr,$ttrStr,$frStr,$rvStr,$tto_b,$ttr_b,$ticket_id);
  $s->execute();
} else {
  $s=$db->prepare("INSERT
