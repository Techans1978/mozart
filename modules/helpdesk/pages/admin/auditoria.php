<?php
// public/modules/helpdesk/pages/admin/auditoria.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once ROOT_PATH . '/includes/hd_rbac.php';
include_once ROOT_PATH . '/system/includes/head.php';
require_once ROOT_PATH.'/system/includes/head_hd.php';

proteger_pagina();
$user_id = $_SESSION['usuario_id'] ?? 0;
hd_require($conn, $user_id, 'audit.view');

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
  <h3 class="mt-3 mb-3">Auditoria — Campo a Campo</h3>

  <form id="f" class="row g-2 mb-3">
    <div class="col-md-3">
      <label class="form-label">Tabela</label>
      <input type="text" class="form-control" name="table_name" placeholder="hd_ticket">
    </div>
    <div class="col-md-2">
      <label class="form-label">Record ID</label>
      <input type="number" class="form-control" name="record_id" min="1">
    </div>
    <div class="col-md-2">
      <label class="form-label">Usuário (ID)</label>
      <input type="number" class="form-control" name="changed_by" min="1">
    </div>
    <div class="col-md-2">
      <label class="form-label">De</label>
      <input type="date" class="form-control" name="date_from">
    </div>
    <div class="col-md-2">
      <label class="form-label">Até</label>
      <input type="date" class="form-control" name="date_to">
    </div>
    <div class="col-md-1 d-flex align-items-end">
      <button class="btn btn-primary w-100">Buscar</button>
    </div>
  </form>

  <div id="aud-box">Use os filtros e clique em "Buscar".</div>
</div>
	  <!-- End content -->
      </div>
    </div>
  </div>
</div>
<!-- layout -->
<script>
document.getElementById('f').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.set('action','list');
  const r = await fetch('/api/hd/admin/auditoria.php',{method:'POST', body:fd}).then(r=>r.json());
  const box = document.getElementById('aud-box');
  if(!r.success){ box.innerHTML = `<div class="alert alert-danger">${r.message||'Erro'}</div>`; return; }
  const rows = r.data||[];
  let html = `<div class="table-responsive"><table class="table table-sm table-bordered">
  <thead><tr>
    <th>ID</th><th>Tabela</th><th>Registro</th><th>Campo</th><th>Antigo</th><th>Novo</th><th>Alterado por</th><th>Quando</th><th>IP</th><th>UA</th><th>Nota</th>
  </tr></thead><tbody>`;
  for (const a of rows){
    html += `<tr>
      <td>${a.id}</td>
      <td>${a.table_name}</td>
      <td>${a.record_id}</td>
      <td>${a.field_name}</td>
      <td><small>${a.old_value||''}</small></td>
      <td><small>${a.new_value||''}</small></td>
      <td>${a.changed_by}</td>
      <td>${a.changed_at}</td>
      <td><small>${a.ip_address||''}</small></td>
      <td><small>${a.user_agent||''}</small></td>
      <td><small>${a.note||''}</small></td>
    </tr>`;
  }
  html += `</tbody></table></div>`;
  box.innerHTML = html;
});
</script>

<?php require_once ROOT_PATH . '/system/includes/footer_hd.php'; ?>
