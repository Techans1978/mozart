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
  <h1>Automação & Regras — Listar</h1>
  <div class="actions"><a class="btn" href="automacoes-form.html">Nova regra</a></div>
</header>

<form class="card" onsubmit="return false;">
  <p class="subtitle">Filtros</p>
  <div class="grid cols-4">
    <div><label>Busca</label><input type="text" placeholder="nome, descrição"/></div>
    <div><label>Status</label><select><option>—</option><option>Ativa</option><option>Inativa</option></select></div>
    <div><label>Tipo</label><select><option>—</option><option>Evento</option><option>Condicional</option><option>Escalonamento</option><option>Notificação</option><option>Integração</option></select></div>
    <div><label>Escopo</label><select><option>—</option><option>Global</option><option>Entidade</option><option>Local</option></select></div>
  </div>
</form>
<section id="list" class="grid cols-2">

<article class="card">
  <div class="row" style="justify-content:space-between;align-items:flex-start">
    <div>
      <div style="font-weight:700">Escalonar OS após 48h</div>
      <div class="hint">Tipo: Escalonamento • Escopo: Global</div>
    </div>
    <div class="row" style="gap:6px;flex-wrap:wrap"><span class="pill">Ativa</span> <span class="pill">Último disparo: hoje 10:05</span></div>
  </div>
  <div class="divider"></div>
  <div class="grid cols-3"><div><label>Condição</label><div>status='Aguardando peça' AND idade>48h</div></div><div><label>Ação</label><div>mover p/ Fila N2</div></div><div><label>Próximo agendamento</label><div>10:30</div></div></div>
  <div class="divider"></div>
  <div class="row" style="justify-content:flex-end;gap:8px"><a class="btn small" href="automacoes-form.html">Editar</a><a class="btn small" href="#">Inativar</a><a class="btn small" href="#">Excluir</a><a class="btn small" href="#">Testar agora</a><a class="btn small" href="#">Logs</a></div>
</article>

<article class="card">
  <div class="row" style="justify-content:space-between;align-items:flex-start">
    <div>
      <div style="font-weight:700">Alerta de garantia 30d</div>
      <div class="hint">Tipo: Notificação • Escopo: Entidade</div>
    </div>
    <div class="row" style="gap:6px;flex-wrap:wrap"><span class="pill">Ativa</span> <span class="pill">Falhas: 0</span></div>
  </div>
  <div class="divider"></div>
  <div class="grid cols-3"><div><label>Condição</label><div>garantia<=30d</div></div><div><label>Ação</label><div>enviar e-mail p/ gestor</div></div><div><label>Execução</label><div>diária 08:00</div></div></div>
  <div class="divider"></div>
  <div class="row" style="justify-content:flex-end;gap:8px"><a class="btn small" href="automacoes-form.html">Editar</a><a class="btn small" href="#">Inativar</a><a class="btn small" href="#">Excluir</a><a class="btn small" href="#">Testar agora</a><a class="btn small" href="#">Logs</a></div>
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