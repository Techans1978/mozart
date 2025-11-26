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
  <h1>Locais / Entidades — Listar</h1>
  <div class="actions">
    <a class="btn" href="locais-entidades-form.html">Novo</a>
    <button class="btn">Exportar CSV</button>
    
  </div>
</header>

<form class="card" onsubmit="return false;">
  <p class="subtitle">Filtros</p>
  
  <div class="grid cols-4">
    <div><label>Busca</label><input type="text" placeholder="nome, endereço"/></div>
    <div><label>Tipo</label><select><option>—</option><option>Empresa</option><option>Filial</option><option>Loja</option><option>Setor</option></select></div>
    <div><label>Status</label><select><option>—</option><option>Ativo</option><option>Inativo</option></select></div>
    <div><label>Entidade</label><select><option>—</option><option>Matriz</option><option>Filial 01</option></select></div>
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
      <div style="font-weight:700">Matriz</div>
      <div class="hint">Empresa • São Paulo/SP</div>
    </div>
    <div class="row" style="gap:6px;flex-wrap:wrap"><span class="pill">Ativo</span></div>
  </div>
  <div class="divider"></div>
  <div class="grid cols-3"><div><label>Filiais</label><div>5</div></div><div><label>Ativos</label><div>820</div></div><div><label>Responsável</label><div>Admin</div></div></div>
  <div class="divider"></div>
  <div class="row" style="justify-content:flex-end;gap:8px"><a class="btn small" href="#">l</a><a class="btn small" href="#">o</a><a class="btn small" href="#">c</a><a class="btn small" href="#">a</a><a class="btn small" href="#">i</a><a class="btn small" href="#">s</a><a class="btn small" href="#">-</a><a class="btn small" href="#">e</a><a class="btn small" href="#">n</a><a class="btn small" href="#">t</a><a class="btn small" href="#">i</a><a class="btn small" href="#">d</a><a class="btn small" href="#">a</a><a class="btn small" href="#">d</a><a class="btn small" href="#">e</a><a class="btn small" href="#">s</a><a class="btn small" href="#">-</a><a class="btn small" href="#">f</a><a class="btn small" href="#">o</a><a class="btn small" href="#">r</a><a class="btn small" href="#">m</a><a class="btn small" href="#">.</a><a class="btn small" href="#">h</a><a class="btn small" href="#">t</a><a class="btn small" href="#">m</a><a class="btn small" href="#">l</a></div>
</article>

<article class="card">
  <div class="row" style="justify-content:space-between;align-items:flex-start">
    <div>
      <div style="font-weight:700">Loja Centro</div>
      <div class="hint">Loja • Campinas/SP</div>
    </div>
    <div class="row" style="gap:6px;flex-wrap:wrap"><span class="pill">Ativo</span></div>
  </div>
  <div class="divider"></div>
  <div class="grid cols-3"><div><label>Ativos</label><div>120</div></div><div><label>Setores</label><div>3</div></div><div><label>Endereço</label><div>Rua X, 100</div></div></div>
  <div class="divider"></div>
  <div class="row" style="justify-content:flex-end;gap:8px"><a class="btn small" href="#">l</a><a class="btn small" href="#">o</a><a class="btn small" href="#">c</a><a class="btn small" href="#">a</a><a class="btn small" href="#">i</a><a class="btn small" href="#">s</a><a class="btn small" href="#">-</a><a class="btn small" href="#">e</a><a class="btn small" href="#">n</a><a class="btn small" href="#">t</a><a class="btn small" href="#">i</a><a class="btn small" href="#">d</a><a class="btn small" href="#">a</a><a class="btn small" href="#">d</a><a class="btn small" href="#">e</a><a class="btn small" href="#">s</a><a class="btn small" href="#">-</a><a class="btn small" href="#">f</a><a class="btn small" href="#">o</a><a class="btn small" href="#">r</a><a class="btn small" href="#">m</a><a class="btn small" href="#">.</a><a class="btn small" href="#">h</a><a class="btn small" href="#">t</a><a class="btn small" href="#">m</a><a class="btn small" href="#">l</a></div>
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