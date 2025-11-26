<?php
// pages/docs_download.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
set_time_limit(0);

require_once __DIR__.'/../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();

proteger_pagina(); // exige login

$user_id = (int)($_SESSION['user_id'] ?? 0);
$tok = $_GET['tok'] ?? '';
if($user_id<=0 || $tok==='') die('Token ausente.');

$st=$conn->prepare("SELECT t.*, f.origem, f.file_path, f.file_name, f.mime, f.external_url
                    FROM doc_download_tokens t
                    JOIN doc_files f ON f.id=t.file_id
                    WHERE t.token=? LIMIT 1");
$st->bind_param('s',$tok); $st->execute(); $res=$st->get_result(); $row=$res->fetch_assoc(); $st->close();
if(!$row) die('Token inválido.');

if((int)$row['user_id'] !== $user_id) die('Token não pertence a este usuário.');
if($row['used_at']!==NULL) die('Token já utilizado.');
if(strtotime($row['expires_at']) < time()) die('Token expirado.');

if($row['origem']==='externo'){
  // redireciona para a URL externa (a validação já passou)
  $conn->query("UPDATE doc_download_tokens SET used_at=NOW() WHERE token='".$conn->real_escape_string($tok)."' LIMIT 1");
  header('Location: '.$row['external_url']);
  exit;
}

// entrega arquivo local
$abs = ROOT_PATH . ltrim($row['file_path'],'/');
if(!is_file($abs)) die('Arquivo não encontrado no servidor.');

$mime = $row['mime'] ?: 'application/octet-stream';
$nome = $row['file_name'] ?: basename($abs);
$size = filesize($abs);

$conn->query("UPDATE doc_download_tokens SET used_at=NOW() WHERE token='".$conn->real_escape_string($tok)."' LIMIT 1");

// Cabeçalhos
header('Content-Description: File Transfer');
header('Content-Type: '.$mime);
header('Content-Disposition: attachment; filename="'.basename($nome).'"');
header('Content-Length: '.$size);
header('Cache-Control: no-store');
header('Pragma: no-cache');
header('Expires: 0');

// Envia
$fp=fopen($abs,'rb');
while(!feof($fp)){ echo fread($fp, 8192); flush(); }
fclose($fp);
exit;
