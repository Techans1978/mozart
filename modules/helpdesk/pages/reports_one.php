<?php
// public/modules/helpdesk/pages/reports_pro.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
include_once ROOT_PATH . '/system/includes/head.php';
require_once ROOT_PATH.'/system/includes/head_hd.php';

proteger_pagina();
$user_id = $_SESSION['usuario_id'] ?? 0;

?>
<style>
  .heatmap { display:grid; grid-template-columns: 100px repeat(24, 1fr); gap:2px; }
  .heatmap .cell { min-height:24px; text-align:center; font-size:11px; line-height:24px; border-radius:4px; }
  .heat-legend { display:flex; gap:6px; align-items:center; }
  .heat-legend .box { width:18px; height:18px; border-radius:4px; }
</style>
<?php
include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<!-- layout -->
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

    <div class="row">
      <div class="col-lg-12">
	  <!-- Content -->

<div class="container-fluid">
  <h3 class="mt-3 mb-3">Relatórios Pro (Help Desk)</h3>

  <div class="card mb-3">
    <div class="card-body">
      <form id="form-range" class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Início</label>
          <input type="date" class="form-control" name="start" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Fim</label>
          <input type="date" class="form-control" name="end" required>
        </div>
        <div class="col-md-6">
          <button class="btn btn-primary me-2" type="submit">Atualizar</button>
          <button class="btn btn-outline-secondary" type="button" id="btn-preset-7">Últimos 7 dias</button>
          <button class="btn btn-outline-secondary" type="button" id="btn-preset-30">Últimos 30 dias</button>
        </div>
      </form>
    </div>
  </div>

  <div class="row">
    <!-- Comparativo -->
    <div class="col-lg-6">
      <div class="card mb-3">
        <div class="card-header">Comparativo (Período anterior / Ano anterior)</div>
        <div class="card-body">
          <div id="compare-box">
            <p class="text-muted">Selecione um período e clique em "Atualizar".</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Heatmap -->
    <div class="col-lg-6">
      <div class="card mb-3">
        <div class="card-header">Heatmap Dia x Hora</div>
        <div class="card-body">
          <div class="heat-legend mb-2">
            <span>Baixa</span>
            <div class="box" style="background:#eef;"></div>
            <div class="box" style="background:#cdd9ff;"></div>
            <div class="box" style="background:#9bb0ff;"></div>
            <div class="box" style="background:#6887ff;"></div>
            <div class="box" style="background:#365eff;"></div>
            <span>Alta</span>
          </div>
          <div id="heatmap"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Agendamentos -->
  <div class="card mb-5">
    <div class="card-header">Agendamentos de Relatórios</div>
    <div class="card-body">
      <form id="form-sched" class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Nome</label>
          <input type="text" class="form-control" name="name" placeholder="Resumo diário" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">Frequência</label>
          <select class="form-select" name="frequency" required>
            <option value="daily">Diário</option>
            <option value="weekly">Semanal</option>
            <option value="monthly">Mensal</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Destinatários (vírgula)</label>
          <input type="text" class="form-control" name="recipients" placeholder="ti@empresa.com,gestor@empresa.com" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Preset</label>
          <select class="form-select" name="range">
            <option value="last_7_days">Últimos 7 dias</option>
            <option value="last_30_days">Últimos 30 dias</option>
            <option value="this_month">Este mês</option>
            <option value="last_month">Mês anterior</option>
          </select>
        </div>
        <div class="col-12">
          <button class="btn btn-success">Adicionar Agendamento</button>
        </div>
      </form>

      <hr>
      <div id="sched-list"></div>
    </div>
  </div>
</div>
	  <!-- End content -->
      </div>
    </div>
  </div>
</div>
<!-- layout -->

<script>
function fmt(n){ return (n===null||n===undefined) ? '-' : n; }

function dateToStr(d){ return d.toISOString().slice(0,10); }

(function initPresets(){
  const end = new Date();
  const start7 = new Date(); start7.setDate(end.getDate()-6);
  const start30 = new Date(); start30.setDate(end.getDate()-29);

  document.getElementById('btn-preset-7').onclick = () => {
    document.querySelector('[name=start]').value = dateToStr(start7);
    document.querySelector('[name=end]').value = dateToStr(end);
    document.getElementById('form-range').dispatchEvent(new Event('submit'));
  };
  document.getElementById('btn-preset-30').onclick = () => {
    document.querySelector('[name=start]').value = dateToStr(start30);
    document.querySelector('[name=end]').value = dateToStr(end);
    document.getElementById('form-range').dispatchEvent(new Event('submit'));
  };
})();

document.getElementById('form-range').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  const q = new URLSearchParams(fd).toString();

  // compare
  const cmp = await fetch('/api/hd/reports/compare.php', {method:'POST', body: fd}).then(r=>r.json());
  const box = document.getElementById('compare-box');
  if (!cmp.success){
    box.innerHTML = `<div class="alert alert-danger">${cmp.message||'Erro'}</div>`;
  } else {
    const c = cmp.data.current, p = cmp.data.prev, y = cmp.data.yoy;
    box.innerHTML = `
      <div class="table-responsive">
        <table class="table table-sm table-bordered">
          <thead><tr>
            <th>Métrica</th><th>Atual</th><th>Período Anterior</th><th>Δ</th><th>Ano Anterior</th><th>Δ</th>
          </tr></thead>
          <tbody>
            <tr><td>Abertos</td><td>${fmt(c.opened)}</td><td>${fmt(p.opened)}</td><td>${fmt(c.opened - p.opened)}</td><td>${fmt(y.opened)}</td><td>${fmt(c.opened - y.opened)}</td></tr>
            <tr><td>Fechados</td><td>${fmt(c.closed)}</td><td>${fmt(p.closed)}</td><td>${fmt(c.closed - p.closed)}</td><td>${fmt(y.closed)}</td><td>${fmt(c.closed - y.closed)}</td></tr>
            <tr><td>TTO médio (h)</td><td>${fmt(c.tto_h)}</td><td>${fmt(p.tto_h)}</td><td>${fmt((c.tto_h - p.tto_h).toFixed(2))}</td><td>${fmt(y.tto_h)}</td><td>${fmt((c.tto_h - y.tto_h).toFixed(2))}</td></tr>
            <tr><td>TTR médio (h)</td><td>${fmt(c.ttr_h)}</td><td>${fmt(p.ttr_h)}</td><td>${fmt((c.ttr_h - p.ttr_h).toFixed(2))}</td><td>${fmt(y.ttr_h)}</td><td>${fmt((c.ttr_h - y.ttr_h).toFixed(2))}</td></tr>
            <tr><td>Backlog (fim)</td><td>${fmt(c.backlog_end)}</td><td>${fmt(p.backlog_end)}</td><td>${fmt(c.backlog_end - p.backlog_end)}</td><td>${fmt(y.backlog_end)}</td><td>${fmt(c.backlog_end - y.backlog_end)}</td></tr>
          </tbody>
        </table>
      </div>
    `;
  }

  // heatmap
  const heat = await fetch('/api/hd/reports/heatmap.php', {method:'POST', body: fd}).then(r=>r.json());
  const hm = document.getElementById('heatmap');
  if (!heat.success) {
    hm.innerHTML = `<div class="alert alert-danger">${heat.message||'Erro'}</div>`;
  } else {
    const data = heat.data; // {max, matrix[7][24]}
    const days = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
    let html = `<div class="heatmap">`;
    html += `<div></div>`; // canto
    for (let h=0; h<24; h++) html += `<div class="cell"><small>${h}h</small></div>`;
    for (let d=0; d<7; d++){
      html += `<div class="cell"><strong>${days[d]}</strong></div>`;
      for (let h=0; h<24; h++){
        const v = data.matrix[d][h]||0;
        const pct = data.max ? v / data.max : 0;
        // gradiente simples de azul claro a escuro
        const c1 = 238 - Math.floor(pct*80); // R e G
        const c2 = 255 - Math.floor(pct*200); // B
        const bg = `rgb(${c1},${c1},${c2})`;
        html += `<div class="cell" title="${v}" style="background:${bg}">${v>0?v:''}</div>`;
      }
    }
    html += `</div>`;
    hm.innerHTML = html;
  }
});

document.getElementById('form-sched').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.set('action','create');
  const r = await fetch('/api/hd/reports/schedule.php', {method:'POST', body:fd}).then(r=>r.json());
  if(!r.success){ alert(r.message||'Erro'); return; }
  e.target.reset();
  loadSched();
});

async function loadSched(){
  const fd = new FormData(); fd.set('action','list');
  const r = await fetch('/api/hd/reports/schedule.php', {method:'POST', body:fd}).then(r=>r.json());
  const box = document.getElementById('sched-list');
  if(!r.success){ box.innerHTML = `<div class="alert alert-danger">${r.message||'Erro'}</div>`; return; }
  const rows = r.data||[];
  let html = `<div class="table-responsive"><table class="table table-sm"><thead><tr>
    <th>ID</th><th>Nome</th><th>Freq.</th><th>Ativo</th><th>Próxima Execução</th><th>Última</th><th>Destinatários</th><th>Ações</th>
  </tr></thead><tbody>`;
  for (const s of rows){
    html += `<tr>
      <td>${s.id}</td>
      <td>${s.name}</td>
      <td>${s.frequency}</td>
      <td>${s.is_active?'Sim':'Não'}</td>
      <td>${s.next_run||'-'}</td>
      <td>${s.last_run||'-'}</td>
      <td><small>${(s.recipients||'').slice(0,80)}</small></td>
      <td>
        <button class="btn btn-sm btn-outline-secondary" onclick="toggleSched(${s.id},${s.is_active?0:1})">${s.is_active?'Desativar':'Ativar'}</button>
        <button class="btn btn-sm btn-outline-danger" onclick="deleteSched(${s.id})">Excluir</button>
      </td>
    </tr>`;
  }
  html += `</tbody></table></div>`;
  box.innerHTML = html;
}

async function toggleSched(id,active){
  const fd = new FormData(); fd.set('action','toggle'); fd.set('id',id); fd.set('is_active',active);
  const r = await fetch('/api/hd/reports/schedule.php',{method:'POST', body:fd}).then(r=>r.json());
  if(!r.success){ alert(r.message||'Erro'); return; }
  loadSched();
}
async function deleteSched(id){
  if(!confirm('Excluir agendamento?')) return;
  const fd = new FormData(); fd.set('action','delete'); fd.set('id',id);
  const r = await fetch('/api/hd/reports/schedule.php',{method:'POST', body:fd}).then(r=>r.json());
  if(!r.success){ alert(r.message||'Erro'); return; }
  loadSched();
}

document.addEventListener('DOMContentLoaded', ()=>{
  // preset: últimos 7 dias
  document.getElementById('btn-preset-7').click();
  loadSched();
});
</script>

<?php require_once ROOT_PATH . '/system/includes/footer_hd.php'; ?>
