<?php
// public/modules/helpdesk/pages/meus.php
ini_set('display_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH.'system/config/autenticacao.php';
require_once ROOT_PATH.'system/config/connect.php';
include_once __DIR__.'/../includes/head_hd.php';
include_once ROOT_PATH.'system/includes/navbar.php';
if (session_status()===PHP_SESSION_NONE) session_start();
if (!usuario_logado()) { header('Location: /login.php'); exit; }
?>
<div class="container hd-feed mt-3 mb-5">
  <h3>Meus chamados</h3>
  <div class="hd-card">
    <div class="row g-2">
      <div class="col-sm-4">
        <input id="search" class="form-control" placeholder="Protocolo, título ou TAG">
      </div>
      <div class="col-sm-3">
        <select id="status" class="form-select">
          <option value="">Status (todos)</option>
          <option value="novo">Novo</option>
          <option value="aberto">Aberto</option>
          <option value="aguardando_usuario">Aguardando usuário</option>
          <option value="resolvido">Resolvido</option>
          <option value="fechado">Fechado</option>
        </select>
      </div>
      <div class="col-sm-2">
        <button id="filtrar" class="btn btn-primary w-100">Filtrar</button>
      </div>
      <div class="col-sm-3 text-end">
        <a class="btn btn-outline-secondary" href="/modules/helpdesk/pages/abrir.php">+ Abrir chamado</a>
      </div>
    </div>
  </div>

  <div id="lista"></div>
  <div id="pager" class="mt-3"></div>
</div>
<script>
async function load(page=1){
  const st = document.getElementById('status').value;
  const se = document.getElementById('search').value;
  const url = `/modules/helpdesk/api/router.php/hd/tickets/mine?page=${page}&per=10&status=${encodeURIComponent(st)}&search=${encodeURIComponent(se)}`;
  const r = await fetch(url);
  const j = await r.json();
  const L = document.getElementById('lista'); L.innerHTML='';
  if(!j.ok){ L.innerHTML=`<div class="alert alert-danger">Erro ao carregar</div>`; return; }
  if(j.items.length===0){ L.innerHTML=`<div class="alert alert-info">Nenhum chamado encontrado.</div>`; }
  j.items.forEach(it=>{
    const d = document.createElement('div'); d.className='hd-card';
    d.innerHTML = `
      <div class="d-flex justify-content-between">
        <div>
          <div><a href="/modules/helpdesk/pages/ticket.php?id=${it.id}">${it.protocolo}</a> — ${it.titulo}</div>
          <div class="text-muted" style="font-size:.9rem">${it.servico_nome||''}</div>
        </div>
        <div class="text-end">
          <div class="hd-chip">${it.status}</div>
          <div class="text-muted" style="font-size:.85rem">${new Date(it.created_at.replace(' ','T')).toLocaleString()}</div>
        </div>
      </div>`;
    L.appendChild(d);
  });
  // Pager simples
  const P = document.getElementById('pager'); P.innerHTML='';
  const totalPages = Math.ceil(j.total / j.per);
  if(totalPages>1){
    const wrap=document.createElement('div');
    for(let p=1;p<=totalPages;p++){
      const b=document.createElement('button');
      b.className='btn btn-sm '+(p===j.page?'btn-primary':'btn-outline-primary');
      b.textContent=p; b.style.marginRight='.25rem';
      b.onclick=()=>load(p);
      wrap.appendChild(b);
    }
    P.appendChild(wrap);
  }
}
document.getElementById('filtrar').addEventListener('click', ()=>load(1));
load(1);
</script>
<?php include_once __DIR__.'/../includes/footer_hd.php'; ?>
