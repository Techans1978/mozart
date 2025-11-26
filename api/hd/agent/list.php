<?php
// public/api/hd/agent/list.php
ini_set('display_errors',1); ini_set('startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

function out($ok, $data=null, $err=null){ echo json_encode($ok? ['success'=>true,'data'=>$data]:['success'=>false,'error'=>$err]); exit; }

$db = $conn ?? ($mysqli ?? null);
if (!$db || !($db instanceof mysqli)) out(false,null,'Sem conexão DB.');

$payload = file_get_contents('php://input');
$in = json_decode($payload, true) ?: $_REQUEST;
if (!isset($_SESSION['csrf_hd']) || ($in['csrf']??'')!==$_SESSION['csrf_hd']) { /* CSRF opcional */ }

$user_id = $_SESSION['usuario_id'] ?? 0;

// Combos?
if (!empty($in['combos'])) {
  $data = ['grupos'=>[], 'lojas'=>[], 'macros'=>[]];
  // grupos
  if ($r=$db->query("SELECT id, nome FROM hd_grupo ORDER BY nome")) {
    while($o=$r->fetch_assoc()) $data['grupos'][]=$o;
  }
  // lojas
  if ($r=$db->query("SELECT id, nome FROM empresa WHERE tipo='loja' OR nome IS NOT NULL ORDER BY nome LIMIT 500")) {
    while($o=$r->fetch_assoc()) $data['lojas'][]=$o;
  }
  // macros (se existir)
  if ($r=$db->query("SHOW TABLES LIKE 'hd_macro'")) {
    $exists = $r && $r->num_rows>0;
    if ($exists) {
      if ($r2=$db->query("SELECT id, nome FROM hd_macro WHERE ativo=1 ORDER BY nome")) {
        while($o=$r2->fetch_assoc()) $data['macros'][]=$o;
      }
    }
  }
  out(true,$data);
}

// Buscar um ticket específico?
if (!empty($in['id'])) {
  $sql = "SELECT t.id,t.protocolo,t.assunto,t.descricao,t.descricao_html,
                 t.status,t.prioridade,t.loja_id,t.grupo_id,t.solicitante_user_id,t.agente_user_id,
                 t.created_at,
                 (SELECT nome FROM empresa e WHERE e.id=t.loja_id LIMIT 1) AS loja_nome,
                 (SELECT nome FROM hd_grupo g WHERE g.id=t.grupo_id LIMIT 1) AS grupo_nome,
                 (SELECT nome FROM usuarios u WHERE u.id=t.solicitante_user_id LIMIT 1) AS solicitante_nome,
                 (SELECT nome FROM usuarios u2 WHERE u2.id=t.agente_user_id LIMIT 1) AS agente_nome
          FROM hd_ticket t WHERE t.id = ?";
  $stmt=$db->prepare($sql); $stmt->bind_param('i',$in['id']); $stmt->execute(); $res=$stmt->get_result();
  $row = $res? $res->fetch_assoc():null;

  // logs/comentários (se existir tabela)
  $rowLogs = [];
  if ($chk=$db->query("SHOW TABLES LIKE 'hd_ticket_log'")) {
    if ($chk->num_rows>0) {
      $stmt2 = $db->prepare("SELECT created_at, autor, texto FROM hd_ticket_log WHERE ticket_id=? ORDER BY id DESC LIMIT 50");
      $stmt2->bind_param('i',$in['id']); $stmt2->execute(); $r2=$stmt2->get_result();
      while($o=$r2->fetch_assoc()) $rowLogs[]=$o;
    }
  }
  if ($row) $row['logs']=$rowLogs;

  out(true, ['items'=>$row? [$row]:[]]);
}

// Listagem com filtros
$q = trim($in['q'] ?? '');
$grupo = trim($in['grupo'] ?? '');
$status = trim($in['status'] ?? '');
$prio = trim($in['prioridade'] ?? '');
$loja = trim($in['loja'] ?? '');
$limit = max(1,min(200, intval($in['limit'] ?? 50)));

$where = [];
$params = []; $types = '';

if ($q!=='') { $where[]="(t.protocolo LIKE CONCAT('%',?,'%') OR t.assunto LIKE CONCAT('%',?,'%'))"; $types.='ss'; $params[]=$q; $params[]=$q; }
if ($grupo!=='') { $where[]="t.grupo_id = ?"; $types.='i'; $params[]=$grupo; }
if ($status!=='') { $where[]="t.status = ?"; $types.='s'; $params[]=$status; }
if ($prio!=='') { $where[]="t.prioridade = ?"; $types.='s'; $params[]=$prio; }
if ($loja!=='') { $where[]="t.loja_id = ?"; $types.='i'; $params[]=$loja; }

// Escopo do agente (opcional): trazer tickets sem agente ou do próprio agente
$where[]="(t.agente_user_id IS NULL OR t.agente_user_id = ?)";
$types.='i'; $params[]=$user_id;

$sql = "SELECT t.id,t.protocolo,t.assunto,t.status,t.prioridade,t.loja_id,t.grupo_id,t.solicitante_user_id,t.agente_user_id,t.created_at,
               (SELECT nome FROM empresa e WHERE e.id=t.loja_id LIMIT 1) AS loja_nome,
               (SELECT nome FROM hd_grupo g WHERE g.id=t.grupo_id LIMIT 1) AS grupo_nome,
               (SELECT nome FROM usuarios u WHERE u.id=t.solicitante_user_id LIMIT 1) AS solicitante_nome,
               (SELECT nome FROM usuarios u2 WHERE u2.id=t.agente_user_id LIMIT 1) AS agente_nome
        FROM hd_ticket t ".
        (count($where)? "WHERE ".implode(' AND ',$where):"").
        " ORDER BY FIELD(t.status,'novo','aberto','pendente','resolvido','fechado'), t.prioridade DESC, t.id DESC
          LIMIT ?";
$types.='i'; $params[]=$limit;

$stmt=$db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute(); $res=$stmt->get_result();

$items=[];
while($row=$res->fetch_assoc()) $items[]=$row;
out(true, ['items'=>$items]);
