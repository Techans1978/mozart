<?php
// public/modules/helpdesk/pages/feed_helpdesk.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
require_once ROOT_PATH.'/system/includes/head_hd.php';
proteger_pagina();
$user_id = $_SESSION['usuario_id'] ?? 0;
?>
<style>
.feed-container { display:flex; gap:20px; }
.feed-sidebar { width:340px; border-right:1px solid #ddd; overflow-y:auto; max-height:calc(100vh - 150px);}
.feed-main { flex:1; display:flex; flex-direction:column; height:calc(100vh - 150px);}
.ticket-item { padding:12px; cursor:pointer; border-bottom:1px solid #eee;}
.ticket-item:hover { background:#f4f6f8; }
.ticket-item.active { background:#e8f0ff; border-left:4px solid #0d6efd; }
.chat-box { flex:1; overflow-y:auto; padding:14px; background:#f9fafb; }
.msg { margin-bottom:12px; max-width:78%; padding:10px 14px; border-radius:12px; box-shadow:0 1px 2px rgba(0,0,0,.05);}
.msg.self { margin-left:auto; background:#0d6efd; color:white; }
.msg.other { background:#eef1f4; color:#222; }
.msg.system { text-align:center; background:none; color:#667; font-size:13px; box-shadow:none; }
.timeline-card { background:white; border-radius:12px; padding:10px 12px; margin-bottom:12px; box-shadow:0 1px 3px rgba(0,0,0,.08);}
.attach-grid { display:flex; gap:8px; flex-wrap:wrap; margin-top:6px; }
.attach { border:1px solid #ddd; border-radius:8px; padding:6px; background:#fff; }
.attach img { max-width:140px; max-height:100px; display:block; border-radius:6px; }
.pending-attach { display:flex; gap:8px; flex-wrap:wrap; margin:8px 0; }
.pending-chip { background:#f0f7ff; border:1px dashed #9ec5fe; padding:4px 8px; border-radius:16px; font-size:12px; }
.dropzone { border:2px dashed #9ec5fe; border-radius:10px; padding:10px; background:#fbfdff; text-align:center; cursor:pointer; }
.dropzone.dragover { background:#eaf3ff; }
.toolbar { display:flex; gap:8px; align-items:center; }
</style>

<div class="container-fluid">
  <h3 class="mt-3 mb-3">Meus Chamados (Feed com Anexos)</h3>
  <div class="feed-container">
    <div class="feed-sidebar" id="ticketList"></div>
    <div class="feed-main">
      <div class="chat-box" id="chatBox">
        <div class="text-center text-muted mt-5">Selecione um chamado ou crie um novo</div>
      </div>
      <div class="border-top p-3 bg-white">
        <form id="msgForm" class="d-flex flex-column gap-2">
          <div class="toolbar">
            <input type="hidden" name="ticket_id" id="ticket_id">
            <input type="text" class="form-control" name="msg" placeholder="Digite sua mensagem..." autocomplete="off" required>
            <select name="tipo" class="form-select" style="max-width:220px">
              <option value="mensagem">Mensagem</option>
              <option value="resposta_tecnica">Resposta técnica</option>
            </select>
            <button class="btn btn-primary">Enviar</button>
            <button type="button" class="btn btn-success" id="btnNew">+ Novo</button>
          </div>
          <div id="dz" class="dropzone">
            <div><strong>Anexos</strong> — arraste arquivos aqui, ou clique para selecionar (JPG/PNG/GIF/PDF, até 12MB)</div>
            <input type="file" id="fileInput" hidden accept=".jpg,.jpeg,.png,.gif,.pdf">
          </div>
          <div id="pending" class="pending-attach"></div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal Novo Chamado -->
<div class="modal fade" id="newModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Novo Chamado</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <form id="newForm">
      <div class="mb-2">
        <label class="form-label">Título</label>
        <input type="text" name="titulo" class="form-control" required>
      </div>
      <div class="mb-2">
        <label class="form-label">Descrição</label>
        <textarea name="descricao" class="form-control" rows="4" required></textarea>
      </div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button class="btn btn-primary" id="btnCreateTicket">Criar</button>
  </div>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentId=null;
const api='<?= BASE_URL ?>/api/hd/feed.php';
const chat=document.getElementById('chatBox');
const list=document.getElementById('ticketList');
const modal=new bootstrap.Modal(document.getElementById('newModal'));
const pending = []; // [{id, name, url}]
const pendingWrap = document.getElementById('pending');

function renderPending(){
  pendingWrap.innerHTML='';
  pending.forEach(f=>{
    const el=document.createElement('div');
    el.className='pending-chip';
    el.innerText = f.name;
    pendingWrap.appendChild(el);
  });
}

async function loadTickets(){
  const r=await fetch(api+'?action=list'); const j=await r.json();
  list.innerHTML='';
  if(!j.success){ list.innerHTML='<div class="p-3 text-danger">'+(j.message||'Erro')+'</div>'; return; }
  j.data.forEach(t=>{
    const el=document.createElement('div');
    el.className='ticket-item'; el.dataset.id=t.id;
    el.innerHTML=`<div class="d-flex justify-content-between">
      <div><strong>${t.protocolo}</strong><br><small>${t.titulo||''}</small></div>
      <div><span class="badge bg-${t.status==='fechado'?'secondary':'primary'}">${t.status}</span></div>
    </div>`;
    el.onclick=()=>loadTicket(t.id,el);
    list.appendChild(el);
  });
}

async function loadTicket(id,el){
  currentId=id;
  pending.splice(0,pending.length); renderPending();
  document.querySelectorAll('.ticket-item').forEach(e=>e.classList.remove('active'));
  if(el) el.classList.add('active');
  document.getElementById('ticket_id').value=id;

  const r=await fetch(api+'?action=details&ticket_id='+id); const j=await r.json();
  if(!j.success){ chat.innerHTML='<div class="p-3 text-danger">'+(j.message||'Erro')+'</div>'; return; }
  const t=j.data.ticket; const msgs=j.data.msgs;

  let html='';
  html+=`<div class="timeline-card mb-3"><strong>${t.titulo||'(sem título)'}</strong><br>
         <small>Status: ${t.status||'-'} • Prioridade: ${t.prioridade||'-'} • Protocolo: ${t.protocolo||'-'}</small></div>`;

  msgs.forEach(m=>{
    if(m.tipo==='sistema'){
      html+=`<div class="msg system">${m.conteudo}</div>`;
    }else{
      const self = (m.user_id==<?= (int)$user_id ?>);
      const cls = self ? 'self' : 'other';
      html+=`<div class="msg ${cls}">
        <div>${m.conteudo}</div>
        ${renderFiles(m.files)}
        <div class="small text-end ${self?'text-light':'text-muted'}">${m.autor||'Você'} • ${m.created_at}</div>
      </div>`;
    }
  });
  chat.innerHTML=html;
  chat.scrollTop=chat.scrollHeight;
}

function renderFiles(files){
  if(!files || !files.length) return '';
  let s = '<div class="attach-grid">';
  files.forEach(f=>{
    if((f.mime||'').startsWith('image/')){
      s += `<div class="attach"><a href="${f.url}" target="_blank"><img src="${f.url}" alt=""></a><div class="small">${f.orig_name}</div></div>`;
    } else {
      s += `<div class="attach"><a href="${f.url}" target="_blank">📄 ${f.orig_name}</a><div class="small text-muted">${(f.size_bytes/1024).toFixed(1)} KB</div></div>`;
    }
  });
  s += '</div>';
  return s;
}

document.getElementById('msgForm').addEventListener('submit',async e=>{
  e.preventDefault();
  const fd=new FormData(e.target);
  if(!fd.get('ticket_id')){ alert('Selecione um chamado'); return; }
  // anexa os IDs pendentes
  pending.forEach((f)=> fd.append('attachment_ids[]', f.id));
  fd.append('action','send');
  const r=await fetch(api,{method:'POST',body:fd}); const j=await r.json();
  if(j.success){
    // limpa pendentes e input
    pending.splice(0,pending.length); renderPending();
    e.target.msg.value='';
    loadTicket(fd.get('ticket_id'));
  } else alert(j.message||'Erro');
});

document.getElementById('btnNew').onclick=()=>modal.show();
document.getElementById('btnCreateTicket').onclick=async()=>{
  const ff=new FormData(document.getElementById('newForm'));
  ff.append('action','new_ticket');
  const r=await fetch(api,{method:'POST',body:ff}); const j=await r.json();
  if(j.success){
    modal.hide();
    await loadTickets();
    loadTicket(j.data.ticket_id);
  } else alert(j.message||'Erro ao criar');
};

// ====== Dropzone / Upload ======
const dz = document.getElementById('dz');
const fi = document.getElementById('fileInput');

dz.addEventListener('click', ()=> fi.click());
dz.addEventListener('dragover', e=>{ e.preventDefault(); dz.classList.add('dragover'); });
dz.addEventListener('dragleave', ()=> dz.classList.remove('dragover'));
dz.addEventListener('drop', e=>{
  e.preventDefault(); dz.classList.remove('dragover');
  const files = e.dataTransfer.files;
  handleFiles(files);
});
fi.addEventListener('change', e=> handleFiles(e.target.files));

async function handleFiles(files){
  if(!currentId){ alert('Selecione um chamado para anexar'); return; }
  for(const f of files){
    const fd=new FormData();
    fd.append('action','upload');
    fd.append('ticket_id', currentId);
    fd.append('file', f);
    const r=await fetch(api,{method:'POST',body:fd});
    const j=await r.json();
    if(j.success && j.data && j.data.file){
      pending.push({ id:j.data.file.id, name:j.data.file.orig_name, url:j.data.file.url });
      renderPending();
    } else {
      alert((j && j.message) ? j.message : 'Falha no upload');
    }
  }
}

loadTickets();
</script>

<?php require_once ROOT_PATH.'/system/includes/footer_hd.php'; ?>
