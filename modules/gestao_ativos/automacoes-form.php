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
  <h1>Automação & Regras — Cadastro</h1>
  <div class="actions"><a class="btn" href="automacoes-listar.html">Listar regras</a></div>
</header>

<form class="card" onsubmit="return false">
  <p class="subtitle">Definição</p>
  <div class="grid cols-4">
    <div><label>Nome da regra</label><input type="text" placeholder="Descreva o objetivo"/></div>
    <div><label>Status</label><select><option>Ativa</option><option>Inativa</option></select></div>
    <div><label>Tipo</label><select><option>Evento</option><option>Condicional</option><option>Escalonamento</option><option>Notificação</option><option>Integração</option></select></div>
    <div><label>Escopo</label><select><option>Global</option><option>Entidade</option><option>Local</option></select></div>
  </div>
  <div class="divider"></div>
  <p class="subtitle">Condições</p>
  <div id="conds" class="stack"></div>
  <button type="button" class="btn small" id="add-cond">+ Adicionar condição</button>
  <div class="divider"></div>
  <p class="subtitle">Ações</p>
  <div id="acts" class="stack"></div>
  <button type="button" class="btn small" id="add-act">+ Adicionar ação</button>
  <div class="divider"></div>
  <div class="row" style="justify-content:flex-end;gap:10px">
    <button class="btn">Salvar rascunho</button>
    <button class="btn primary">Publicar</button>
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
function condRow(){
  const el=document.createElement('div'); el.className='grid cols-4'; el.style.alignItems='end';
  el.innerHTML=`
    <div><label>Campo</label><input type="text" placeholder="ex.: OS.status"/></div>
    <div><label>Operador</label><select><option>=</option><option>!=</option><option>&gt;</option><option>&lt;</option></select></div>
    <div><label>Valor</label><input type="text" placeholder="Aguardando peça"/></div>
    <div class="row"><button type="button" class="btn small danger">Remover</button></div>`;
  el.querySelector('.btn.danger').addEventListener('click',()=>el.remove()); return el;
}
function actRow(){
  const el=document.createElement('div'); el.className='grid cols-4'; el.style.alignItems='end';
  el.innerHTML=`
    <div><label>Ação</label><select><option>Enviar e-mail</option><option>Mover para fila</option><option>Mudar status</option><option>Abrir OS</option><option>Criar OC</option><option>Webhook</option></select></div>
    <div><label>Parâmetros</label><input type="text" placeholder="destinatário/URL/campos"/></div>
    <div><label>Assunto/Label</label><input type="text" placeholder="opcional"/></div>
    <div class="row"><button type="button" class="btn small danger">Remover</button></div>`;
  el.querySelector('.btn.danger').addEventListener('click',()=>el.remove()); return el;
}
const cwrap=document.getElementById('conds'), awrap=document.getElementById('acts');
document.getElementById('add-cond').addEventListener('click',()=>cwrap.appendChild(condRow()));
document.getElementById('add-act').addEventListener('click',()=>awrap.appendChild(actRow()));
cwrap.appendChild(condRow()); awrap.appendChild(actRow());
</script>








<?php
include_once ROOT_PATH . 'system/includes/footer.php';
?>