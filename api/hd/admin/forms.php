<?php
// public/api/hd/admin/forms.php
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

// LIST
if ($op==='list'){
  $q = trim($in['q']??'');
  $sql = "SELECT id,nome,categoria,status FROM hd_form ".($q!==''?"WHERE nome LIKE CONCAT('%',?,'%')":"")." ORDER BY id DESC LIMIT 200";
  if ($q!==''){ $s=$db->prepare($sql); $s->bind_param('s',$q); } else { $s=$db->prepare($sql); }
  $s->execute(); $r=$s->get_result(); $items=[]; while($o=$r->fetch_assoc()) $items[]=$o;
  out(true,['items'=>$items]);
}

// GET
if ($op==='get'){
  $id = intval($in['id']??0); if(!$id) out(false,null,'id inválido');
  $sql = "SELECT f.id,f.nome,f.categoria,f.status,
                 (SELECT schema_json FROM hd_form_version v WHERE v.form_id=f.id ORDER BY v.id DESC LIMIT 1) AS schema_json
          FROM hd_form f WHERE f.id=?";
  $s=$db->prepare($sql); $s->bind_param('i',$id); $s->execute(); $r=$s->get_result(); $o=$r->fetch_assoc();
  out(true,$o);
}

// SAVE (cria/atualiza e salva versão)
if ($op==='save'){
  $id = intval($in['id']??0);
  $nome = trim($in['nome']??''); if($nome==='') out(false,null,'Nome obrigatório');
  $categoria = trim($in['categoria']??'');
  $status = trim($in['status']??'rascunho');
  $schema_json = $in['schema_json'] ?? '{}';

  if ($id>0){
    $s=$db->prepare("UPDATE hd_form SET nome=?, categoria=?, status=? WHERE id=?");
    $s->bind_param('sssi',$nome,$categoria,$status,$id); $s->execute();
  } else {
    $s=$db->prepare("INSERT INTO hd_form (nome,categoria,status,created_at) VALUES(?,?,?,NOW())");
    $s->bind_param('sss',$nome,$categoria,$status); $s->execute();
    $id = $db->insert_id;
  }

  // salva nova versão
  if ($chk=$db->query("SHOW TABLES LIKE 'hd_form_version'")) {
    if ($chk->num_rows>0) {
      $s=$db->prepare("INSERT INTO hd_form_version(form_id,schema_json,created_at,autor_id) VALUES(?,?,NOW(),?)");
      $autor_id = $_SESSION['usuario_id'] ?? null;
      $s->bind_param('isi',$id,$schema_json,$autor_id);
      $s->execute();
    }
  }
  out(true,['id'=>$id]);
}

// PUBLISH
if ($op==='publish'){
  $id=intval($in['id']??0); if(!$id) out(false,null,'id inválido');
  $s=$db->prepare("UPDATE hd_form SET status='publicado' WHERE id=?");
  $s->bind_param('i',$id); $s->execute();
  out(true,['id'=>$id]);
}
// UNPUBLISH
if ($op==='unpublish'){
  $id=intval($in['id']??0); if(!$id) out(false,null,'id inválido');
  $s=$db->prepare("UPDATE hd_form SET status='rascunho' WHERE id=?");
  $s->bind_param('i',$id); $s->execute();
  out(true,['id'=>$id]);
}

out(false,null,'op inválida');
