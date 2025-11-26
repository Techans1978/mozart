<?php
// public/modules/helpdesk/pages/ticket.php
ini_set('display_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH.'system/config/autenticacao.php';
require_once ROOT_PATH.'system/config/connect.php';
include_once __DIR__.'/../includes/head_hd.php';
include_once ROOT_PATH.'system/includes/navbar.php';
if (session_status()===PHP_SESSION_NONE) session_start();
if (!usuario_logado()) { header('Location: /login.php'); exit; }
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>
<div class="container hd-feed mt-3 mb-5">
  <div id="head" class="mb-3"></div>
  <div class="row g-3">
    <div class="col-lg-8">
      <div id="timeline"></div>
      <div class="hd-card">
        <h5>Responder</h5>
        <form id="fmsg" enctype="multipart/form-data">
          <div class="mb-2">
            <textarea name="texto" class="form-control" rows="3" placeholder="Digite sua mensagem"></textarea>
          </div>
          <div class="mb-2">
            <input type="file" name="anexos[]" multiple class="form-control">
          </div>
          <button class="btn btn-primary">Enviar</button>
        </form>
        <div id="mres" class="mt-2"></div>
      </div>
    </div>
    <div class="col-lg-4">
      <div id="sla" class="hd-card"></div>
      <div class="hd-card">
        <h6>Avaliar atendimento</h6>
        <div class="d-flex gap-1">
          <button class="btn btn-outline-success btn-sm" onclick="rate(5)">5</button>
          <button class="btn btn-outline-success btn-sm" onclick="rate(4)">4</button>
          <button class="btn btn-outline-secondary btn-sm" onclick="rate(3)">3</button>
          <button class="btn btn-outline-warning btn-sm" onclick="rate(2)">2</button>
          <button class="btn btn-outline-danger btn-sm" onclick="rate(1)">1</button>
        </div>
        <small class="text-muted d-block mt-2">Sua nota ajuda a melhorar nosso serviço.</small>
        <div id="rres" class="mt-2"></div>
      </div>
    </div>
  </div>
</div>
<script>
const TID = <?= json_encode($id) ?>;

async function loadTicket(){
  const r = await fetch(`/modules/helpdesk/api/router.php/hd/tickets/${TID}`);
  const j = await r.json();
  if(!j.ok){ document.getElementById('head').innerHTML='<div class="alert alert-danger">Ticket não encontrado</div>'; return; }
  const t = j.ticket;
  document.getElementById('head').innerHTML = `
    <div class="hd-card d-flex justify-content-between align-items-center">
      <div>
        <div><b>${t.protocolo}</b> — ${t.titulo}</div>
        <div class="text-muted">${t.servico_nome||''} · ${t.categoria_nome||''}</div>
      </div>
      <div class="text-end">
        <div class="hd-chip">${t.status}</div>
        <div class="text-muted" style="font-size:.85rem">${new Date(t.created_at.replace(' ','T')).toLocaleString()}</div>
      </div>
    </div>
  `;
  // SLA
  const sla = document.getElementById('sla');
  const pr = t.primeira_resposta_due ? new Date(t.primeira_resposta_due.replace(' ','T')).toLocaleString() : '-';
  const rs = t.resolucao_due ? new Date(t.resolucao_due.replace(' ','T')).toLocaleString() : '-';
  sla.innerHTML = `<h6>SLA</h6><div><span class="hd-sla">1ª Resposta:</span> ${pr}</div><div><span class="hd-sla">Resolução:</span> ${rs}</div><div>Status SLA: <b>${t.sla_status || '-'}</b></div>`;

  // Timeline
  const TL = document.getElementById('timeline'); TL.innerHTML='';
  j.mensagens.forEach(m=>{
    const div = document.createElement('div'); div.className='hd-card hd-msg '+(m.visibilidade==='privada'?'privada':'');
    const who = m.autor_nome ? m.autor_nome : (m.externo_nome||'');
    div.innerHTML = `<div class="d-flex justify-content-between">
      <div><b>${who||'Mensagem'}</b> <span class="text-muted">(${m.canal_origem})</span></div>
      <div class="text-muted" style="font-size:.85rem">${new Date(m.created_at.replace(' ','T')).toLocaleString()}</div>
    </div>
    <div class="mt-2">${(m.texto_html||'').trim() || (m.texto_plain||'').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div>`;
    TL.appendChild(div);
  });
  j.status_hist.forEach(h=>{
    const d=document.createElement('div'); d.className='hd-card';
    d.innerHTML = `<div>🡒 Status: <b>${h.de_status||'-'}</b> → <b>${h.para_status}</b> <span class="text-muted" style="font-size:.85rem">${new Date(h.created_at.replace(' ','T')).toLocaleString()}</span></div>`;
    TL.appendChild(d);
  });
  j.tarefas.forEach(tk=>{
    const d=document.createElement('div'); d.className='hd-card';
    d.innerHTML = `<div>🗒️ Tarefa: <b>${tk.titulo}</b> — ${tk.status} <span class="text-muted" style="font-size:.85rem">${new Date(tk.created_at.replace(' ','T')).toLocaleString()}</span></div>`;
    TL.appendChild(d);
  });
}

document.getElementById('fmsg').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const form = e.target;
  const fd = new FormData(form);
  const r = await fetch(`/modules/helpdesk/api/router.php/hd/tickets/${TID}/mensagens`, { method:'POST', body: fd });
  const j = await r.json();
  const M = document.getElementById('mres');
  if(j.ok){ M.innerHTML='<div class="alert alert-success">Mensagem enviada!</div>'; form.reset(); loadTicket(); }
  else { M.innerHTML=`<div class="alert alert-danger">Erro: ${j.error||'desconhecido'}</div>`; }
});

async function rate(nota){
  const r = await fetch(`/modules/helpdesk/api/router.php/hd/tickets/${TID}/avaliacao`, {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({nota})
  });
  const j = await r.json();
  const R = document.getElementById('rres');
  if(j.ok){ R.innerHTML='<div class="alert alert-success">Obrigado pela avaliação!</div>'; }
  else { R.innerHTML=`<div class="alert alert-warning">Não foi possível registrar: ${j.error||'erro'}</div>`; }
}

loadTicket();
</script>
<?php include_once __DIR__.'/../includes/footer_hd.php'; ?>
