<?php
// modules/bpm/bpm_designer.php
// Mozart BPM — Modeler com Properties + Element Templates (CDN + fallback local)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

// Abre <html><head>...<body>
include_once ROOT_PATH . 'system/includes/head.php';
?>

<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">

<?php
// (se o seu navbar ficar dentro do head/footer, não precisa incluir aqui)
include_once ROOT_PATH . 'system/includes/navbar.php';
?>

<!-- Page Content -->
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

    <div class="row">
      <div class="col-lg-12">
<!-- Top Content -->


<session class="bpm">
  <div class="container">
    
<header class="toolbar">
  <h1>Sub-locações — Listar</h1>
  <div class="actions">
    <a class="btn" href="sublocacao-form.html">Novo</a>
    <button class="btn">Exportar CSV</button>
    
  </div>
</header>

<form class="card" onsubmit="return false;">
  <p class="subtitle">Filtros</p>
  
  <div class="grid cols-4">
    <div><label>Busca</label><input type="text" placeholder="contrato, destino"/></div>
    <div><label>Status</label><select><option>—</option><option>Ativa</option><option>Encerrada</option><option>Suspensa</option></select></div>
    <div><label>Destino</label><input type="text" placeholder="Setor/Loja"/></div>
    <div><label>Vigência</label><select><option>—</option><option>Atuais</option><option>Vencendo 30d</option></select></div>
  </div>
  
  <div class="row" style="justify-content:flex-end">
    <button class="btn">Limpar</button>
    <button class="btn primary">Aplicar</button>
  </div>
</form>

<div id="bulkbar" class="card" style="display:none">
  <div class="row" style="justify-content:space-between; align-items:center">
    <div><strong id="bulk-count">0</strong> selecionados</div>
    <div class="row">
      <button class="btn small">Inativar</button>
      <button class="btn small">Excluir</button>
      <button class="btn small">Exportar</button>
      <button class="btn small danger">Limpar seleção</button>
    </div>
  </div>
</div>

<section id="list" class="grid cols-2">
  
<article class="card">
  <div class="row" style="justify-content:space-between;align-items:flex-start">
    <div>
      <div style="font-weight:700">SL-2025-01</div>
      <div class="hint">Destino: Loja Centro</div>
    </div>
    <div class="row" style="gap:6px;flex-wrap:wrap"><span class="pill">Ativa</span> <span class="pill">valor: R$ 1.200/mês</span></div>
  </div>
  <div class="divider"></div>
  <div class="grid cols-3"><div><label>Início–Fim</label><div>01/2025–12/2025</div></div><div><label>Itens</label><div>8</div></div><div><label>Contrato origem</label><div>LOC-55</div></div></div>
  <div class="divider"></div>
  <div class="row" style="justify-content:flex-end;gap:8px"><a class="btn small" href="#">s</a><a class="btn small" href="#">u</a><a class="btn small" href="#">b</a><a class="btn small" href="#">l</a><a class="btn small" href="#">o</a><a class="btn small" href="#">c</a><a class="btn small" href="#">a</a><a class="btn small" href="#">c</a><a class="btn small" href="#">a</a><a class="btn small" href="#">o</a><a class="btn small" href="#">-</a><a class="btn small" href="#">f</a><a class="btn small" href="#">o</a><a class="btn small" href="#">r</a><a class="btn small" href="#">m</a><a class="btn small" href="#">.</a><a class="btn small" href="#">h</a><a class="btn small" href="#">t</a><a class="btn small" href="#">m</a><a class="btn small" href="#">l</a></div>
</article>

<article class="card">
  <div class="row" style="justify-content:space-between;align-items:flex-start">
    <div>
      <div style="font-weight:700">SL-2024-11</div>
      <div class="hint">Destino: TI Matriz</div>
    </div>
    <div class="row" style="gap:6px;flex-wrap:wrap"><span class="pill">Encerrada</span></div>
  </div>
  <div class="divider"></div>
  <div class="grid cols-3"><div><label>Início–Fim</label><div>05/2024–12/2024</div></div><div><label>Itens</label><div>3</div></div><div><label>Contrato origem</label><div>LOC-12</div></div></div>
  <div class="divider"></div>
  <div class="row" style="justify-content:flex-end;gap:8px"><a class="btn small" href="#">s</a><a class="btn small" href="#">u</a><a class="btn small" href="#">b</a><a class="btn small" href="#">l</a><a class="btn small" href="#">o</a><a class="btn small" href="#">c</a><a class="btn small" href="#">a</a><a class="btn small" href="#">c</a><a class="btn small" href="#">a</a><a class="btn small" href="#">o</a><a class="btn small" href="#">-</a><a class="btn small" href="#">f</a><a class="btn small" href="#">o</a><a class="btn small" href="#">r</a><a class="btn small" href="#">m</a><a class="btn small" href="#">.</a><a class="btn small" href="#">h</a><a class="btn small" href="#">t</a><a class="btn small" href="#">m</a><a class="btn small" href="#">l</a></div>
</article>

</section>

<div class="card">
  <div class="row" style="justify-content:space-between; align-items:center">
    <div class="hint">1–20 de 120</div>
    <div class="row">
      <label>Por página</label>
      <select><option>20</option><option>50</option><option>100</option></select>
      <button class="btn">Anterior</button>
      <button class="btn">Próxima</button>
    </div>
  </div>
</div>

</session>

  <!-- Fim Content -->
        </div>
    </div>
  </div>
</div>

<?php
include_once ROOT_PATH . 'system/includes/code_footer.php';
?>






<?php
include_once ROOT_PATH . 'system/includes/footer.php';
?>