<?php
// public_html/system/updown/download.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
require_once __DIR__.'/lib_storage.php';

if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

function current_user_id(): ?int {
  // ajuste se seu sistema usa outro nome na sessão
  $uid = $_SESSION['user_id'] ?? ($_SESSION['usuario_id'] ?? null);
  return $uid ? (int)$uid : null;
}
function current_user_name(): string {
  return (string)($_SESSION['user_name'] ?? ($_SESSION['usuario_nome'] ?? ($_SESSION['username'] ?? '')));
}

/**
 * ACL central (você vai evoluir isso por módulo).
 * Regra inicial segura:
 * - Se estiver logado => permite (por enquanto)
 * - Depois a gente restringe por permissão/role e vínculo module/entity_id.
 */
function moz_can_download(array $fileRow): bool {
  // TODO futuro: checar permissão real por módulo/registro
  return true;
}

function audit(mysqli $dbc, array $data): void {
  $sql = "INSERT INTO moz_file_audit
          (file_id,module,entity_id,rel_path,user_id,user_name,ip,user_agent,action,note)
          VALUES (?,?,?,?,?,?,?,?,?,?)";
  $st = $dbc->prepare($sql);
  if(!$st) return;

  $file_id    = $data['file_id'] ?? null;
  $module     = $data['module'] ?? null;
  $entity_id  = $data['entity_id'] ?? null;
  $rel_path   = $data['rel_path'] ?? null;
  $user_id    = $data['user_id'] ?? null;
  $user_name  = $data['user_name'] ?? null;
  $ip         = $data['ip'] ?? null;
  $ua         = $data['user_agent'] ?? null;
  $action     = $data['action'] ?? 'download';
  $note       = $data['note'] ?? null;

  // null-safe (bind_param não aceita null bem com tipos primitivos)
  $file_id   = $file_id   === null ? 0 : (int)$file_id;
  $entity_id = $entity_id === null ? 0 : (int)$entity_id;
  $user_id   = $user_id   === null ? 0 : (int)$user_id;

  $module    = (string)($module ?? '');
  $rel_path  = (string)($rel_path ?? '');
  $user_name = (string)($user_name ?? '');
  $ip        = (string)($ip ?? '');
  $ua        = (string)($ua ?? '');
  $action    = (string)($action ?? 'download');
  $note      = (string)($note ?? '');

  $st->bind_param(
    'isisisssss',
    $file_id, $module, $entity_id, $rel_path,
    $user_id, $user_name, $ip, $ua, $action, $note
  );

  $st->execute();
  $st->close();
}

// ===== Entrada: sempre por ID =====
$uid = current_user_id();
$uname = current_user_name();
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);

// ===== Entrada: por ID (preferencial) OU por rel_path (p=) =====
$fileId = (int)($_GET['id'] ?? 0);
$rel = trim((string)($_GET['p'] ?? ''));

if ($fileId <= 0 && $rel === '') {
  http_response_code(400);
  die('Informe id ou p.');
}

if ($fileId > 0) {
  // ---- MODO IDEAL: via moz_file ----
  $st = $dbc->prepare("SELECT * FROM moz_file WHERE id=? AND is_deleted=0 LIMIT 1");
  $st->bind_param('i', $fileId);
  $st->execute();
  $file = $st->get_result()->fetch_assoc();
  $st->close();

  if (!$file) {
    audit($dbc, [
      'file_id'=>$fileId,'user_id'=>$uid,'user_name'=>$uname,'ip'=>$ip,'user_agent'=>$ua,
      'action'=>'notfound','note'=>'file not found'
    ]);
    http_response_code(404);
    die('Arquivo não encontrado.');
  }

} else {
  // ---- MODO LEGADO/TRANSIÇÃO: p=rel_path (fora do public_html) ----
  try {
    $abs = storage_resolve_abs($rel);

    // Nome sugerido para download (opcional)
    $downloadName = trim((string)($_GET['name'] ?? ''));
    if ($downloadName === '') $downloadName = basename($rel);

    audit($dbc, [
      'file_id'=>0,'module'=>'','entity_id'=>0,'rel_path'=>$rel,
      'user_id'=>$uid,'user_name'=>$uname,'ip'=>$ip,'user_agent'=>$ua,
      'action'=>'download_p','note'=>null
    ]);

    storage_stream_file($abs, $downloadName, null);
    exit;

  } catch(Exception $e) {
    audit($dbc, [
      'file_id'=>0,'module'=>'','entity_id'=>0,'rel_path'=>$rel,
      'user_id'=>$uid,'user_name'=>$uname,'ip'=>$ip,'user_agent'=>$ua,
      'action'=>'error_p','note'=>substr($e->getMessage(),0,250)
    ]);
    http_response_code(404);
    die('Arquivo não encontrado.');
  }
}


// ACL
if (!moz_can_download($file)) {
  audit($dbc, [
    'file_id'=>$fileId,'module'=>$file['module'],'entity_id'=>(int)$file['entity_id'],'rel_path'=>$file['rel_path'],
    'user_id'=>$uid,'user_name'=>$uname,'ip'=>$ip,'user_agent'=>$ua,
    'action'=>'deny','note'=>'acl deny'
  ]);
  http_response_code(403);
  die('Acesso negado.');
}

try {
  $abs = storage_resolve_abs($file['rel_path']);
  audit($dbc, [
    'file_id'=>$fileId,'module'=>$file['module'],'entity_id'=>(int)$file['entity_id'],'rel_path'=>$file['rel_path'],
    'user_id'=>$uid,'user_name'=>$uname,'ip'=>$ip,'user_agent'=>$ua,
    'action'=>'download','note'=>null
  ]);

  storage_stream_file($abs, $file['original_name'] ?: $file['stored_name'], $file['mime'] ?? null);
  exit;

} catch(Exception $e) {
  audit($dbc, [
    'file_id'=>$fileId,'module'=>$file['module'],'entity_id'=>(int)$file['entity_id'],'rel_path'=>$file['rel_path'],
    'user_id'=>$uid,'user_name'=>$uname,'ip'=>$ip,'user_agent'=>$ua,
    'action'=>'error','note'=>substr($e->getMessage(),0,250)
  ]);
  http_response_code(500);
  die('Falha ao baixar arquivo.');
}
