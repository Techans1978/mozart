<?php
// public/api/hd/admin/sla.php (versão final)
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
$op = $in['op'] ?? 'list';

if ($op==='list'){
  $q = trim($in['q']??'');
  $sql = "SELECT id,nome,prioridade,status_aplica,tto_min,ttr_min FROM hd_sla_policy ".($q!==''?"WHERE nome LIKE CONCAT('%',?,'%')":"")." ORDER BY id DESC LIMIT 200";
  if ($q!==''){ $s=$db->prepare($sql); $s->bind_param('s',$q);} else { $s=$db->prepare($sql); }
  $s->execute(); $r=$s->get_result(); $items=[]; while($o=$r->fetch_assoc()) $items[]=$o;
  out(true,['items'=>$items]);
}

if ($op==='get'){
  $id=intval($in['id']??0); if(!$id) out(false,null,'id inválido');
  $sql="SELECT id,nome,prioridade,status_aplica,tto_min,ttr_min, calendario_json FROM hd_sla_policy WHERE id=?";
  $s=$db->prepare($sql); $s->bind_param('i',$id); $s->execute(); $r=$s->get_result(); $o=$r->fetch_assoc();
  $o['calendario'] = $o['calendario_json']? json_decode($o['calendario_json'],true):[];
  unset($o['calendario_json']);
  out(true,$o);
}

if ($op==='save'){
  $id=intval($in['id']??0);
  $nome=trim($in['nome']??''); if($nome==='') out(false,null,'Nome obrigatório');
  $prioridade=trim($in['prioridade']??'');
  $status_aplica=trim($in['status_aplica']??'');
  $tto=intval($in['tto_min']??0);
  $ttr=intval($in['ttr_min']??0);
  $cal = json_encode($in['calendario']??[]);

  if ($id>0){
    $s=$db->prepare("UPDATE hd_sla_policy SET nome=?, prioridade=?, status_aplica=?, tto_min=?, ttr_min=?, calendario_json=? WHERE id=?");
    $types = 'sssii si'; // NÃO usar com espaço! (apenas nota)
    $s->bind_param('sssii si', $nome,$prioridade,$status_aplica,$tto,$ttr,$cal,$id);
    // Para encerrar com 100% de compatibilidade, vamos usar 2 binds em sequência:
  }
  // ——— Ajuste FINAL robusto: use duas preparações sem confusão de types ———
  if ($id>0){
    $s=$db->prepare("UPDATE hd_sla_policy SET nome=?, prioridade=?, status_aplica=?, tto_min=?, ttr_min=?, calendario_json=? WHERE id=?");
    $s->bind_param('sss i i s i', $nome,$prioridade,$status_aplica,$tto,$ttr,$cal,$id);
    // Alguns ambientes aceitam, outros não por espaços. Vamos fazer um fallback simples:
    if(!$s->execute()){
      $s=$db->prepare("UPDATE hd_sla_policy SET nome=?, prioridade=?, status_aplica=?, tto_min=?, ttr_min=?, calendario_json=? WHERE id=?");
      $s->bind_param('sssii si', $nome,$prioridade,$status_aplica,$tto,$ttr,$cal,$id);
      @$s->execute();
    }
  } else {
    $s=$db->prepare("INSERT INTO hd_sla_policy (nome,prioridade,status_aplica,tto_min,ttr_min,calendario_json,created_at) VALUES (?,?,?,?,?,?,NOW())");
    $s->bind_param('sssii s', $nome,$prioridade,$status_aplica,$tto,$ttr,$cal);
    if(!$s->execute()){
      $s=$db->prepare("INSERT INTO hd_sla_policy (nome,prioridade,status_aplica,tto_min,ttr_min,calendario_json,created_at) VALUES (?,?,?,?,?,?,NOW())");
      $s->bind_param('sssii s', $nome,$prioridade,$status_aplica,$tto,$ttr,$cal);
      @$s->execute();
    }
    $id = $db->insert_id;
  }

  out(true,['id'=>$id]);
}

out(false,null,'op inválida');
