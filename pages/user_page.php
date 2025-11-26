<?php
// pages/user_page.php
// ===== Debug opcional (dev) =====
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===== Bootstrap do app =====
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Autenticação + conexão
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php'; // cria $conn (mysqli)
proteger_pagina();

// Serviço do Feed
require_once ROOT_PATH . '/system/services/feed_service.php';

// helper local (caso ainda não exista)
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// Parâmetros de listagem
$user_id = (int)($_SESSION['user_id'] ?? 0);
$opts = [
  'limit'  => isset($_GET['limit'])  ? (int)$_GET['limit']  : 20,
  'offset' => isset($_GET['offset']) ? (int)$_GET['offset'] : 0,
  // 'tipo' => ['artigo','dica','midia','documento','evento'], // opcional
  // 'categoria_id' => null,
  // 'apenas_destaque' => false,
];

// Busca itens do feed (tolerante: se $conn = null, retorna vazio)
$data  = feed_fetch($conn, $user_id, $opts);
$items = $data['items'] ?? [];
$next  = $data['next_offset'] ?? null;

// em public/pages/user_page.php (depois de montar $items vindo do feed padrão):
$data  = feed_fetch($conn, $user_id, $opts);
$items = $data['items'] ?? [];
include_once ROOT_PATH . '/pages/_snippet_enable_bpm_in_feed.php'; // <- adiciona os cards BPM


?>
<?php include_once ROOT_PATH . '/system/includes/user_head.php'; ?>

<body>
  <!-- Topbar -->
  <?php include_once ROOT_PATH . '/system/includes/user_navbar.php'; ?>

  <!-- ===== Feed (cards) ===== -->
  <main class="feed" aria-live="polite">
    <!-- Se quiser, coloque aqui seus chips/filtros já existentes -->

    <?php if (!$items): ?>
      <article class="card">
        <div class="body"><span class="muted">Nada por aqui por enquanto.</span></div>
      </article>
    <?php else: foreach($items as $it): ?>
      <article class="card">
        <div class="head">
          <div class="title">
            <?php
              $emoji = [
                'artigo'    => '📰',
                'dica'      => '💡',
                'midia'     => '🖼️',
                'documento' => '📄',
                'evento'    => '📅'
              ][$it['tipo']] ?? '📌';
            ?>
            <span><?= $emoji ?> <?= h(ucfirst($it['tipo'])) ?> · <?= h($it['titulo']) ?></span>
          </div>
          <div class="meta">
            <?php if(!empty($it['featured'])): ?><span class="badge ok">Destaque</span><?php endif; ?>
          </div>
        </div>

        <div class="body">
          <?php if(!empty($it['resumo'])): ?>
            <div class="muted"><?= h($it['resumo']) ?></div>
          <?php endif; ?>

          <?php if($it['tipo']==='evento' && !empty($it['extra'])): ?>
            <div class="meta">
              <span class="chip">
                Quando:
                <?php
                  $e = $it['extra'];
                  $ini = !empty($e['dia_todo'])
                    ? date('d/m/Y', strtotime($e['inicio']))
                    : date('d/m/Y H:i', strtotime($e['inicio']));
                  $fim = !empty($e['fim'])
                    ? (!empty($e['dia_todo']) ? date('d/m/Y', strtotime($e['fim'])) : date('d/m/Y H:i', strtotime($e['fim'])))
                    : '';
                  echo $fim ? " $ini → $fim" : " $ini";
                ?>
              </span>
            </div>
          <?php endif; ?>

          <?php if($it['tipo']==='midia' && !empty($it['extra'])): ?>
            <div class="meta">
              <span class="chip">Arquivos: <?= (int)($it['extra']['qtde'] ?? 0) ?></span>
              <?php if(!empty($it['extra']['tipos'])): ?>
                <span class="chip">Tipos: <?= h(implode(', ', $it['extra']['tipos'])) ?></span>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if($it['tipo']==='documento' && !empty($it['extra'])): ?>
            <div class="meta">
              <span class="chip">Arquivos: <?= (int)($it['extra']['arquivos'] ?? 0) ?></span>
              <span class="chip"><?= !empty($it['extra']['pasta']) ? 'Pasta' : 'Arquivo único' ?></span>
              <?php if(isset($it['extra']['exibir_no_feed']) && !$it['extra']['exibir_no_feed']): ?>
                <span class="chip warn">Oculto do Feed</span>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <div class="actions">
            <?php
              // Links internos (ajuste se tiver páginas de "ver" separadas das de "editar")
              $map = [
                'artigo'    => BASE_URL.'/pages/conteudo_editar.php?id=',
                'dica'      => BASE_URL.'/pages/dicas_editar.php?id=',
                'midia'     => BASE_URL.'/pages/midia_editar.php?id=',
                'documento' => BASE_URL.'/pages/documentos_editar.php?id=',
                'evento'    => BASE_URL.'/pages/event_editar.php?id=',
              ];
              $href = ($map[$it['tipo']] ?? '#') . (int)$it['id'];
            ?>
            <a class="btn ghost" href="<?= $href ?>">Abrir</a>
          </div>
        </div>
      </article>
    <?php endforeach; endif; ?>

    <?php if($next!==null): ?>
      <div style="text-align:center;margin:10px 0">
        <a class="btn" href="?offset=<?= (int)$next ?>&limit=<?= (int)$opts['limit'] ?>">Carregar mais</a>
      </div>
    <?php endif; ?>
  </main>

  <!-- Sidebar direita / scripts / rodapé -->
  <?php include_once ROOT_PATH . '/system/includes/user_navbar_right.php'; ?>
  <?php include_once ROOT_PATH . '/system/includes/user_code_footer.php'; ?>
  <?php include_once ROOT_PATH . '/system/includes/user_footer.php'; ?>