<?php
// modules/dmn/dmn_list.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();


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
  .wrap{ padding:12px; }
  .card{ background:var(--card); border:1px solid var(--bd); border-radius:12px; padding:12px; }
  .filters{ display:grid; grid-template-columns: 1fr 220px 240px 200px auto; gap:10px; align-items:end; }
  label{ display:block; font-weight:800; margin:0 0 6px; color:#111827; }
  input, select{ width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px; }
  table{ width:100%; border-collapse:separate; border-spacing:0; margin-top:12px; }
  th, td{ text-align:left; padding:10px; border-bottom:1px solid var(--bd); vertical-align:top; }
  th{ font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; }
  .muted{ color:#6b7280; font-size:12px; }
  .pill{ display:inline-flex; padding:3px 10px; border-radius:999px; font-weight:900; font-size:12px; border:1px solid var(--bd); background:#fff; }
  .pill.draft{ border-color:#fde68a; }
  .pill.published{ border-color:#86efac; }
  .pill.archived{ border-color:#fecaca; }
  .actions{ display:flex; gap:8px; flex-wrap:wrap; }
  .linkbtn{ text-decoration:none; }
  .empty{ padding:14px; text-align:center; color:#6b7280; }
  @media (max-width: 1100px){
    .filters{ grid-template-columns: 1fr 1fr; }
  }
</style>

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
  <div class="brand">Mozart — DMN Biblioteca</div>
  <div class="spacer"></div>
  <a class="btn primary linkbtn" href="dmn_editor.php">+ Nova decisão</a>
  <a class="btn linkbtn" href="dmn_categories.php">Categorias</a>
</div>

<div class="wrap">
  <div class="card">
    <div class="filters">
      <div>
        <label>Buscar</label>
        <input id="q" placeholder="nome ou rule_key">
      </div>
      <div>
        <label>Status</label>
        <select id="status">
          <option value="">(todos)</option>
          <option value="draft">draft</option>
          <option value="published">published</option>
          <option value="archived">archived</option>
        </select>
      </div>
      <div>
        <label>Categoria</label>
        <select id="category_id">
          <option value="">(todas)</option>
        </select>
      </div>
      <div>
        <label>Tag</label>
        <input id="tag" placeholder="ex: ti">
      </div>
      <div>
        <button class="btn" id="btnSearch">Filtrar</button>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Decisão</th>
          <th>Rule Key</th>
          <th>Categoria</th>
          <th>Status</th>
          <th>Atualizado</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody id="tbody">
        <tr><td colspan="6" class="empty">Carregando…</td></tr>
      </tbody>
    </table>
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
  categories:  'api/categories.php?action=list&active=1',
  list:        'api/decision_list.php'
};
const $ = (s)=>document.querySelector(s);

async function apiGet(url){
  const r = await fetch(url, { credentials:'same-origin' });
  const j = await r.json().catch(()=>null);
  if (!r.ok || !j || j.ok === false) throw new Error(j?.error || ('HTTP '+r.status));
  return j;
}

function pill(status){
  const cls = status || 'draft';
  return `<span class="pill ${cls}">${cls}</span>`;
}

function esc(s){
  return (s??'').toString()
    .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
    .replaceAll('"','&quot;').replaceAll("'","&#039;");
}

async function loadCategories(){
  try{
    const data = await apiGet(API.categories);
    const sel = $('#category_id');
    for (const c of (data.items||[])) {
      const opt = document.createElement('option');
      opt.value = c.id;
      opt.textContent = c.name;
      sel.appendChild(opt);
    }
  }catch(e){ console.warn(e); }
}

function buildUrl(){
  const u = new URL(API.list, window.location.href);
  const q = $('#q').value.trim();
  const st = $('#status').value;
  const cat = $('#category_id').value;
  const tag = $('#tag').value.trim();

  if (q) u.searchParams.set('q', q);
  if (st) u.searchParams.set('status', st);
  if (cat) u.searchParams.set('category_id', cat);
  if (tag) u.searchParams.set('tag', tag);

  return u.toString();
}

async function search(){
  $('#tbody').innerHTML = `<tr><td colspan="6" class="empty">Carregando…</td></tr>`;
  try{
    const data = await apiGet(buildUrl());
    const items = data.items || [];
    if (!items.length) {
      $('#tbody').innerHTML = `<tr><td colspan="6" class="empty">Nenhum resultado.</td></tr>`;
      return;
    }
    $('#tbody').innerHTML = items.map(d => {
      return `
      <tr>
        <td>
          <div style="font-weight:900">${esc(d.name)}</div>
          <div class="muted">#${d.id}</div>
        </td>
        <td>
          <div style="font-weight:800">${esc(d.rule_key)}</div>
          ${d.description ? `<div class="muted">${esc(d.description)}</div>` : ``}
        </td>
        <td>${esc(d.category_name || '')}</td>
        <td>${pill(d.status)}</td>
        <td>${esc(d.updated_at || '')}</td>
        <td>
          <div class="actions">
            <a class="btn linkbtn" href="dmn_editor.php?id=${d.id}">Editar</a>
            <a class="btn linkbtn" href="dmn_versions.php?id=${d.id}">Versões</a>
          </div>
        </td>
      </tr>`;
    }).join('');
  }catch(e){
    $('#tbody').innerHTML = `<tr><td colspan="6" class="empty">Erro: ${esc(e.message)}</td></tr>`;
  }
}

$('#btnSearch').onclick = search;
window.addEventListener('keydown', (e)=>{
  if (e.key === 'Enter') search();
});

(async function init(){
  await loadCategories();
  // se quiser manter filtro na URL futuramente, dá pra ler query string aqui
  await search();
})();
</script>

<?php
include_once ROOT_PATH . 'system/includes/footer.php';
?>
