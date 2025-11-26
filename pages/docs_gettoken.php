<?php
// pages/docs_gettoken.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();

proteger_pagina(); // exige login

$user_id = (int)($_SESSION['user_id'] ?? 0);
$item_id = (int)($_GET['item'] ?? 0);
$file_id = (int)($_GET['file'] ?? 0);
if($user_id<=0 || $item_id<=0 || $file_id<=0) die('Parâmetros inválidos.');

function user_has_access(mysqli $conn, int $user_id, array $item): bool {
  if ((int)$item['acesso_publico']===1) return true;
  // Regras mínimas: você pode expandir com grupos/perfis/roles como no seu sistema.
  // Aqui, só checamos se está logado e dentro da janela de publicação + status.
  $now = date('Y-m-d H:i:s');
  if ($item['status']!=='publicado') return false;
  if ($item['publish_up'] && $item['publish_up'] > $now) return false;
  if ($item['publish_down'] && $item['publish_down'] < $now) return false;
  return true;
}

// carrega item + file
$st=$conn->prepare("SELECT * FROM doc_items WHERE id=? LIMIT 1");
$st->bind_param('i',$item_id); $st->execute(); $it=$st->get_result()->fetch_assoc(); $st->close();
if(!$it) die('Documento não encontrado.');

$st=$conn->prepare("SELECT * FROM doc_files WHERE id=? AND item_id=? LIMIT 1");
$st->bind_param('ii',$file_id,$item_id); $st->execute(); $fi=$st->get_result()->fetch_assoc(); $st->close();
if(!$fi) die('Arquivo não encontrado.');

if(!user_has_access($conn,$user_id,$it)) die('Acesso negado.');

// gera token válido por 24h
$token = bin2hex(random_bytes(20));
$expires = date('Y-m-d H:i:s', time()+86400);
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '',0,255);
$ip = inet_pton($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

$st=$conn->prepare("INSERT INTO doc_download_tokens(token,user_id,item_id,file_id,ip,user_agent,expires_at) VALUES(?,?,?,?,?,?,?)");
$st->bind_param('siiibss', $token,$user_id,$item_id,$file_id,$ip,$ua,$expires);
$st->send_long_data(4, $ip); // ip como blob
$ok=$st->execute(); $st->close();
if(!$ok) die('Falha ao gerar token.');

header('Location: '.BASE_URL.'/pages/docs_download.php?tok='.$token);
exit;
