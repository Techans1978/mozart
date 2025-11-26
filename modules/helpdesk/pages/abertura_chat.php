<?php
// public/modules/helpdesk/pages/abertura_chat.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
require_once ROOT_PATH.'/system/includes/head_hd.php';
proteger_pagina();
?>
<style>
:root{ --bubble:#e7f3ff; --bubble-agent:#fff; --muted:#6b7280; }
.chat-shell{ max-width:900px; margin:10px auto; display:grid; grid-template-columns: 280px 1fr; gap:12px; }
.chat-sidebar{ background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:8px; }
.chat-main{ background:#fff; border:1px solid #e5e7eb; border-radius:12px; display:flex; flex-direction:column; }
.header{ padding:10px 12px; border-bottom:1px solid #eee; display:flex; align-items:center; justify-content:space-between;}
.messages{ padding:12px; height:520px; overflow:auto; display:flex; flex-direction:column; gap:8px; }
.msg{ max-width:72%; padding:8px 12px; border-radius:14px; box-shadow:0 1px 2px rgba(0,0,0,.04); }
.msg.agent{ background:var(--bubble-agent); border:1px solid #eef2f7; align-self:flex-start; }
.msg.user{ background:var(--bubble); border:1px solid #dbeafe; align-self:flex-end; }
.footer{ padding:10px; border-top:1px solid #eee; display:flex; gap:8px; }
.input{ flex:1; }
.typing{ display:inline-flex; align-items:center; gap:4px; color:var(--muted); font-size:12px; }
.typing .dot{ width:6px; height:6px; border-radius:999px; background:#9ca3af; animation: blink 1.2s infinite; }
.typing .dot:nth-child(2){ animation-delay:.2s }
.typing .dot:nth-child(3){ animation-delay:.4s }
@keyframes blink{ 0%{opacity:.2} 50%{opacity:1} 100%{opacity:.2} }
.badge{display:inline-flex; align-items:center; gap:6px; background:#f3f4f6; border:1px solid #e5e7eb; padding:4px 8px; border-radius:999px; font-size:12px; color:#374151;}
</style>

<div class="container-fluid">
  <h3 class="mt-2">Abertura Roteirizada (chat)</h3>
  <div class="chat-shell">
    <div class="chat-sidebar">
      <div class="mb-2"><strong>Scripts disponíveis</strong></div>
      <div id="scripts"></div>
      <hr>
      <div class="badge">Dica: responda palavras-chave (ex.: "impressora")</div>
    </div>

    <div class="chat-main">
      <div class="header">
        <div><strong>Mozart Help Desk</strong><div class="typing" id="typing" style="display:none"><span>escrevendo</span><div class="dot"></div><div class="dot"></div><div class="dot"></div></div></div>
        <div class="badge">WhatsApp-like</div>
      </div>
      <div class="messages" id="log"></div>
      <div class="footer">
        <input class="form-control input" id="msg" placeholder="Digite sua resposta e pressione Enter...">
        <button class="btn btn-primary" onclick="send()">Enviar</button>
      </div>
    </div>
  </div>
</div>

<script>
let sessionToken = Math.random().toString(36).slice(2);
let awaiting = false;
let state = { step:null, script_id:null };

function showTyping(on){ document.getElementById('typing').style.display = on? 'inline-flex' : 'none'; }

function pushAgent(text){
  const log = document.getElementById('log');
  const n = document.createElement('div'); n.className='msg agent'; n.innerText = text; log.appendChild(n); log.scrollTop = log.scrollHeight;
}
function pushUser(text){
  const log = document.getElementById('log');
  const n = document.createElement('div'); n.className='msg user'; n.innerText = text; log.appendChild(n); log.scrollTop = log.scrollHeight;
}

function send(){
  const inp = document.getElementById('msg');
  const val = inp.value.trim(); if(!val || awaiting) return;
  inp.value=''; pushUser(val);
  route(val);
}

function route(answer){
  awaiting = true; showTyping(true);
  const params = new URLSearchParams({ session:sessionToken, answer:answer, step: state.step||'', script_id: state.script_id||'' });
  fetch('/public/api/hd/forms/route_script.php?'+params.toString(), {credentials:'same-origin'})
    .then(r=>r.json())
    .then(j=>{
      showTyping(false); awaiting=false;
      if(!j.ok){ pushAgent('Ops: '+(j.error||'erro')); return; }
      state.step = j.next_step || null; state.script_id = j.script_id || state.script_id;
      (j.messages||[]).forEach(m=> pushAgent(m));
      if(j.action==='render_form' && j.form){
        renderForm(j.form);
      }
      if(j.action==='open_link' && j.url){
        pushAgent('Abrindo formulário: '+j.url);
        window.open(j.url, '_blank');
      }
    }).catch(e=>{ showTyping(false); awaiting=false; pushAgent('Falha na rota: '+e); });
}

function renderForm(formSchema){
  const log = document.getElementById('log');
  const wrap = document.createElement('div'); wrap.className='msg agent'; wrap.style.width='100%';
  const mount = document.createElement('div'); mount.id='formMount';
  wrap.appendChild(mount); log.appendChild(wrap); log.scrollTop = log.scrollHeight;
  // carrega renderer (já está na página em produção — aqui, assumimos global MozForms)
  MozForms.render(mount, formSchema);
  mount.addEventListener('mozform:submit', (ev)=>{
    const payload = ev.detail.data;
    pushUser('[Formulário enviado]');
    // aqui você pode chamar um endpoint que cria ticket ou inicia BPM
    fetch('/public/api/hd/forms/save_answer.php', {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({session:sessionToken, schema:ev.detail.schema, data:payload})
    }).then(r=>r.json()).then(j=>{
      pushAgent(j.ok? 'Recebido! Protocolo: '+(j.protocolo||'-') : ('Falhou: '+(j.error||'erro')));
    }).catch(e=> pushAgent('Erro ao salvar: '+e));
  });
}

// inicializa com um "olá" e a primeira pergunta do script default
function bootstrap(){
  fetch('/public/api/hd/forms/route_script.php?session='+sessionToken)
    .then(r=>r.json()).then(j=>{
      (j.messages||[]).forEach(m=> pushAgent(m));
      state.step = j.next_step || null; state.script_id = j.script_id || null;
      // lista scripts
      fetch('/public/api/hd/forms/list_scripts.php').then(r=>r.json()).then(j2=>{
        const box = document.getElementById('scripts'); box.innerHTML='';
        (j2.data||[]).forEach(s=>{
          const a=document.createElement('a'); a.href='#'; a.textContent='• '+s.nome; a.onclick=(e)=>{e.preventDefault(); state={step:null,script_id:s.id}; pushAgent('Iniciando: '+s.nome); route(''); };
          box.appendChild(a); box.appendChild(document.createElement('br'));
        });
      });
    });
}
bootstrap();
</script>

<script src="/public/modules/helpdesk/assets/forms/forms.js"></script>
