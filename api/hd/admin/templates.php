<?php
// public/api/hd/admin/templates.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json');
require_once __DIR__.'/../../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if(session_status()===PHP_SESSION_NONE) session_start(); proteger_pagina();
function out($ok,$data=null,$err=null){echo json_encode($ok?['success'=>true,'data'=>$data]:['success'=>false,'error'=>$err]);exit;}
$db=$conn??($mysqli??null); if(!$db||!($db instanceof mysqli)) out(false,null,'Sem conexão DB.');
$in=json_decode(file_get_contents('php://input'),true)?:$_REQUEST; $op=$in['op']??'list';

if($op==='list'){
  $q=trim($in['q']??'');
  $sql="SELECT id,nome,assunto,ativo FROM hd_email_template ".($q!==''?"WHERE nome LIKE CONCAT('%',?,'%')":"")." ORDER BY id DESC LIMIT 200";
  if($q!==''){ $s=$db->prepare($sql); $s->bind_param('s',$q);} else { $s=$db->prepare($sql); }
  $s->execute(); $r=$s->get_result(); $items=[]; while($o=$r->fetch_assoc()) $items[]=$o; out(true,['items'=>$items]);
}
if($op==='get'){
  $id=intval($in['id']??0); if(!$id) out(false,null,'id inválido');
  $s=$db->prepare("SELECT * FROM hd_email_template WHERE id=?"); $s->bind_param('i',$id); $s->execute(); $r=$s->get_result(); out(true,$r->fetch_assoc());
}
if($op==='save'){
  $id=intval($in['id']??0); $nome=trim($in['nome']??''); $ass=trim($in['assunto']??''); $html=$in['corpo_html']??''; $ativo=intval($in['ativo']??1);
  if(!$nome||!$ass||!$html) out(false,null,'Campos obrigatórios');
  if($id>0){
    $s=$db->prepare("UPDATE hd_email_template SET nome=?,assunto=?,corpo_html=?,ativo=? WHERE id=?");
    $s->bind_param('sssii',$nome,$ass,$html,$ativo,$id); $s->execute(); out(true,['id'=>$id]);
  } else {
    $s=$db->prepare("INSERT INTO hd_email_template (nome,assunto,corpo_html,ativo,created_at) VALUES (?,?,?,?,NOW())");
    $s->bind_param('sssi',$nome,$ass,$html,$ativo); $s->execute(); out(true,['id'=>$db->insert_id]);
  }
}
out(false,null,'op inválida');
