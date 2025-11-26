<?php
// public/modules/helpdesk/pages/admin/mensageria.php
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
  <h3>Admin • Mensageria (WhatsApp / Telegram)</h3>
  <div class="grid">
    <div class="card">
      <h4>Canais</h4>
      <div style="display:flex;gap:8px;margin-bottom:6px">
        <button class="btn primary" onclick="novoCanal()">Novo Canal</button>
        <button class="btn" onclick="loadCanais()">Recarregar</button>
      </div>
      <table>
        <thead><tr><th>Nome</th><th>Ativo</th><th>Ação</th></tr></thead>
        <tbody id="chan_list"></tbody>
      </table>
      <hr>
      <div style="display:grid;gap:8px">
        <input id="chan_nome" placeholder="whatsapp ou telegram">
        <select id="chan_ativo"><option value="1">Ativo</option><option value="0">Inativo</option></select>
        <textarea id="chan_cfg" style="min-height:160px" placeholder='{"host":"http://127.0.0.1:21465","instance":"default","token":"xxx"}'></textarea>
        <div style="display:flex;gap:8px">
          <button class="btn primary" onclick="saveCanal()">Salvar Canal</button>
        </div>
        <div id="chan_msg" style="color:#555"></div>
      </div>
    </div>
    <div class="card">
      <h4>Templates</h4>
      <div style="display:flex;gap:8px;margin-bottom:6px">
        <input id="tpl_q" placeholder="Buscar template...">
        <button class="btn" onclick="loadTpls()">Buscar</button>
        <button class="btn primary" onclick="novoTpl()">Novo</button>
      </div>
      <table>
        <thead><tr><th>Nome</th><th>Canal</th><th>Ativo</th><th>Ação</th></tr></thead>
        <tbody id="tpl_list"></tbody>
      </table>
      <hr>
      <div style="display:grid;gap:8px">
        <input id="tpl_nome" placeholder="Nome do template">
        <input id="tpl_canal" placeholder="whatsapp|telegram|multi">
        <textarea id="tpl_texto" style="min-height:160px" placeholder="Mensagem com {{variaveis}}."></textarea>
        <select id="tpl_ativo"><option value="1">Ativo</option><option value="0">Inativo</option></select>
        <div style="display:flex;gap:8px">
          <button class="btn primary" onclick="saveTpl()">Salvar Template</button>
          <button class="btn" onclick="testeEnvio()">Enviar Teste</button>
          <input id="teste_dest" placeholder="+5531999999999 ou chat_id">
        </div>
        <div id="tpl_msg" style="color:#555"></div>
      </div>
    </div>
  </div>
</div>
<script>
const CSRF="<?php echo $csrf;?>"; let currentChan=null, currentTpl=null;
function api(url,data={}){return fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...data,csrf:CSRF})}).then(r=>r.json());}
function rowCanal(c){return `<tr><td>${c.nome}</td><td>${c.ativo?'Sim':'Não'}</td><td><button class='btn' onclick='openCanal(${c.id})'>Abrir</button></td></tr>`;}
function rowTpl(t){return `<tr><td>${t.nome}</td><td>${t.canal}</td><td>${t.ativo?'Sim':'Não'}</td><td><button class='btn' onclick='openTpl(${t.id})'>Abrir</button></td></tr>`;}
function loadCanais(){ api('/api/hd/admin/mensageria.php',{op:'list_channels'}).then(j=>{ chan_list.innerHTML=(j.data.items||[]).map(rowCanal).join('')||'<tr><td colspan="3">Sem canais</td></tr>'; });}
function openCanal(id){ api('/api/hd/admin/mensageria.php',{op:'get_channel',id}).then(j=>{ if(!j.success||!j.data) return; currentChan=j.data.id; chan_nome.value=j.data.nome||''; chan_ativo.value=j.data.ativo?'1':'0'; chan_cfg.value=j.data.cfg_json||'{}'; });}
function novoCanal(){ currentChan=null; chan_nome.value=''; chan_ativo.value='1'; chan_cfg.value='{}'; chan_msg.textContent=''; }
function saveCanal(){ const d={op:'save_channel',id:currentChan,nome:chan_nome.value.trim(),ativo:chan_ativo.value==='1'?1:0,cfg_json:chan_cfg.value}; api('/api/hd/admin/mensageria.php',d).then(j=>{ chan_msg.textContent=j.success?'Canal salvo.':('Erro: '+j.error); if(j.data&&j.data.id) currentChan=j.data.id; loadCanais(); });}
function loadTpls(){ const q=tpl_q.value.trim(); api('/api/hd/admin/mensageria.php',{op:'list_templates',q}).then(j=>{ tpl_list.innerHTML=(j.data.items||[]).map(rowTpl).join('')||'<tr><td colspan="4">Sem templates</td></tr>';});}
function openTpl(id){ api('/api/hd/admin/mensageria.php',{op:'get_template',id}).then(j=>{ if(!j.success||!j.data) return; currentTpl=j.data.id; tpl_nome.value=j.data.nome||''; tpl_canal.value=j.data.canal||''; tpl_texto.value=j.data.texto||''; tpl_ativo.value=j.data.ativo?'1':'0'; });}
function novoTpl(){ currentTpl=null; tpl_nome.value=''; tpl_canal.value='whatsapp'; tpl_texto.value='Olá! Ticket {{protocolo}} atualizado.'; tpl_ativo.value='1'; tpl_msg.textContent=''; }
function saveTpl(){ const d={op:'save_template',id:currentTpl,nome:tpl_nome.value.trim(),canal:tpl_canal.value.trim(),texto:tpl_texto.value,ativo:tpl_ativo.value==='1'?1:0}; if(!d.nome||!d.canal||!d.texto){ tpl_msg.textContent='Preencha nome, canal e texto.'; return;} api('/api/hd/admin/mensageria.php',d).then(j=>{ tpl_msg.textContent=j.success?'Template salvo.':('Erro: '+j.error); if(j.data&&j.data.id) currentTpl=j.data.id; loadTpls(); });}
function testeEnvio(){
  const dest=document.getElementById('teste_dest').value.trim();
  if(!dest){ tpl_msg.textContent='Informe o destino.'; return; }
  api('/api/hd/notify/send.php',{canal:tpl_canal.value.trim(),destino:dest,template_id:currentTpl,vars:{protocolo:'TESTE',assunto:'Teste Mozart'}}).then(j=>{
    tpl_msg.textContent = j.success ? 'Enfileirado com sucesso.' : ('Erro: '+j.error);
  });
}
loadCanais(); loadTpls();
</script>
<?php @include_once ROOT_PATH . '/modules/helpdesk/includes/footer_hd.php'; ?>
