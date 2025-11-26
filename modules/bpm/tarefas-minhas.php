<?php
// modules/bpm/tarefas-minhas.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();
$user_id = (int)($_SESSION['user_id'] ?? 0);

include_once ROOT_PATH . '/system/includes/head.php';
include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<style>
  #page-wrapper{ background:#f6f7f9; }
  .shell{max-width:1180px;margin:10px auto;padding:0 10px;}
  .list{background:#fff;border:1px solid #e5e7eb;border-radius:12px}
  table{width:100%}
  thead th{background:#f3f4f6;border-bottom:1px solid #e5e7eb;padding:10px}
  tbody td{border-bottom:1px solid #f3f4f6;padding:10px;vertical-align:middle}
  .sla{padding:2px 6px;border-radius:999px;border:1px solid #e5e7eb}
</style>

<div class="shell">
  <h2>Minhas tarefas</h2>
  <div class="list card">
    <table>
      <thead>
        <tr>
          <th>Processo</th>
          <th>Atividade</th>
          <th>Instância</th>
          <th>SLA</th>
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

async function load(){
  const data = await fetchJSON('<?= BASE_URL ?>/modules/bpm/api/task_list_my.php');
  const rows = (data.items||[]).map(t=>`
    <tr>
      <td>${esc(t.process_name||'-')}</td>
      <td><b>${esc(t.name||t.node_id||'-')}</b></td>
      <td>#${esc(t.instance_id)}</td>
      <td><span class="sla">${esc(t.due_at||'—')}</span></td>
      <td>
        ${!t.assignee_user_id? `<a class="btn btn-xs btn-default" href="#" onclick="claim(${t.id})">Assumir</a>`:''}
        <a class="btn btn-xs btn-success" href="#" onclick="complete(${t.id})">Concluir</a>
        <a class="btn btn-xs btn-outline" href="<?= BASE_URL ?>/modules/bpm/instancia-detalhes.php?id=${encodeURIComponent(t.instance_id)}">Abrir</a>
      </td>
    </tr>`).join('');
  document.getElementById('tbody').innerHTML = rows || `<tr><td colspan="5" class="text-muted">Sem tarefas pendentes.</td></tr>`;
}

async function claim(id){
  const r = await fetch('<?= BASE_URL ?>/modules/bpm/api/task_claim.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id='+encodeURIComponent(id)});
  const d = await r.json();
  if(!d.ok) alert(d.error||'Erro ao assumir');
  await load();
}
async function complete(id){
  const r = await fetch('<?= BASE_URL ?>/modules/bpm/api/task_complete.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id='+encodeURIComponent(id)});
  const d = await r.json();
  if(!d.ok) alert(d.error||'Erro ao concluir');
  await load();
}

load();
</script>
<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>
