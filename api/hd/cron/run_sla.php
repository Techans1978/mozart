<?php
// public/api/hd/cron/run_sla.php
// Recalcula TTO/TTR e marca rompimentos, dispara evento "on_sla_breach" (flag em log)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__.'/../../../config.php';
require_once ROOT_PATH.'/system/config/connect.php';

// Proteção simples por token (opcional)
$token_ok = true;
if (!empty($_GET['token'])) {
  $token_ok = hash_equals($_GET['token'], getenv('HD_CRON_TOKEN') ?: 'changeme');
}
if (!$token_ok) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'forbidden']); exit; }

$db = $conn ?? ($mysqli ?? null);
if (!$db || !($db instanceof mysqli)) { http_response_code(500); echo json_encode(['success'=>false,'error'=>'Sem conexão DB.']); exit; }

// helpers
function getSlaPolicyFor($db, $ticket){
  // simples: por prioridade + status
  $prio = $ticket['prioridade'] ?? '';
  $st   = $ticket['status'] ?? '';
  $sql="SELECT * FROM hd_sla_policy
        WHERE (prioridade='' OR prioridade IS NULL OR prioridade=?)
          AND (status_aplica='' OR status_aplica IS NULL OR status_aplica=?)
        ORDER BY (prioridade IS NULL), (status_aplica IS NULL) LIMIT 1";
  $s=$db->prepare($sql); $s->bind_param('ss',$prio,$st); $s->execute(); $r=$s->get_result(); return $r->fetch_assoc() ?: null;
}

function businessMinutesBetween(DateTime $a, DateTime $b, array $cal): int {
  // cálculo simples: soma minutos apenas dentro do range diário [ini..fim] exceto pausas
  if ($b <= $a) return 0;
  $tz = new DateTimeZone('America/Sao_Paulo');
  $cur = clone $a; $cur->setTimezone($tz);
  $end = clone $b; $end->setTimezone($tz);
  $mins = 0;

  while ($cur < $end) {
    $d = (int)$cur->format('w'); // 0..6 (Dom..Sáb)
    $ini = $cal["ini_{$d}"] ?? '';
    $fim = $cal["fim_{$d}"] ?? '';
    $pausas = $cal["pausas_{$d}"] ?? '';
    if ($ini && $fim) {
      $dayStart = DateTime::createFromFormat('Y-m-d H:i', $cur->format('Y-m-d').' '.$ini, $tz);
      $dayEnd   = DateTime::createFromFormat('Y-m-d H:i', $cur->format('Y-m-d').' '.$fim, $tz);
      $from = max($cur, $dayStart);
      $to   = min($end, $dayEnd);
      if ($to > $from) {
        // pausas
        $block = ($to->getTimestamp() - $from->getTimestamp())/60;
        if ($pausas) {
          foreach (explode(',', $pausas) as $p) {
            $p = trim($p); if(!$p) continue;
            if (!preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/',$p,$m)) continue;
            $pIni = DateTime::createFromFormat('Y-m-d H:i', $cur->format('Y-m-d').' '.$m[1], $tz);
            $pFim = DateTime::createFromFormat('Y-m-d H:i', $cur->format('Y-m-d').' '.$m[2], $tz);
            $pf = max($from,$pIni); $pt = min($to,$pFim);
            if ($pt > $pf) $block -= ($pt->getTimestamp()-$pf->getTimestamp())/60;
          }
        }
        if ($block>0) $mins += (int)round($block);
      }
    }
    // passa para o próximo dia
    $cur = DateTime::createFromFormat('Y-m-d H:i', $cur->modify('+1 day')->format('Y-m-d').' 00:00', $tz);
  }
  return max(0,$mins);
}

// pega tickets abertos/pendentes etc.
$q = "SELECT id, protocolo, prioridade, status, created_at,
             primeira_resposta_at, resolvido_at
      FROM hd_ticket
      WHERE status IN ('novo','aberto','pendente') /* ajuste conforme seu fluxo */ 
      ORDER BY id DESC LIMIT 1000";
$res = $db->query($q);
$agora = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
$breaches = [];

while ($t = $res->fetch_assoc()){
  $pol = getSlaPolicyFor($db, $t);
  if (!$pol) continue;
  $cal = $pol['calendario_json'] ? json_decode($pol['calendario_json'], true) : [];

  // TTO
  if (!empty($pol['tto_min']) && empty($t['primeira_resposta_at'])) {
    $created = new DateTime($t['created_at'] ?? 'now', new DateTimeZone('America/Sao_Paulo'));
    $elapsed = businessMinutesBetween($created, $agora, $cal);
    if ($elapsed > (int)$pol['tto_min']) {
      $breaches[] = ['tipo'=>'TTO','ticket_id'=>$t['id'],'protocolo'=>$t['protocolo']];
    }
  }
  // TTR
  if (!empty($pol['ttr_min']) && empty($t['resolvido_at'])) {
    $created = new DateTime($t['created_at'] ?? 'now', new DateTimeZone('America/Sao_Paulo'));
    $elapsed = businessMinutesBetween($created, $agora, $cal);
    if ($elapsed > (int)$pol['ttr_min']) {
      $breaches[] = ['tipo'=>'TTR','ticket_id'=>$t['id'],'protocolo'=>$t['protocolo']];
    }
  }
}

// loga e sinaliza evento
if (!empty($breaches)) {
  // registra em log
  foreach ($breaches as $b) {
    @$db->query(sprintf(
      "INSERT INTO hd_ticket_log(ticket_id, autor, texto, created_at) VALUES (%d,'%s','%s', NOW())",
      (int)$b['ticket_id'],
      $db->real_escape_string('sistema:sla'),
      $db->real_escape_string("SLA ".$b['tipo']." rompido")
    ));
  }
}

echo json_encode(['success'=>true,'breaches'=>$breaches]);
