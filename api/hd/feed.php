<?php
// public/api/hd/feed.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/../../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
proteger_pagina();

$user_id = $_SESSION['usuario_id'] ?? 0;
$action  = $_POST['action'] ?? $_GET['action'] ?? 'list';

function ok($data){ echo json_encode(['success'=>true,'data'=>$data]); exit; }
function err($msg,$code=400){ http_response_code(200); echo json_encode(['success'=>false,'message'=>$msg]); exit; }
function can_view_ticket($conn,$tid,$uid){
  $stmt = $conn->prepare("SELECT COUNT(*) FROM hd_ticket WHERE id=? AND solicitante_user_id=?");
  $stmt->bind_param('ii',$tid,$uid); $stmt->execute(); $stmt->bind_result($c); $stmt->fetch(); $stmt->close();
  return $c>0;
}

if ($action==='list') {
  $sql = "SELECT id, protocolo, titulo, status, prioridade, created_at, updated_at
          FROM hd_ticket
          WHERE solicitante_user_id = ?
          ORDER BY COALESCE(updated_at,created_at) DESC, id DESC LIMIT 200";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i',$user_id);
  $stmt->execute();
  $res = $stmt->get_result(); $rows=[];
  while($r=$res->fetch_assoc()) $rows[]=$r;
  $stmt->close();
  ok($rows);
}

if ($action==='details') {
  $tid = (int)($_GET['ticket_id'] ?? $_POST['ticket_id'] ?? 0);
  if(!$tid) err('Ticket inválido');
  if(!can_view_ticket($conn,$tid,$user_id)) err('Acesso negado');

  $t = $conn->query("SELECT * FROM hd_ticket WHERE id=$tid")->fetch_assoc();
  $msgs = [];
  $q = $conn->query("SELECT m.*, u.nome AS autor
                     FROM hd_ticket_msg m
                     LEFT JOIN usuarios u ON u.id=m.user_id
                     WHERE m.ticket_id=$tid ORDER BY m.id ASC");
  while($x=$q->fetch_assoc()){
    $x['files']=[];
    $m_id = (int)$x['id'];
    $fq = $conn->query("SELECT id, orig_name, mime, size_bytes, path, stored_name
                        FROM hd_file WHERE msg_id=$m_id ORDER BY id ASC");
    while($f=$fq->fetch_assoc()){
      $f['url'] = BASE_URL.'/uploads/helpdesk/'.$f['stored_name'];
      $x['files'][] = $f;
    }
    $msgs[]=$x;
  }
  ok(['ticket'=>$t,'msgs'=>$msgs]);
}

if ($action==='send') {
  $tid   = (int)($_POST['ticket_id'] ?? 0);
  $msg   = trim($_POST['msg'] ?? '');
  $tipo  = in_array(($_POST['tipo'] ?? 'mensagem'), ['mensagem','resposta_tecnica']) ? $_POST['tipo'] : 'mensagem';
  $att   = $_POST['attachment_ids'] ?? []; if(!is_array($att)) $att=[];
  if(!$tid || $msg==='') err('Dados inválidos');
  if(!can_view_ticket($conn,$tid,$user_id)) err('Acesso negado');

  // cria mensagem
  $stmt = $conn->prepare("INSERT INTO hd_ticket_msg (ticket_id,user_id,tipo,conteudo) VALUES (?,?,?,?)");
  $stmt->bind_param('iiss',$tid,$user_id,$tipo,$msg);
  $ok = $stmt->execute(); $msg_id = $conn->insert_id; $stmt->close();
  if(!$ok) err('Erro ao salvar mensagem: '.$conn->error);

  // vincula anexos já enviados
  if($att){
    $ids = array_map('intval',$att);
    $conn->query("UPDATE hd_file SET msg_id=$msg_id WHERE id IN (".implode(',',$ids).") AND ticket_id=$tid AND user_id=$user_id");
  }

  $conn->query("UPDATE hd_ticket SET updated_at=NOW() WHERE id=$tid");
  ok(['msg_id'=>$msg_id]);
}

if ($action==='upload') {
  $tid = (int)($_POST['ticket_id'] ?? 0);
  if(!$tid) err('ticket_id obrigatório');
  if(!can_view_ticket($conn,$tid,$user_id)) err('Acesso negado');

  if(empty($_FILES['file'])) err('Arquivo não enviado');

  $f = $_FILES['file'];
  if($f['error']!==UPLOAD_ERR_OK) err('Falha no upload (erro '.$f['error'].')');

  // Validações
  $allowed = ['image/jpeg','image/png','image/gif','application/pdf'];
  $mime = mime_content_type($f['tmp_name']);
  if(!in_array($mime,$allowed)) err('Tipo de arquivo não permitido');
  $maxMB = 12; // limite 12MB
  if($f['size'] > $maxMB*1024*1024) err('Arquivo excede o limite de '.$maxMB.'MB');

  // Pasta (crie com permissão de escrita do PHP)
  $dir = ROOT_PATH.'/public/uploads/helpdesk';
  if(!is_dir($dir)) @mkdir($dir,0775,true);

  // Nome seguro
  $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
  $stored = 'hd_'.date('Ymd_His').'_'.$user_id.'_'.bin2hex(random_bytes(4)).($ext?('.'.$ext):'');
  $dest = $dir.'/'.$stored;

  if(!move_uploaded_file($f['tmp_name'],$dest)) err('Não foi possível salvar o arquivo');

  $orig = $f['name'];
  $size = (int)$f['size'];
  $path = '/uploads/helpdesk/'.$stored;

  $stmt = $conn->prepare("INSERT INTO hd_file (ticket_id,msg_id,user_id,orig_name,stored_name,mime,size_bytes,path) VALUES (NULLIF(?,0),NULL,?,?,?,?,?,?)");
  $stmt->bind_param('i ss sisss', $tid, $orig, $stored, $mime, $size, $path);
  // Ajuste do bind (evitar espaços errados):
  $stmt->close();
  $stmt = $conn->prepare("INSERT INTO hd_file (ticket_id,msg_id,user_id,orig_name,stored_name,mime,size_bytes,path) VALUES (?,?,?,?,?,?,?,?)");
  $stmt->bind_param('ii ssssis s', $tid, $null= null, $user_id, $orig, $stored, $mime, $size, $path);
  // Para simplificar, insere com query direta:
  $orig_e   = $conn->real_escape_string($orig);
  $stored_e = $conn->real_escape_string($stored);
  $mime_e   = $conn->real_escape_string($mime);
  $path_e   = $conn->real_escape_string($path);
  $q = "INSERT INTO hd_file (ticket_id,msg_id,user_id,orig_name,stored_name,mime,size_bytes,path)
        VALUES ($tid,NULL,$user_id,'$orig_e','$stored_e','$mime_e',$size,'$path_e')";
  if(!$conn->query($q)) err('Erro ao registrar arquivo: '.$conn->error);
  $fid = $conn->insert_id;

  ok([
    'file'=>[
      'id'=>$fid,'orig_name'=>$orig,'mime'=>$mime,'size_bytes'=>$size,
      'stored_name'=>$stored,'path'=>$path,'url'=>BASE_URL.$path
    ]
  ]);
}

if ($action==='files_by_msg') {
  $mid = (int)($_GET['msg_id'] ?? 0);
  if(!$mid) err('msg_id obrigatório');
  $r = $conn->query("SELECT m.ticket_id, m.user_id FROM hd_ticket_msg m WHERE m.id=$mid")->fetch_assoc();
  if(!$r) err('Mensagem inexistente');
  if(!can_view_ticket($conn,(int)$r['ticket_id'],$user_id)) err('Acesso negado');

  $files=[];
  $q = $conn->query("SELECT id, orig_name, mime, size_bytes, path, stored_name
                     FROM hd_file WHERE msg_id=$mid ORDER BY id ASC");
  while($f=$q->fetch_assoc()){
    $f['url'] = BASE_URL.'/uploads/helpdesk/'.$f['stored_name'];
    $files[]=$f;
  }
  ok($files);
}

err('Ação inválida');
