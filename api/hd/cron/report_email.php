<?php
// public/api/hd/cron/report_email.php
// Envia por e-mail o JSON de qualquer endpoint de relatório.
// Ex.: navegador/CRON: /public/api/hd/cron/report_email.php?to=gestor@ex.com&url=/public/api/hd/reports/minidash-comparar.php?date_from=2025-10-01&date_to=2025-10-28
// Ex.: CLI: php report_email.php "to=gestor@ex.com&url=/public/api/hd/reports/minidash-comparar.php?date_from=2025-10-01&date_to=2025-10-28"

ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

header('Content-Type: application/json; charset=utf-8');

// aceita query via CLI
if (php_sapi_name() === 'cli' && isset($argv[1]) && strpos($argv[1],'=')!==false) { parse_str($argv[1], $_GET); }

$to  = trim($_GET['to']  ?? '');
$url = trim($_GET['url'] ?? '');

if (!$to || !$url) { echo json_encode(['ok'=>false,'error'=>'Parâmetros obrigatórios: to, url']); exit; }

// monta URL absoluta se necessário
$abs = $url;
if (strpos($url,'http') !== 0) {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $abs = $scheme.'://'.$host.(substr($url,0,1)=='/'?'':'/').$url;
}

// baixa JSON do relatório
$ctx = stream_context_create(['http'=>['timeout'=>30]]);
$json = @file_get_contents($abs, false, $ctx);
if ($json === false) { echo json_encode(['ok'=>false,'error'=>'Falha ao baixar: '.$abs]); exit; }

$subject = 'Relatório Help Desk — '.date('d/m/Y H:i');
$bodyTxt = "Segue relatório (JSON)\nFonte: $abs\n\nPrévia:\n".substr($json,0,1200).(strlen($json)>1200?'...':'')."\n";

$sent=false; $err=null;

// tenta MailerSMTP (se existir no seu projeto)
try{
  if (file_exists(ROOT_PATH.'/system/lib/MailerSMTP.php')) {
    require_once ROOT_PATH.'/system/lib/MailerSMTP.php';
    require_once ROOT_PATH.'/system/lib/EmailLogger.php';
    $logger = new EmailLogger(ROOT_PATH.'/logs/email_reports.log');
    $cfg = [
      'host'=> defined('SMTP_HOST')?SMTP_HOST:'',
      'port'=> defined('SMTP_PORT')?SMTP_PORT:587,
      'username'=> defined('SMTP_USER')?SMTP_USER:'',
      'password'=> defined('SMTP_PASS')?SMTP_PASS:'',
      'from_email'=> defined('SMTP_FROM')?SMTP_FROM:'noreply@localhost',
      'from_name'=> defined('SMTP_FROM_NAME')?SMTP_FROM_NAME:'Mozart HelpDesk',
    ];
    $mailer = new MailerSMTP($cfg, $logger);
    $res = $mailer->send($to, $subject, nl2br(htmlentities($bodyTxt)));
    $sent = $res['success'] ?? false; if(!$sent){ $err = $res['error'] ?? 'Falha no SMTP'; }
  }
} catch (Throwable $e){ $err = 'Exceção SMTP: '.$e->getMessage(); }

// fallback mail()
if(!$sent){
  $headers = "Content-Type: text/plain; charset=UTF-8\r\nFrom: Mozart HelpDesk <noreply@localhost>\r\n";
  $sent = @mail($to, $subject, $bodyTxt, $headers); if(!$sent && !$err) $err='mail() retornou false';
}

echo json_encode(['ok'=>$sent,'to'=>$to,'url'=>$abs,'error'=>$err]);
