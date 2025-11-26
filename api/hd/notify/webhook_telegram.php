<?php
// public/api/hd/notify/webhook_telegram.php
ini_set('display_errors',0);
header('Content-Type: application/json');
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/connect.php';

$db=$conn??($mysqli??null); if(!$db||!($db instanceof mysqli)){ http_response_code(500); echo json_encode(['success'=>false]); exit; }
$raw=file_get_contents('php://input'); $hdrs=json_encode(getallheaders());
$db->query("INSERT INTO hd_msg_webhook_log (canal,headers,body,received_at) VALUES ('telegram','".$db->real_escape_string($hdrs)."','".$db->real_escape_string($raw)."',NOW())");

$u=json_decode($raw,true)?:[];
$msg=$u['message']??[];
$text=$msg['text']??'';
$from_id = $msg['from']['id'] ?? null;
$ticket_id=null;
if(preg_match('/#(\d{1,12})/',$text,$m)) $ticket_id=(int)$m[1];

$s=$db->prepare("INSERT INTO hd_msg_inbox (canal,origem,payload,text,received_at,related_ticket_id) VALUES ('telegram',?,?,?,NOW(),?)");
$payload=json_encode($u);
$s->bind_param('sssi',$from_id,$payload,$text,$ticket_id);
$s->execute();

if($ticket_id){
  $db->query("INSERT INTO hd_ticket_log (ticket_id,autor,texto,created_at) VALUES (".$ticket_id.",'telegram:".$db->real_escape_string((string)$from_id)."','".$db->real_escape_string($text)."',NOW())");
}
echo json_encode(['success'=>true]);
