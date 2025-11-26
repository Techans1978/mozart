<?php
// modules/bpm/instancia-detalhes.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id<=0) { http_response_code(400); die('Instância inválida.'); }

include_once ROOT_PATH . '/system/includes/head.php';
include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<style>
  :root{ --chip:#e5e7eb; --ok:#10b981; --warn:#f59e0b; --bad:#ef4444;}
  #page-wrapper{ background:#f6f7f9; }
  .shell{max-width:1100px;margin:10px auto;padding:0 10px;}
  .header{display:flex;gap:10px;align-items:center;justify-content:space-between;margin:10px 0;}
  .kpis{display:flex;gap:10px;flex-wrap:wrap}
  .kpi{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:10px 14px;min-width:160px}
  .tabs{display:flex;gap:6px;margin:14px 0}
  .tabs a{padding:8px 12px;border-radius:999px;background:#eef2ff;border:1px solid #c7d2fe;text-decoration:none;color:#111827}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin-bottom:10px}
  .timeline{display:flex;flex-direction:column;gap:8px}
  .evt{padding:8px 10px;border-left:4px solid #e5e7eb;background:#f9fafb;border-radius:8px}
  .evt .meta{color:#6b7280;font-size:12px;margin-bottom:4px}
  .toolbar{display:flex;gap:8px;justify-content:flex-end}
</style>

<div class="shell">
  <div class="header">
    <h2>Instância #<?= (int)$id ?></h2>
    <div class="toolbar">
      <a class="btn btn-sm btn-outline" href="<?= BASE_URL ?>/modules/bpm/api/diagram_svg.php?instance_id=<?= (int)$id ?>" target="_blank">Visualizar diagrama</a>
      <a class="btn btn-sm btn-outline" href="#" onclick="exportar()">Exportar JSON</a>
    </div>
  </div>

  <div id="summary" class="kpis"></div>

  <div class="tabs">
    <a href="#" onclick="openTab('info');return false;">Informações</a>
    <a href="#" onclick="openTab('hist');return false;">Histórico</a>
    <a href="#" onclick="openTab('vars');return false;">Variáveis</a>
    <a href="#" onclick="openTab('anx');return false;">Anexos</a>
  </div>

  <div id="tab-info" class="card"></div>
  <div id="tab-hist" class="card" style="display:none;">
    <div id="history" class="timeline"></div>
  </div>
  <div id="tab-vars" class="card" style="display:none;">
    <div id="vars"></div>
  </div>
  <div id="tab-anx" class="card" style="display:none;">
    <div id="anexos"></div>
  </div>
</div>

<script>
function openTab(t){
  ['info','hist','vars','anx'].forEach(k=>document.getElementById('tab-'+k).style.display = (k===t?'block':'none'));
}
async function fetchJSON(url){ const r = await fetch(url, {credentials:'same-origin'}); return await r.json(); }
function esc(s){return (''+(s??'')).replace(/[&<>\"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]))}

async function load(){
  const id = <?= (int)$id ?>;

  // Resumo + atividades atuais
  const sum = await fetchJSON('<?= BASE_URL ?>/modules/bpm/api/instance_summary.php?id='+id);
  if (sum.ok){
    const i = sum.instance||{};
    document.getElementById('summary').innerHTML = `
      <div class='kpi'><div><b>Processo</b></div><div>${esc(i.process_name||'-')}</div></div>
      <div class='kpi'><div><b>Versão</b></div><div>${esc(i.semver||'-')}</div></div>
      <div class='kpi'><div><b>Status</b></div><div>${esc(i.status||'-')}</div></div>
      <div class='kpi'><div><b>Início</b></div><div>${esc(i.started_at||'-')}</div></div>
      <div class='kpi'><div><b>Fim</b></div><div>${esc(i.ended_at||'-')}</div></div>`;

    document.getElementById('tab-info').innerHTML = `
      <div><b>Atividades atuais</b></div>
      ${(sum.tasks||[]).map(t=>`
        <div class='evt'>
          <div class='meta'>${esc(t.created_at)} · ${esc(t.status)}</div>
          <div><b>${esc(t.node_id)}</b> (${esc(t.type)})</div>
          ${t.due_at?`<div>SLA: ${esc(t.due_at)}</div>`:''}
        </div>
      `).join('') || '<div class="text-muted">Nenhuma tarefa pendente.</div>'}`;
  }

  // Histórico
  const hist = await fetchJSON('<?= BASE_URL ?>/modules/bpm/api/instance_history.php?id='+id+'&limit=200');
  if (hist.ok){
    document.getElementById('history').innerHTML = (hist.items||[]).map(e=>`
      <div class='evt'>
        <div class='meta'>${esc(e.event_time)} · ${esc(e.event_type)}</div>
        <div>${esc(JSON.stringify(e.data)||'')}</div>
      </div>`).join('');
  }

  // Variáveis
  const vrs = await fetchJSON('<?= BASE_URL ?>/modules/bpm/api/instance_variables.php?id='+id);
  if (vrs.ok){
    document.getElementById('vars').innerHTML = (vrs.vars||[]).map(v=>`
      <div class='evt'>
        <div class='meta'>${esc(v.updated_at)} · ${esc(v.type)} (${esc(v.scope)})</div>
        <div><b>${esc(v.key)}</b>: <code>${esc(JSON.stringify(v.value))}</code></div>
      </div>`).join('');
  }

  // Anexos (stub)
  const anx = await fetchJSON('<?= BASE_URL ?>/modules/bpm/api/instance_attachments.php?id='+id);
  if (anx.ok){
    document.getElementById('anexos').innerHTML = (anx.items||[]).length ? 'Arquivos listados aqui.' : '<div class="text-muted">Sem anexos.</div>';
  }
}
function exportar(){
  const id = <?= (int)$id ?>;
  window.open('<?= BASE_URL ?>/modules/bpm/api/instance_history.php?id='+id+'&limit=1000', '_blank');
}
load();
</script>
<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>
