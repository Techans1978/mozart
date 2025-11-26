<?php
// pages/feed_content.php  (Artigos + Mídia) — chips de categoria + lazyload
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../config.php';
if (session_status()===PHP_SESSION_NONE) session_start();
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
proteger_pagina();

require_once ROOT_PATH.'/system/services/feed_service_content.php';
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

// Helpers locais
function tbl_exists($conn,$name){ $name=$conn->real_escape_string($name); $rs=$conn->query("SHOW TABLES LIKE '$name'"); return $rs? (bool)$rs->num_rows:false; }
function col_exists($conn,$table,$col){
  $t=$conn->real_escape_string($table); $c=$conn->real_escape_string($col);
  $rs=$conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1");
  return $rs? (bool)$rs->num_rows:false;
}
function fetch_categories($conn,$table){
  $items=[];
  if (!tbl_exists($conn,$table)) return $items;
  // detecta coluna de título (titulo/nome)
  $title = col_exists($conn,$table,'titulo')?'titulo':(col_exists($conn,$table,'nome')?'nome':null);
  if(!$title) return $items;
  $pub = col_exists($conn,$table,'published')?'published':(col_exists($conn,$table,'publicado')?'publicado':null);
  $sql = "SELECT id, `$title` AS t FROM `$table`";
  if($pub) $sql .= " WHERE `$pub`=1";
  $sql .= " ORDER BY t";
  if ($rs=$conn->query($sql)) while($r=$rs->fetch_assoc()) $items[]=['id'=>(int)$r['id'],'t'=>$r['t']];
  return $items;
}

$user_id     = (int)($_SESSION['user_id'] ?? 0);
$cat_art     = isset($_GET['cat'])     ? (int)$_GET['cat']     : null; // mantém compatibilidade
$cat_midia   = isset($_GET['cat_mid']) ? (int)$_GET['cat_mid'] : null;
$limit       = isset($_GET['limit'])   ? (int)$_GET['limit']   : 20;
$offset      = isset($_GET['offset'])  ? (int)$_GET['offset']  : 0;
$onlyFeat    = !empty($_GET['feat']);
$isAjax      = isset($_GET['ajax']) && $_GET['ajax']=='1';

// Monta filtro para o serviço: ele usa 1 categoria por vez, então buscamos duas rodadas e unificamos via serviço.
// Para simplificar, passaremos categoria comum para cada tipo internamente no serviço (ele já aceita cat para ambos).
// Aqui, estratégia: se vier cat_mid, priorizamos mídia nesse param; se vier cat (artigos), passamos também.
$opts = [
  'limit'          => $limit,
  'offset'         => $offset,
  'categoria_id'   => $cat_art,     // usado para artigos
  'categoria_mid'  => $cat_midia,   // extensão: o service lê isso para mídia (já implementei)
  'apenas_destaque'=> $onlyFeat,
];

// Busca itens
$data  = feed_content_fetch($conn, $user_id, $opts);
$items = $data['items'] ?? [];
$next  = $data['next_offset'] ?? null;

// === Chips de categoria ===
$cats_art  = fetch_categories($conn,'content_categories');
$cats_mid  = fetch_categories($conn,'midia_categories');

// Render parcial se AJAX (somente cards)
if ($isAjax) {
  ob_start();
  if (!$items): ?>
    <article class="card"><div class="body"><span class="muted">Nenhum item encontrado.</span></div></article>
  <?php else: foreach($items as $it): ?>
    <article class="card">
      <div class="head">
        <div class="title">
          <?php $emoji = ['artigo'=>'📰','midia'=>'🖼️'][$it['tipo']] ?? '📌'; ?>
          <?= $emoji ?> <?= h(ucfirst($it['tipo'])) ?> · <?= h($it['titulo']) ?>
        </div>
        <div class="meta"><?php if(!empty($it['featured'])): ?><span class="badge ok">Destaque</span><?php endif; ?></div>
      </div>
      <div class="body">
        <?php if(!empty($it['resumo'])): ?><div class="muted"><?= h($it['resumo']) ?></div><?php endif; ?>
        <?php if($it['tipo']==='midia' && !empty($it['extra'])): ?>
          <div class="meta">
            <span class="chip">Arquivos: <?= (int)($it['extra']['qtde'] ?? 0) ?></span>
            <?php if(!empty($it['extra']['tipos'])): ?>
              <span class="chip">Tipos: <?= h(implode(', ', $it['extra']['tipos'])) ?></span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="actions">
          <?php
            $map = [
              'artigo' => BASE_URL.'/pages/conteudo_editar.php?id=',
              'midia'  => BASE_URL.'/pages/midia_editar.php?id=',
            ];
            $href = ($map[$it['tipo']] ?? '#') . (int)$it['id'];
          ?>
          <a class="btn ghost" href="<?= $href ?>">Abrir</a>
        </div>
      </div>
    </article>
  <?php endforeach; endif;
  $html = ob_get_clean();
  echo $html; exit;
}

include_once ROOT_PATH.'/system/includes/user_head.php';
?>
<body>
  <?php include_once ROOT_PATH.'/system/includes/user_navbar.php'; ?>

  <main class="feed" aria-live="polite">
    <section class="card">
      <div class="head">
        <div class="title">📌 <span>Artigos & Mídia</span></div>
        <div class="meta">
          <?php if ($cat_art): ?><span class="chip">Cat. Artigos: #<?= (int)$cat_art ?></span><?php endif; ?>
          <?php if ($cat_midia): ?><span class="chip">Cat. Mídia: #<?= (int)$cat_midia ?></span><?php endif; ?>
          <?php if ($onlyFeat): ?><span class="chip ok">Destaques</span><?php endif; ?>
        </div>
      </div>
      <div class="body filterbar">
        <!-- Chips: Artigos -->
        <span class="view">Categorias (Artigos):</span>
        <a class="chip<?= !$cat_art?' ok':'' ?>" href="?<?= http_build_query(array_filter(['cat'=>null,'cat_mid'=>$cat_midia,'feat'=>$onlyFeat?'1':null])) ?>">Todas</a>
        <?php foreach($cats_art as $c): ?>
          <?php
            $qs = ['cat'=>$c['id']];
            if($cat_midia) $qs['cat_mid']=$cat_midia;
            if($onlyFeat)  $qs['feat']='1';
          ?>
          <a class="chip<?= ($cat_art===$c['id'])?' ok':'' ?>" href="?<?= http_build_query($qs) ?>"><?= h($c['t']) ?></a>
        <?php endforeach; ?>
      </div>
      <?php if ($cats_mid): ?>
      <div class="body filterbar" style="border-top:1px solid var(--border)">
        <!-- Chips: Mídia -->
        <span class="view">Categorias (Mídia):</span>
        <a class="chip<?= !$cat_midia?' ok':'' ?>" href="?<?= http_build_query(array_filter(['cat'=>$cat_art,'cat_mid'=>null,'feat'=>$onlyFeat?'1':null])) ?>">Todas</a>
        <?php foreach($cats_mid as $c): ?>
          <?php
            $qs = ['cat_mid'=>$c['id']];
            if($cat_art)   $qs['cat']=$cat_art;
            if($onlyFeat)  $qs['feat']='1';
          ?>
          <a class="chip<?= ($cat_midia===$c['id'])?' ok':'' ?>" href="?<?= http_build_query($qs) ?>"><?= h($c['t']) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>

    <div id="feed-list">
      <?php if(!$items): ?>
        <article class="card"><div class="body"><span class="muted">Nenhum item encontrado.</span></div></article>
      <?php else: foreach($items as $it): ?>
        <article class="card">
          <div class="head">
            <div class="title">
              <?php $emoji = ['artigo'=>'📰','midia'=>'🖼️'][$it['tipo']] ?? '📌'; ?>
              <?= $emoji ?> <?= h(ucfirst($it['tipo'])) ?> · <?= h($it['titulo']) ?>
            </div>
            <div class="meta"><?php if(!empty($it['featured'])): ?><span class="badge ok">Destaque</span><?php endif; ?></div>
          </div>
          <div class="body">
            <?php if(!empty($it['resumo'])): ?><div class="muted"><?= h($it['resumo']) ?></div><?php endif; ?>
            <?php if($it['tipo']==='midia' && !empty($it['extra'])): ?>
              <div class="meta">
                <span class="chip">Arquivos: <?= (int)($it['extra']['qtde'] ?? 0) ?></span>
                <?php if(!empty($it['extra']['tipos'])): ?>
                  <span class="chip">Tipos: <?= h(implode(', ', $it['extra']['tipos'])) ?></span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <div class="actions">
              <?php
                $map = [
                  'artigo' => BASE_URL.'/pages/conteudo_editar.php?id=',
                  'midia'  => BASE_URL.'/pages/midia_editar.php?id=',
                ];
                $href = ($map[$it['tipo']] ?? '#') . (int)$it['id'];
              ?>
              <a class="btn ghost" href="<?= $href ?>">Abrir</a>
            </div>
          </div>
        </article>
      <?php endforeach; endif; ?>
    </div>

    <?php if($next!==null): ?>
      <div id="load-more-wrap" style="text-align:center;margin:10px 0">
        <?php
          // link de fallback
          $qs = ['offset'=>$next,'limit'=>$limit,'ajax'=>1];
          if($cat_art)   $qs['cat']=$cat_art;
          if($cat_midia) $qs['cat_mid']=$cat_midia;
          if($onlyFeat)  $qs['feat']='1';
          $ajaxUrl = '?'.http_build_query($qs);
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
    var cat = <?= $cat_art ? (int)$cat_art : 'null' ?>;
    var catMid = <?= $cat_midia ? (int)$cat_midia : 'null' ?>;
    var feat = <?= $onlyFeat ? '1' : '0' ?>;

    function buildAjaxUrl(off){
      var p = new URLSearchParams();
      p.set('ajax','1');
      p.set('offset', off);
      p.set('limit',  limit);
      if (cat !== null)    p.set('cat', cat);
      if (catMid !== null) p.set('cat_mid', catMid);
      if (feat)            p.set('feat','1');
      return '?' + p.toString();
    }

    function loadMore(){
      if (loading || nextOffset===null) return;
      loading=true;
      var url = buildAjaxUrl(nextOffset);
      fetch(url, {credentials:'same-origin'})
        .then(function(r){ return r.text(); })
        .then(function(html){
          var wrap = document.getElementById('feed-list');
          var tmp = document.createElement('div');
          tmp.innerHTML = html;
          // se não vier nada útil, para lazy
          if (!tmp.textContent.trim()) { nextOffset = null; return; }
          // anexa cards
          while (tmp.firstChild) wrap.appendChild(tmp.firstChild);
          // calcula próximo offset (padrão: soma limit)
          nextOffset += limit;

          // Se já não há mais próximo (heurística: se retornou menos de 1 card)
          var cardsAdded = wrap.querySelectorAll('article.card').length;
          // não dá para contar apenas os adicionados sem mais controle; então deixo continuar até o servidor não devolver nada
        })
        .catch(function(_e){ /* silencia */ })
        .finally(function(){ loading=false; });
    }

    // Fallback botão
    var btn = document.getElementById('btn-more');
    if (btn) btn.addEventListener('click', function(e){
      e.preventDefault(); loadMore();
    });

    // Lazy via IntersectionObserver
    var sentinel = document.getElementById('lazy-sentinel');
    if ('IntersectionObserver' in window && sentinel){
      var io = new IntersectionObserver(function(entries){
        entries.forEach(function(ent){
          if (ent.isIntersecting) loadMore();
        });
      }, {root:null, rootMargin:'0px', threshold:0.1});
      io.observe(sentinel);
    }
  })();
  </script>
</body>
</html>
