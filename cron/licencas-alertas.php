<?php
// public/cron/licencas-alertas.php
// Cron de alertas de vencimento de licenças (mysqli)

ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../modules/../config.php';              // sobe 1 nível a partir de /cron
require_once ROOT_PATH . '/system/config/connect.php';

// --------- CONFIG BÁSICA ---------
$MAIL_FROM = defined('APP_NAME') ? (APP_NAME.' <noreply@'.($_SERVER['SERVER_NAME'] ?? 'localhost').'>') : 'Mozart BPM <noreply@localhost>';

// CLI flags
$is_cli = (php_sapi_name()==='cli');
$argv_str = $is_cli ? implode(' ',$argv) : '';
$dry_run = (isset($_GET['dry_run']) && $_GET['dry_run']=='1') || (strpos($argv_str,'--dry-run')!==false);
$hard_limit = isset($_GET['limit']) ? max(1,(int)$_GET['limit']) : (preg_match('/--limit=(\d+)/',$argv_str,$m)? max(1,(int)$m[1]) : 500);

// --------- CHECAGEM CONEXÃO ---------
$dbc = $conn ?? null;
if(!$dbc || !($dbc instanceof mysqli)){
  echo "[ERRO] Sem conexão mysqli.\n";
  exit(1);
}

// --------- MIGRAÇÃO DO LOG ---------
$dbc->query("CREATE TABLE IF NOT EXISTS moz_notif_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo VARCHAR(30) NOT NULL,           -- ex.: 'licenca'
  ref_id BIGINT UNSIGNED NOT NULL,     -- id da licença
  alerta_id BIGINT UNSIGNED NULL,      -- id do alerta (moz_licenca_alerta)
  target_date DATE NOT NULL,           -- data em que o alerta se aplica (hoje)
  canal ENUM('Email','WhatsApp','SMS','Sistema') NOT NULL DEFAULT 'Email',
  destinatarios VARCHAR(500) NULL,
  subject VARCHAR(200) NULL,
  body MEDIUMTEXT NULL,
  status ENUM('QUEUED','SENT','SKIPPED','ERROR') NOT NULL DEFAULT 'QUEUED',
  error TEXT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  sent_at DATETIME NULL,
  uniq_hash CHAR(64) NOT NULL,         -- sha256(tipo|ref_id|alerta_id|target_date|canal|destinatarios)
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id),
  UNIQUE KEY uk_uniq (uniq_hash),
  KEY idx_tipo_ref (tipo, ref_id),
  KEY idx_date (target_date),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// --------- HELPERS ---------
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function normalize_emails($s){
  // aceita "a@b,c@d ; e@f" e quebra em array limpinho
  $s = str_replace([';',"\n","\r"], [',',',',','], (string)$s);
  $arr = array_filter(array_map('trim', explode(',', $s)));
  // remove duplicados
  return array_values(array_unique($arr));
}
function send_email_native($from, $to, $subject, $html){
  // Envio simples via mail(). Se tiver SMTP no servidor, respeita.
  // Para produção, considere PHPMailer/SMTP autenticado.
  $headers  = "MIME-Version: 1.0\r\n";
  $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
  $headers .= "From: {$from}\r\n";
  return mail($to, $subject, $html, $headers);
}

// --------- BUSCA LICENÇAS A NOTIFICAR ---------
// regra: DATEDIFF(vig_fim, CURDATE()) = dias_antes
// licença ativa, status != 'Expirada', alerta ativo
$today = (new DateTime('now'))->format('Y-m-d');

$sql = "SELECT 
          L.id AS lic_id, L.produto_nome, L.vig_inicio, L.vig_fim, L.tipo, L.periodicidade, L.status,
          L.valor_ciclo, L.moeda, L.fornecedor_id, L.empresa_id, L.codigo_chave,
          L.suporte_nome, L.suporte_email, L.suporte_tel, L.suporte_whatsapp, L.suporte_url,
          A.id AS alerta_id, A.dias_antes, A.canal, A.destinatarios, A.mensagem,
          F.nome AS fornecedor_nome,
          E.nome_fantasia AS empresa_fantasia, E.nome_empresarial AS empresa_razao
        FROM moz_licenca L
        INNER JOIN moz_licenca_alerta A ON A.licenca_id = L.id AND A.ativo=1
        LEFT JOIN moz_fornecedor F ON F.id=L.fornecedor_id
        LEFT JOIN empresas E ON E.id=L.empresa_id
        WHERE L.ativo=1
          AND L.status <> 'Expirada'
          AND L.vig_fim IS NOT NULL
          AND DATEDIFF(L.vig_fim, CURDATE()) = A.dias_antes
        ORDER BY L.vig_fim ASC
        LIMIT {$hard_limit}";
$rs = $dbc->query($sql);
if(!$rs){
  echo "[ERRO] Query falhou: ".$dbc->error."\n";
  exit(1);
}

$rows=[]; while($r=$rs->fetch_assoc()) $rows[]=$r;

if(!$rows){
  echo "[INFO] Nenhuma licença para notificar hoje ({$today}).\n";
  exit(0);
}

// --------- PROCESSAMENTO ---------
$base_url = rtrim(BASE_URL ?? '', '/');
$ok=0; $skip=0; $err=0;

foreach($rows as $r){
  $lic_id  = (int)$r['lic_id'];
  $aid     = (int)$r['alerta_id'];
  $canal   = $r['canal'];
  $destraw = (string)$r['destinatarios'];
  $destinatarios = $canal==='Email' ? normalize_emails($destraw) : [$destraw];

  // idempotência
  $uniq = hash('sha256', implode('|', [
    'licenca', $lic_id, $aid, $today, $canal, strtolower(trim($destraw))
  ]));

  $chk = $dbc->prepare("SELECT id,status FROM moz_notif_log WHERE uniq_hash=? LIMIT 1");
  $chk->bind_param('s',$uniq); $chk->execute(); $chkres = $chk->get_result(); $exists = $chkres->fetch_assoc(); $chk->close();
  if($exists){
    $skip++; 
    if($dry_run) echo "[SKIP] já existe log #{$exists['id']} (status={$exists['status']}) lic={$lic_id} alerta={$aid}\n";
    continue;
  }

  // monta título/corpo
  $empresa = $r['empresa_fantasia'] ?: $r['empresa_razao'] ?: '—';
  $forn    = $r['fornecedor_nome'] ?: '—';
  $dias    = (int)$r['dias_antes'];
  $vfim    = $r['vig_fim'];
  $vfim_br = date('d/m/Y', strtotime($vfim));
  $link    = $base_url . "/modules/gestao_ativos/licencas-form.php?id={$lic_id}";

  $subject = "[Licenças] {$r['produto_nome']} — vence em {$dias} dia(s) ({$vfim_br})";
  $body = '
    <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px">
      <h3 style="margin:0 0 8px">Aviso de renovação — '.$r['produto_nome'].'</h3>
      <p><b>Empresa:</b> '.h($empresa).'<br>
         <b>Fornecedor/Emissor:</b> '.h($forn).'<br>
         <b>Vigência:</b> até '.h($vfim_br).' (faltam '.h($dias).' dia(s))<br>
         <b>Periodicidade:</b> '.h($r['periodicidade']).' — <b>Status:</b> '.h($r['status']).'<br>
         <b>Código/Chave:</b> '.h($r['codigo_chave'] ?: '—').'<br>
         <b>Valor/ciclo:</b> '.($r['valor_ciclo']!==null ? (h($r['moeda']).' '.number_format((float)$r['valor_ciclo'],2,',','.')) : '—').'
      </p>';

  if($r['suporte_nome'] || $r['suporte_email'] || $r['suporte_tel'] || $r['suporte_whatsapp'] || $r['suporte_url']){
    $body .= '<p><b>Suporte:</b> '.h(($r['suporte_nome']?:'')).' '.h(($r['suporte_email']?' • '.$r['suporte_email']:'')).' '.h(($r['suporte_tel']?' • '.$r['suporte_tel']:'')).' '.h(($r['suporte_whatsapp']?' • '.$r['suporte_whatsapp']:'')).' '.h(($r['suporte_url']?' • '.$r['suporte_url']:'')).'</p>';
  }
  if($r['mensagem']){
    $body .= '<p style="padding:8px;background:#f7f7f7;border-left:4px solid #999">'.nl2br(h($r['mensagem'])).'</p>';
  }
  $body .= '<p><a href="'.h($link).'" target="_blank">Abrir licença no sistema</a></p></div>';

  // grava log inicial (QUEUED)
  $ins = $dbc->prepare("INSERT INTO moz_notif_log (tipo,ref_id,alerta_id,target_date,canal,destinatarios,subject,body,status,uniq_hash) VALUES ('licenca',?,?,?,?,?,?,?,'QUEUED',?)");
  $destJoin = is_array($destinatarios) ? implode(',', $destinatarios) : (string)$destinatarios;
  $ins->bind_param('siisssss', $lic_id, $aid, $today, $canal, $destJoin, $subject, $body, $uniq);
  $ins->execute(); $log_id = (int)$ins->insert_id; $ins->close();

  if($dry_run){
    echo "[DRY] lic={$lic_id} alerta={$aid} canal={$canal} para={$destJoin}\n";
    continue;
  }

  $ok_this = true; $err_msg = null;

  if($canal==='Email'){
    if(!$destinatarios){
      $ok_this=false; $err_msg='Sem destinatários';
    } else {
      foreach($destinatarios as $to){
        $sent = send_email_native($MAIL_FROM, $to, $subject, $body);
        if(!$sent){ $ok_this=false; $err_msg='mail() retornou false para '.$to; break; }
      }
    }
  } else {
    // Para WhatsApp/SMS/Sistema, deixamos como QUEUED para outro worker consumir
    // Evolução: criar outbox por canal e integradores.
  }

  if($ok_this){
    $upd=$dbc->prepare("UPDATE moz_notif_log SET status='SENT', sent_at=NOW() WHERE id=?");
    $upd->bind_param('i',$log_id); $upd->execute(); $upd->close();
    $ok++;
  } else {
    $upd=$dbc->prepare("UPDATE moz_notif_log SET status='ERROR', error=?, attempts=attempts+1 WHERE id=?");
    $upd->bind_param('si',$err_msg,$log_id); $upd->execute(); $upd->close();
    $err++;
  }
}

echo "[FIM] {$today} OK={$ok} SKIP={$skip} ERR={$err}".($dry_run?" (dry-run)":"")."\n";
