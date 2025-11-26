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
  <h1>Ordens de Serviço — Listar</h1>
  <div class="actions"><a class="btn" href="os-form.html">Nova OS</a></div>
</header>

<form class="card" onsubmit="return false;">
  <p class="subtitle">Filtros</p>
  <div class="grid cols-4">
    <div><label>Busca</label><input type="text" placeholder="Nº OS, tag, solicitante"/></div>
    <div><label>Status</label><select><option>—</option><option>Aberta</option><option>Aguardando peça</option><option>Concluída</option></select></div>
    <div><label>Prioridade</label><select><option>—</option><option>Baixa</option><option>Média</option><option>Alta</option><option>Crítica</option></select></div>
    <div><label>Técnico/Fila</label><input type="text" placeholder="nome/fila"/></div>
  </div>
</form>
<section id="list" class="grid cols-2">

<article class="card">
  <div class="row" style="justify-content:space-between;align-items:flex-start">
    <div>
      <div style="font-weight:700">OS #4509</div>
      <div class="hint">Ativo: NB-0001 • Latitude 5440</div>
    </div>
    <div class="row" style="gap:6px;flex-wrap:wrap"><span class="pill">Aberta</span> <span class="pill">Prioridade: Alta</span></div>
  </div>
  <div class="divider"></div>
  <div class="grid cols-3"><div><label>Solicitante</label><div>Ana</div></div><div><label>Local</label><div>TI • Matriz</div></div><div><label>SLA</label><div>8x5 — 4h</div></div></div>
  <div class="divider"></div>
  <div class="row" style="justify-content:flex-end;gap:8px"><a class="btn small" href="os-form.html">Editar</a><a class="btn small" href="#">Inativar</a><a class="btn small" href="#">Excluir</a><a class="btn small" href="#">Atribuir</a></div>
</article>

<article class="card">
  <div class="row" style="justify-content:space-between;align-items:flex-start">
    <div>
      <div style="font-weight:700">OS #4510</div>
      <div class="hint">Ativo: IMP-010 • ZT-230</div>
    </div>
    <div class="row" style="gap:6px;flex-wrap:wrap"><span class="pill">Aguardando peça</span> <span class="pill">Prioridade: Média</span></div>
  </div>
  <div class="divider"></div>
  <div class="grid cols-3"><div><label>Fila</label><div>Oficina</div></div><div><label>Peças</label><div>Ribbon 230</div></div><div><label>SLA</label><div>em risco</div></div></div>
  <div class="divider"></div>
  <div class="row" style="justify-content:flex-end;gap:8px"><a class="btn small" href="os-form.html">Editar</a><a class="btn small" href="#">Inativar</a><a class="btn small" href="#">Excluir</a><a class="btn small" href="#">Concluir</a></div>
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