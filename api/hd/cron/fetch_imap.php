<?php
// public/api/hd/cron/fetch_imap.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__.'/../../../config.php';
require_once ROOT_PATH.'/system/config/connect.php';

$db=$conn??($mysqli??null); if(!$db||!($db instanceof mysqli)){ http_response_code(500); echo json_encode(['success'=>false,'error'=>'Sem conexão DB.']); exit; }
$r=$db->query("SELECT * FROM hd_email_config WHERE active=1 ORDER BY id DESC LIMIT 1"); $cfg=$r?$r->fetch_assoc():null;
if(!$cfg){ echo json_encode(['success'=>true,'imported'=>0,'note'=>'Email não ativo']); exit; }

if(!function_exists('imap_open')){ echo json_encode(['success'=>false,'error'=>'Extensão PHP imap não instalada']); exit; }

$host = $cfg['host'];
$port = (int)($cfg['port']?:993);
$proto= $cfg['protocol']?:'imap';
$sec  = $cfg['secure']?:'ssl';
$box  = $cfg['mailbox']?:'INBOX';

$mbxStr = sprintf("{%s:%d/%s/%s}%s",$host,$port,$proto,$sec==='none'?'novalidate-cert':$sec,$box);
$inbox = @imap_open($mbxStr, $cfg['username'], $cfg['password']);
if(!$inbox){ echo json_encode(['success'=>false,'error'=>'imap_open falhou']); exit; }

$emails = imap_search($inbox,'UNSEEN'); $count=0;
if($emails){
  rsort($emails);
  foreach($emails as $num){
    $header = imap_headerinfo($inbox,$num);
    $from   = isset($header->from[0]) ? strtolower($header->from[0]->mailbox.'@'.$header->from[0]->host) : null;
    $subject= isset($header->subject)? imap_utf8($header->subject):'';
    $mid    = $header->message_id ?? null;
    $date   = $header->date ?? null;

    // corpo (prefira HTML > texto)
    $body = imap_body($inbox,$num);
    $stripped = trim($body);

    // Tentar achar thread_token ou protocolo no subject
    $ticket_id = null; $action='created'; $note=null;

    // Match por token: [HD #PROTO|TOKEN]
    if(preg_match('/\[(HD|Mozart|HELPDESK)\s*#([^\|\]]+)\|([A-Fa-f0-9]+)\]/',$subject,$m)){
      $proto = trim($m[2]); $tok=$m[3];
      $s=$db->prepare("SELECT id FROM hd_ticket WHERE (protocolo=? OR id=CAST(? AS UNSIGNED)) AND thread_token=? LIMIT 1");
      $s->bind_param('sss',$proto,$proto,$tok); $s->execute(); $res=$s->get_result();
      if($o=$res->fetch_assoc()) $ticket_id=$o['id'];
    } else if(preg_match('/\b#(\d{1,12})\b/',$subject,$m)){ // fallback simples #123
      $idGuess=(int)$m[1]; $q=$db->query("SELECT id FROM hd_ticket WHERE id=".$idGuess." LIMIT 1"); if($q && $q->num_rows) $ticket_id=$idGuess;
    }

    if($ticket_id){
      // Append como comentário
      $s=$db->prepare("INSERT INTO hd_ticket_log(ticket_id,autor,texto,created_at) VALUES (?,?,?,NOW())");
      $autor=$from?:'remetente:desconhecido'; $texto=$stripped?:'(sem conteúdo)';
      $s->bind_param('iss',$ticket_id,$autor,$texto); $s->execute();
      $db->query("UPDATE hd_ticket SET last_email_at=NOW() WHERE id=".(int)$ticket_id);
      $action='appended';
    } else {
      // Criar novo ticket
      $ass=mb_substr($subject,0,190);
      $desc=$stripped?:'(sem conteúdo)';
      $email=$from;
      $s=$db->prepare("INSERT INTO hd_ticket (protocolo,assunto,descricao,solicitante_email,status,prioridade,created_at) VALUES (?,?,?,?, 'novo','media', NOW())");
      $proto = strtoupper(bin2hex(random_bytes(4)));
      $s->bind_param('ssss',$proto,$ass,$desc,$email);
      $s->execute(); $ticket_id=$db->insert_id;

      // thread_token e message_id
      $tok = bin2hex(random_bytes(8));
      $u=$db->prepare("UPDATE hd_ticket SET thread_token=?, message_id=?, last_email_at=NOW() WHERE id=?");
      $u->bind_param('ssi',$tok,$mid,$ticket_id); $u->execute();

      // Log
      @$db->query("INSERT INTO hd_ticket_log(ticket_id,autor,texto,created_at) VALUES (".$ticket_id.",'sistema:email','Ticket criado via e-mail',NOW())");
      $action='created';
    }

    // registra inbound
    $si=$db->prepare("INSERT INTO hd_mail_inbound(message_id,from_email,subject,received_at,ticket_id,action,note) VALUES (?,?,?,?,?,?,?)");
    $rcv= date('Y-m-d H:i:s', strtotime($date?:'now'));
    $si->bind_param('sssiiss',$mid,$from,$subject,$rcv,$ticket_id,$action,$note);
    $si->execute();

    // marcar como lida
    imap_setflag_full($inbox,$num,"\\Seen");
    $count++;
  }
}
imap_close($inbox);
echo json_encode(['success'=>true,'imported'=>$count]);
