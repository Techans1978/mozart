<?php
// modules/bpm/processos-listar.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

include_once ROOT_PATH . '/system/includes/head.php';
include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<style>
  #page-wrapper{ background:#f6f7f9; }
  .shell{max-width:1180px;margin:10px auto;padding:0 10px;}
  .filters{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0}
  .list{background:#fff;border:1px solid #e5e7eb;border-radius:12px}
  table{width:100%}
  thead th{background:#f3f4f6;border-bottom:1px solid #e5e7eb;padding:10px}
  tbody td{border-bottom:1px solid #f3f4f6;padding:10px;vertical-align:middle}
  .badge{padding:3px 8px;border-radius:999px;border:1px solid #e5e7eb;background:#fafafa}
  .actions a{margin-right:8px}
</style>

<div class="shell">
  <h2>Processos</h2>

  <div class="filters">
    <input id="f-nome" class="form-control" placeholder="Nome do processo">
    <select id="f-status" class="form-control">
      <option value="">Status (todos)</option>
      <option value="draft">Rascunho</option>
      <option value="published">Publicado</option>
      <option value="disabled">Desativado</option>
    </select>
    <input id="f-categoria" class="form-control" placeholder="Categoria">
    <button class="btn btn-primary" onclick="load()">Buscar</button>
  </div>

  <div class="list card">
    <table>
      <thead>
        <tr>
          <th style="width:40%">Processo</th>
          <th>Versão</th>
          <th>Categoria</th>
          <th>Status</th>
          <th style="width:220px">Ações</th>
        </tr>
      </thead>
      <tbody id="tbody"></tbody>
    </table>
  </div>
</div>

<script>
function esc(s){return (''+(s??'')).replace(/[&<>\"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]))}
async function fetchJSON(u){const r=await fetch(u,{credentials:'same-origin'});return await r.json();}

async function load(page=1){
  const nome = encodeURIComponent(document.getElementById('f-nome').value||'');
  const st   = encodeURIComponent(document.getElementById('f-status').value||'');
  const cat  = encodeURIComponent(document.getElementById('f-categoria').value||'');
  const url  = '<?= BASE_URL ?>/modules/bpm/api/process_list.php?name='+nome+'&status='+st+'&category='+cat+'&page='+page;
  const data = await fetchJSON(url);
  const rows = (data.items||[]).map(p => `
    <tr>
      <td><b>${esc(p.name)}</b><br><small>${esc(p.description||'')}</small></td>
      <td><span class="badge">${esc(p.semver||'-')}</span></td>
      <td>${esc(p.category||'-')}</td>
      <td>${esc(p.status||'-')}</td>
      <td class="actions">
        <a class="btn btn-xs btn-default" href="<?= BASE_URL ?>/modules/bpm/wizard/index.php?process_id=${encodeURIComponent(p.id||'')}">Editar</a>
        ${p.status==='published'
          ? `<a class="btn btn-xs btn-warning" href="#" onclick="toggle('${p.id}','disabled')">Desativar</a>`
          : `<a class="btn btn-xs btn-success" href="#" onclick="toggle('${p.id}','published')">Ativar</a>`}
        <a class="btn btn-xs btn-danger" href="#" onclick="removeProc('${p.id}')">Excluir</a>
      </td>
    </tr>`).join('');
  document.getElementById('tbody').innerHTML = rows || `<tr><td colspan="5" class="text-muted">Nada encontrado.</td></tr>`;
}

async function toggle(id, target){
  // endpoint futuro / stub:
  alert('Alterar status para: '+target+' (implementar API de status do processo)');
}
async function removeProc(id){
  if(!confirm('Confirmar exclusão do processo?')) return;
  alert('Excluir processo '+id+' (implementar API de exclusão)');
}
load();
</script>
<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>
