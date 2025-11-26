<?php
// pages/niveis_listar.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . 'system/config/autenticacao.php'; // se houver
if (session_status() === PHP_SESSION_NONE) session_start();

// Helpers
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); } }

$page_title = "Níveis de Acesso";

include ROOT_PATH . 'system/includes/head.php';
include ROOT_PATH . 'system/includes/navbar.php';
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?=h($page_title)?></h1></div></div>

    <div class="row">
      <div class="col-lg-12">
        <div class="panel panel-default">
          <div class="panel-heading" style="display:flex;justify-content:space-between;align-items:center;">
            <span>Listagem</span>
            <div>
              <a class="btn btn-primary btn-sm" href="niveis_form.php">+ Novo nível</a>
            </div>
          </div>
          <div class="panel-body">
            <table id="tb-niveis" class="table table-striped table-bordered" width="100%">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nome</th>
                  <th>Back</th>
                  <th>Front</th>
                  <th>Ativo</th>
                  <th>Atualizado</th>
                  <th style="width:160px;">Ações</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<?php include_once ROOT_PATH . 'system/includes/code_footer.php'; ?>

<script>
$(function(){
  const dt = $('#tb-niveis').DataTable({
    processing: true,
    serverSide: true,
    ajax: 'niveis_list.php',
    order: [[0,'desc']],
    columns: [
      { data: 'id' },
      { data: 'nome' },
      { data: 'acesso_backend', render: v => v==1?'Sim':'Não' },
      { data: 'acesso_frontend', render: v => v==1?'Sim':'Não' },
      { data: 'ativo', render: v => v==1?'<span class="label label-success">Ativo</span>':'<span class="label label-default">Inativo</span>' },
      { data: 'updated_at' },
      { data: 'acoes', orderable:false, searchable:false }
    ],
    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' }
  });

  // excluir
  $(document).on('click','.btn-excluir',function(e){
    e.preventDefault();
    if(!confirm('Confirma excluir este nível? Esta ação não pode ser desfeita.')) return;
    $.post('/system/actions/niveis_delete.php', { id: $(this).data('id') }, function(r){
      if(r && r.ok){ dt.ajax.reload(null,false); } else { alert(r.msg||'Falha ao excluir.'); }
    },'json');
  });

  // toggle ativo
  $(document).on('click','.btn-toggle',function(e){
    e.preventDefault();
    $.post('/system/actions/niveis_toggle.php', { id: $(this).data('id') }, function(r){
      if(r && r.ok){ dt.ajax.reload(null,false); } else { alert(r.msg||'Falha ao alterar status.'); }
    },'json');
  });
});
</script>
<?php include ROOT_PATH . 'system/includes/footer.php'; ?>
