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
  <h1>Listas / Dropdowns — Gerenciamento</h1>
  <div class="actions">
    <a class="btn" href="listas-dropdowns-listar.html">Listar listas</a>
  </div>
</header>

<form class="card" autocomplete="off" novalidate>
  <p class="subtitle">Lista</p>
  <div class="grid cols-3">
    <div>
      <label>Selecionar lista</label>
      <select id="sel-lista">
        <option value="">— nova lista —</option>
        <option value="status_ativo">Status de Ativo</option>
        <option value="motivos_transferencia">Motivos de Transferência</option>
        <option value="tipos_contrato">Tipos de Contrato</option>
        <option value="setores">Setores</option>
      </select>
    </div>
    <div>
      <label>Nome da lista *</label>
      <input type="text" id="nome-lista" placeholder="Ex.: Status de Ativo"/>
    </div>
    <div>
      <label>Escopo *</label>
      <select>
        <option>Global</option>
        <option>Por entidade</option>
      </select>
    </div>
  </div>

  <div class="grid cols-2">
    <div>
      <label>Descrição</label>
      <textarea placeholder="Defina o uso desta lista (onde aparece, exemplos)."></textarea>
    </div>
    <div class="card-muted">
      <label><input type="checkbox" checked/> Habilitar ordenação manual</label><br/>
      <label><input type="checkbox" /> Permitir hierarquia (pai/filho)</label><br/>
      <label><input type="checkbox" /> Permitir cor/ícone por item</label>
    </div>
  </div>

  <div class="divider"></div>

  <p class="subtitle">Valores da lista</p>
  <div id="valores" class="stack"></div>
  <div class="row">
    <button type="button" class="btn small" id="add-valor">+ Adicionar valor</button>
    <button type="button" class="btn small">Importar CSV</button>
    <button type="button" class="btn small">Exportar CSV</button>
  </div>

  <div class="divider"></div>
  <div style="display:flex;justify-content:flex-end;gap:10px">
    <button class="btn" type="button">Cancelar</button>
    <button class="btn primary" type="button">Salvar lista (visual)</button>
  </div>
</form>

<div class="card">
  <p class="hint">Mock visual. Depois ligamos com o cadastro que usa cada lista.</p>
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
  
<script>
function valorRow(){
  const el=document.createElement('div'); el.className='grid cols-4'; el.style.alignItems='end';
  el.innerHTML=`
    <div><label>Nome *</label><input type="text" placeholder="Ex.: Em operação"/></div>
    <div><label>Valor/Código</label><input type="text" placeholder="ex.: OP"/></div>
    <div><label>Ordem</label><input type="number" min="0" value="0"/></div>
    <div class="row"><button type="button" class="btn small danger">Remover</button></div>
    <div><label>Pai (opcional)</label><input type="text" placeholder="Ex.: Operacional"/></div>
    <div><label>Cor</label><input type="color" value="#3b82f6"/></div>
    <div><label>Ícone/emoji</label><input type="text" placeholder="🔧"/></div>
    <div><label>Status</label><select><option>Ativo</option><option>Inativo</option></select></div>
  `;
  el.querySelector('.btn.danger').addEventListener('click',()=>el.remove()); return el;
}
const valores=document.getElementById('valores');
document.getElementById('add-valor').addEventListener('click',()=>valores.appendChild(valorRow()));
valores.appendChild(valorRow()); valores.appendChild(valorRow());
</script>

<?php
include_once ROOT_PATH . 'system/includes/footer.php';
?>