<?php
// pages/event_calendario.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function ymd($ts){ return date('Y-m-d', is_int($ts)?$ts:strtotime($ts)); }
function dt($s){ return new DateTime($s); }
function within($ts, $ini, $fim){ return ($ts >= $ini && $ts <= $fim); }

/* === Entrada (ano/mês) === */
$year = (int)($_GET['y'] ?? date('Y'));
$month= (int)($_GET['m'] ?? date('n'));
if($month<1||$month>12){ $month=date('n'); }
$first = new DateTime("$year-$month-01 00:00:00");
$startWin = (clone $first)->modify('first day of this month')->modify('last monday'); // abre na segunda anterior
$endWin   = (clone $first)->modify('last day of this month')->modify('next sunday');   // fecha no domingo seguinte

$prev = (clone $first)->modify('-1 month'); $next=(clone $first)->modify('+1 month');

/* === Dados auxiliares === */
$cals=[]; $cs=$conn->query("SELECT id,titulo,cor_hex FROM event_calendars WHERE published=1 ORDER BY ordering,titulo");
while($x=$cs->fetch_assoc()) $cals[$x['id']]=$x;

$rows=[]; // eventos no período (base)
$st=$conn->prepare("SELECT * FROM event_items WHERE status='publicado' AND (publish_up IS NULL OR publish_up<=?) AND (publish_down IS NULL OR publish_down>=?)");
$pu=$endWin->format('Y-m-d 23:59:59'); $pd=$startWin->format('Y-m-d 00:00:00');
$st->bind_param('ss',$pu,$pd); $st->execute(); $res=$st->get_result(); while($x=$res->fetch_assoc()) $rows[]=$x; $st->close();

/* === Expansor de RRULE simples === */
function parse_rrule($s){
  $out=[]; if(!$s) return $out;
  foreach(explode(';',$s) as $part){ $kv=explode('=',$part,2); if(count($kv)==2) $out[strtoupper(trim($kv[0]))]=strtoupper(trim($kv[1])); }
  return $out;
}
function weekday_to_num($abbr){ return ['SU'=>0,'MO'=>1,'TU'=>2,'WE'=>3,'TH'=>4,'FR'=>5,'SA'=>6][$abbr] ?? null; }

function expand_event($e, DateTime $winStart, DateTime $winEnd){
  $inst=[]; $start=dt($e['start_at']); $end = $e['end_at']?dt($e['end_at']):(clone $start);
  $ex = []; if(!empty($e['exdates_json'])){ $arr=json_decode($e['exdates_json'],true); if(is_array($arr)) foreach($arr as $d){ $ex[substr($d,0,10)]=true; } }

  $rr=parse_rrule($e['rrule']);
  if(!$rr){ // não recorrente
    if($start <= $winEnd && $end >= $winStart){
      $inst[]=['start'=>$start,'end'=>$end];
    }
    return $inst;
  }

  $freq=$rr['FREQ']??'DAILY'; $interval=(int)($rr['INTERVAL']??1);
  $until = !empty($rr['UNTIL']) ? new DateTime($rr['UNTIL'].' 23:59:59') : null;
  $curStart=(clone $start); $curEnd=(clone $end);

  // helpers BY*
  $byday = !empty($rr['BYDAY']) ? array_map('trim', explode(',', $rr['BYDAY'])) : [];
  $bymd  = !empty($rr['BYMONTHDAY']) ? array_map('intval', explode(',', $rr['BYMONTHDAY'])) : [];
  $bym   = !empty($rr['BYMONTH']) ? array_map('intval', explode(',', $rr['BYMONTH'])) : [];

  // limite de segurança para não travar
  $maxIter=2000; $iter=0;

  while($iter++ < $maxIter){
    if($until && $curStart > $until) break;
    if($curStart > $winEnd) break;

    $ok=true;
    if($bym && !in_array((int)$curStart->format('n'), $bym, true)) $ok=false;
    if($bymd && !in_array((int)$curStart->format('j'), $bymd, true)) $ok=false;
    if($byday){
      $wdx=(int)$curStart->format('w'); // 0..6
      $abbr=['SU','MO','TU','WE','TH','FR','SA'][$wdx];
      if(!in_array($abbr,$byday,true)) $ok=false;
    }

    $keyEx=$curStart->format('Y-m-d');
    if($ok && empty($ex[$keyEx]) && $curStart <= $winEnd && $curEnd >= $winStart){
      $inst[]=['start':clone $curStart,'end':clone $curEnd];
    }

    // próximo
    switch($freq){
      case 'DAILY':
        $curStart->modify("+{$interval} day"); $curEnd->modify("+{$interval} day"); break;
      case 'WEEKLY':
        $curStart->modify("+{$interval} week"); $curEnd->modify("+{$interval} week"); break;
      case 'MONTHLY':
        $curStart->modify("+{$interval} month"); $curEnd->modify("+{$interval} month"); break;
      case 'YEARLY':
      default:
        $curStart->modify("+{$interval} year"); $curEnd->modify("+{$interval} year"); break;
    }
  }
  return $inst;
}

/* === Monta instâncias visíveis === */
$instances=[];
foreach($rows as $e){
  $exp = expand_event($e, $startWin, $endWin);
  if(!$exp){ // sem RRULE
    if(dt($e['start_at']) <= $endWin && dt($e['end_at'] ?: $e['start_at']) >= $startWin){
      $instances[]=['e'=>$e,'start'=>dt($e['start_at']),'end'=>dt($e['end_at'] ?: $e['start_at'])];
    }
  }else{
    foreach($exp as $k=>$occ){
      $instances[]=['e'=>$e,'start'=>$occ['start'],'end'=>$occ['end']];
    }
  }
}

/* === Aniversários (opt-in) === */
$birth=[]; // array por dia
$q = "
 SELECT u.id, u.nome_completo, u.data_nascimento
   FROM usuarios u
   LEFT JOIN user_prefs p ON p.user_id=u.id
  WHERE u.ativo=1
    AND (p.mostrar_aniversario IS NULL OR p.mostrar_aniversario=1)
    AND u.data_nascimento IS NOT NULL
";
if($r=$conn->query($q)){
  while($u=$r->fetch_assoc()){
    $d=DateTime::createFromFormat('Y-m-d',$u['data_nascimento']);
    if(!$d) continue;
    $bday=(clone $d)->setDate($year,$month,(int)$d->format('d'));
    if($bday >= $startWin && $bday <= $endWin){
      $birth[$bday->format('Y-m-d')][]=$u;
    }
  }
}

/* === grade calendar === */
include_once ROOT_PATH.'/system/includes/head.php';
include_once ROOT_PATH.'/system/includes/navbar.php';
?>
<style>
.cal-wrap{background:var(--surface);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);padding:12px}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}
.cal-cell{min-height:110px;border:1px solid var(--border);border-radius:10px;padding:6px;position:relative;background:#fff}
.cal-head{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;margin-bottom:6px}
.cal-head div{font-weight:600;text-align:center}
.cal-date{position:absolute;top:6px;right:8px;color:#6b7280;font-size:12px}
.tag{display:block;margin-top:18px;padding:4px 6px;border-radius:8px;font-size:12px;line-height:1.2;color:#0f172a;border:1px solid #e5e7eb;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tag .dot{display:inline-block;width:8px;height:8px;border-radius:999px;margin-right:6px}
.born{background:#fff7ed;border-color:#fed7aa;color:#9a3412}
.navcal a{margin-right:8px}
</style>

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12">
    <h1 class="page-header">Calendário — <?=strftime('%B de %Y', $first->getTimestamp())?></h1>
    <div class="navcal">
      <a class="btn btn-default" href="?y=<?=$prev->format('Y')?>&m=<?=$prev->format('n')?>">◀ <?=$prev->format('m/Y')?></a>
      <a class="btn btn-default" href="?y=<?=date('Y')?>&m=<?=date('n')?>">Hoje</a>
      <a class="btn btn-default" href="?y=<?=$next->format('Y')?>&m=<?=$next->format('n')?>"><?=$next->format('m/Y')?> ▶</a>
      <a class="btn btn-primary" href="<?= BASE_URL.'/pages/event_editar.php'?>">+ Novo evento</a>
      <a class="btn btn-default" href="<?= BASE_URL.'/pages/event_listar.php'?>">Lista</a>
    </div>
  </div></div>

  <div class="cal-head">
    <div>Seg</div><div>Ter</div><div>Qua</div><div>Qui</div><div>Sex</div><div>Sáb</div><div>Dom</div>
  </div>
  <div class="cal-wrap">
    <div class="cal-grid">
      <?php
      $cur=(clone $startWin);
      $end=(clone $endWin);
      while($cur <= $end){
        $isOtherMonth = ((int)$cur->format('n') !== $month);
        echo '<div class="cal-cell" style="background:'.($isOtherMonth?'#f9fafb':'#fff').'">';
        echo '<span class="cal-date">'. $cur->format('d/m') .'</span>';

        // Eventos do dia
        foreach($instances as $it){
          if($it['start']->format('Y-m-d') === $cur->format('Y-m-d')){
            $cal = $it['e']['calendario_id'] ? ($cals[$it['e']['calendario_id']]['cor_hex'] ?? '#0ea5e9') : '#0ea5e9';
            $time = ((int)$it['e']['dia_todo']===1) ? 'Dia todo' : $it['start']->format('H:i');
            echo '<span class="tag" title="'.h($it['e']['titulo']).'"><span class="dot" style="background:'.h($cal).'"></span>'.h($time).' · '.h($it['e']['titulo']).'</span>';
          }
        }

        // Aniversários do dia
        $key=$cur->format('Y-m-d');
        if(!empty($birth[$key])){
          foreach($birth[$key] as $u){
            echo '<span class="tag born" title="Aniversário"><span class="dot" style="background:#f59e0b"></span>🎂 '.h($u['nome_completo']).'</span>';
          }
        }

        echo '</div>';
        $cur->modify('+1 day');
      }
      ?>
    </div>
  </div>
</div></div>

<?php include_once ROOT_PATH.'/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'/system/includes/footer.php'; ?>
