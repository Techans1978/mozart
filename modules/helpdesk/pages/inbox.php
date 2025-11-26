<?php
// public/modules/helpdesk/pages/inbox.php
ini_set('display_errors',1); ini_set('startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

// Normaliza conexão
$db = $conn ?? ($mysqli ?? null);
if (!$db || !($db instanceof mysqli)) { http_response_code(500); die('Sem conexão DB.'); }

$user_id = $_SESSION['usuario_id'] ?? 0; // ajuste conforme seu auth
$csrf = bin2hex(random_bytes(16));
$_SESSION['csrf_hd'] = $csrf;

include_once ROOT_PATH . '/system/includes/head.php';
require_once ROOT_PATH.'/system/includes/head_hd.php';
?>
<style>
  :root { --gap:10px; --sidebar-w:360px; --toolbar-h:58px; }
  #page-wrapper { background:#f6f7f9; }
  .hd-shell { display:grid; grid-template-rows: var(--toolbar-h) 1fr; height: calc(100vh - 80px); gap:var(--gap);}
  .hd-toolbar { display:flex; align-items:center; gap:8px; padding:8px 12px; background:#fff; border-radius:10px; box-shadow:0 1px 0 rgba(0,0,0,.06);}
  .hd-body { display:grid; grid-template-columns: 420px 1fr; gap:var(--gap); }
  .hd-filters, .hd-list, .hd-preview { background:#fff; border-radius:12px; box-shadow:0 1px 0 rgba(0,0,0,.06); }
  .hd-filters { padding:12px; }
  .hd-list { display:flex; flex-direction:column; overflow:auto; }
  .hd-item { border-bottom:1px solid #f0f0f0; padding:10px 12px; cursor:pointer; }
  .hd-item:hover { background:#fafafa; }
  .hd-item .meta { font-size:12px; color:#7a7a7a; display:flex; gap:8px; flex-wrap:wrap;}
  .hd-preview { padding:0; display:flex; flex-direction:column; }
  .hd-preview-header { padding:12px 14px; border-bottom:1px solid #f1f1f1; display:flex; align-items:center; justify-content:space-between;}
  .hd-preview-body { padding:14px; overflow:auto; }
  .hd-actions { display:flex; gap:8px; flex-wrap:wrap;}
  .badge { padding:2px 8px; border-radius:999px; font-size:11px; background:#f3f4f6; }
  .btn { border:1px solid #e5e7eb; padding:6px 10px; border-radius:8px; background:#fff; cursor:pointer;}
  .btn.primary { background:#2563eb; color:#fff; border-color:#2563eb; }
  .btn.danger { background:#ef4444; color:#fff; border-color:#ef4444; }
  .row { display:flex; gap:8px; }
  .col { flex:1; }
  select, input[type="text"] { width:100%; padding:8px 10px; border:1px solid #e5e7eb; border-radius:8px; }
  textarea { width:100%; min-height:100px; border:1px solid #e5e7eb; border-radius:8px; padding:8px 10px; }
  .split { display:grid; grid-template-columns: 420px 1fr; gap:var(--gap); }
  @media(max-width:1100px){ .hd-body{ grid-template-columns: 1fr; } .split{ grid-template-columns:1fr; } }
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
<div id="page-wrapper" class="container-fluid">
  <div class="hd-shell">
    <div class="hd-toolbar">
      <strong>Caixa do Agente</strong>
      <div style="margin-left:auto" class="row">
        <input id="q" type="text" placeholder="Buscar por protocolo, assunto, solicitante..." />
        <button class="btn" onclick="reloadList()">Buscar</button>
      </div>
    </div>

    <div class="hd-body">
      <div class="split">
        <div class="hd-filters">
          <div class="row">
            <div class="col">
              <label>Grupo</label>
              <select id="f_grupo"><option value="">Todos</option></select>
            </div>
            <div class="col">
              <label>Status</label>
              <select id="f_status">
                <option value="">Todos</option>
                <option value="novo">Novo</option>
                <option value="aberto">Aberto</option>
                <option value="pendente">Pendente</option>
                <option value="resolvido">Resolvido</option>
                <option value="fechado">Fechado</option>
              </select>
            </div>
          </div>
          <div class="row" style="margin-top:8px;">
            <div class="col">
              <label>Prioridade</label>
              <select id="f_prioridade">
                <option value="">Todas</option>
                <option value="baixa">Baixa</option>
                <option value="media">Média</option>
                <option value="alta">Alta</option>
                <option value="urgente">Urgente</option>
              </select>
            </div>
            <div class="col">
              <label>Loja</label>
              <select id="f_loja"><option value="">Todas</option></select>
            </div>
          </div>
          <div class="row" style="margin-top:10px;">
            <button class="btn" onclick="resetFilters()">Limpar</button>
            <button class="btn primary" onclick="reloadList()">Aplicar</button>
          </div>
          <hr>
          <div>
            <label>Respostas rápidas / Macros</label>
            <div class="row">
              <select id="macro_id"><option value="">Selecione...</option></select>
              <button class="btn" onclick="aplicarMacro()">Aplicar</button>
            </div>
          </div>
          <hr>
          <div>
            <label>Merge de Duplicados</label>
            <div class="row">
              <input id="merge_master" type="text" placeholder="Protocolo principal" />
              <input id="merge_child" type="text" placeholder="Protocolo duplicado" />
            </div>
            <div class="row" style="margin-top:6px;">
              <button class="btn danger" onclick="fundir()">Fundir</button>
            </div>
          </div>
        </div>

        <div class="hd-list" id="list">
          <!-- itens por JS -->
        </div>
      </div>

      <div class="hd-preview">
        <div class="hd-preview-header">
          <div>
            <strong id="pv_title">Selecione um ticket</strong>
            <div class="meta" id="pv_meta"></div>
          </div>
          <div class="hd-actions">
            <button class="btn" onclick="tomarPosse()">Tomar posse</button>
            <button class="btn" onclick="reloadPreview()">Atualizar</button>
          </div>
        </div>
        <div class="hd-preview-body">
          <div id="pv_body" style="margin-bottom:10px; color:#555;"></div>
          <div id="pv_logs" style="border-top:1px solid #eee; padding-top:10px;"></div>
          <hr>
          <label>Responder</label>
          <textarea id="pv_resposta" placeholder="Escreva sua resposta..."></textarea>
          <div class="row" style="margin-top:8px;">
            <button class="btn primary" onclick="responder()">Enviar resposta</button>
          </div>
        </div>
      </div>
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
const CSRF = "<?php echo $csrf;?>";
let currentTicketId = null;
let timer = null;

function api(url, data={}, method='POST') {
  const hdrs = {'Content-Type':'application/json'};
  return fetch(url+(method==='GET' ? ('?'+new URLSearchParams(data)) : ''), {
    method, headers: hdrs, body: method==='GET' ? undefined : JSON.stringify({...data, csrf:CSRF})
  }).then(r=>r.json());
}

function resetFilters(){
  document.getElementById('f_grupo').value='';
  document.getElementById('f_status').value='';
  document.getElementById('f_prioridade').value='';
  document.getElementById('f_loja').value='';
  document.getElementById('q').value='';
}

function reloadList() {
  const params = {
    q: document.getElementById('q').value.trim(),
    grupo: document.getElementById('f_grupo').value,
    status: document.getElementById('f_status').value,
    prioridade: document.getElementById('f_prioridade').value,
    loja: document.getElementById('f_loja').value,
    limit: 50
  };
  api('/api/hd/agent/list.php', params,'POST').then(json=>{
    const ul = document.getElementById('list'); ul.innerHTML='';
    if(!json.success) { ul.innerHTML = '<div style="padding:12px;color:#b00">'+(json.error||'Erro')+'</div>'; return;}
    json.data.items.forEach(it=>{
      const div = document.createElement('div');
      div.className='hd-item';
      div.onclick=()=>openTicket(it.id);
      div.innerHTML = `
        <div><strong>${it.protocolo||('#'+it.id)}</strong> — ${it.assunto||'(sem assunto)'}</div>
        <div class="meta">
          <span class="badge">${(it.status||'--').toUpperCase()}</span>
          <span class="badge">Prio: ${it.prioridade||'--'}</span>
          <span>Loja: ${it.loja_nome||it.loja_id||'--'}</span>
          <span>Solicitante: ${it.solicitante_nome||it.solicitante_user_id||'--'}</span>
          <span>Agente: ${it.agente_nome||it.agente_user_id||'—'}</span>
          <span>${it.created_at||''}</span>
        </div>
      `;
      ul.appendChild(div);
    });
  });
}

function openTicket(id){
  currentTicketId = id;
  // auto-assumir ao abrir (opcional; pode comentar se preferir só no botão)
  api('/api/hd/agent/take.php', {ticket_id:id}).then(_=>reloadPreview());
}

function reloadPreview(){
  if(!currentTicketId) return;
  api('/api/hd/agent/list.php', {id: currentTicketId}, 'POST').then(json=>{
    if(!json.success || !json.data || !json.data.items || !json.data.items.length){ return; }
    const t = json.data.items[0];
    document.getElementById('pv_title').textContent = (t.protocolo||('#'+t.id))+' — '+(t.assunto||'');
    document.getElementById('pv_meta').innerHTML = `
      <span class="badge">${(t.status||'--').toUpperCase()}</span>
      <span class="badge">Prioridade: ${(t.prioridade||'--')}</span>
      <span>Grupo: ${t.grupo_nome||t.grupo_id||'--'}</span>
      <span>Loja: ${t.loja_nome||t.loja_id||'--'}</span>
      <span>Agente: ${t.agente_nome||t.agente_user_id||'—'}</span>
    `;
    document.getElementById('pv_body').innerHTML = `<div>${(t.descricao_html||t.descricao||'').replace(/\n/g,'<br>')}</div>`;
    // logs/comentários
    const logs = t.logs||[];
    const wrap = document.getElementById('pv_logs');
    wrap.innerHTML = logs.map(l=>`<div style="padding:6px 0;border-bottom:1px dashed #eee;">
      <div style="font-size:12px;color:#777;">${l.created_at||''} — ${l.autor||''}</div>
      <div>${(l.texto||'').replace(/\n/g,'<br>')}</div>
    </div>`).join('');
  });
}

function tomarPosse(){
  if(!currentTicketId) return;
  api('/api/hd/agent/take.php',{ticket_id:currentTicketId}).then(_=>reloadPreview());
}

function responder(){
  if(!currentTicketId) return;
  const texto = document.getElementById('pv_resposta').value.trim();
  if(!texto) return;
  api('/api/hd/agent/macro.php', {ticket_id: currentTicketId, resposta:texto, aplicar:false}, 'POST')
    .then(_=>{ document.getElementById('pv_resposta').value=''; reloadPreview(); reloadList(); });
}

function aplicarMacro(){
  if(!currentTicketId) return;
  const mid = document.getElementById('macro_id').value;
  if(!mid) return;
  api('/api/hd/agent/macro.php', {ticket_id: currentTicketId, macro_id: mid, aplicar:true}, 'POST')
    .then(_=>{ reloadPreview(); reloadList(); });
}

function fundir(){
  const master = document.getElementById('merge_master').value.trim();
  const child  = document.getElementById('merge_child').value.trim();
  if(!master || !child) return;
  api('/api/hd/agent/merge.php', {master, child}, 'POST').then(_=>{
    document.getElementById('merge_master').value='';
    document.getElementById('merge_child').value='';
    reloadList(); if(currentTicketId) reloadPreview();
  });
}

// Carregar combos (grupos, lojas, macros)
function loadCombos(){
  api('/api/hd/agent/list.php', {combos:1}, 'POST').then(json=>{
    if(!json.success) return;
    const g = document.getElementById('f_grupo');
    const l = document.getElementById('f_loja');
    const m = document.getElementById('macro_id');
    (json.data.grupos||[]).forEach(o=>{
      const opt=document.createElement('option'); opt.value=o.id; opt.textContent=o.nome; g.appendChild(opt);
    });
    (json.data.lojas||[]).forEach(o=>{
      const opt=document.createElement('option'); opt.value=o.id; opt.textContent=o.nome; l.appendChild(opt);
    });
    (json.data.macros||[]).forEach(o=>{
      const opt=document.createElement('option'); opt.value=o.id; opt.textContent=o.nome; m.appendChild(opt);
    });
  });
}

loadCombos();
reloadList();
// auto refresh leve
timer = setInterval(()=>{ reloadList(); if(currentTicketId) reloadPreview();}, 30000);
</script>

<?php @include_once ROOT_PATH . '/modules/helpdesk/includes/footer_hd.php'; ?>
