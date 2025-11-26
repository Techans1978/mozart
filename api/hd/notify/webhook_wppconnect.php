<?php
// public/api/hd/notify/webhook_wppconnect.php
ini_set('display_errors',0);
header('Content-Type: application/json');
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/connect.php';

$db=$conn??($mysqli??null); if(!$db||!($db instanceof mysqli)){ http_response_code(500); echo json_encode(['success'=>false]); exit; }
$raw=file_get_contents('php://input'); $hdrs=json_encode(getallheaders());

$db->query("INSERT INTO hd_msg_webhook_log (canal,headers,body,received_at) VALUES ('whatsapp','".$db->real_escape_string($hdrs)."','".$db->real_escape_string($raw)."',NOW())");

$in=json_decode($raw,true)?:[];
$text = $in['text'] ?? ($in['message'] ?? '');
$from = $in['from'] ?? ($in['phone'] ?? null);
$ticket_id=null;

// heurística simples: procurar #123 no texto para associar ticket
if(preg_match('/#(\d{1,12})/',$text,$m)){
  $ticket_id=(int)$m[1];
}

$s=$db->prepare("INSERT INTO hd_msg_inbox (canal,origem,payload,text,received_at,related_ticket_id) VALUES ('whatsapp',?,?,?,NOW(),?)");
$payload=json_encode($in);
$s->bind_param('sssi',$from,$payload,$text,$ticket_id);
$s->execute();

// Se houver ticket, cria log
if($ticket_id){
  $db->query("INSERT INTO hd_ticket_log (ticket_id,autor,texto,created_at) VALUES (".$ticket_id.",'whatsapp:".$db->real_escape_string($from)."','".$db->real_escape_string($text)."',NOW())");
}

echo json_encode(['success'=>true]);
