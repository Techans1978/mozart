<?php
// public/api/hd/admin/oncall.php
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

if ($op==='combos'){
  $data=['grupos'=>[],'agentes'=>[]];
  if ($r=$db->query("SELECT id,nome FROM hd_grupo ORDER BY nome")) while($o=$r->fetch_assoc()) $data['grupos'][]=$o;
  if ($r=$db->query("SELECT id,nome FROM usuarios WHERE ativo=1 ORDER BY nome")) while($o=$r->fetch_assoc()) $data['agentes'][]=$o;
  out(true,$data);
}

if ($op==='list'){
  $gid=intval($in['grupo_id']??0); if(!$gid) out(true,['items'=>[]]); // ok vazio
  $s=$db->prepare("SELECT id,grupo_id,agente_id,tipo,DATE_FORMAT(data_ini,'%Y-%m-%d') AS data_ini, DATE_FORMAT(data_fim,'%Y-%m-%d') AS data_fim,
                  (SELECT nome FROM usuarios u WHERE u.id=agente_id) AS agente_nome
                  FROM hd_oncall_roster WHERE grupo_id=? ORDER BY data_ini DESC, id DESC LIMIT 500");
  $s->bind_param('i',$gid); $s->execute(); $r=$s->get_result(); $items=[]; while($o=$r->fetch_assoc()) $items[]=$o;
  out(true,['items'=>$items]);
}

if ($op==='save'){
  $gid=intval($in['grupo_id']??0); if(!$gid) out(false,null,'grupo_id obrigatório');
  $items = $in['itens']??[];
  // simples: apaga e recria (pode trocar por diff incremental se preferir)
  $s=$db->prepare("DELETE FROM hd_oncall_roster WHERE grupo_id=?"); $s->bind_param('i',$gid); $s->execute();
  if (!empty($items)) {
    $stmt=$db->prepare("INSERT INTO hd_oncall_roster (grupo_id, agente_id, tipo, data_ini, data_fim, created_at) VALUES (?,?,?,?,?,NOW())");
    foreach($items as $it){
      $aid = intval($it['agente_id']??0); $tipo = trim($it['tipo']??'primario');
      $ini = $it['data_ini']??null; $fim=$it['data_fim']??null;
      if ($aid && $ini && $fim){
        $stmt->bind_param('iisss',$gid,$aid,$tipo,$ini,$fim);
        $stmt->execute();
      }
    }
  }
  out(true,['grupo_id'=>$gid]);
}

out(false,null,'op inválida');
