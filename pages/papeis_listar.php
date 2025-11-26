<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';

include_once ROOT_PATH.'/system/includes/head.php';
include_once ROOT_PATH.'/system/includes/navbar.php';
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= htmlspecialchars(APP_NAME) ?></h1></div></div>

    <div class="row"><div class="col-lg-12">
      <h2>Papéis</h2>
      <div style="margin:10px 0 18px;">
        <a class="btn btn-primary" href="<?= BASE_URL ?>/pages/papeis_form.php">+ Adicionar Papel</a>
      </div>

      <div class="table-responsive">
        <table id="tabelaPapeis" class="display table table-bordered table-hover table-striped" style="width:100%">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nome</th>
              <th>Caminho</th>
              <th>Nível</th>
              <th>Status</th>
              <th style="width:230px;">Ação</th>
            </tr>
          </thead>
        </table>
      </div>
    </div></div>
  </div>
</div>

<?php include_once ROOT_PATH.'/system/includes/code_footer.php'; ?>
<script>
$(function(){
  const $t = $('#tabelaPapeis');
  if ($.fn.DataTable.isDataTable($t)) {
    $t.DataTable().clear().destroy();
    $t.find('tbody').empty();
  }

  $t.DataTable({
    ajax: {
      url: '<?= BASE_URL ?>/system/actions/papeis_list.php',
      dataSrc: function (json) {
        // espere um ARRAY: [] ; se vier um objeto com {data: []}, adapte aqui.
        if (Array.isArray(json)) return json;
        if (json && Array.isArray(json.data)) return json.data;
        console.warn('Resposta inesperada de papeis_list.php:', json);
        return [];
      }
    },
    columns: [
      { data:'id' },
      { data:'nome', render:v=> v? $('<div>').text(v).html() : '' },
      { data:'path', defaultContent:'', render:v=> v||'' },
      { data:'depth', defaultContent:0, className:'text-center' },
      { data:'ativo', defaultContent:0, render:v=> (String(v)==='1'||v===1)
          ? '<span class="label label-success">Ativo</span>'
          : '<span class="label label-default">Inativo</span>' },
      { data:null, orderable:false, searchable:false,
        render: function(row){
          const id = Number(row.id);
          const ativo = (String(row.ativo)==='1'||row.ativo===1);
          return `
            <a class="btn btn-primary btn-xs" href="<?= BASE_URL ?>/pages/papeis_form.php?id=${id}">Atualizar</a>
            <button class="btn btn-warning btn-xs" onclick="togglePapel(${id}, ${ativo?1:0})">${ativo?'Inativar':'Ativar'}</button>
            <button class="btn btn-danger btn-xs" onclick="deletePapel(${id})">Excluir</button>`;
        }
      }
    ],
    // use índices numéricos (2=path, 3=depth)
    order: [[3,'asc'], [2,'asc']],
    pageLength: 50,
    responsive: true,
    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' }
  });
});


function togglePapel(id, atual){
  const acao = atual ? 'inativar' : 'ativar';
  if (!confirm(`Confirma ${acao} o papel ${id}?`)) return;
  $.post('<?= BASE_URL ?>/system/actions/papeis_toggle.php',{id},function(res){
    try{ if(typeof res==='string') res=JSON.parse(res);}catch(e){}
    alert((res&&res.mensagem)?res.mensagem:'Status atualizado');
    $('#tabelaPapeis').DataTable().ajax.reload(null,false);
  },'json').fail(function(xhr){
    alert('Falha ao alterar status: '+(xhr.responseText||xhr.status));
  });
}
function deletePapel(id){
  const check = prompt(`Para confirmar a EXCLUSÃO do papel ${id}, digite EXCLUIR:`);
  if (check!=='EXCLUIR') return;
  $.post('<?= BASE_URL ?>/system/actions/papeis_delete.php',{id},function(res){
    try{ if(typeof res==='string') res=JSON.parse(res);}catch(e){}
    alert((res&&res.mensagem)?res.mensagem:'Papel excluído');
    $('#tabelaPapeis').DataTable().ajax.reload(null,false);
  },'json').fail(function(xhr){
    alert('Falha ao excluir: '+(xhr.responseText||xhr.status));
  });
}
</script>
<?php include_once ROOT_PATH.'/system/includes/footer.php'; ?>
