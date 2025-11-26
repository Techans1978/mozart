<?php
// public/api/hd/admin/email.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json');
require_once __DIR__.'/../../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if(session_status()===PHP_SESSION_NONE) session_start(); proteger_pagina();
function out($ok,$data=null,$err=null){echo json_encode($ok?['success'=>true,'data'=>$data]:['success'=>false,'error'=>$err]);exit;}
$db=$conn??($mysqli??null); if(!$db||!($db instanceof mysqli)) out(false,null,'Sem conexão DB.');
$in=json_decode(file_get_contents('php://input'),true)?:$_REQUEST; $op=$in['op']??'get';

if($op==='get'){
  $r=$db->query("SELECT * FROM hd_email_config ORDER BY id DESC LIMIT 1"); $o=$r?$r->fetch_assoc():null; out(true,$o?:[]);
}
if($op==='save'){
  $cols=['active','protocol','host','port','secure','mailbox','username','password','smtp_host','smtp_port','smtp_secure','smtp_username','smtp_password','from_name','from_email','reply_subject_prefix'];
  $v=[]; foreach($cols as $c){ $v[$c]=$in[$c]??null; }
  // upsert simples: se existe, update; se não, insert
  $r=$db->query("SELECT id FROM hd_email_config ORDER BY id DESC LIMIT 1");
  if($r && ($o=$r->fetch_assoc())){
    $s=$db->prepare("UPDATE hd_email_config SET active=?,protocol=?,host=?,port=?,secure=?,mailbox=?,username=?,password=?,smtp_host=?,smtp_port=?,smtp_secure=?,smtp_username=?,smtp_password=?,from_name=?,from_email=?,reply_subject_prefix=? WHERE id=?");
    $s->bind_param('ississssssisssssi',
      $v['active'],$v['protocol'],$v['host'],$v['port'],$v['secure'],$v['mailbox'],$v['username'],$v['password'],$v['smtp_host'],$v['smtp_port'],$v['smtp_secure'],$v['smtp_username'],$v['smtp_password'],$v['from_name'],$v['from_email'],$v['reply_subject_prefix'],$o['id']);
    $s->execute(); out(true,['id'=>$o['id']]);
  } else {
    $s=$db->prepare("INSERT INTO hd_email_config (active,protocol,host,port,secure,mailbox,username,password,smtp_host,smtp_port,smtp_secure,smtp_username,smtp_password,from_name,from_email,reply_subject_prefix) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $s->bind_param('ississssssisssss',
      $v['active'],$v['protocol'],$v['host'],$v['port'],$v['secure'],$v['mailbox'],$v['username'],$v['password'],$v['smtp_host'],$v['smtp_port'],$v['smtp_secure'],$v['smtp_username'],$v['smtp_password'],$v['from_name'],$v['from_email'],$v['reply_subject_prefix']);
    $s->execute(); out(true,['id'=>$db->insert_id]);
  }
}
out(false,null,'op inválida');
