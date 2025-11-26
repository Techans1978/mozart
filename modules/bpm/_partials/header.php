<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Descoberta automática dos assets CSS/JS do BPM
$asset_candidates = [
  '/public/modeules/bpm/includes/assets', // caminho informado
  '/public/modules/bpm/includes/assets',  // correção comum
  '/public/assets/bpm',                   // fallback do scaffold
  '/modules/bpm/assets',                  // outro fallback
];
function pick_asset($candidates, $file){
  $root = defined('ROOT_PATH') ? rtrim(ROOT_PATH, '/\\') : null;
  foreach ($candidates as $base){
    if ($root && file_exists($root . $base . '/' . $file)) return $base . '/' . $file;
  }
  return $candidates[0] . '/' . $file;
}
$BPM_CSS = pick_asset($asset_candidates, 'style.css');
$BPM_JS  = pick_asset($asset_candidates, 'app.js');
?><!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>BPM Wizard</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($BPM_CSS, ENT_QUOTES) ?>">
  <script src="<?= htmlspecialchars($BPM_JS, ENT_QUOTES) ?>" defer></script>
</head>
<body>
<div class="container">
  <div class="nav">
    <a href="/modules/bpm/list_bpm.php" class="btn">← Voltar à lista</a>
    <div class="small">Sessão: <?php echo session_id(); ?></div>
  </div>
