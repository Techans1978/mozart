<?php
// public/modules/helpdesk/pages/admin/forms.php
ini_set('display_errors',1); ini_set('startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

$db = $conn ?? ($mysqli ?? null);
if (!$db || !($db instanceof mysqli)) { http_response_code(500); die('Sem conexão DB.'); }

$csrf = bin2hex(random_bytes(16));
$_SESSION['csrf_hd_admin'] = $csrf;

@include_once ROOT_PATH . '/modules/helpdesk/includes/head_hd.php';
?>
<style>
  .wrap { display:grid; grid-template-columns: 320px 1fr; gap:12px; }
  .card { background:#fff; border-radius:12px; box-shadow:0 1px 0 rgba(0,0,0,.06); padding:12px; }
  textarea, input, select { width:100%; border:1px solid #e5e7eb; border-radius:8px; padding:8px 10px; }
  .btn { border:1px solid #e5e7eb; padding:6px 10px; border-radius:8px; background:#fff; cursor:pointer;}
  .btn.primary { background:#2563eb; color:#fff; border-color:#2563eb; }
  .row { display:flex; gap:8px; flex-wrap:wrap; }
</style>

<div id="page-wrapper" class="container-fluid">
  <h3>Admin • Forms (visual)</h3>
  <div class="wrap">
    <div class="card">
      <div class="row">
        <input id="q" placeholder="Buscar form..." />
        <button class="btn" onclick="loadForms()">Buscar</button>
      </div>
      <div id="list" style="margin-top:10px;"></div>
      <hr>
      <button class="btn primary" onclick="novo()">Novo Form</button>
    </div>
    <div class="card">
      <div class="row">
        <input id="form_nome" placeholder="Nome do Form" />
        <select id="form_categoria"><option value="">Categoria</option></select>
        <select id="form_status">
          <option value="rascunho">Rascunho</option>
          <option value="publicado">Publicado</option>
          <option value="arquivado">Arquivado</option>
        </select>
      </div>
      <label>Schema (JSON)</label>
      <textarea id="form_schema" style="min-height:300px;" placeholder='{"type":"object","properties":{}}'></textarea>
      <div class="row" style="margin-top:8px;">
        <button class="btn" onclick="validar()">Validar JSON</button>
        <button class="btn primary" onclick="salvar()">Salvar Versão</button>
        <button class="btn" onclick="publicar()">Publicar</button>
        <button class="btn" onclick="despublicar()">Despublicar</button>
      </div>
      <div id="msg" style="margin-top:8px;color:#555;"></div>
    </div>
  </div>
</div>

<script>
const CSRF="<?php echo $csrf;?>";
let currentId = null;

function api(url, data={}, method='POST'){
  return fetch(url, {method, headers:{'Content-Type':'application/json'}, body: JSON.stringify({...data, csrf:CSRF})}).then(r=>r.json());
}

function itemHtml(f){
  return `<div style="padding:8px;border-bottom:1px solid #f1f1f1;cursor:pointer" onclick="abrir(${f.id})">
    <strong>${f.nome}</strong> <span style="font-size:12px;color:#777">(${f.status||'rascunho'})</span><br>
    <span style="font-size:12px;color:#777">Categoria: ${f.categoria||'—'}</span>
  </div>`;
}

function loadForms(){
  const q=document.getElementById('q').value.trim();
  api('/api/hd/admin/forms.php', {op:'list', q}).then(j=>{
    if(!j.success) return;
    document.getElementById('list').innerHTML = (j.data.items||[]).map(itemHtml).join('') || '<div>Nenhum form</div>';
  });
}

function novo(){
  currentId = null;
  document.getElementById('form_nome').value='';
  document.getElementById('form_categoria').value='';
  document.getElementById('form_status').value='rascunho';
  document.getElementById('form_schema').value='{"type":"object","properties":{}}';
  document.getElementById('msg').textContent='';
}

function abrir(id){
  api('/api/hd/admin/forms.php',{op:'get', id}).then(j=>{
    if(!j.success || !j.data) return;
    const f=j.data;
    currentId=f.id;
    document.getElementById('form_nome').value=f.nome||'';
    document.getElementById('form_categoria').value=f.categoria||'';
    document.getElementById('form_status').value=f.status||'rascunho';
    document.getElementById('form_schema').value=f.schema_json||'{"type":"object","properties":{}}';
  });
}

function validar(){
  try { JSON.parse(document.getElementById('form_schema').value); document.getElementById('msg').textContent='JSON OK.'; }
  catch(e){ document.getElementById('msg').textContent='JSON inválido: '+e.message; }
}

function salvar(){
  const nome = document.getElementById('form_nome').value.trim();
  if(!nome) { document.getElementById('msg').textContent='Informe o nome.'; return;}
  const payload = {
    op:'save',
    id: currentId,
    nome,
    categoria: document.getElementById('form_categoria').value,
    status: document.getElementById('form_status').value,
    schema_json: document.getElementById('form_schema').value
  };
  api('/api/hd/admin/forms.php', payload).then(j=>{
    document.getElementById('msg').textContent = j.success? 'Salvo.' : ('Erro: '+j.error);
    loadForms();
    if(j.data && j.data.id) currentId=j.data.id;
  });
}

function publicar(){
  if(!currentId) { document.getElementById('msg').textContent='Salve o form antes.'; return;}
  api('/api/hd/admin/forms.php', {op:'publish', id:currentId}).then(j=>{
    document.getElementById('msg').textContent = j.success? 'Publicado.' : ('Erro: '+j.error);
    loadForms();
  });
}

function despublicar(){
  if(!currentId) return;
  api('/api/hd/admin/forms.php', {op:'unpublish', id:currentId}).then(j=>{
    document.getElementById('msg').textContent = j.success? 'Despublicado.' : ('Erro: '+j.error);
    loadForms();
  });
}

loadForms();
</script>
<?php @include_once ROOT_PATH . '/modules/helpdesk/includes/footer_hd.php'; ?>
