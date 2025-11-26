<?php
// pages/feed_dicas.php  (somente Dicas) — chips de categoria + lazyload
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../config.php';
if (session_status()===PHP_SESSION_NONE) session_start();
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
proteger_pagina();

require_once ROOT_PATH.'/system/services/feed_service_dicas.php';
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

// Helpers simples
function tbl_exists($conn,$name){ $name=$conn->real_escape_string($name); $rs=$conn->query("SHOW TABLES LIKE '$name'"); return $rs? (bool)$rs->num_rows:false; }
function col_exists($conn,$table,$col){
  $t=$conn->real_escape_string($table); $c=$conn->real_escape_string($col);
  $rs=$conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1");
  return $rs? (bool)$rs->num_rows:false;
}
function fetch_categories($conn,$table){
  $items=[];
  if (!tbl_exists($conn,$table)) return $items;
  $title = col_exists($conn,$table,'titulo')?'titulo':(col_exists($conn,$table,'nome')?'nome':null);
  if(!$title) return $items;
  $pub = col_exists($conn,$table,'published')?'published':(col_exists($conn,$table,'publicado')?'publicado':null);
  $sql = "SELECT id, `$title` AS t FROM `$table`";
  if($pub) $sql .= " WHERE `$pub`=1";
  $sql .= " ORDER BY t";
  if ($rs=$conn->query($sql)) while($r=$rs->fetch_assoc()) $items[]=['id'=>(int)$r['id'],'t'=>$r['t']];
  return $items;
}

$user_id   = (int)($_SESSION['user_id'] ?? 0);
$categoria = isset($_GET['cat']) ? (int)$_GET['cat'] : null;
$limit     = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset    = isset($_GET['offset'])? (int)$_GET['offset']: 0;
$onlyFeat  = !empty($_GET['feat']);
$isAjax    = isset($_GET['ajax']) && $_GET['ajax']=='1';

$opts = [
  'limit'        => $limit,
  'offset'       => $offset,
  'categoria_id' => $categoria,
  'apenas_destaque' => $onlyFeat,
];

$data  = feed_dicas_fetch($conn, $user_id, $opts);
$items = $data['items'] ?? [];
$next  = $data['next_offset'] ?? null;

// categorias de dicas
$cats = fetch_categories($conn,'dicas_categories');

// Partial AJAX (somente cards)
if ($isAjax) {
  ob_start();
  if (!$items): ?>
    <article class="card"><div class="body"><span class="muted">Nenhuma dica encontrada.</span></div></article>
  <?php else: foreach($items as $it): ?>
    <article class="card">
      <div class="head">
        <div class="title">💡 <?= h($it['titulo']) ?></div>
        <div class="meta"><?php if(!empty($it['featured'])): ?><span class="badge ok">Destaque</span><?php endif; ?></div>
      </div>
      <div class="body">
        <?php if(!empty($it['resumo'])): ?><div class="muted"><?= h($it['resumo']) ?></div><?php endif; ?>
        <div class="actions">
          <a class="btn ghost" href="<?= BASE_URL.'/pages/dicas_editar.php?id='.(int)$it['id'] ?>">Abrir</a>
        </div>
      </div>
    </article>
  <?php endforeach; endif;
  echo ob_get_clean(); exit;
}

include_once ROOT_PATH.'/system/includes/user_head.php';
?>
<body>
  <?php include_once ROOT_PATH.'/system/includes/user_navbar.php'; ?>

  <main class="feed" aria-live="polite">
    <section class="card">
      <div class="head">
        <div class="title">💡 <span>Dicas</span></div>
        <div class="meta">
          <?php if ($categoria): ?><span class="chip">Categoria: #<?= (int)$categoria ?></span><?php endif; ?>
          <?php if ($onlyFeat): ?><span class="chip ok">Destaques</span><?php endif; ?>
        </div>
      </div>
      <div class="body filterbar">
        <span class="view">Categorias:</span>
        <a class="chip<?= !$categoria?' ok':'' ?>" href="?<?= http_build_query(array_filter(['cat'=>null,'feat'=>$onlyFeat?'1':null])) ?>">Todas</a>
        <?php foreach($cats as $c): ?>
          <?php $qs = ['cat'=>$c['id']]; if($onlyFeat) $qs['feat']='1'; ?>
          <a class="chip<?= ($categoria===$c['id'])?' ok':'' ?>" href="?<?= http_build_query($qs) ?>"><?= h($c['t']) ?></a>
        <?php endforeach; ?>
      </div>
    </section>

    <div id="feed-list">
      <?php if(!$items): ?>
        <article class="card"><div class="body"><span class="muted">Nenhuma dica encontrada.</span></div></article>
      <?php else: foreach($items as $it): ?>
        <article class="card">
          <div class="head">
            <div class="title">💡 <?= h($it['titulo']) ?></div>
            <div class="meta"><?php if(!empty($it['featured'])): ?><span class="badge ok">Destaque</span><?php endif; ?></div>
          </div>
          <div class="body">
            <?php if(!empty($it['resumo'])): ?><div class="muted"><?= h($it['resumo']) ?></div><?php endif; ?>
            <div class="actions">
              <a class="btn ghost" href="<?= BASE_URL.'/pages/dicas_editar.php?id='.(int)$it['id'] ?>">Abrir</a>
            </div>
          </div>
        </article>
      <?php endforeach; endif; ?>
    </div>

    <?php if($next!==null): ?>
      <div id="load-more-wrap" style="text-align:center;margin:10px 0">
        <?php
          $qs = ['offset'=>$next,'limit'=>$limit,'ajax'=>1];
          if($categoria) $qs['cat']=$categoria;
          if($onlyFeat)  $qs['feat']='1';
          $ajaxUrl='?'.http_build_query($qs);
        ?>
        <a id="btn-more" class="btn" href="<?= h($ajaxUrl) ?>">Carregar mais</a>
      </div>
      <div id="lazy-sentinel" style="height:1px"></div>
    <?php endif; ?>
  </main>

  <?php include_once ROOT_PATH.'/system/includes/user_navbar_right.php'; ?>
  <?php include_once ROOT_PATH.'/system/includes/user_code_footer.php'; ?>
  <?php include_once ROOT_PATH.'/system/includes/user_footer.php'; ?>

  <script>
  (function(){
    var loading=false, nextOffset=<?= (int)$next ?>, limit=<?= (int)$limit ?>;
    var cat = <?= $categoria ? (int)$categoria : 'null' ?>;
    var feat = <?= $onlyFeat ? '1':'0' ?>;

    function buildAjaxUrl(off){
      var p = new URLSearchParams();
      p.set('ajax','1');
      p.set('offset', off);
      p.set('limit',  limit);
      if (cat!==null) p.set('cat', cat);
      if (feat)       p.set('feat','1');
      return '?' + p.toString();
    }

    function loadMore(){
      if (loading || nextOffset===null) return;
      loading=true;
      fetch(buildAjaxUrl(nextOffset), {credentials:'same-origin'})
        .then(function(r){ return r.text(); })
        .then(function(html){
          var wrap=document.getElementById('feed-list');
          var tmp=document.createElement('div'); tmp.innerHTML=html;
          if(!tmp.textContent.trim()){ nextOffset=null; return; }
          while(tmp.firstChild) wrap.appendChild(tmp.firstChild);
          nextOffset += limit;
        })
        .catch(function(_e){})
        .finally(function(){ loading=false; });
    }

    var btn=document.getElementById('btn-more');
    if (btn) btn.addEventListener('click', function(e){ e.preventDefault(); loadMore(); });

    var sentinel=document.getElementById('lazy-sentinel');
    if ('IntersectionObserver' in window && sentinel){
      var io=new IntersectionObserver(function(entries){
        entries.forEach(function(ent){ if(ent.isIntersecting) loadMore(); });
      }, {root:null, rootMargin:'0px', threshold:0.1});
      io.observe(sentinel);
    }
  })();
  </script>
</body>
</html>
