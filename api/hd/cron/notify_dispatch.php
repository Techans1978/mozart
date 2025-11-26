<?php
// public/api/hd/cron/notify_dispatch.php
// Processa hd_msg_outbox e envia via WPPConnect / Telegram
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json');
require_once __DIR__.'/../../../config.php';
require_once ROOT_PATH.'/system/config/connect.php';

$db=$conn??($mysqli??null); if(!$db||!($db instanceof mysqli)){ http_response_code(500); echo json_encode(['success'=>false,'error'=>'Sem conexão DB.']); exit; }

function getChannelCfg($db,$name){
  $s=$db->prepare("SELECT CAST(cfg_json AS CHAR) AS cfg_json FROM hd_msg_channel WHERE nome=? AND ativo=1 ORDER BY id DESC LIMIT 1");
  $s->bind_param('s',$name); $s->execute(); $r=$s->get_result(); $o=$r->fetch_assoc();
  return $o ? json_decode($o['cfg_json']?:'{}', true) : null;
}

function http_post_json($url,$data){
  $opts=['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\n",'content'=>json_encode($data),'timeout'=>15]];
  $ctx=stream_context_create($opts);
  return @file_get_contents($url,false,$ctx);
}

$wpp = getChannelCfg($db,'whatsapp');
$tg  = getChannelCfg($db,'telegram');

$r=$db->query("SELECT * FROM hd_msg_outbox WHERE status IN ('queued','retry') ORDER BY id ASC LIMIT 30");
$sent=0; $errors=[];
while($o=$r->fetch_assoc()){
  $ok=false; $err=null;
  if($o['canal']==='whatsapp' && $wpp){
    // WPPConnect send message
    // Esperado em cfg_json: {"host":"http://127.0.0.1:21465","instance":"default","token":"xxx"}
    $url = rtrim($wpp['host'] ?? '', '/')."/api/".$wpp['instance']."/send-message";
    if(!empty($wpp['token'])) $url .= "?token=".$wpp['token'];
    $payload=[ "phone" => $o['destino'], "message" => $o['corpo'] ];
    $res=http_post_json($url,$payload);
    $ok = $res!==false;
    if(!$ok) $err='send failed';
  } elseif($o['canal']==='telegram' && $tg){
    // Telegram sendMessage
    // Esperado: {"bot_token":"123:ABC"}
    $bot=$tg['bot_token'] ?? null;
    if($bot){
      $url="https://api.telegram.org/bot".$bot."/sendMessage";
      $payload=["chat_id"=>$o['destino'],"text"=>$o['corpo']];
      $res=http_post_json($url,$payload);
      $ok = $res!==false;
      if(!$ok) $err='send failed';
    } else { $err='bot_token vazio'; }
  } else {
    $err='canal inativo/sem cfg';
  }

  if($ok){
    $s=$db->prepare("UPDATE hd_msg_outbox SET status='sent', sent_at=NOW() WHERE id=?"); $s->bind_param('i',$o['id']); $s->execute();
    $sent++;
  } else {
    $att=$o['attempts']+1;
    $status = ($att>=5)?'error':'retry';
    $s=$db->prepare("UPDATE hd_msg_outbox SET status=?, attempts=?, last_error=? WHERE id=?");
    $s->bind_param('sisi',$status,$att,$err,$o['id']); $s->execute();
    $errors[]=['id'=>$o['id'],'error'=>$err];
  }
}

echo json_encode(['success'=>true,'sent'=>$sent,'errors'=>$errors]);
