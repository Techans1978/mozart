<?php
// public/api/hd/notify/send.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json');
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if(session_status()===PHP_SESSION_NONE) session_start(); proteger_pagina();
function out($ok,$data=null,$err=null){echo json_encode($ok?['success'=>true,'data'=>$data]:['success'=>false,'error'=>$err]);exit;}
$db=$conn??($mysqli??null); if(!$db||!($db instanceof mysqli)) out(false,null,'Sem conexão DB.');
$in=json_decode(file_get_contents('php://input'),true)?:$_REQUEST;

$canal=trim($in['canal']??'');
$destino=trim($in['destino']??'');
$template_id=intval($in['template_id']??0);
$assunto=trim($in['assunto']??'');
$corpo=$in['corpo']??'';
$vars=$in['vars']??[];
$ticket_id=intval($in['related_ticket_id']??($in['ticket_id']??0));

if(!$canal||!$destino) out(false,null,'canal e destino são obrigatórios');

// Se veio template, carrega e mescla
if($template_id>0){
  $s=$db->prepare("SELECT texto FROM hd_msg_template WHERE id=? AND ativo=1");
  $s->bind_param('i',$template_id); $s->execute(); $r=$s->get_result(); $t=$r->fetch_assoc();
  if(!$t) out(false,null,'Template não encontrado/ativo');
  $text=$t['texto'];
  if(is_array($vars)){
    foreach($vars as $k=>$v){
      $text=str_replace('{{'.$k.'}}',''.$v.'',$text);
    }
  }
  $corpo=$text;
}

$vars_json=json_encode($vars?:new stdClass());
$s=$db->prepare("INSERT INTO hd_msg_outbox (canal,destino,template_id,assunto,corpo,vars_json,related_ticket_id,status,attempts,created_at) VALUES (?,?,?,?,?,?,?,'queued',0,NOW())");
$s->bind_param('ssisssi',$canal,$destino,$template_id,$assunto,$corpo,$vars_json,$ticket_id);
$s->execute(); out(true,['id'=>$db->insert_id]);
