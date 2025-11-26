<?php
// public/cron/hd_sla_worker.php
// Executa o cálculo de TTO/TTR e marca breach com base em hd_sla_policy.
// Agende no crontab: */5 * * * * php /caminho/public/cron/hd_sla_worker.php

ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/connect.php';

$db = $conn ?? ($mysqli ?? null);
if (!$db || !($db instanceof mysqli)) { fwrite(STDERR,"Sem conexão DB.\n"); exit(1); }

// Cria tabela auxiliar se não existir
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

// Helpers
function fetchOne($db,$sql,$types='',$params=[]){
  $s=$db->prepare($sql); if($types) $s->bind_param($types, ...$params);
  $s->execute(); $r=$s->get_result(); return $r? $r->fetch_assoc():null;
}
function fetchAll($db,$sql,$types='',$params=[]){
  $s=$db->prepare($sql); if($types) $s->bind_param($types, ...$params);
  $s->execute(); $r=$s->get_result(); $rows=[]; while($o=$r->fetch_assoc()) $rows[]=$o; return $rows;
}
function parseCal($json){ $o=json_decode($json,true); return is_array($o)?$o:[]; }

// Converte minutos úteis (considerando calendário/pausas) em um prazo absoluto
function addBusinessMinutes(DateTime $start, int $mins, array $cal, DateTimeZone $tz): DateTime {
  // cal chaves: ini_0..6, fim_0..6, pausas_0..6  (Dom..Sáb)
  $dt = clone $start;
  $remaining = $mins;
  // limita laço de segurança
  for ($guard=0;$guard<100000 && $remaining>0;$guard++){
    $dow = (int)$dt->format('w'); // 0..6
    $ini = $cal["ini_$dow"] ?? ''; $fim = $cal["fim_$dow"] ?? '';
    if ($ini && $fim) {
      [$ih,$im] = array_map('intval', explode(':',$ini)+[0,0]);
      [$fh,$fm] = array_map('intval', explode(':',$fim)+[0,0]);
      $wStart = (clone $dt)->setTime($ih,$im);
      $wEnd   = (clone $dt)->setTime($fh,$fm);

      // pula se fora da janela
      if ($dt < $wStart) $dt = clone $wStart;
      if ($dt >= $wEnd) { $dt->modify('+1 day')->setTime(0,0); continue; }

      // calcula minutos disponíveis hoje descontando pausas
      $minutesToday = (int)(($wEnd->getTimestamp() - $dt->getTimestamp())/60);
      $pausas = array_filter(array_map('trim', explode(',', $cal["pausas_$dow"] ?? '')));
      foreach($pausas as $p){
        if (strpos($p,'-')===false) continue;
        [$pa,$pb] = explode('-',$p,2);
        [$pah,$pam] = array_map('intval', explode(':',$pa)+[0,0]);
        [$pbh,$pbm] = array_map('intval', explode(':',$pb)+[0,0]);
        $pStart=(clone $dt)->setTime($pah,$pam);
        $pEnd  =(clone $dt)->setTime($pbh,$pbm);
        // interseção entre [dt..wEnd] e [pStart..pEnd]
        $lo = max($dt->getTimestamp(), $pStart->getTimestamp());
        $hi = min($wEnd->getTimestamp(), $pEnd->getTimestamp());
        if ($hi>$lo) $minutesToday -= (int)(($hi-$lo)/60);
      }

      if ($remaining <= $minutesToday) {
        $dt->modify("+$remaining minutes");
        return $dt;
      } else {
        // consome o dia e vai pro próximo
        $remaining -= $minutesToday;
        $dt = (clone $wEnd)->modify('+1 minute');
      }
    } else {
      // dia sem atendimento -> vai para próximo dia
      $dt->modify('+1 day')->setTime(0,0);
    }
  }
  return $dt; // fallback
}

// Busca políticas
$policies = fetchAll($db,"SELECT id,nome,prioridade,status_aplica,tto_min,ttr_min,calendario_json FROM hd_sla_policy","",[]);

// Index por (prioridade,status)
$byKey=[];
foreach($policies as $p){
  $k = ($p['prioridade']??'').'|'.($p['status_aplica']??'');
  $byKey[$k] = $p;
}

// Tickets candidatos: abertos/pendentes/novos
$tickets = fetchAll($db,"
  SELECT id, prioridade, status, created_at, first_response_at, resolved_at
  FROM hd_ticket
  WHERE status IN ('novo','aberto','pendente') 
  ORDER BY id DESC
  LIMIT 2000
");

$tz = new DateTimeZone('America/Sao_Paulo');
$now = new DateTime('now',$tz);

foreach($tickets as $t){
  $k1 = ($t['prioridade']??'').'|'.($t['status']??'');
  $k2 = ($t['prioridade']??'').'|'; // política por prioridade independente de status
  $pol = $byKey[$k1] ?? ($byKey[$k2] ?? null);
  if (!$pol) continue;

  $cal = parseCal($pol['calendario_json'] ?? '{}');
  $created = new DateTime($t['created_at'],$tz);
  $firstResp = !empty($t['first_response_at']) ? new DateTime($t['first_response_at'],$tz) : null;
  $resolved  = !empty($t['resolved_at']) ? new DateTime($t['resolved_at'],$tz) : null;

  // TTO: a partir do created_at
  $tto_due = ($pol['tto_min']>0) ? addBusinessMinutes($created, (int)$pol['tto_min'], $cal, $tz) : null;
  // TTR: a partir do created_at (ou da 1ª resposta, se preferir — aqui mantemos created)
  $ttr_due = ($pol['ttr_min']>0) ? addBusinessMinutes($created, (int)$pol['ttr_min'], $cal, $tz) : null;

  $tto_breached = ($tto_due && !$firstResp && $now > $tto_due) ? 1 : 0;
  $ttr_breached = ($ttr_due && !$resolved  && $now > $ttr_due) ? 1 : 0;

  // Upsert em hd_ticket_sla
  $row = fetchOne($db,"SELECT id FROM hd_ticket_sla WHERE ticket_id=?","i",[$t['id']]);
  if ($row){
    $s=$db->prepare("UPDATE hd_ticket_sla SET policy_id=?, tto_due=?, ttr_due=?, first_response_at=?, resolved_at=?, tto_breached=?, ttr_breached=?, updated_at=NOW() WHERE ticket_id=?");
    $ttoStr = $tto_due ? $tto_due->format('Y-m-d H:i:s') : null;
    $ttrStr = $ttr_due ? $ttr_due->format('Y-m-d H:i:s') : null;
    $frStr  = $firstResp ? $firstResp->format('Y-m-d H:i:s') : null;
    $rvStr  = $resolved ? $resolved->format('Y-m-d H:i:s') : null;
    $pid = (int)$pol['id'];
    $s->bind_param('issssiii', $pid,$ttoStr,$ttrStr,$frStr,$rvStr,$tto_breached,$ttr_breached,$t['id']);
    $s->execute();
  } else {
    $s=$db->prepare("INSERT INTO hd_ticket_sla (ticket_id,policy_id,tto_due,ttr_due,first_response_at,resolved_at,tto_breached,ttr_breached,created_at,updated_at)
                     VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())");
    $ttoStr = $tto_due ? $tto_due->format('Y-m-d H:i:s') : null;
    $ttrStr = $ttr_due ? $ttr_due->format('Y-m-d H:i:s') : null;
    $frStr  = $firstResp ? $firstResp->format('Y-m-d H:i:s') : null;
    $rvStr  = $resolved ? $resolved->format('Y-m-d H:i:s') : null;
    $pid = (int)$pol['id'];
    $s->bind_param('iisssii', $t['id'],$pid,$ttoStr,$ttrStr,$frStr,$rvStr,$tto_breached,$ttr_breached);
    $s->execute();
  }
}

echo json_encode(['success'=>true,'processed'=>count($tickets),'at'=>$now->format('Y-m-d H:i:s')]);
