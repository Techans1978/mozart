<?php
// public/modules/helpdesk/pages/admin/email.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start(); proteger_pagina();

$db = $conn ?? ($mysqli ?? null); if(!$db || !($db instanceof mysqli)) { http_response_code(500); die('Sem conexão DB.'); }
$csrf = bin2hex(random_bytes(16)); $_SESSION['csrf_hd_admin']=$csrf;
@include_once ROOT_PATH . '/modules/helpdesk/includes/head_hd.php';
?>
<style>
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .card{background:#fff;border-radius:12px;box-shadow:0 1px 0 rgba(0,0,0,.06);padding:12px}
  input,select,textarea{width:100%;border:1px solid #e5e7eb;border-radius:8px;padding:8px 10px}
  .btn{border:1px solid #e5e7eb;padding:6px 10px;border-radius:8px;background:#fff;cursor:pointer}
  .btn.primary{background:#2563eb;color:#fff;border-color:#2563eb}
  table{width:100%;border-collapse:collapse} th,td{border:1px solid #eee;padding:6px;font-size:13px}
</style>
<div id="page-wrapper" class="container-fluid">
  <h3>Admin • E-mail (IMAP/SMTP)</h3>
  <div class="grid">
    <div class="card">
      <h4>Configuração</h4>
      <div class="grid" style="grid-template-columns:1fr 1fr;gap:8px">
        <label>Ativo<select id="active"><option value="0">Não</option><option value="1">Sim</option></select></label>
        <label>Protocolo<select id="protocol"><option>imap</option><option>pop3</option></select></label>
        <label>Host<input id="host"></label>
        <label>Porta<input id="port" type="number" value="993"></label>
        <label>Secure<select id="secure"><option>ssl</option><option>tls</option><option>none</option></select></label>
        <label>Mailbox<input id="mailbox" placeholder="INBOX"></label>
        <label>Usuário<input id="username"></label>
        <label>Senha<input id="password" type="password"></label>
      </div>
      <h4 style="margin-top:10px;">SMTP</h4>
      <div class="grid" style="grid-template-columns:1fr 1fr;gap:8px">
        <label>SMTP Host<input id="smtp_host"></label>
        <label>SMTP Porta<input id="smtp_port" type="number" value="587"></label>
        <label>SMTP Secure<select id="smtp_secure"><option>tls</option><option>ssl</option><option>none</option></select></label>
        <label>SMTP Usuário<input id="smtp_username"></label>
        <label>SMTP Senha<input id="smtp_password" type="password"></label>
        <label>From Name<input id="from_name" placeholder="Mozart Help Desk"></label>
        <label>From E-mail<input id="from_email" placeholder="helpdesk@dominio.com"></label>
        <label>Prefixo assunto<input id="reply_subject_prefix" placeholder="[HD]"></label>
      </div>
      <div style="margin-top:8px;display:flex;gap:8px">
        <button class="btn primary" onclick="save()">Salvar</button>
        <button class="btn" onclick="test()">Enviar teste</button>
        <input id="test_to" placeholder="destinatario@exemplo.com" style="max-width:320px">
      </div>
      <div id="msg" style="margin-top:8px;color:#555"></div>
    </div>
    <div class="card">
      <h4>Templates de E-mail</h4>
      <div style="display:flex;gap:8px;margin-bottom:6px">
        <input id="tpl_q" placeholder="Buscar template...">
        <button class="btn" onclick="loadTpls()">Buscar</button>
        <button class="btn primary" onclick="novoTpl()">Novo</button>
      </div>
      <table>
        <thead><tr><th>Nome</th><th>Assunto</th><th>Ativo</th><th>Ação</th></tr></thead>
        <tbody id="tpl_list"></tbody>
      </table>
      <hr>
      <div style="display:grid;gap:8px">
        <input id="tpl_nome" placeholder="Nome do template">
        <input id="tpl_assunto" placeholder="Assunto (pode usar {{protocolo}} {{assunto}})">
        <textarea id="tpl_html" style="min-height:180px" placeholder="HTML do e-mail"></textarea>
        <div style="display:flex;gap:8px">
          <select id="tpl_ativo"><option value="1">Ativo</option><option value="0">Inativo</option></select>
          <button class="btn primary" onclick="saveTpl()">Salvar Template</button>
        </div>
        <div id="tpl_msg" style="color:#555"></div>
      </div>
    </div>
  </div>
</div>
<script>
const CSRF="<?php echo $csrf;?>"; let currentTpl=null;
function api(url,data={}){return fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...data,csrf:CSRF})}).then(r=>r.json());}
function load(){
  api('/api/hd/admin/email.php',{op:'get'}).then(j=>{
    if(!j.success||!j.data) return;
    const d=j.data;
    ['active','protocol','host','port','secure','mailbox','username','password','smtp_host','smtp_port','smtp_secure','smtp_username','smtp_password','from_name','from_email','reply_subject_prefix'].forEach(k=>{
      if(document.getElementById(k)) document.getElementById(k).value = (d[k]??'');
    });
  });
}
function save(){
  const d={ op:'save' };
  ['active','protocol','host','port','secure','mailbox','username','password','smtp_host','smtp_port','smtp_secure','smtp_username','smtp_password','from_name','from_email','reply_subject_prefix'].forEach(k=>{
    d[k]=document.getElementById(k).value;
  });
  api('/api/hd/admin/email.php',d).then(j=>{ document.getElementById('msg').textContent=j.success?'Salvo.':('Erro: '+j.error); });
}
function test(){
  const to=document.getElementById('test_to').value.trim(); if(!to){document.getElementById('msg').textContent='Informe o destinatário.';return;}
  api('/api/hd/mail/send.php',{to,subject:'Teste Mozart HD',html:'<p>Teste OK</p>'}).then(j=>{ document.getElementById('msg').textContent=j.success?'Enviado.':('Erro: '+j.error); });
}
function rowTpl(t){return `<tr><td>${t.nome}</td><td>${t.assunto}</td><td>${t.ativo?'Sim':'Não'}</td><td><button class="btn" onclick="openTpl(${t.id})">Abrir</button></td></tr>`;}
function loadTpls(){ const q=document.getElementById('tpl_q').value.trim(); api('/api/hd/admin/templates.php',{op:'list',q}).then(j=>{ document.getElementById('tpl_list').innerHTML=(j.data.items||[]).map(rowTpl).join('')||'<tr><td colspan="4">Sem templates</td></tr>';});}
function openTpl(id){ api('/api/hd/admin/templates.php',{op:'get',id}).then(j=>{ if(!j.success||!j.data) return; currentTpl=j.data.id; document.getElementById('tpl_nome').value=j.data.nome||''; document.getElementById('tpl_assunto').value=j.data.assunto||''; document.getElementById('tpl_html').value=j.data.corpo_html||''; document.getElementById('tpl_ativo').value=j.data.ativo?'1':'0';});}
function novoTpl(){ currentTpl=null; document.getElementById('tpl_nome').value=''; document.getElementById('tpl_assunto').value=''; document.getElementById('tpl_html').value=''; document.getElementById('tpl_ativo').value='1'; document.getElementById('tpl_msg').textContent='';}
function saveTpl(){
  const d={op:'save',id:currentTpl,nome:tpl_nome.value.trim(),assunto:tpl_assunto.value.trim(),corpo_html:tpl_html.value,ativo:tpl_ativo.value==='1'?1:0};
  if(!d.nome||!d.assunto||!d.corpo_html){ tpl_msg.textContent='Preencha nome, assunto e HTML.'; return;}
  api('/api/hd/admin/templates.php',d).then(j=>{ tpl_msg.textContent=j.success?'Template salvo.':('Erro: '+j.error); if(j.data&&j.data.id) currentTpl=j.data.id; loadTpls(); });
}
load(); loadTpls();
</script>
<?php @include_once ROOT_PATH . '/modules/helpdesk/includes/footer_hd.php'; ?>
