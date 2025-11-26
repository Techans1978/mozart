<?php
// public/cron/hd_rules_worker.php
// Avalia hd_regra (JSON Logic simples) e aplica ações nos tickets.
// Crontab: */5 * * * * php /caminho/public/cron/hd_rules_worker.php

ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/connect.php';

$db = $conn ?? ($mysqli ?? null);
if (!$db || !($db instanceof mysqli)) { fwrite(STDERR,"Sem conexão DB.\n"); exit(1); }

// Tabelas auxiliares brand-safe (tags)
$db->query("CREATE TABLE IF NOT EXISTS hd_ticket_tag (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  tag VARCHAR(64) NOT NULL,
  UNIQUE KEY tk (ticket_id, tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Helpers
function fetchAll($db,$sql,$types='',$params=[]){ $s=$db->prepare($sql); if($types) $s->bind_param($types,...$params); $s->execute(); $r=$s->get_result(); $rows=[]; while($o=$r->fetch_assoc()) $rows[]=$o; return $rows; }
function jget($o,$k,$def=null){ return is_array($o)&&array_key_exists($k,$o) ? $o[$k] : $def; }

// JSON Logic básico
function jlogic($rule, $data){
  if (!is_array($rule)) return $rule;
  if (isset($rule['var'])) {
    $path = $rule['var'];
    return jget($data,$path,null);
  }
  foreach ($rule as $op=>$args){
    switch($op){
      case '==': return ($args[0]??null) == ($args[1]??null);
      case '!=': return ($args[0]??null) != ($args[1]??null);
      case '>':  return ($args[0]??0) >  ($args[1]??0);
      case '<':  return ($args[0]??0) <  ($args[1]??0);
      case '>=': return ($args[0]??0) >= ($args[1]??0);
      case '<=': return ($args[0]??0) <= ($args[1]??0);
      case 'and':
        foreach((array)$args as $a){ if(!jlogic($a,$data)) return false; } return true;
      case 'or':
        foreach((array)$args as $a){ if(jlogic($a,$data)) return true; } return false;
      case 'in':
        $needle = jlogic($args[0],$data);
        $hay = jlogic($args[1],$data);
        return is_array($hay) ? in_array($needle,$hay,true) : (is_string($hay) && strpos($hay,(string)$needle)!==false);
      default: return false;
    }
  }
  return false;
}

// Regras ativas
$regras = fetchAll($db,"SELECT id,nome,evento,ativo,cond_json,acao_json FROM hd_regra WHERE ativo=1 ORDER BY id DESC");

if (!$regras){ echo json_encode(['success'=>true,'processed'=>0]); exit; }

// Tickets recentes (ajuste o escopo conforme necessidade)
$tickets = fetchAll($db,"
  SELECT id, protocolo, status, prioridade, loja_id, grupo_id, solicitante_user_id, agente_user_id, created_at, updated_at
  FROM hd_ticket
  WHERE updated_at >= NOW() - INTERVAL 3 DAY
  ORDER BY id DESC
  LIMIT 2000
");

$aplicadas = 0;

foreach($tickets as $t){
  $ctx = $t; // contexto para JSON logic
  foreach($regras as $r){
    $cond = json_decode($r['cond_json'] ?? '{}', true); if(!$cond) continue;
    $ok = jlogic($cond, $ctx);
    if (!$ok) continue;

    $acao = json_decode($r['acao_json'] ?? '{}', true); if(!$acao) continue;

    // Ações suportadas: set_status, set_prioridade, add_tag, notify (stub)
    if (isset($acao['set_status'])){
      $st = (string)$acao['set_status'];
      $s=$db->prepare("UPDATE hd_ticket SET status=? WHERE id=?");
      $s->bind_param('si',$st,$t['id']); $s->execute();
    }
    if (isset($acao['set_prioridade'])){
      $pr = (string)$acao['set_prioridade'];
      $s=$db->prepare("UPDATE hd_ticket SET prioridade=? WHERE id=?");
      $s->bind_param('si',$pr,$t['id']); $s->execute();
    }
    if (isset($acao['add_tag'])){
      $tag = (string)$acao['add_tag'];
      $s=$db->prepare("INSERT IGNORE INTO hd_ticket_tag(ticket_id,tag) VALUES(?,?)");
      $s->bind_param('is',$t['id'],$tag); $s->execute();
    }
    if (!empty($acao['notify'])){
      // stub de notificação: grava em log se existir
      if ($chk=$db->query("SHOW TABLES LIKE 'hd_ticket_log'")) {
        if ($chk->num_rows>0) {
          $j = json_encode($acao['notify'], JSON_UNESCAPED_UNICODE);
          $txt = "Notificação (stub) disparada pela regra #{$r['id']}: ".$j;
          $s=$db->prepare("INSERT INTO hd_ticket_log(ticket_id, autor, texto, created_at) VALUES(?, 'regra', ?, NOW())");
          $s->bind_param('is',$t['id'],$txt); $s->execute();
        }
      }
    }

    $aplicadas++;
  }
}

echo json_encode(['success'=>true,'rules'=>count($regras),'tickets'=>count($tickets),'applied'=>$aplicadas]);
