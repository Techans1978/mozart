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
  <h1>Ordem de Compra — Cadastro</h1>
  <div class="actions"><a class="btn" href="oc-listar.html">Listar OCs</a></div>
</header>

<form class="card" onsubmit="return false">
  <p class="subtitle">Cabeçalho</p>
  <div class="grid cols-4">
    <div><label>Nº OC</label><input type="text" placeholder="auto" disabled/></div>
    <div><label>Status</label><select><option>Rascunho</option><option>Em aprovação</option><option>Aprovada</option><option>Emitida</option><option>Pendente receb.</option><option>Concluída</option><option>Cancelada</option></select></div>
    <div><label>Tipo</label><select><option>Peças</option><option>Serviço</option><option>Ativo</option><option>Contrato</option></select></div>
    <div><label>Moeda</label><select><option>BRL</option><option>USD</option><option>EUR</option></select></div>
  </div>
  <div class="grid cols-3">
    <div><label>Fornecedor</label><input type="text" placeholder="Distribuidor/Fabricante"/></div>
    <div><label>Condição de pagamento</label><input type="text" placeholder="ex.: 28/56 dias"/></div>
    <div><label>Contato</label><input type="text" placeholder="nome/e-mail/telefone"/></div>
  </div>
  <div class="divider"></div>
  <p class="subtitle">Itens</p>
  <div id="oc-itens" class="stack"></div>
  <button type="button" class="btn small" id="add-item">+ Adicionar item</button>
  <div class="divider"></div>
  <div class="row" style="justify-content:flex-end;gap:10px">
    <button class="btn">Enviar p/ aprovação</button>
    <button class="btn primary">Salvar</button>
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
<script>
const wrap=document.getElementById('oc-itens');
function itemRow(){
  const el=document.createElement('div'); el.className='grid cols-4'; el.style.alignItems='end';
  el.innerHTML=`
    <div><label>Produto/Serviço</label><input type="text" placeholder="Código ou nome"/></div>
    <div><label>Qtd</label><input type="number" min="1" value="1"/></div>
    <div><label>Preço unitário</label><input type="number" min="0" step="0.01" placeholder="0,00"/></div>
    <div class="row"><button type="button" class="btn small danger">Remover</button></div>`;
  el.querySelector('.btn.danger').addEventListener('click',()=>el.remove());
  return el;
}
document.getElementById('add-item').addEventListener('click',()=>wrap.appendChild(itemRow()));
wrap.appendChild(itemRow());
</script>



<?php
include_once ROOT_PATH . 'system/includes/footer.php';
?>