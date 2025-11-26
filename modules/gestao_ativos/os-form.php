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
  <h1>Ordem de Serviço — Cadastro</h1>
  <div class="actions"><a class="btn" href="os-listar.html">Listar OS</a></div>
</header>

<form class="card" onsubmit="return false">
  <p class="subtitle">Cabeçalho</p>
  <div class="grid cols-4">
    <div><label>Nº OS</label><input type="text" placeholder="auto" disabled /></div>
    <div><label>Status</label><select><option>Aberta</option><option>Atribuída</option><option>Em atendimento</option><option>Aguardando peça</option><option>Concluída</option><option>Cancelada</option></select></div>
    <div><label>Prioridade</label><select><option>Baixa</option><option>Média</option><option selected>Alta</option><option>Crítica</option></select></div>
    <div><label>Tipo</label><select><option>Corretiva</option><option>Preventiva</option><option>Instalação</option><option>Melhoria</option></select></div>
  </div>
  <div class="grid cols-4">
    <div><label>Ativo</label><input type="text" placeholder="Tag/Modelo"/></div>
    <div><label>Contrato/Garantia</label><select><option>Sem garantia</option><option>GAR-2025</option></select></div>
    <div><label>Técnico / Fila</label><input type="text" placeholder="Nome ou fila"/></div>
    <div><label>SLA aplicado</label><select><option>8x5</option><option>24x7</option></select></div>
  </div>
  <div class="grid cols-3">
    <div class="stack"><label>Problema relatado</label><textarea placeholder="Descrição do usuário"></textarea></div>
    <div class="stack"><label>Diagnóstico</label><textarea placeholder="Análise técnica"></textarea></div>
    <div class="stack"><label>Checklist</label><textarea placeholder="Passos executados"></textarea></div>
  </div>
  <div class="divider"></div>
  <div class="row" style="justify-content:flex-end;gap:10px">
    <button class="btn">Salvar rascunho</button>
    <button class="btn primary">Concluir OS</button>
  </div>
</form>

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