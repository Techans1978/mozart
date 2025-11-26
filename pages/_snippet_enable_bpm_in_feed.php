<?php
// Include opcional do provider BPM para o feed
$__feed_bpm_provider__ = ROOT_PATH . '/system/services/feed_bpm_provider.php';
if (file_exists($__feed_bpm_provider__)) { include_once $__feed_bpm_provider__; }

// Após chamar feed_fetch($conn, $user_id, $opts), mescla itens BPM (se provider existir)
// Exemplo de uso na user_page.php:
// $data  = feed_fetch($conn, $user_id, $opts);
// $items = $data['items'] ?? [];
// include_once ROOT_PATH . '/pages/_snippet_enable_bpm_in_feed.php';
if (isset($items) && function_exists('feed_fetch_bpm_items')) {
  $uid = (int)($_SESSION['user_id'] ?? 0);
  $lim = (int)($opts['limit'] ?? 20);
  $off = (int)($opts['offset'] ?? 0);
  $bpm_items = feed_fetch_bpm_items($conn, $uid, $lim, $off);
  if (!empty($bpm_items)) {
    $items = array_merge($items, $bpm_items);
    usort($items, function($a,$b){
      $fa = (int)($a['featured'] ?? 0); $fb = (int)($b['featured'] ?? 0);
      if ($fa !== $fb) return $fb - $fa;
      return strcmp((string)($b['dt_ref'] ?? ''), (string)($a['dt_ref'] ?? ''));
    });
  }
}
