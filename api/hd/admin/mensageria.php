<?php
// public/api/hd/admin/mensageria.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json');
require_once __DIR__.'/../../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if(session_status()===PHP_SESSION_NONE) session_start(); proteger_pagina();
function out($ok,$data=null,$err=null){echo json_encode($ok?['success'=>true,'data'=>$data]:['success'=>false,'error'=>$err]);exit;}
$db=$conn??($mysqli??null); if(!$db||!($db instanceof mysqli)) out(false,null,'Sem conexão DB.');
$in=json_decode(file_get_contents('php://input'),true)?:$_REQUEST; $op=$in['op']??'list_channels';

if($op==='list_channels'){
  $r=$db->query("SELECT id,nome,ativo,CAST(cfg_json AS CHAR) AS cfg_json FROM hd_msg_channel ORDER BY id DESC");
  $items=[]; if($r) while($o=$r->fetch_assoc()) $items[]=$o; out(true,['items'=>$items]); }

if($op==='get_channel'){
  $id=intval($in['id']??0); if(!$id) out(false,null,'id inválido');
  $s=$db->prepare("SELECT id,nome,ativo,CAST(cfg_json AS CHAR) AS cfg_json FROM hd_msg_channel WHERE id=?"); $s->bind_param('i',$id);
  $s->execute(); $r=$s->get_result(); out(true,$r->fetch_assoc()); }

if($op==='save_channel'){
  $id=intval($in['id']??0); $nome=trim($in['nome']??''); $ativo=intval($in['ativo']??0); $cfg=$in['cfg_json']??'{}';
  if(!$nome) out(false,null,'Nome obrigatório');
  if($id>0){
    $s=$db->prepare("UPDATE hd_msg_channel SET nome=?, ativo=?, cfg_json=? WHERE id=?"); $s->bind_param('sisi',$nome,$ativo,$cfg,$id); $s->execute(); out(true,['id'=>$id]);
  }else{
    $s=$db->prepare("INSERT INTO hd_msg_channel (nome,ativo,cfg_json,created_at) VALUES (?,?,?,NOW())"); $s->bind_param('sis',$nome,$ativo,$cfg); $s->execute(); out(true,['id'=>$db->insert_id]);
  }
}

if($op==='list_templates'){
  $q=trim($in['q']??'');
  $sql = "SELECT id,nome,canal,ativo FROM hd_msg_template ".($q!==''?"WHERE nome LIKE CONCAT('%',?,'%')":"")." ORDER BY id DESC LIMIT 200";
  if($q!==''){ $s=$db->prepare($sql); $s->bind_param('s',$q);} else { $s=$db->prepare($sql); }
  $s->execute(); $r=$s->get_result(); $items=[]; while($o=$r->fetch_assoc()) $items[]=$o; out(true,['items'=>$items]); }

if($op==='get_template'){
  $id=intval($in['id']??0); if(!$id) out(false,null,'id inválido');
  $s=$db->prepare("SELECT id,nome,canal,texto,ativo FROM hd_msg_template WHERE id=?"); $s->bind_param('i',$id);
  $s->execute(); $r=$s->get_result(); out(true,$r->fetch_assoc()); }

if($op==='save_template'){
  $id=intval($in['id']??0); $nome=trim($in['nome']??''); $canal=trim($in['canal']??''); $texto=$in['texto']??''; $ativo=intval($in['ativo']??1);
  if(!$nome||!$canal||!$texto) out(false,null,'Campos obrigatórios');
  if($id>0){
    $s=$db->prepare("UPDATE hd_msg_template SET nome=?, canal=?, texto=?, ativo=? WHERE id=?");
    $s->bind_param('sssii',$nome,$canal,$texto,$ativo,$id); $s->execute(); out(true,['id'=>$id]);
  }else{
    $s=$db->prepare("INSERT INTO hd_msg_template (nome,canal,texto,ativo,created_at) VALUES (?,?,?,?,NOW())");
    $s->bind_param('sssi',$nome,$canal,$texto,$ativo); $s->execute(); out(true,['id'=>$db->insert_id]);
  }
}

out(false,null,'op inválida');
