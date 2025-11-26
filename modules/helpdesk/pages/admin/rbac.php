<?php
// public/modules/helpdesk/pages/admin/rbac.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once ROOT_PATH . '/includes/hd_rbac.php';
include_once ROOT_PATH . '/system/includes/head.php';
require_once ROOT_PATH.'/system/includes/head_hd.php';

proteger_pagina();
$user_id = $_SESSION['usuario_id'] ?? 0;
hd_require($conn, $user_id, 'rbac.manage');

include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<!-- layout -->
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

    <div class="row">
      <div class="col-lg-12">
	  <!-- Content -->
<div class="container-fluid">
  <h3 class="mt-3 mb-3">Admin — RBAC</h3>

  <div class="row">
    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header">Criar Função</div>
        <div class="card-body">
          <form id="form-role" class="row g-2">
            <div class="col-12">
              <label class="form-label">Nome</label>
              <input type="text" name="name" class="form-control" placeholder="Analista N1" required>
            </div>
            <div class="col-12">
              <label class="form-label">Descrição</label>
              <input type="text" name="description" class="form-control" placeholder="Acesso básico">
            </div>
            <div class="col-12">
              <button class="btn btn-primary">Criar</button>
            </div>
          </form>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">Conceder/Revogar Permissão</div>
        <div class="card-body">
          <form id="form-grant" class="row g-2">
            <div class="col-12">
              <label class="form-label">Role ID</label>
              <input type="number" name="role_id" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Permissão (code)</label>
              <input type="text" name="perm_code" class="form-control" placeholder="report.view" required>
            </div>
            <div class="col-12">
              <button class="btn btn-success me-2" onclick="this.form.dataset.mode='grant'">Conceder</button>
              <button class="btn btn-outline-danger" onclick="this.form.dataset.mode='revoke'">Revogar</button>
            </div>
          </form>
        </div>
      </div>

    </div>
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header">Funções & Permissões</div>
        <div class="card-body" id="roles-box">Carregando...</div>
      </div>

      <div class="card mb-5">
        <div class="card-header">Vincular Usuário → Função</div>
        <div class="card-body">
          <form id="form-assign" class="row g-2">
            <div class="col-md-4">
              <label class="form-label">User ID</label>
              <input type="number" name="user_id" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Role ID</label>
              <input type="number" name="role_id" class="form-control" required>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button class="btn btn-primary me-2" onclick="this.form.dataset.mode='assign'">Atribuir</button>
              <button class="btn btn-outline-danger" onclick="this.form.dataset.mode='revoke'">Revogar</button>
            </div>
          </form>
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
async function loadRoles(){
  const fd = new FormData(); fd.set('action','list_all');
  const r = await fetch('/api/hd/admin/rbac.php',{method:'POST', body:fd}).then(r=>r.json());
  const box = document.getElementById('roles-box');
  if(!r.success){ box.innerHTML = `<div class="alert alert-danger">${r.message||'Erro'}</div>`; return; }
  let html = `<div class="table-responsive"><table class="table table-sm table-bordered">
  <thead><tr><th>ID</th><th>Nome</th><th>Descrição</th><th>Permissões</th><th>Ações</th></tr></thead><tbody>`;
  for (const it of r.data.roles){
    const perms = (it.perms||[]).map(p=>p.code).join(', ');
    html += `<tr>
      <td>${it.id}</td>
      <td>${it.name}</td>
      <td>${it.description||''}</td>
      <td><small>${perms}</small></td>
      <td><button class="btn btn-sm btn-outline-danger" onclick="delRole(${it.id})">Excluir</button></td>
    </tr>`;
  }
  html += `</tbody></table></div>
  <h6>Usuários por Role</h6>
  <pre style="white-space:pre-wrap">${JSON.stringify(r.data.users_by_role, null, 2)}</pre>`;
  box.innerHTML = html;
}

async function delRole(id){
  if(!confirm('Excluir esta função?')) return;
  const fd = new FormData(); fd.set('action','delete_role'); fd.set('role_id', id);
  const r = await fetch('/api/hd/admin/rbac.php',{method:'POST', body:fd}).then(r=>r.json());
  if(!r.success){ alert(r.message||'Erro'); return; }
  loadRoles();
}

document.getElementById('form-role').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target); fd.set('action','create_role');
  const r = await fetch('/api/hd/admin/rbac.php',{method:'POST', body:fd}).then(r=>r.json());
  if(!r.success){ alert(r.message||'Erro'); return; }
  e.target.reset();
  loadRoles();
});

document.getElementById('form-grant').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const mode = e.target.dataset.mode || 'grant';
  const fd = new FormData(e.target);
  fd.set('action', mode==='grant' ? 'grant_perm' : 'revoke_perm');
  const r = await fetch('/api/hd/admin/rbac.php',{method:'POST', body:fd}).then(r=>r.json());
  if(!r.success){ alert(r.message||'Erro'); return; }
  e.target.reset();
  loadRoles();
});

document.getElementById('form-assign').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const mode = e.target.dataset.mode || 'assign';
  const fd = new FormData(e.target);
  fd.set('action', mode==='assign' ? 'assign_user_role' : 'revoke_user_role');
  const r = await fetch('/api/hd/admin/rbac.php',{method:'POST', body:fd}).then(r=>r.json());
  if(!r.success){ alert(r.message||'Erro'); return; }
  e.target.reset();
  loadRoles();
});

document.addEventListener('DOMContentLoaded', loadRoles);
</script>

<?php require_once ROOT_PATH . '/system/includes/footer_hd.php'; ?>
