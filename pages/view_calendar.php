<?php
// pages/view_calendar.php
// Calendário mensal (Mozart UI) — tolerante a esquemas diferentes

ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
if (session_status()===PHP_SESSION_NONE) session_start();
require_once ROOT_PATH . '/system/includes/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

proteger_pagina();
if (empty($_SESSION['user_id'])) die('Erro: Usuário não autenticado.');
$user_id = (int)$_SESSION['user_id'];

/* ==================== helpers ==================== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function tbl_exists(mysqli $conn,string $t): bool {
  $t=$conn->real_escape_string($t);
  if($rs=$conn->query("SHOW TABLES LIKE '$t'")) return (bool)$rs->num_rows;
  return false;
}
function col_exists(mysqli $conn,string $t,string $c): bool {
  $t=$conn->real_escape_string($t); $c=$conn->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  if($rs=$conn->query($sql)) return (bool)$rs->num_rows;
  return false;
}
function detect_col(mysqli $conn,string $t,array $cands){
  foreach($cands as $c){ if(col_exists($conn,$t,$c)) return $c; } return null;
}

/* ===== contexto do usuário (grupos, perfil, role) ===== */
function get_user_ctx(mysqli $conn,int $uid): array {
  $groups=[];
  $ug='usuarios_grupos';
  if(!tbl_exists($conn,$ug)){
    foreach(['usuario_grupos','user_groups'] as $alt){ if(tbl_exists($conn,$alt)){ $ug=$alt; break; } }
  }
  if(tbl_exists($conn,$ug)){
    $col_gid = detect_col($conn,$ug,['group_id','grupo_id']);
    $col_uid = detect_col($conn,$ug,['user_id','usuario_id','id_usuario']);
    if($col_gid && $col_uid){
      if($st=$conn->prepare("SELECT `$col_gid` AS gid FROM `$ug` WHERE `$col_uid`=?")){
        $st->bind_param('i',$uid); $st->execute(); $r=$st->get_result();
        while($x=$r->fetch_assoc()) $groups[]=(int)$x['gid'];
        $st->close();
      }
    }
  }
  $perfil=0; $role='';
  if(tbl_exists($conn,'usuarios')){
    $perfil_col = detect_col($conn,'usuarios',['perfil_id','perfil']);
    $role_col   = detect_col($conn,'usuarios',['nivel_acesso','nivel']);
    $sel="id"; $sel .= $perfil_col? ", `$perfil_col` AS perfil" : ", 0 AS perfil";
    $sel .= $role_col?   ", `$role_col` AS role"     : ", '' AS role";
    if($st=$conn->prepare("SELECT $sel FROM usuarios WHERE id=? LIMIT 1")){
      $st->bind_param('i',$uid); $st->execute(); $r=$st->get_result();
      if($u=$r->fetch_assoc()){ $perfil=(int)($u['perfil']??0); $role=(string)($u['role']??''); }
      $st->close();
    }
  }
  return ['groups'=>$groups,'perfil'=>$perfil,'role'=>$role];
}

/* ==================== parâmetros ==================== */
$today     = new DateTime('today');
$ym        = isset($_GET['ym']) ? $_GET['ym'] : $today->format('Y-m'); // YYYY-MM
$q         = trim($_GET['q'] ?? '');
$onlyMine  = !empty($_GET['meus']);

if(!preg_match('/^\d{4}\-\d{2}$/',$ym)) $ym = $today->format('Y-m');
$viewDate = DateTime::createFromFormat('Y-m-d', $ym.'-01');
if(!$viewDate) $viewDate = new DateTime('first day of this month');
$firstDay = clone $viewDate; $firstDay->modify('first day of this month');
$lastDay  = clone $firstDay; $lastDay->modify('last day of this month');

$gridStart = clone $firstDay; $gridStart->modify('last monday');   // semana inicia seg
$gridEnd   = clone $lastDay;  $gridEnd->modify('next monday');

$prev = clone $firstDay; $prev->modify('-1 month');
$next = clone $firstDay; $next->modify('+1 month');

/* ==================== consulta de eventos ==================== */
// mapeamento "padrão", mas tolerante
$T = [
  'main'   => 'event_items',
  'g'      => 'event_item_groups',
  'pf'     => 'event_item_profiles',
  'r'      => 'event_item_roles',
  'cat'    => 'event_categories',
  'key'    => 'event_id', // ajusta se necessário
  'cols'   => [
    'id'      => 'id',
    'titulo'  => 'titulo',
    'desc'    => 'descricao_curta',
    'start'   => 'start_at',
    'end'     => 'end_at',
    'dia'     => 'dia_todo',
    'status'  => 'status',
    'pubup'   => 'publish_up',
    'pubdn'   => 'publish_down',
    'feat'    => 'featured',
    'public'  => 'acesso_publico',
    'cat_id'  => 'categoria_id',
    'tipo'    => 'tipo',
    'created' => 'created_at',
    'by'      => 'created_by',
  ]
];

$EVENTS = [];

if (tbl_exists($conn,$T['main'])) {

  // auto-detect de colunas alternativas
  foreach ($T['cols'] as $k=>$cand) {
    if (!col_exists($conn,$T['main'],$cand)) {
      $alt = [
        'titulo'=>['nome','title'],
        'desc'  =>['resumo','intro','descricao'],
        'start' =>['inicio','start'],
        'end'   =>['fim','end'],
        'dia'   =>['all_day','diatodo'],
        'status'=>['situacao','state'],
        'pubup' =>['publicacao_inicio'],
        'pubdn' =>['publicacao_fim'],
        'feat'  =>['destaque'],
        'public'=>['publico','is_public'],
        'cat_id'=>['categoria','categoriaId'],
        'tipo'  =>['kind','icone'],
        'created'=>['data_criacao'],
        'by'    =>['autor_id','user_id'],
      ][$k] ?? [];
      foreach ($alt as $c) { if (col_exists($conn,$T['main'],$c)) { $T['cols'][$k]=$c; break; } }
    }
  }
  if (tbl_exists($conn,$T['g'])) {
    $det = detect_col($conn,$T['g'],['event_id','item_id','evento_id']);
    if ($det) $T['key']=$det;
  }

  // intervalo SQL
  $startSQL = $gridStart->format('Y-m-d 00:00:00');
  $endSQL   = $gridEnd->format('Y-m-d 00:00:00');
  $now      = date('Y-m-d H:i:s');

  $ctx = get_user_ctx($conn,$user_id);
  $groups = $ctx['groups'];
  $perfil = (int)$ctx['perfil'];
  $role   = $conn->real_escape_string($ctx['role']);

  $w = [];
  $w[] = "t.`{$T['cols']['status']}`='publicado'";
  $w[] = "(t.`{$T['cols']['pubup']}` IS NULL OR t.`{$T['cols']['pubup']}` <= '$now')";
  $w[] = "(t.`{$T['cols']['pubdn']}` IS NULL OR t.`{$T['cols']['pubdn']}` >= '$now')";
  // interseção com janela do grid
  $w[] = "(t.`{$T['cols']['start']}` < '$endSQL' AND (t.`{$T['cols']['end']}` IS NULL OR t.`{$T['cols']['end']}` >= '$startSQL'))";

  // busca
  if ($q!=='') {
    $qq = $conn->real_escape_string($q);
    $tcol = $T['cols']['titulo'];
    $dcol = $T['cols']['desc'];
    if (col_exists($conn,$T['main'],$dcol))
      $w[] = "(t.`$tcol` LIKE '%$qq%' OR t.`$dcol` LIKE '%$qq%')";
    else
      $w[] = "(t.`$tcol` LIKE '%$qq%')";
  }

  // criados por mim (opcional)
  if ($onlyMine && col_exists($conn,$T['main'],$T['cols']['by'])) {
    $w[] = "t.`{$T['cols']['by']}` = $user_id";
  }

  // permissões
  $perms = ["t.`{$T['cols']['public']}`=1"];
  $groups_sql = $groups ? implode(',', array_map('intval',$groups)) : 'NULL';
  if (tbl_exists($conn,$T['g']) && $groups_sql!=='NULL') $perms[] = "EXISTS(SELECT 1 FROM `{$T['g']}` g WHERE g.`{$T['key']}`=t.id AND g.group_id IN ($groups_sql))";
  if (tbl_exists($conn,$T['pf'])) $perms[] = "EXISTS(SELECT 1 FROM `{$T['pf']}` p WHERE p.`{$T['key']}`=t.id AND p.perfil_id=$perfil)";
  if (tbl_exists($conn,$T['r']))  $perms[] = "EXISTS(SELECT 1 FROM `{$T['r']}`  r WHERE r.`{$T['key']}`=t.id AND r.role='$role')";
  $w[] = '('.implode(' OR ',$perms).')';

  $where = implode(' AND ',$w);

  // ===== SELECT tolerante =====
  $hasDesc = col_exists($conn,$T['main'],$T['cols']['desc']);
  $hasTipo = col_exists($conn,$T['main'],$T['cols']['tipo']);
  $hasDia  = col_exists($conn,$T['main'],$T['cols']['dia']);
  $hasCat  = col_exists($conn,$T['main'],$T['cols']['cat_id']);

  $sel = "t.id,
          t.`{$T['cols']['titulo']}` AS titulo,".
         ($hasDesc ? " t.`{$T['cols']['desc']}` AS resumo," : " '' AS resumo,") .
         " t.`{$T['cols']['start']}` AS inicio,".
         " t.`{$T['cols']['end']}`   AS fim,".
         ($hasDia  ? " t.`{$T['cols']['dia']}`  AS dia_todo," : " 1 AS dia_todo,") .
         ($hasCat  ? " t.`{$T['cols']['cat_id']}` AS categoria_id," : " 0 AS categoria_id,") .
         ($hasTipo ? " t.`{$T['cols']['tipo']}` AS tipo" : " '' AS tipo");

  $sql = "SELECT $sel
          FROM `{$T['main']}` t
          WHERE $where
          ORDER BY COALESCE(t.`{$T['cols']['start']}`,'9999-12-31') ASC";

  if ($rs=$conn->query($sql)) {
    while($r=$rs->fetch_assoc()){
      $ini = $r['inicio'] ? new DateTime($r['inicio']) : null;
      $fim = $r['fim']    ? new DateTime($r['fim'])    : null;
      if(!$ini) continue;

      $start = max($ini->getTimestamp(), $gridStart->getTimestamp());
      $end   = $fim ? min($fim->getTimestamp(), $gridEnd->getTimestamp()-1) : $ini->getTimestamp();

      for($ts = $start; $ts <= $end; $ts = strtotime('+1 day', $ts)){
        $key = date('Y-m-d', $ts);
        $EVENTS[$key][] = [
          'id'    => (int)$r['id'],
          'titulo'=> (string)$r['titulo'],
          'resumo'=> (string)($r['resumo'] ?? ''),
          'inicio'=> $r['inicio'],
          'fim'   => $r['fim'],
          'dia'   => (int)$r['dia_todo'],
          'tipo'  => (string)($r['tipo'] ?? ''),
          'cat'   => (int)($r['categoria_id'] ?? 0),
        ];
      }
    }
  }
}

/* ícone por tipo/categoria texto */
function cat_icon($tipoOrCatName){
  $s = mb_strtolower(trim((string)$tipoOrCatName));
  if ($s==='birthday' || strpos($s,'aniver')!==false) return '🎂';
  if (strpos($s,'feriad')!==false || $s==='holiday')   return '🏖️';
  if (strpos($s,'rh')!==false)                         return '🧑‍💼';
  if (strpos($s,'trein')!==false)                      return '📚';
  if (strpos($s,'campanha')!==false || strpos($s,'marketing')!==false) return '📣';
  return '📅';
}

/* opcional: mapa id->nome de categoria (só se existir) */
$CATNAMES = [];
if (tbl_exists($conn,$T['cat'])) {
  $title = detect_col($conn,$T['cat'],['titulo','nome','title']) ?: 'titulo';
  $sqlc = "SELECT id, `$title` AS t FROM `{$T['cat']}`";
  if ($rc=$conn->query($sqlc)) while($x=$rc->fetch_assoc()) $CATNAMES[(int)$x['id']]=$x['t'];
}

include_once ROOT_PATH . '/system/includes/user_head.php';
?>
<style>
.cal-wrap{ padding:12px; }
.cal-controls{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:space-between; margin-bottom:10px; }
.cal-controls .nav{ display:flex; gap:8px; align-items:center; }
.cal-controls .filters{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.calendar{ background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; }
.cal-head{ display:grid; grid-template-columns:repeat(7,1fr); border-bottom:1px solid var(--border); background:linear-gradient(180deg, rgba(14,165,233,.08), transparent); }
.cal-head div{ padding:10px; font-weight:700; text-align:center; }
.cal-grid{ display:grid; grid-template-columns:repeat(7,1fr); }
.day{ min-height:120px; border-right:1px solid var(--border); border-bottom:1px solid var(--border); padding:8px; position:relative; }
.day:nth-child(7n){ border-right:0; }
.day .dnum{ font-weight:700; font-size:13px; color:var(--text-muted); }
.day.out{ background:rgba(148,163,184,.08); color:var(--text-muted); }
.day.today{ outline:2px solid var(--brand); outline-offset:-2px; border-radius:10px; }
.ev{ display:flex; align-items:center; gap:6px; margin-top:6px; font-size:12px; padding:4px 6px; border:1px solid var(--border); border-radius:8px; background:var(--bg); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.ev .ico{ width:16px; height:16px; display:inline-flex; align-items:center; justify-content:center; }
.more{ margin-top:6px; font-size:12px; color:var(--brand); cursor:pointer; }
.cal-legend{ display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
.cal-legend .chip{ font-size:12px; }
.popup-day{ position:fixed; inset:0; display:none; align-items:center; justify-content:center; background:rgba(0,0,0,.35); padding:16px; z-index:100; }
.popup-day .box{ max-width:600px; width:100%; background:var(--surface); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow); padding:16px; }
.popup-day .list{ display:flex; flex-direction:column; gap:8px; max-height:60vh; overflow:auto; }
</style>

<body>
  <?php include_once ROOT_PATH . '/system/includes/user_navbar.php'; ?>

  <main class="feed" aria-live="polite">
    <section class="card">
      <div class="head">
        <div class="title">📅 <span>Calendário</span></div>
        <div class="meta">
          <span class="chip">Mensal</span>
          <?php if($q!==''): ?><span class="chip warn">Busca: <?= h($q) ?></span><?php endif; ?>
        </div>
      </div>

      <div class="body cal-wrap">
        <div class="cal-controls">
          <div class="nav">
            <a class="btn" href="?ym=<?= $prev->format('Y-m') ?>&q=<?= urlencode($q) ?>&meus=<?= $onlyMine?1:0 ?>">⟵ Mês anterior</a>
            <span class="chip"><strong><?= date('F \d\e Y', $firstDay->getTimestamp()) ?></strong></span>
            <a class="btn" href="?ym=<?= $next->format('Y-m') ?>&q=<?= urlencode($q) ?>&meus=<?= $onlyMine?1:0 ?>">Próximo mês ⟶</a>
          </div>
          <form class="filters" method="get">
            <input type="month" class="form-control" name="ym" value="<?= h($firstDay->format('Y-m')) ?>">
            <input type="text" class="form-control" name="q" placeholder="Buscar eventos…" value="<?= h($q) ?>" style="min-width:220px">
            <label class="chip"><input type="checkbox" name="meus" value="1" <?= $onlyMine?'checked':'' ?>> Meus</label>
            <button class="btn primary" type="submit">Aplicar</button>
            <a class="btn ghost" href="?">Limpar</a>
          </form>
        </div>

        <div class="calendar">
          <div class="cal-head">
            <?php foreach(['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'] as $d) echo '<div>'.$d.'</div>'; ?>
          </div>
          <div class="cal-grid">
            <?php
              $cur = clone $gridStart;
              $end = clone $gridEnd;
              while ($cur < $end):
                $ymCur = $cur->format('Y-m');
                $isOut = ($ymCur !== $firstDay->format('Y-m'));
                $isToday = ($cur->format('Y-m-d') === (new DateTime())->format('Y-m-d'));
                $key = $cur->format('Y-m-d');
                $evs = $EVENTS[$key] ?? [];
            ?>
              <div class="day <?= $isOut?'out':'' ?> <?= $isToday?'today':'' ?>" data-day="<?= h($key) ?>">
                <div class="dnum"><?= (int)$cur->format('j') ?></div>
                <?php
                  $maxShow = 3; $shown=0;
                  foreach($evs as $e){
                    if($shown>=$maxShow) break;
                    $ico = '📅';
                    if (!empty($e['tipo'])) $ico = cat_icon($e['tipo']);
                    elseif (!empty($e['cat']) && isset($CATNAMES[$e['cat']])) $ico = cat_icon($CATNAMES[$e['cat']]);
                    $tit = $e['titulo'];
                    $lblHora = (!$e['dia'] && $e['inicio']) ? date('H:i', strtotime($e['inicio'])) : '';
                    echo '<div class="ev" title="'.h($tit).'"><span class="ico">'.$ico.
                         '</span><span>'.h(($lblHora?$lblHora.' · ':'').$tit).'</span></div>';
                    $shown++;
                  }
                  if (count($evs) > $shown) {
                    $rest = count($evs) - $shown;
                    echo '<div class="more" data-open-day="'.h($key).'">+'.$rest.' evento'.($rest>1?'s':'').'</div>';
                  }
                ?>
              </div>
            <?php
                $cur->modify('+1 day');
              endwhile;
            ?>
          </div>
        </div>

        <div class="cal-legend">
          <span class="chip"><span class="ico">🎂</span> Aniversários</span>
          <span class="chip"><span class="ico">🏖️</span> Feriados</span>
          <span class="chip"><span class="ico">🧑‍💼</span> RH</span>
          <span class="chip"><span class="ico">📚</span> Treinamentos</span>
          <span class="chip"><span class="ico">📣</span> Campanhas</span>
        </div>
      </div>
    </section>
  </main>

  <!-- Popup de dia -->
  <div class="popup-day" id="popupDay">
    <div class="box">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
        <h3 id="popTitle" style="margin:0">Eventos do dia</h3>
        <button class="btn" onclick="closeDay()">Fechar</button>
      </div>
      <div class="list" id="popList"></div>
    </div>
  </div>

  <?php include_once ROOT_PATH . '/system/includes/user_navbar_right.php'; ?>
  <?php include_once ROOT_PATH . '/system/includes/user_code_footer.php'; ?>
  <?php include_once ROOT_PATH . '/system/includes/user_footer.php'; ?>

  <script>
  (function(){
    var grid = document.querySelector('.cal-grid');
    var popup = document.getElementById('popupDay');
    var popTitle = document.getElementById('popTitle');
    var popList = document.getElementById('popList');
    var DATA = <?= json_encode($EVENTS, JSON_UNESCAPED_UNICODE) ?>;

    function fmtHora(dt){
      try{
        if(!dt) return '';
        var d = new Date(dt.replace(' ', 'T'));
        if (isNaN(d)) return '';
        return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
      }catch(_e){ return ''; }
    }
    function esc(s){ var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

    function openDay(day){
      var items = DATA[day] || [];
      var pp = day.split('-'); popTitle.textContent = 'Eventos de ' + pp[2]+'/'+pp[1]+'/'+pp[0];
      popList.innerHTML = '';
      if (!items.length) { popList.innerHTML='<div class="muted">Nenhum evento.</div>'; }
      else {
        items.forEach(function(e){
          var hora = e.dia==1 ? '' : (fmtHora(e.inicio) ? fmtHora(e.inicio)+' · ' : '');
          var el = document.createElement('div');
          el.className = 'ev';
          el.innerHTML = '<span class="ico">📅</span><span>'+hora+esc(e.titulo)+'</span>';
          popList.appendChild(el);
        });
      }
      popup.style.display='flex';
    }
    window.closeDay = function(){ popup.style.display='none'; };

    grid.addEventListener('click', function(ev){
      var more = ev.target.closest('.more');
      if (more) { openDay(more.getAttribute('data-open-day')); }
    });
    popup.addEventListener('click', function(e){ if(e.target===popup) closeDay(); });
  })();
  </script>
</body>
</html>
