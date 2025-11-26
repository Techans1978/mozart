<?php
// public/api/hd/agent/take.php
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
$ticket_id = intval($in['ticket_id'] ?? 0);
if (!$ticket_id) out(false,null,'ticket_id inválido');

$stmt = $db->prepare("UPDATE hd_ticket SET agente_user_id = IFNULL(agente_user_id, ?) WHERE id=?");
$stmt->bind_param('ii',$user_id,$ticket_id);
$stmt->execute();

if ($db->affected_rows>=0) {
  // log se existir
  if ($chk=$db->query("SHOW TABLES LIKE 'hd_ticket_log'")) {
    if ($chk->num_rows>0) {
      $txt = "Ticket assumido automaticamente pelo agente #$user_id";
      $s=$db->prepare("INSERT INTO hd_ticket_log(ticket_id, autor, texto, created_at) VALUES(?, ?, ?, NOW())");
      $autor = "agente:$user_id";
      $s->bind_param('iss',$ticket_id,$autor,$txt);
      $s->execute();
    }
  }
  out(true,['ticket_id'=>$ticket_id]);
}
out(false,null,'Falha ao assumir');
