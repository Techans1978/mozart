<?php
// public/api/hd/agent/merge.php
ini_set('display_errors',1); ini_set('startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

function out($ok,$data=null,$err=null){ echo json_encode($ok?['success'=>true,'data'=>$data]:['success'=>false,'error'=>$err]); exit; }

$db = $conn ?? ($mysqli ?? null);
if (!$db || !($db instanceof mysqli)) out(false,null,'Sem conexão DB.');

$in = json_decode(file_get_contents('php://input'), true) ?: $_REQUEST;
$user_id = $_SESSION['usuario_id'] ?? 0;

$master_proto = trim($in['master'] ?? '');
$child_proto  = trim($in['child'] ?? '');
if ($master_proto==='' || $child_proto==='') out(false,null,'Protocolo principal/duplicado inválidos');
if ($master_proto===$child_proto) out(false,null,'Protocolos iguais');

function getIdByProto($db,$p){
  $s=$db->prepare("SELECT id FROM hd_ticket WHERE protocolo=? LIMIT 1");
  $s->bind_param('s',$p); $s->execute(); $r=$s->get_result(); $o=$r->fetch_assoc();
  return $o['id']??null;
}

$master_id = getIdByProto($db,$master_proto);
$child_id  = getIdByProto($db,$child_proto);
if (!$master_id || !$child_id) out(false,null,'Protocolos não encontrados');

// Tenta fundir: mover logs, marcar filho como merged e apontar para master (se colunas existirem)
$merged = false;

// mover logs (se tabela existir)
if ($chk=$db->query("SHOW TABLES LIKE 'hd_ticket_log'")) {
  if ($chk->num_rows>0) {
    $db->query("UPDATE hd_ticket_log SET ticket_id=".$master_id." WHERE ticket_id=".$child_id);
  }
}

// marcar filho como fundido (status + merged_into_id se existir)
$hasMergedInto = false;
$descCols = $db->query("SHOW COLUMNS FROM hd_ticket LIKE 'merged_into_id'");
if ($descCols && $descCols->num_rows>0) $hasMergedInto=true;

if ($hasMergedInto) {
  $s=$db->prepare("UPDATE hd_ticket SET status='fechado', merged_into_id=? WHERE id=?");
  $s->bind_param('ii',$master_id,$child_id);
  $s->execute();
  $merged = $db->affected_rows>=0;
} else {
  $s=$db->prepare("UPDATE hd_ticket SET status='fechado' WHERE id=?");
  $s->bind_param('i',$child_id);
  $s->execute();
  $merged = $db->affected_rows>=0;
}

// Log
if ($chk=$db->query("SHOW TABLES LIKE 'hd_ticket_log'")) {
  if ($chk->num_rows>0) {
    $txt = "Ticket $child_proto foi fundido em $master_proto";
    $s=$db->prepare("INSERT INTO hd_ticket_log(ticket_id, autor, texto, created_at) VALUES(?, ?, ?, NOW())");
    $autor = "agente:$user_id";
    $s->bind_param('iss',$master_id,$autor,$txt);
    $s->execute();
  }
}

out(true, ['master_id'=>$master_id,'child_id'=>$child_id,'merged'=>$merged]);
