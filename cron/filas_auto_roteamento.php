<?php
/**
 * Auto-roteamento de OS para Filas/Técnicos
 * - Seleciona filas com auto_pull=1
 * - Puxa até pull_lote OS em triagem (por fila) e atribui via round-robin ponderado (peso) entre técnicos elegíveis
 * - Elegibilidade: ativo, dentro do turno, sem indisponibilidade no momento, não estourou limite_os (se definido)
 * - Skills: técnico deve atender todos os requisitos da OS (nivel >= requerido)
 * - Concorrência: usa GET_LOCK por fila para evitar corrida entre cron workers
 *
 * Requer as tabelas entregues no módulo Técnicos & Filas + as tabelas de OS abaixo (criadas aqui se necessário).
 */
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../../../config.php';
require_once ROOT_PATH.'/system/config/connect.php';
$dbc = $conn ?? null; if(!$dbc) { fwrite(STDERR, "Sem conexão.\n"); exit(1); }

/* -------------------- MIGRAÇÃO MÍNIMA OS -------------------- */
$dbc->query("CREATE TABLE IF NOT EXISTS moz_os (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(200) NOT NULL,
  descricao TEXT NULL,
  prioridade ENUM('Baixa','Normal','Alta','Crítica') NOT NULL DEFAULT 'Normal',
  status ENUM('NOVA','TRIAGEM','NA_FILA','ATRIBUIDA','EM_ANDAMENTO','FECHADA','CANCELADA') NOT NULL DEFAULT 'TRIAGEM',
  fila_id BIGINT UNSIGNED NULL,
  tecnico_id BIGINT UNSIGNED NULL,
  solicitante VARCHAR(160) NULL,
  entidade VARCHAR(160) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_stat (status),
  KEY idx_fila (fila_id),
  KEY idx_tecnico (tecnico_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$dbc->query("CREATE TABLE IF NOT EXISTS moz_os_skill (
  os_id BIGINT UNSIGNED NOT NULL,
  skill_id BIGINT UNSIGNED NOT NULL,
  nivel_min TINYINT UNSIGNED NOT NULL DEFAULT 3,
  PRIMARY KEY (os_id, skill_id),
  KEY idx_skill (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$dbc->query("CREATE TABLE IF NOT EXISTS moz_os_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  os_id BIGINT UNSIGNED NOT NULL,
  evento VARCHAR(120) NOT NULL,
  detalhe TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_os (os_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* Estado por fila para round-robin */
$dbc->query("CREATE TABLE IF NOT EXISTS moz_fila_state (
  fila_id BIGINT UNSIGNED PRIMARY KEY,
  rr_cursor INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* -------------------- HELPERS -------------------- */
function now_in_turno(mysqli $db, array $tec): bool {
  // Por ora: 8x5 = seg-sex 08:00–17:59, 24x7 = sempre, 12x36 = sempre (pode ser detalhado depois com escala)
  if(($tec['turno'] ?? '8x5') === '24x7') return true;
  if(($tec['turno'] ?? '8x5') === '12x36') return true; // simplificado
  $res = $db->query("SELECT (WEEKDAY(NOW())<=4 AND TIME(NOW()) BETWEEN '08:00:00' AND '17:59:59') ok")->fetch_assoc();
  return (bool)$res['ok'];
}
function has_current_indisp(mysqli $db, int $tecnico_id): bool {
  $q=$db->prepare("SELECT 1 FROM moz_indisp_tecnico WHERE tecnico_id=? AND NOW() BETWEEN ini AND fim LIMIT 1");
  $q->bind_param('i',$tecnico_id); $q->execute(); $q->bind_result($one); $ok=$q->fetch(); $q->close();
  return (bool)$ok;
}
function count_open_os(mysqli $db, int $tecnico_id): int {
  $q=$db->prepare("SELECT COUNT(*) FROM moz_os WHERE tecnico_id=? AND status IN ('ATRIBUIDA','EM_ANDAMENTO','NA_FILA')");
  $q->bind_param('i',$tecnico_id); $q->execute(); $q->bind_result($n); $q->fetch(); $q->close();
  return (int)$n;
}
function tecnico_meets_skills(mysqli $db, int $tec_id, int $os_id): bool {
  // todo requisito da OS deve ser atendido por uma skill do técnico com nivel >=
  $sql = "SELECT COUNT(*)=0 AS ok
          FROM moz_os_skill r
          LEFT JOIN moz_tecnico_skill ts ON ts.tecnico_id=? AND ts.skill_id=r.skill_id
          WHERE r.os_id=? AND (ts.nivel IS NULL OR ts.nivel < r.nivel_min)";
  $st=$db->prepare($sql); $st->bind_param('ii',$tec_id,$os_id); $st->execute(); $st->bind_result($ok); $st->fetch(); $st->close();
  return (bool)$ok;
}
function fila_required_skills(mysqli $db, int $fila_id): array {
  $res=$db->query("SELECT skill_id,nivel_min FROM moz_fila_skill WHERE fila_id=".$fila_id);
  $m=[]; if($res) while($r=$res->fetch_assoc()) $m[(int)$r['skill_id']]=(int)$r['nivel_min']; return $m;
}
function os_has_skills(mysqli $db,int $os_id): bool {
  $r=$db->query("SELECT 1 FROM moz_os_skill WHERE os_id=".$os_id." LIMIT 1"); return $r && $r->num_rows>0;
}
function os_meets_fila(mysqli $db,int $os_id,int $fila_id): bool {
  // Se a fila tiver requisitos de skill, a OS deve ter requisitos compatíveis (ou nenhum requisito na fila)
  $req = fila_required_skills($db,$fila_id);
  if(!$req) return true;
  // Checa se a OS possui pelo menos esses requisitos (nível da OS pode ser menor/igual ao exigido)
  $sql="SELECT COUNT(*) FROM moz_fila_skill fs
        LEFT JOIN moz_os_skill osr ON osr.os_id=? AND osr.skill_id=fs.skill_id
        WHERE fs.fila_id=? AND osr.skill_id IS NULL";
  $st=$db->prepare($sql); $st->bind_param('ii',$os_id,$fila_id); $st->execute(); $st->bind_result($missing); $st->fetch(); $st->close();
  return ((int)$missing)===0;
}
function log_os(mysqli $db,int $os_id,string $evt,string $det=''){
  $st=$db->prepare("INSERT INTO moz_os_log (os_id,evento,detalhe) VALUES (?,?,?)");
  $st->bind_param('iss',$os_id,$evt,$det); $st->execute(); $st->close();
}

/* -------------------- CORE -------------------- */
// Filas habilitadas
$filas=[]; $r=$dbc->query("SELECT * FROM moz_fila WHERE ativo=1 AND auto_pull=1 ORDER BY id");
if($r) while($x=$r->fetch_assoc()) $filas[]=$x;

$total_assigned=0; $report=[];

foreach($filas as $fila){
  $fila_id=(int)$fila['id'];
  // Lock por fila para evitar corrida
  $lk=$dbc->query("SELECT GET_LOCK(CONCAT('auto_route_fila_',".$fila_id."), 5) AS got")->fetch_assoc();
  if(!$lk || !$lk['got']) { $report[]="Fila {$fila['nome']}: lock indisponível"; continue; }

  try {
    // Carrega técnicos desta fila
    $tecs=[]; 
    $sql="SELECT t.*, ft.prioridade, ft.peso, ft.limite_os
          FROM moz_fila_tecnico ft
          JOIN moz_tecnico t ON t.id=ft.tecnico_id
          WHERE ft.fila_id=$fila_id AND t.ativo=1
          ORDER BY (t.online DESC), t.nome";
    $rt=$dbc->query($sql); if($rt) while($x=$rt->fetch_assoc()) $tecs[]=$x;
    if(!$tecs){ $report[]="Fila {$fila['nome']}: sem técnicos"; $dbc->query("SELECT RELEASE_LOCK(CONCAT('auto_route_fila_',".$fila_id."))"); continue; }

    // Round-robin ponderado: constrói vetor expandido por peso
    $rr=[]; foreach($tecs as $t){ for($i=0;$i<max(1,(int)$t['peso']);$i++) $rr[]=$t; }
    // Cursor
    $cs=$dbc->query("SELECT rr_cursor FROM moz_fila_state WHERE fila_id=$fila_id")->fetch_assoc();
    $cursor = $cs ? (int)$cs['rr_cursor'] : 0;

    // Busca OS candidatas: se a OS já tem fila_id=esta fila e status TRIAGEM|NA_FILA; 
    // se não tiver fila, também permite (fila “puxa” por requisito)
    $lote = (int)$fila['pull_lote'];
    $qos = $dbc->prepare("
      SELECT id FROM moz_os
      WHERE status IN ('TRIAGEM','NA_FILA')
        AND (fila_id IS NULL OR fila_id=?)
      ORDER BY prioridade DESC, created_at ASC
      LIMIT ?
    ");
    $qos->bind_param('ii',$fila_id,$lote);
    $qos->execute(); $rs=$qos->get_result(); $os_ids=array_column($rs->fetch_all(MYSQLI_ASSOC),'id'); $qos->close();
    if(!$os_ids){ $report[]="Fila {$fila['nome']}: sem OS candidatas"; $dbc->query("INSERT INTO moz_fila_state (fila_id,rr_cursor) VALUES ($fila_id,$cursor) ON DUPLICATE KEY UPDATE rr_cursor=VALUES(rr_cursor)"); $dbc->query("SELECT RELEASE_LOCK(CONCAT('auto_route_fila_',".$fila_id."))"); continue; }

    $assigned_here=0;

    foreach($os_ids as $os_id){
      // Se a fila tem requisitos, verifique compatibilidade da OS
      if(!os_meets_fila($dbc,$os_id,$fila_id)) continue;

      // Gira até encontrar técnico elegível
      $tries = count($rr);
      $chosen = null;
      while($tries--){
        $t = $rr[$cursor % count($rr)];
        $cursor++;

        // Disponibilidade
        if(!now_in_turno($dbc,$t)) continue;
        if(has_current_indisp($dbc,(int)$t['id'])) continue;

        // Limite individual
        $lim=$t['limite_os']; if($lim!==null && $lim!==''){
          if(count_open_os($dbc,(int)$t['id']) >= (int)$lim) continue;
        }

        // Skills
        if(os_has_skills($dbc,$os_id) && !tecnico_meets_skills($dbc,(int)$t['id'],$os_id)) continue;

        $chosen = $t; break;
      }

      if(!$chosen) continue; // ninguém elegível neste ciclo

      // Atribuir em transação
      $dbc->begin_transaction();
      try{
        // Verifica novamente status para evitar race
        $ck=$dbc->prepare("SELECT status FROM moz_os WHERE id=? FOR UPDATE");
        $ck->bind_param('i',$os_id); $ck->execute(); $ck->bind_result($st); $ck->fetch(); $ck->close();
        if(!in_array($st,['TRIAGEM','NA_FILA'])){ $dbc->rollback(); continue; }

        // Define fila (se não tinha), marca ATRIBUIDA e seta técnico
        $st1=$dbc->prepare("UPDATE moz_os SET fila_id=?, tecnico_id=?, status='ATRIBUIDA' WHERE id=?");
        $tid=(int)$chosen['id'];
        $st1->bind_param('iii',$fila_id,$tid,$os_id); $st1->execute(); $st1->close();

        log_os($dbc,$os_id,'ATRIBUICAO',"Fila={$fila['nome']} / Tecnico={$chosen['nome']}");

        $dbc->commit();
        $assigned_here++; $total_assigned++;
      } catch(Throwable $e){
        $dbc->rollback();
      }
    }

    // Persiste cursor
    $dbc->query("INSERT INTO moz_fila_state (fila_id,rr_cursor) VALUES ($fila_id,$cursor)
                 ON DUPLICATE KEY UPDATE rr_cursor=VALUES(rr_cursor)");
    $report[]="Fila {$fila['nome']}: atribuídas {$assigned_here}";
  } finally {
    $dbc->query("SELECT RELEASE_LOCK(CONCAT('auto_route_fila_',".$fila_id."))");
  }
}

/* -------------------- OUTPUT -------------------- */
$ts=date('Y-m-d H:i:s');
echo "[{$ts}] Auto-roteamento finalizado. Total atribuídas: {$total_assigned}\n";
foreach($report as $line) echo " - $line\n";
