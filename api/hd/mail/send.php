<?php
// public/api/hd/mail/send.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json');
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/connect.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
if(session_status()===PHP_SESSION_NONE) session_start(); proteger_pagina();

function out($ok,$data=null,$err=null){echo json_encode($ok?['success'=>true,'data'=>$data]:['success'=>false,'error'=>$err]);exit;}
$db=$conn??($mysqli??null); if(!$db||!($db instanceof mysqli)) out(false,null,'Sem conexão DB.');
$in=json_decode(file_get_contents('php://input'),true)?:$_REQUEST;
$to=trim($in['to']??''); $subject=trim($in['subject']??''); $html=$in['html']??''; $ticket_id=intval($in['ticket_id']??0);
if(!$to||!$subject||!$html) out(false,null,'to/subject/html obrigatórios');

$r=$db->query("SELECT * FROM hd_email_config ORDER BY id DESC LIMIT 1"); $cfg=$r?$r->fetch_assoc():null;
if(!$cfg || empty($cfg['smtp_host'])) out(false,null,'SMTP não configurado');

$from_email=$cfg['from_email']?:'no-reply@localhost';
$from_name =$cfg['from_name']?:'Mozart Help Desk';

if($ticket_id>0){
  $s=$db->prepare("SELECT id,protocolo,assunto,thread_token,message_id,solicitante_email FROM hd_ticket WHERE id=?");
  $s->bind_param('i',$ticket_id); $s->execute(); $res=$s->get_result(); $t=$res->fetch_assoc();
  if($t){
    if(!$t['thread_token']){
      $tok = bin2hex(random_bytes(8));
      $u=$db->prepare("UPDATE hd_ticket SET thread_token=? WHERE id=?"); $u->bind_param('si',$tok,$ticket_id); $u->execute();
      $t['thread_token']=$tok;
    }
    $prefix = $cfg['reply_subject_prefix'] ?: '[HD]';
    $subject = sprintf("%s #%s|%s %s", $prefix, $t['protocolo']?:$t['id'], $t['thread_token'], $subject);
    if(empty($to) && !empty($t['solicitante_email'])) $to=$t['solicitante_email'];
  }
}

require_once ROOT_PATH.'/vendor/phpmailer/PHPMailer.php'; // ajuste se preciso
require_once ROOT_PATH.'/vendor/phpmailer/SMTP.php';
require_once ROOT_PATH.'/vendor/phpmailer/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;

$mail=new PHPMailer(true);
try{
  $mail->isSMTP();
  $mail->Host=$cfg['smtp_host'];
  $mail->Port=(int)($cfg['smtp_port']?:587);
  $sec=$cfg['smtp_secure']?:'tls'; if($sec!=='none'){ $mail->SMTPSecure=$sec; }
  $mail->SMTPAuth=true;
  $mail->Username=$cfg['smtp_username']?:$cfg['username'];
  $mail->Password=$cfg['smtp_password']?:$cfg['password'];
  $mail->setFrom($from_email,$from_name);
  $mail->addAddress($to);
  $mail->isHTML(true);
  $mail->Subject=$subject;
  $mail->Body=$html;

  // threading headers (se houver message_id anterior)
  if(!empty($t['message_id'])){
    $mail->addCustomHeader('In-Reply-To',$t['message_id']);
    $mail->addCustomHeader('References',$t['message_id']);
  }

  $sent=$mail->send();

  // registra message-id gerado
  $mid = $mail->getLastMessageID();
  if($ticket_id>0){
    $u=$db->prepare("UPDATE hd_ticket SET message_id=IFNULL(message_id,?), last_email_at=NOW() WHERE id=?");
    $u->bind_param('si',$mid,$ticket_id); $u->execute();
    // log
    @$db->query("INSERT INTO hd_ticket_log(ticket_id,autor,texto,created_at) VALUES (".(int)$ticket_id.",'sistema:email','E-mail enviado para ".$db->real_escape_string($to)."',NOW())");
  }
  out(true,['message_id'=>$mid,'sent'=>$sent]);
}catch(Exception $e){ out(false,null,$e->getMessage()); }
