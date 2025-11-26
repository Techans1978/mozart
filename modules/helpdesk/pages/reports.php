<?php
// public/modules/helpdesk/pages/reports.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

$db = $conn ?? ($mysqli ?? null);
if (!$db || !($db instanceof mysqli)) { http_response_code(500); die('Sem conexão DB.'); }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function has_col(mysqli $db,$t,$c){
  $s=$db->prepare("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
  $s->bind_param('ss',$t,$c); $s->execute(); $r=$s->get_result()->fetch_assoc(); return (int)$r['c']>0;
}

// Precarrega opções simples (grupos/lojas) se existirem
$tem_grupo = has_col($db,'hd_ticket','grupo_atual_id');
$tem_loja  = has_col($db,'hd_ticket','loja_id');

// Você pode ajustar para buscar nomes reais em suas tabelas (soft-FK):
$grupos = [];
if ($tem_grupo) {
  // se tiver tabela de grupos, troque por SELECT id,nome FROM hd_grupo ...
  // aqui vamos derivar do próprio ticket (distintos)
  $q = $db->query("SELECT DISTINCT grupo_atual_id AS id FROM hd_ticket WHERE grupo_atual_id IS NOT NULL ORDER BY grupo_atual_id");
  while($o=$q->fetch_assoc()){ $grupos[] = ['id'=>$o['id'],'nome'=>'Grupo #'.$o['id']]; }
}
$lojas = [];
if ($tem_loja) {
  $q = $db->query("SELECT DISTINCT loja_id AS id FROM hd_ticket WHERE loja_id IS NOT NULL ORDER BY loja_id");
  while($o=$q->fetch_assoc()){ $lojas[] = ['id'=>$o['id'],'nome'=>'Loja #'.$o['id']]; }
}

@include_once ROOT_PATH . '/modules/helpdesk/includes/head_hd.php';
?>
<style>
  :root { --gap:12px; --card-r:14px; --muted:#6b7280; --line:#e5e7eb; }
  #page-wrapper { background:#f6f7f9; }
  .toolbar{display:flex;gap:var(--gap);align-items:end;flex-wrap:wrap;margin-bottom:12px}
  .f{display:flex;flex-direction:column;gap:6px}
  .f>label{font-size:12px;color:var(--muted)}
  .f>input,.f>select{border:1px solid var(--line);border-radius:10px;padding:8px 10px;background:#fff;min-width:180px}
  .btn{border:1px solid var(--line);border-radius:10px;background:#fff;padding:8px 12px;cursor:pointer}
  .btn.primary{background:#2563eb;color:#fff;border-color:#2563eb}
  .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:var(--gap)}
  .card{background:#fff;border-radius:var(--card-r);box-shadow:0 1px 0 rgba(0,0,0,.06);padding:14px}
  .kpi{display:flex;align-items:center;justify-content:space-between}
  .kpi .v{font-size:26px;font-weight:700}
  .muted{color:var(--muted);font-size:12px}
  .table{width:100%;border-collapse:collapse}
  .table th,.table td{border-bottom:1px solid var(--line);padding:8px;font-size:13px}
  .chips{display:flex;gap:6px;flex-wrap:wrap}
  .chip{background:#f3f4f6;border:1px solid #e5e7eb;border-radius:9999px;padding:4px 8px;font-size:12px}
  canvas{width:100%;height:160px}
</style>

<div id="page-wrapper" class="container-fluid">
  <div style="display:flex;align-items:center;justify-content:space-between;margin:14px 0 8px">
    <h3 style="margin:0">Relatórios & KPIs — Help Desk</h3>
    <div class="chips">
      <span class="chip">PARTE 8</span>
      <span class="chip">Mozart HD</span>
    </div>
  </div>

  <div class="toolbar">
    <div class="f">
      <label>De</label>
      <input type="date" id="f_de">
    </div>
    <div class="f">
      <label>Até</label>
      <input type="date" id="f_ate">
    </div>
    <div class="f">
      <label>Status</label>
      <select id="f_status">
        <option value="">(todos)</option>
        <option>novo</option><option>aberto</option><option>pendente</option>
        <option>resolvido</option><option>cancelado</option>
      </select>
    </div>
    <div class="f">
      <label>Prioridade</label>
      <select id="f_prio">
        <option value="">(todas)</option>
        <option>baixa</option><option>media</option><option>alta</option><option>urgente</option>
      </select>
    </div>
    <?php if($tem_grupo): ?>
    <div class="f">
      <label>Grupo</label>
      <select id="f_grupo">
        <option value="">(todos)</option>
        <?php foreach($grupos as $g): ?>
          <option value="<?php echo (int)$g['id'];?>"><?php echo h($g['nome']);?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <?php if($tem_loja): ?>
    <div class="f">
      <label>Loja</label>
      <select id="f_loja">
        <option value="">(todas)</option>
        <?php foreach($lojas as $l): ?>
          <option value="<?php echo (int)$l['id'];?>"><?php echo h($l['nome']);?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="f">
      <label>&nbsp;</label>
      <button class="btn primary" onclick="loadAll()">Aplicar</button>
    </div>
    <div class="f">
      <label>&nbsp;</label>
      <button class="btn" onclick="exportar()">Exportar CSV</button>
    </div>
  </div>

  <div class="grid">
    <div class="card" style="grid-column:span 3">
      <div class="kpi"><div>
        <div class="muted">Tickets no período</div>
        <div class="v" id="k_total">–</div>
      </div><div class="muted" id="k_total_hint"></div></div>
    </div>
    <div class="card" style="grid-column:span 3">
      <div class="kpi"><div>
        <div class="muted">Abertos agora</div>
        <div class="v" id="k_abertos">–</div>
      </div><div class="muted" id="k_abertos_hint"></div></div>
    </div>
    <div class="card" style="grid-column:span 3">
      <div class="kpi"><div>
        <div class="muted">TTO médio</div>
        <div class="v" id="k_tto">–</div>
      </div><div class="muted">min até 1ª resposta</div></div>
    </div>
    <div class="card" style="grid-column:span 3">
      <div class="kpi"><div>
        <div class="muted">TTR médio</div>
        <div class="v" id="k_ttr">–</div>
      </div><div class="muted">min até resolução</div></div>
    </div>

    <div class="card" style="grid-column:span 6">
      <div class="muted" style="margin-bottom:6px">Série (últimos 30 dias)</div>
      <canvas id="c_times"></canvas>
    </div>
    <div class="card" style="grid-column:span 6">
      <div class="muted" style="margin-bottom:6px">Distribuição por prioridade</div>
      <canvas id="c_prio"></canvas>
    </div>

    <div class="card" style="grid-column:span 6">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
        <div class="muted">SLA — rompimentos no período</div>
        <div class="muted" id="sla_hint"></div>
      </div>
      <table class="table">
        <thead><tr><th>Tipo</th><th>Qtd</th><th>Taxa</th></tr></thead>
        <tbody id="tb_sla"><tr><td colspan="3">–</td></tr></tbody>
      </table>
    </div>

    <div class="card" style="grid-column:span 6">
      <div class="muted" style="margin-bottom:6px">Carga por Grupo</div>
      <table class="table">
        <thead><tr><th>Grupo</th><th>Abertos</th><th>Novos (período)</th></tr></thead>
        <tbody id="tb_group"><tr><td colspan="3">–</td></tr></tbody>
      </table>
    </div>

    <div class="card" style="grid-column:span 12">
      <div class="muted" style="margin-bottom:6px">Produtividade por Agente</div>
      <table class="table">
        <thead><tr><th>Agente</th><th>Tickets em posse</th><th>Resolvidos (período)</th><th>TTR médio (min)</th></tr></thead>
        <tbody id="tb_agents"><tr><td colspan="4">–</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<script>
// util
function api(url, data={}, method='POST'){ return fetch(url,{method,headers:{'Content-Type':'application/json'},body: JSON.stringify(data)}).then(r=>r.json()); }
function q(id){ return document.getElementById(id); }
function params(){
  return {
    de: q('f_de').value || '',
    ate: q('f_ate').value || '',
    status: q('f_status').value || '',
    prio: q('f_prio').value || '',
    grupo: q('f_grupo')? q('f_grupo').value : '',
    loja: q('f_loja')? q('f_loja').value : ''
  };
}
// mini-chart (sem libs)
function drawBars(canvas, labels, series){
  const ctx = canvas.getContext('2d');
  const w = canvas.width = canvas.clientWidth * window.devicePixelRatio;
  const h = canvas.height = canvas.clientHeight * window.devicePixelRatio;
  ctx.clearRect(0,0,w,h);
  const pad=24, n=labels.length;
  if(!n){ return; }
  const max = Math.max(1, ...series);
  const bw = (w - pad*2)/n;
  ctx.strokeStyle = '#e5e7eb';
  ctx.beginPath(); ctx.moveTo(pad, h-pad); ctx.lineTo(w-pad, h-pad); ctx.stroke();
  for(let i=0;i<n;i++){
    const x = pad + i*bw + bw*0.1;
    const bh = (h - pad*2) * (series[i]/max);
    const y = h-pad - bh;
    ctx.fillStyle = '#2563eb';
    ctx.fillRect(x, y, bw*0.8, bh);
  }
  ctx.fillStyle='#6b7280'; ctx.font = (12*window.devicePixelRatio)+'px sans-serif';
  for(let i=0;i<n;i+=Math.ceil(n/6)){
    ctx.fillText(labels[i], pad + i*bw, h-6);
  }
}
function drawPie(canvas, data){
  const ctx = canvas.getContext('2d');
  const w = canvas.width = canvas.clientWidth * window.devicePixelRatio;
  const h = canvas.height = canvas.clientHeight * window.devicePixelRatio;
  const cx=w/2, cy=h/2, r=Math.min(w,h)/2 - 10;
  ctx.clearRect(0,0,w,h);
  const sum = data.reduce((a,b)=>a+b.v,0) || 1;
  let a0=-Math.PI/2;
  const cols=['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#14b8a6'];
  data.forEach((d,i)=>{
    const ang = 2*Math.PI*(d.v/sum);
    ctx.beginPath();
    ctx.moveTo(cx,cy); ctx.arc(cx,cy,r,a0,a0+ang); ctx.closePath();
    ctx.fillStyle=cols[i%cols.length]; ctx.fill();
    a0+=ang;
  });
}

function loadAll(){
  const p = params();
  Promise.all([
    api('/api/hd/reports/summary.php', p),
    api('/api/hd/reports/timeseries.php', p),
    api('/api/hd/reports/agents.php', p),
    api('/api/hd/reports/groups.php', p)
  ]).then(([sum, ts, ag, gp])=>{
    if(sum.success){
      q('k_total').textContent = sum.data.total||0;
      q('k_total_hint').textContent = (sum.data.periodo||'');
      q('k_abertos').textContent = sum.data.abertos||0;
      q('k_abertos_hint').textContent = 'resolvidos '+(sum.data.resolvidos||0);
      q('k_tto').textContent = sum.data.tto_avg_min ?? '–';
      q('k_ttr').textContent = sum.data.ttr_avg_min ?? '–';

      // SLA tabela
      const s = sum.data.sla||{};
      q('sla_hint').textContent = (s.total||0)+' eventos';
      const rows = ['TTO','TTR'].map(k=>{
        const v = s[k]||{qtd:0, rate:'0%'};
        return `<tr><td>${k}</td><td>${v.qtd||0}</td><td>${v.rate||'0%'}</td></tr>`;
      }).join('');
      q('tb_sla').innerHTML = rows || '<tr><td colspan="3">–</td></tr>';

      // pizza prioridades
      const pdata = Object.entries(sum.data.prioridades||{}).map(([k,v])=>({k,v}));
      drawPie(q('c_prio'), pdata.map(o=>({label:o.k,v:o.v})));
    }
    if(ts.success){
      const labels = (ts.data||[]).map(d=>d.dia);
      const created = (ts.data||[]).map(d=>d.criados);
      drawBars(q('c_times'), labels, created);
    }
    if(gp.success){
      q('tb_group').innerHTML = (gp.data||[]).map(r=>`<tr><td>${r.nome||('Grupo #'+r.grupo_id)}</td><td>${r.abertos||0}</td><td>${r.criados||0}</td></tr>`).join('') || '<tr><td colspan="3">–</td></tr>';
    }
    if(ag.success){
      q('tb_agents').innerHTML = (ag.data||[]).map(r=>`<tr><td>${r.nome||('Agente #'+r.agente_id)}</td><td>${r.posse||0}</td><td>${r.resolvidos||0}</td><td>${r.ttr_avg_min ?? '–'}</td></tr>`).join('') || '<tr><td colspan="4">–</td></tr>';
    }
  });
}
function exportar(){
  const p = params();
  const qs = new URLSearchParams(p).toString();
  window.open('/api/hd/reports/export.php?'+qs, '_blank');
}

// default: últimos 30 dias
(function init(){
  const today = new Date(); const d2 = today.toISOString().slice(0,10);
  const d1 = new Date(today.getTime()-29*24*3600*1000).toISOString().slice(0,10);
  q('f_de').value = d1; q('f_ate').value = d2;
  loadAll();
})();
</script>

<?php @include_once ROOT_PATH . '/modules/helpdesk/includes/footer_hd.php'; ?>
