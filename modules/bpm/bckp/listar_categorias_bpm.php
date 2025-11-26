<?php
// modules/bpm/bpm_designer.php
// Mozart BPM — Modeler com Properties + Element Templates (CDN + fallback local)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

include_once ROOT_PATH . 'system//includes/head.php';
include_once ROOT_PATH . 'system//includes/navbar.php';
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12">
      <h1 class="page-header"><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
    </div></div>

    <div class="row"><div class="col-lg-12">
      <h2>Categorias</h2>
      <div style="margin:10px 0 18px;">
        <a class="btn btn-primary" href="<?= BASE_URL ?>/modules/bpm/form_categorias_bpm.php">+ Adicionar Categoria</a>
      </div>

      <div class="table-responsive">
        <table id="tabelaGrupos" class="display table table-bordered table-hover table-striped" style="width:100%">
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

<?php include_once ROOT_PATH . 'system/includes/code_footer.php'; ?>

<script>
$(function () {
  var $t = $('#tabelaCatbpm');
  if ($.fn.DataTable.isDataTable($t)) {
    $t.DataTable().clear().destroy();
    $t.find('tbody').empty();
  }

  var dt = $t.DataTable({
    ajax: {
      url: '<?= BASE_URL ?>/modules/includes/actions/categorias_bpm_list.php',
      dataSrc: function (json) {
        if (Array.isArray(json)) return json;
        if (json && Array.isArray(json.data)) return json.data;
        console.warn('Formato inesperado em categorias_bpm_list.php:', json);
        return [];
      },
      error: function(xhr){
        console.error('AJAX catbpm_list falhou:', xhr.status, xhr.responseText);
        alert('Falha ao carregar a lista de grupos. Verifique o console (F12 > Network).');
      }
    },
    columns: [
      { data: 'id' },
      { data: 'nome',
        render: function (v) { return v ? $('<div>').text(v).html() : ''; }
      },
      { data: 'caminho',
        render: function (v) { return v ? $('<div>').text(v).html() : ''; }
      },
      { data: 'nivel', className: 'text-center' },
      { data: 'ativo',
        render: function (v) {
          return (String(v)==='1' || v===1)
            ? '<span class="label label-success">Ativo</span>'
            : '<span class="label label-default">Inativo</span>';
        }
      },
      { data: null, orderable:false, searchable:false,
        render: function (row) {
          var id = Number(row.id);
          var href = '<?= BASE_URL ?>/modules/bpm/form_categorias_bpm.php?id=' + id;
          var btnAtualizar = '<a class="btn btn-primary btn-xs" href="' + href + '">Atualizar</a>';
          var btnToggle = '<button class="btn btn-warning btn-xs" onclick="toggleGrupo(' + id + ',' + (row.ativo?1:0) + ')">' + (row.ativo ? 'Inativar' : 'Ativar') + '</button>';
          var btnExcluir = '<button class="btn btn-danger btn-xs" onclick="deleteGrupo(' + id + ')">Excluir</button>';
          return btnAtualizar + ' ' + btnToggle + ' ' + btnExcluir;
        }
      }
    ],
    order: [[3,'asc'], [2,'asc']], // nivel, caminho
    pageLength: 50,
    responsive: true,
    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' }
  });

  window.toggleGrupo = function(id, atual) {
    var acao = atual ? 'inativar' : 'ativar';
    if (!confirm('Confirma ' + acao + ' da categoria ' + id + '?')) return;
    $.post('<?= BASE_URL ?>/modules/bpm/actions/categorias_bpm_toggle.php', { id: id }, function (res) {
      try { if (typeof res === 'string') res = JSON.parse(res); } catch(e){}
      alert((res && res.mensagem) ? res.mensagem : 'Status atualizado');
      dt.ajax.reload(null, false);
    }, 'json').fail(function(xhr){
      alert('Falha ao alterar status: ' + (xhr.responseText || xhr.status));
    });
  };

  window.deleteGrupo = function(id) {
    var check = prompt('Para confirmar a EXCLUSÃO da categoria ' + id + ', digite EXCLUIR:');
    if (check !== 'EXCLUIR') return;
    $.post('<?= BASE_URL ?>/modules/bpm/categorias_bpm_delete.php', { id: id }, function (res) {
      try { if (typeof res === 'string') res = JSON.parse(res); } catch(e){}
      alert((res && res.mensagem) ? res.mensagem : 'Grupo excluído');
      dt.ajax.reload(null, false);
    }, 'json').fail(function(xhr){
      alert('Falha ao excluir: ' + (xhr.responseText || xhr.status));
    });
  };
});
</script>


<?php include_once ROOT_PATH . 'system//includes/footer.php'; ?>
