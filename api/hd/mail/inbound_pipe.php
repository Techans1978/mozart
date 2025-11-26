<?php
// public/api/hd/mail/inbound_pipe.php
ini_set('display_errors',0);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/connect.php';
$raw = file_get_contents('php://stdin'); if(!$raw) exit;

$db=$conn??($mysqli??null); if(!$db||!($db instanceof mysqli)) exit;
$subject=''; $from=''; $mid=null;

if(preg_match('/^Subject:\s*(.*)$/mi',$raw,$m)) $subject=trim(iconv_mime_decode($m[1],0,'UTF-8'));
if(preg_match('/^From:\s*.*<(.*)>/mi',$raw,$m)) $from=strtolower(trim($m[1]));
if(preg_match('/^Message-ID:\s*(.*)$/mi',$raw,$m)) $mid=trim($m[1]);

$body = preg_replace('/.*?\r?\n\r?\n/s','',$raw); // após cabeçalhos
$ticket_id=null; $action='created'; $note=null;

if(preg_match('/\[(HD|Mozart|HELPDESK)\s*#([^\|\]]+)\|([A-Fa-f0-9]+)\]/',$subject,$mm)){
  $proto=trim($mm[2]); $tok=$mm[3];
  $s=$db->prepare("SELECT id FROM hd_ticket WHERE (protocolo=? OR id=CAST(? AS UNSIGNED)) AND thread_token=? LIMIT 1");
  $s->bind_param('sss',$proto,$proto,$tok); $s->execute(); $r=$s->get_result(); if($o=$r->fetch_assoc()) $ticket_id=$o['id'];
}

if($ticket_id){
  $s=$db->prepare("INSERT INTO hd_ticket_log(ticket_id,autor,texto,created_at) VALUES (?,?,?,NOW())");
  $autor=$from?:'remetente:desconhecido'; $texto=$body?:'(sem conteúdo)';
  $s->bind_param('iss',$ticket_id,$autor,$texto); $s->execute();
  $db->query("UPDATE hd_ticket SET last_email_at=NOW() WHERE id=".(int)$ticket_id);
  $action='appended';
} else {
  $ass=mb_substr($subject?:'(sem assunto)',0,190);
  $desc=$body?:'(sem conteúdo)';
  $email=$from?:null;
  $s=$db->prepare("INSERT INTO hd_ticket (protocolo,assunto,descricao,solicitante_email,status,prioridade,created_at) VALUES (?,?,?,?, 'novo','media', NOW())");
  $proto=strtoupper(bin2hex(random_bytes(4)));
  $s->bind_param('ssss',$proto,$ass,$desc,$email);
  $s->execute(); $ticket_id=$db->insert_id;
  $tok=bin2hex(random_bytes(8));
  $u=$db->prepare("UPDATE hd_ticket SET thread_token=?, message_id=?, last_email_at=NOW() WHERE id=?");
  $u->bind_param('ssi',$tok,$mid,$ticket_id); $u->execute();
  @$db->query("INSERT INTO hd_ticket_log(ticket_id,autor,texto,created_at) VALUES (".$ticket_id.",'sistema:email','Ticket criado via PIPE',NOW())");
  $action='created';
}
$si=$db->prepare("INSERT INTO hd_mail_inbound(message_id,from_email,subject,received_at,ticket_id,action,note) VALUES (?,?,?,?,?,?,?)");
$rcv=date('Y-m-d H:i:s');
$si->bind_param('sssiiss',$mid,$from,$subject,$rcv,$ticket_id,$action,$note);
$si->execute();
