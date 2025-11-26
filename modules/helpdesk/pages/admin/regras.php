<?php
// public/api/hd/admin/regras.php
ini_set('display_errors',1); ini_set('startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

function out($ok,$data=null,$err=null){ echo json_encode($ok?['success'=>true,'data'=>$data]:['success'=>false,'error'=>$err]); exit; }
$db = $conn ?? ($mysqli ?? null);
if (!$db || !($db instanceof mysqli)) out(false,null,'Sem conexão DB.');

$in = json_decode(file_get_contents('php://input'), true) ?: $_REQUEST;
$op = $in['op'] ?? 'list';

if ($op==='list'){
  $q=trim($in['q']??'');
  $sql="SELECT id,nome,evento,ativo FROM hd_regra ".($q!==''?"WHERE nome LIKE CONCAT('%',?,'%')":"")." ORDER BY id DESC LIMIT 200";
  if($q!==''){ $s=$db->prepare($sql); $s->bind_param('s',$q);} else {$s=$db->prepare($sql);}
  $s->execute(); $r=$s->get_result(); $items=[]; while($o=$r->fetch_assoc()) $items[]=$o;
  out(true,['items'=>$items]);
}
if ($op==='get'){
  $id=intval($in['id']??0); if(!$id) out(false,null,'id inválido');
  $s=$db->prepare("SELECT id,nome,evento,ativo,cond_json,acao_json FROM hd_regra WHERE id=?");
  $s->bind_param('i',$id); $s->execute(); $r=$s->get_result(); $o=$r->fetch_assoc();
  out(true,$o);
}
if ($op==='save'){
  $id=intval($in['id']??0);
  $nome=trim($in['nome']??''); if($nome==='') out(false,null,'Nome obrigatório');
  $evento=trim($in['evento']??'on_create');
  $ativo=intval($in['ativo']??1);
  $cond=$in['cond_json']??'{}';
  $acao=$in['acao_json']??'{}';

  if ($id>0){
    $s=$db->prepare("UPDATE hd_regra SET nome=?, evento=?, ativo=?, cond_json=?, acao_json=? WHERE id=?");
    $s->bind_param('ssissi',$nome,$evento,$ativo,$cond,$acao,$id);
    $s->execute();
  } else {
    $s=$db->prepare("INSERT INTO hd_regra (nome,evento,ativo,cond_json,acao_json,created_at) VALUES (?,?,?,?,?,NOW())");
    $s->bind_param('ssiss',$nome,$evento,$ativo,$cond,$acao);
    $s->execute(); $id=$db->insert_id;
  }
  out(true,['id'=>$id]);
}

out(false,null,'op inválida');
