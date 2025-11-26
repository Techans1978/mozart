<?php
// public/api/hd/agent/macro.php
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
$aplicar   = !empty($in['aplicar']);
$macro_id  = intval($in['macro_id'] ?? 0);
$resposta  = trim($in['resposta'] ?? '');

if (!$ticket_id) out(false,null,'ticket_id inválido');

// Se for macro, tenta buscar ações
$textoMacro = '';
$alterar_status = null; $definir_prioridade = null;
if ($aplicar && $macro_id>0) {
  if ($chk=$db->query("SHOW TABLES LIKE 'hd_macro'")) {
    if ($chk->num_rows>0) {
      $s=$db->prepare("SELECT nome, resposta, alterar_status, definir_prioridade FROM hd_macro WHERE id=? AND ativo=1");
      $s->bind_param('i',$macro_id);
      $s->execute(); $r=$s->get_result(); $m=$r->fetch_assoc();
      if ($m) {
        $textoMacro = $m['resposta'] ?? '';
        $alterar_status = $m['alterar_status'] ?? null;
        $definir_prioridade = $m['definir_prioridade'] ?? null;
      }
    }
  }
}

$texto = $aplicar ? $textoMacro : $resposta;
if ($texto==='') $texto = '(sem conteúdo)';

// Insere comentário/log se tabela existir
if ($chk=$db->query("SHOW TABLES LIKE 'hd_ticket_log'")) {
  if ($chk->num_rows>0) {
    $s=$db->prepare("INSERT INTO hd_ticket_log(ticket_id, autor, texto, created_at) VALUES(?, ?, ?, NOW())");
    $autor = "agente:$user_id";
    $s->bind_param('iss', $ticket_id, $autor, $texto);
    $s->execute();
  }
}

// Aplica efeitos no ticket (status/prioridade) se colunas existirem
if ($alterar_status) {
  @$db->query("UPDATE hd_ticket SET status=".$db->quote($alterar_status)." WHERE id=".$ticket_id);
  // fallback seguro
  $st = $db->prepare("UPDATE hd_ticket SET status=? WHERE id=?");
  $st->bind_param('si',$alterar_status,$ticket_id);
  $st->execute();
}
if ($definir_prioridade) {
  $st = $db->prepare("UPDATE hd_ticket SET prioridade=? WHERE id=?");
  $st->bind_param('si',$definir_prioridade,$ticket_id);
  $st->execute();
}

out(true,['ticket_id'=>$ticket_id,'aplicado'=>$aplicar? $macro_id : null]);
