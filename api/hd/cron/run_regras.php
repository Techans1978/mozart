<?php
// public/api/hd/cron/run_regras.php
// Executa regras do tipo on_sla_breach (e pode ser expandido para outras varreduras)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__.'/../../../config.php';
require_once ROOT_PATH.'/system/config/connect.php';

$db = $conn ?? ($mysqli ?? null);
if (!$db || !($db instanceof mysqli)) { http_response_code(500); echo json_encode(['success'=>false,'error'=>'Sem conexão DB.']); exit; }

// carrega regras ativas para evento on_sla_breach
$regras = [];
if ($r=$db->query("SELECT id,nome,cond_json,acao_json FROM hd_regra WHERE ativo=1 AND evento='on_sla_breach' ORDER BY id")) {
  while($o=$r->fetch_assoc()) $regras[]=$o;
}
if (!$regras) { echo json_encode(['success'=>true,'aplicadas'=>0]); exit; }

// tickets com logs de SLA rompido nos últimos 30 minutos (exemplo simples)
$rompidos = $db->query("
  SELECT DISTINCT t.id, t.protocolo, t.status, t.prioridade
  FROM hd_ticket t
  JOIN hd_ticket_log l ON l.ticket_id=t.id
  WHERE l.autor='sistema:sla' AND l.created_at > (NOW() - INTERVAL 30 MINUTE)
  ORDER BY t.id DESC
");

$aplicadas = 0;
while ($tk = $rompidos->fetch_assoc()) {
  foreach ($regras as $reg) {
    $cond = json_decode($reg['cond_json'] ?: '{}', true);
    // Avaliação ultra-simples de JSON Logic (somente alguns operadores comuns):
    $ok = true;
    if (!empty($cond['=='])) {
      // exemplo: {"==":[{"var":"status"},"aberto"]}
      $a = $cond['=='][0]; $b=$cond['=='][1];
      if (is_array($a) && isset($a['var'])) $a = $tk[$a['var']] ?? null;
      $ok = ($a==$b);
    }
    if ($ok && !empty($cond['>'])) {
      $a=$cond['>'][0]; $b=$cond['>'][1];
      if (is_array($a) && isset($a['var'])) $a=$tk[$a['var']] ?? null;
      $ok = ($a>$b);
    }
    if (!$ok) continue;

    // aplica ação
    $acao = json_decode($reg['acao_json'] ?: '{}', true);
    if (!empty($acao['set_status'])) {
      $st = $db->real_escape_string($acao['set_status']);
      $db->query("UPDATE hd_ticket SET status='{$st}' WHERE id=".(int)$tk['id']);
    }
    if (!empty($acao['set_prioridade'])) {
      $pr = $db->real_escape_string($acao['set_prioridade']);
      $db->query("UPDATE hd_ticket SET prioridade='{$pr}' WHERE id=".(int)$tk['id']);
    }
    if (!empty($acao['add_tag'])) {
      // se tiver tabela de tags/comentário, aqui só logamos
      $txt = "Regra '". $db->real_escape_string($reg['nome']) ."' aplicou add_tag: ".$db->real_escape_string($acao['add_tag']);
      $db->query("INSERT INTO hd_ticket_log(ticket_id, autor, texto, created_at) VALUES (".(int)$tk['id'].",'sistema:regra','".$txt."',NOW())");
    }
    $aplicadas++;
  }
}

echo json_encode(['success'=>true,'aplicadas'=>$aplicadas]);
