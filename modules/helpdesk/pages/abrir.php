<?php
// public/modules/helpdesk/pages/abrir.php
ini_set('display_errors',1); error_reporting(E_ALL);
require_once dirname(__DIR__, 3).'/config.php';
require_once ROOT_PATH.'system/config/autenticacao.php';
require_once ROOT_PATH.'system/config/connect.php';
include_once __DIR__.'/../includes/head_hd.php';
include_once ROOT_PATH.'system/includes/navbar.php';
if (session_status()===PHP_SESSION_NONE) session_start();
if (!usuario_logado()) { header('Location: /login.php'); exit; }
?>
<div class="container hd-feed mt-3 mb-5">
  <h3>Abrir chamado</h3>
  <div class="hd-card">
    <div id="chat-area">
      <div class="mb-2">👋 Olá! Sobre o que é o seu chamado?</div>
      <div class="mb-3">
        <label class="form-label">Serviço</label>
        <select id="srv" class="form-select"></select>
      </div>
      <div class="mb-3">
        <label class="form-label">Título</label>
        <input id="titulo" class="form-control" placeholder="Resuma em uma frase...">
      </div>
      <div class="mb-3">
        <label class="form-label">Descrição</label>
        <textarea id="desc" rows="4" class="form-control" placeholder="Conte os detalhes. Você pode anexar depois."></textarea>
      </div>
      <div class="row">
        <div class="col-sm-6 mb-3">
          <label class="form-label">Entidade</label>
          <input id="entidade_id" class="form-control" placeholder="ID da empresa (temporário)">
        </div>
        <div class="col-sm-6 mb-3">
          <label class="form-label">Loja</label>
          <input id="loja_id" class="form-control" placeholder="ID da loja (se houver)">
        </div>
      </div>
      <button id="btnAbrir" class="btn btn-primary">Abrir chamado</button>
      <div id="res" class="mt-3"></div>
    </div>
  </div>
  <div class="hd-card">
    <div class="d-flex justify-content-between align-items-center">
      <strong>Artigos sugeridos</strong>
      <span class="text-muted" style="font-size:.85rem">mostraremos aqui sugestões com base no texto</span>
    </div>
    <div id="kb-sug"></div>
  </div>
</div>
<script>
(async function() {
  // Carrega serviços (mínimo)
  const srvSel = document.getElementById('srv');
  try {
    const resp = await fetch('/modules/helpdesk/api/router.php/hd/tickets/mine?page=1&per=1'); // ping simples p/ sessão
    // carrega por SQL leve (substituir por endpoint /servicos quando pronto)
    const opt = document.createElement('option'); opt.value=''; opt.textContent='Selecione...'; srvSel.appendChild(opt);
    // Até criarmos o endpoint de serviços, puxar por um embed simples via PHP seria o ideal.
    // Deixe manual temporariamente:
    ['Acesso a Sistema','Impressora','PDV/Frente de Caixa','Internet/Rede','Balança (erro/calibração)'].forEach((n,i)=>{
      const o=document.createElement('option'); o.value= (i+1); o.textContent=n; srvSel.appendChild(o);
    });
  } catch(e){}

  document.getElementById('btnAbrir').addEventListener('click', async ()=>{
    const payload = {
      servico_id: parseInt(srvSel.value || '0',10),
      titulo: document.getElementById('titulo').value,
      descricao: document.getElementById('desc').value,
      entidade_id: parseInt(document.getElementById('entidade_id').value||'0',10),
      loja_id: parseInt(document.getElementById('loja_id').value||'0',10),
      canal: 'portal',
      prioridade: 'media',
      criticidade: 'C3'
    };
    const r = await fetch('/modules/helpdesk/api/router.php/hd/tickets', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const j = await r.json();
    const res = document.getElementById('res');
    if (j.ok) {
      res.innerHTML = `<div class="alert alert-success">Chamado criado! Protocolo <b>${j.protocolo}</b>. <a href="/modules/helpdesk/pages/ticket.php?id=${j.ticket_id}">Abrir</a></div>`;
    } else {
      res.innerHTML = `<div class="alert alert-danger">Erro: ${j.error||'desconhecido'}</div>`;
    }
  });
})();
</script>
<?php include_once __DIR__.'/../includes/footer_hd.php'; ?>
