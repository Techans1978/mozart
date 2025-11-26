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
  <h1>Ordens de Compra — Listar</h1>
  <div class="actions"><a class="btn" href="oc-form.html">Nova OC</a></div>
</header>

<form class="card" onsubmit="return false;">
  <p class="subtitle">Filtros</p>
  <div class="grid cols-4">
    <div><label>Busca</label><input type="text" placeholder="Nº OC, fornecedor"/></div>
    <div><label>Status</label><select><option>—</option><option>Rascunho</option><option>Em aprovação</option><option>Aprovada</option><option>Emitida</option><option>Pendente receb.</option><option>Concluída</option><option>Cancelada</option></select></div>
    <div><label>Valor</label><select><option>—</option><option>&lt; 5 mil</option><option>5–50 mil</option><option>&gt; 50 mil</option></select></div>
    <div><label>Período</label><select><option>—</option><option>Este mês</option><option>Últimos 3 meses</option></select></div>
  </div>
</form>
<section id="list" class="grid cols-2">

<article class="card">
  <div class="row" style="justify-content:space-between;align-items:flex-start">
    <div>
      <div style="font-weight:700">OC #2025-010</div>
      <div class="hint">Fornecedor: Ingram</div>
    </div>
    <div class="row" style="gap:6px;flex-wrap:wrap"><span class="pill">Em aprovação</span> <span class="pill">R$ 28.400</span></div>
  </div>
  <div class="divider"></div>
  <div class="grid cols-3"><div><label>Itens</label><div>12</div></div><div><label>Tipo</label><div>Peças</div></div><div><label>Data</label><div>12/01/2025</div></div></div>
  <div class="divider"></div>
  <div class="row" style="justify-content:flex-end;gap:8px"><a class="btn small" href="oc-form.html">Editar</a><a class="btn small" href="#">Inativar</a><a class="btn small" href="#">Excluir</a><a class="btn small" href="#">Aprovar</a></div>
</article>

<article class="card">
  <div class="row" style="justify-content:space-between;align-items:flex-start">
    <div>
      <div style="font-weight:700">OC #2025-011</div>
      <div class="hint">Fornecedor: Dell</div>
    </div>
    <div class="row" style="gap:6px;flex-wrap:wrap"><span class="pill">Pendente receb.</span> <span class="pill">R$ 120.000</span></div>
  </div>
  <div class="divider"></div>
  <div class="grid cols-3"><div><label>Itens</label><div>2</div></div><div><label>Tipo</label><div>Ativos</div></div><div><label>Data</label><div>08/01/2025</div></div></div>
  <div class="divider"></div>
  <div class="row" style="justify-content:flex-end;gap:8px"><a class="btn small" href="oc-form.html">Editar</a><a class="btn small" href="#">Inativar</a><a class="btn small" href="#">Excluir</a><a class="btn small" href="#">Receber</a></div>
</article>

</section>

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