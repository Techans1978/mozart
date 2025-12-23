<?php
// modules/dmn/dmn_categories.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();
?>

</head>
<body>

<?php
include_once ROOT_PATH . 'system/includes/head.php';
?>

<style>
  :root{ --bg:#f6f7f9; --card:#fff; --bd:#e5e7eb; --txt:#111; }
  *{ box-sizing:border-box; }
  body{ margin:0; font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial; background:var(--bg); color:var(--txt); }
  .top{ padding:12px; border-bottom:1px solid var(--bd); background:#fff; display:flex; gap:10px; align-items:center; flex-wrap:wrap; position:sticky; top:0; z-index:10; }
  .brand{ font-weight:900; }
  .spacer{ flex:1; }
  .btn{ border:1px solid #d1d5db; background:#fff; padding:8px 12px; border-radius:10px; font-weight:800; cursor:pointer; }
  .btn:hover{ background:#f3f4f6; }
  .btn.primary{ background:#111827; border-color:#111827; color:#fff; }
  .btn.danger{ background:#b91c1c; border-color:#b91c1c; color:#fff; }
  .wrap{ padding:12px; }
  .grid{ display:grid; grid-template-columns: 430px 1fr; gap:12px; }
  .card{ background:var(--card); border:1px solid var(--bd); border-radius:12px; padding:12px; }
  label{ display:block; font-weight:900; margin:10px 0 6px; color:#111827; }
  input, select{ width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px; }
  .hint{ color:#6b7280; font-size:12px; }
  table{ width:100%; border-collapse:separate; border-spacing:0; }
  th, td{ text-align:left; padding:10px; border-bottom:1px solid var(--bd); vertical-align:top; }
  th{ font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; }
  .muted{ color:#6b7280; font-size:12px; }
  .rowactions{ display:flex; gap:8px; flex-wrap:wrap; }
  @media (max-width: 1100px){ .grid{ grid-template-columns: 1fr; } }
</style>


// (se o seu navbar ficar dentro do head/footer, não precisa incluir aqui)
<?php
include_once ROOT_PATH . 'system/includes/navbar.php';
?>


<!-- Incio Page Content -->
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

    <div class="row">
      <div class="col-lg-12">
<!-- Meio Page Content -->


<div class="top">
  <div class="brand">Mozart — DMN Categorias</div>
  <div class="spacer"></div>
  <a class="btn" href="dmn_list.php">Voltar</a>
  <button class="btn primary" id="btnNew">+ Nova categoria</button>
</div>

<div class="wrap">
  <div class="grid">
    <div class="card">
      <div style="font-weight:900;margin-bottom:8px">Cadastro / Edição</div>

      <input type="hidden" id="id" value="0">

      <label>Nome</label>
      <input id="name" placeholder="ex: TI / Aprovação / SLA">

      <label>Slug (opcional)</label>
      <input id="slug" placeholder="se vazio, gera automático">

      <div class="row" style="display:flex; gap:10px">
        <div style="flex:1">
          <label>Parent</label>
          <select id="parent_id">
            <option value="">(nenhum)</option>
          </select>
        </div>
        <div style="width:120px">
          <label>Ordem</label>
          <input id="sort" type="number" value="0">
        </div>
      </div>

      <div class="row" style="display:flex; gap:10px">
        <div style="flex:1">
          <label>Ícone (opcional)</label>
          <input id="icon" placeholder="ex: fa fa-folder">
        </div>
        <div style="width:140px">
          <label>Ativo</label>
          <select id="is_active">
            <option value="1">Sim</option>
            <option value="0">Não</option>
          </select>
        </div>
      </div>

      <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
        <button class="btn primary" id="btnSave">Salvar</button>
        <button class="btn danger" id="btnDelete">Excluir</button>
        <button class="btn" id="btnClear">Limpar</button>
      </div>

      <div class="hint" style="margin-top:10px">
        Dica: não deixa excluir categoria com decisões vinculadas (a API já bloqueia).
      </div>
    </div>

    <div class="card">
      <div style="font-weight:900;margin-bottom:8px">Lista</div>

      <table>
        <thead>
          <tr>
            <th>Nome</th>
            <th>Slug</th>
            <th>Parent</th>
            <th>Ativo</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody id="tbody">
          <tr><td colspan="5" class="muted">Carregando…</td></tr>
        </tbody>
      </table>
    </div>

  </div>
</div>

<!-- Meio Page Content -->
      </div>
    </div>
  </div>
</div>
<!-- Fim Page Content -->


<?php
// carrega seus scripts globais + Camunda JS (inserido no code_footer.php)
include_once ROOT_PATH . 'system/includes/code_footer.php';
?>

<script>
const API = {
  categories: 'api/categories.php?action=list',
  save: 'api/categories.php',
  del: 'api/categories.php'
};
const $ = (s)=>document.querySelector(s);

async function apiGet(url){
  const r = await fetch(url, { credentials:'same-origin' });
  const j = await r.json().catch(()=>null);
  if (!r.ok || !j || j.ok === false) throw new Error(j?.error || ('HTTP '+r.status));
  return j;
}
async function apiPost(url, payload){
  const r = await fetch(url, {
    method:'POST',
    credentials:'same-origin',
    headers:{'Content-Type':'application/json; charset=utf-8'},
    body: JSON.stringify(payload||{})
  });
  const j = await r.json().catch(()=>null);
  if (!r.ok || !j || j.ok === false) throw new Error(j?.error || ('HTTP '+r.status));
  return j;
}
function esc(s){
  return (s??'').toString()
    .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
    .replaceAll('"','&quot;').replaceAll("'","&#039;");
}

let cats = [];

function clearForm(){
  $('#id').value = 0;
  $('#name').value = '';
  $('#slug').value = '';
  $('#parent_id').value = '';
  $('#icon').value = '';
  $('#sort').value = 0;
  $('#is_active').value = 1;
}

function fillParents(){
  const sel = $('#parent_id');
  sel.innerHTML = `<option value="">(nenhum)</option>`;
  for (const c of cats) {
    const opt = document.createElement('option');
    opt.value = c.id;
    opt.textContent = `${c.name} (#${c.id})`;
    sel.appendChild(opt);
  }
}

function parentName(pid){
  if (!pid) return '';
  const p = cats.find(x => String(x.id) === String(pid));
  return p ? p.name : '';
}

function render(){
  fillParents();

  const tb = $('#tbody');
  if (!cats.length) {
    tb.innerHTML = `<tr><td colspan="5" class="muted">Sem categorias.</td></tr>`;
    return;
  }

  tb.innerHTML = cats.map(c => `
    <tr>
      <td>
        <div style="font-weight:900">${esc(c.name)}</div>
        <div class="muted">#${c.id}</div>
      </td>
      <td>${esc(c.slug)}</td>
      <td>${esc(parentName(c.parent_id))}</td>
      <td>${c.is_active == 1 ? 'Sim' : 'Não'}</td>
      <td>
        <div class="rowactions">
          <button class="btn" onclick="editCat(${c.id})">Editar</button>
        </div>
      </td>
    </tr>
  `).join('');
}

window.editCat = (id) => {
  const c = cats.find(x => Number(x.id) === Number(id));
  if (!c) return;
  $('#id').value = c.id;
  $('#name').value = c.name || '';
  $('#slug').value = c.slug || '';
  $('#parent_id').value = (c.parent_id ?? '') + '';
  $('#icon').value = c.icon || '';
  $('#sort').value = c.sort || 0;
  $('#is_active').value = c.is_active ?? 1;
  window.scrollTo({ top:0, behavior:'smooth' });
};

async function load(){
  const data = await apiGet(API.categories);
  cats = data.items || [];
  render();
}

async function save(){
  const id = parseInt($('#id').value,10) || 0;
  const payload = {
    action:'save',
    id,
    name: $('#name').value.trim(),
    slug: $('#slug').value.trim(),
    parent_id: $('#parent_id').value !== '' ? parseInt($('#parent_id').value,10) : null,
    icon: $('#icon').value.trim(),
    sort: parseInt($('#sort').value,10) || 0,
    is_active: parseInt($('#is_active').value,10) || 0
  };
  if (!payload.name) return alert('Informe o nome.');

  try{
    await apiPost(API.save, payload);
    await load();
    clearForm();
    alert('Salvo!');
  }catch(e){
    alert('Erro: ' + e.message);
  }
}

async function del(){
  const id = parseInt($('#id').value,10) || 0;
  if (!id) return alert('Selecione uma categoria para excluir.');
  if (!confirm('Excluir esta categoria?')) return;
  try{
    await apiPost(API.del, { action:'delete', id });
    await load();
    clearForm();
    alert('Excluído!');
  }catch(e){
    alert('Erro: ' + e.message);
  }
}

$('#btnNew').onclick = ()=>{ clearForm(); window.scrollTo({top:0, behavior:'smooth'}); };
$('#btnClear').onclick = clearForm;
$('#btnSave').onclick = save;
$('#btnDelete').onclick = del;

(async function init(){
  await load();
  clearForm();
})();
</script>

<?php
include_once ROOT_PATH . 'system/includes/footer.php';
?>
