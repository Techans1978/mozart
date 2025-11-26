<?php
// public/modules/helpdesk/pages/admin/sla.php
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

include_once ROOT_PATH . '/system/includes/head.php';

@include_once ROOT_PATH . '/modules/helpdesk/includes/head_hd.php';

include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<!-- layout -->
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

    <div class="row">
      <div class="col-lg-12">
	  <!-- Content -->
<style>
  .grid { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
  .card { background:#fff; border-radius:12px; box-shadow:0 1px 0 rgba(0,0,0,.06); padding:12px; }
  table { width:100%; border-collapse:collapse; }
  th, td { border:1px solid #eee; padding:6px; font-size:13px; text-align:center; }
  .btn { border:1px solid #e5e7eb; padding:6px 10px; border-radius:8px; background:#fff; cursor:pointer;}
  .btn.primary { background:#2563eb; color:#fff; border-color:#2563eb; }
  input, select, textarea { width:100%; border:1px solid #e5e7eb; border-radius:8px; padding:6px 8px; }
</style>

<div id="page-wrapper" class="container-fluid">
  <h3>Admin • SLAs</h3>
  <div class="grid">
    <div class="card">
      <div class="row" style="display:flex; gap:8px;">
        <input id="q" placeholder="Buscar política..." />
        <button class="btn" onclick="load()">Buscar</button>
        <button class="btn primary" onclick="novo()">Nova política</button>
      </div>
      <div id="list" style="margin-top:10px;"></div>
    </div>
    <div class="card">
      <div class="row" style="display:flex; gap:8px;">
        <input id="nome" placeholder="Nome da política" />
        <select id="prioridade">
          <option value="">Qualquer prioridade</option>
          <option>baixa</option><option>media</option><option>alta</option><option>urgente</option>
        </select>
        <select id="status_aplica">
          <option value="">Todos status</option>
          <option>novo</option><option>aberto</option><option>pendente</option>
        </select>
      </div>
      <div class="row" style="margin-top:6px; display:flex; gap:8px;">
        <input id="tto_min" type="number" placeholder="TTO (min)" />
        <input id="ttr_min" type="number" placeholder="TTR (min)" />
      </div>
      <h4 style="margin-top:10px;">Calendário de atendimento & Matriz de pausas</h4>
      <div style="font-size:12px;color:#666;margin-bottom:6px;">Marque os horários em que o relógio de SLA **conta** (ex.: 08:00–18:00 seg-sex). Adicione pausas por feriado ou janela.</div>
      <table id="cal">
        <thead><tr><th>Dia</th><th>Início</th><th>Fim</th><th>Pausas (HH:MM-HH:MM; separados por vírgula)</th></tr></thead>
        <tbody></tbody>
      </table>
      <div class="row" style="margin-top:8px;">
        <button class="btn primary" onclick="salvar()">Salvar</button>
      </div>
      <div id="msg" style="margin-top:6px;color:#555;"></div>
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
const CSRF="<?php echo $csrf;?>";
let currentId=null;

const dias=['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
function linha(d){
  return `<tr>
    <td>${dias[d]}</td>
    <td><input data-k="ini_${d}" placeholder="08:00"></td>
    <td><input data-k="fim_${d}" placeholder="18:00"></td>
    <td><input data-k="pausas_${d}" placeholder="12:00-13:00"></td>
  </tr>`;
}
document.querySelector('#cal tbody').innerHTML = [0,1,2,3,4,5,6].map(linha).join('');

function api(op, data={}, method='POST'){
  return fetch('/api/hd/admin/sla.php', {method, headers:{'Content-Type':'application/json'}, body: JSON.stringify({op, csrf:CSRF, ...data})}).then(r=>r.json());
}
function novo(){ currentId=null; document.getElementById('nome').value=''; document.getElementById('prioridade').value=''; document.getElementById('status_aplica').value=''; document.getElementById('tto_min').value=''; document.getElementById('ttr_min').value=''; document.getElementById('msg').textContent=''; setCal({}); }
function setCal(obj){
  [0,1,2,3,4,5,6].forEach(d=>{
    const ini = document.querySelector(`[data-k="ini_${d}"]`); ini.value = (obj[`ini_${d}`]||'');
    const fim = document.querySelector(`[data-k="fim_${d}"]`); fim.value = (obj[`fim_${d}`]||'');
    const pausas = document.querySelector(`[data-k="pausas_${d}"]`); pausas.value = (obj[`pausas_${d}`]||'');
  });
}
function getCal(){
  const o={};
  [0,1,2,3,4,5,6].forEach(d=>{
    o[`ini_${d}`]=document.querySelector(`[data-k="ini_${d}"]`).value.trim();
    o[`fim_${d}`]=document.querySelector(`[data-k="fim_${d}"]`).value.trim();
    o[`pausas_${d}`]=document.querySelector(`[data-k="pausas_${d}"]`).value.trim();
  });
  return o;
}
function load(){
  const q=document.getElementById('q').value.trim();
  api('list',{q}).then(j=>{
    if(!j.success) return;
    document.getElementById('list').innerHTML = (j.data.items||[]).map(p=>`<div style="padding:8px;border-bottom:1px solid #eee;cursor:pointer" onclick="abrir(${p.id})"><strong>${p.nome}</strong> — TTO:${p.tto_min||'—'} TTR:${p.ttr_min||'—'}</div>`).join('') || 'Nenhuma política';
  });
}
function abrir(id){
  api('get',{id}).then(j=>{
    if(!j.success || !j.data) return;
    currentId=j.data.id;
    document.getElementById('nome').value=j.data.nome||'';
    document.getElementById('prioridade').value=j.data.prioridade||'';
    document.getElementById('status_aplica').value=j.data.status_aplica||'';
    document.getElementById('tto_min').value=j.data.tto_min||'';
    document.getElementById('ttr_min').value=j.data.ttr_min||'';
    setCal(j.data.calendario||{});
  });
}
function salvar(){
  const o = {
    id: currentId,
    nome: document.getElementById('nome').value.trim(),
    prioridade: document.getElementById('prioridade').value,
    status_aplica: document.getElementById('status_aplica').value,
    tto_min: parseInt(document.getElementById('tto_min').value||0,10),
    ttr_min: parseInt(document.getElementById('ttr_min').value||0,10),
    calendario: getCal()
  };
  api('save',o).then(j=>{
    document.getElementById('msg').textContent = j.success? 'Salvo.' : ('Erro: '+j.error);
    if(j.data && j.data.id) currentId=j.data.id;
    load();
  });
}
load();
</script>
<?php @include_once ROOT_PATH . '/modules/helpdesk/includes/footer_hd.php'; ?>
