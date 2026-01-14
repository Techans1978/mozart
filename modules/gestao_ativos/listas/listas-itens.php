<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/listas_guard.php';

$listSlug = ga_trim($_GET['list'] ?? '');
$list = ga_list_by_slug($conn, $listSlug);
if (!$list) {
  ga_flash_set("Lista inválida.", "danger");
  ga_redirect(BASE_URL . "/modules/gestao_ativos/listas/listas-index.php");
}

include_once ROOT_PATH . 'system/includes/head.php';
include_once ROOT_PATH . 'system/includes/navbar.php';

$flash = ga_flash_get();
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12">
      <h1 class="page-header"><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
    </div></div>

    <div class="row"><div class="col-lg-12">
      <h2>
        Itens da Lista: <?= htmlspecialchars($list['title'], ENT_QUOTES, 'UTF-8') ?>
        <small class="text-muted">(slug: <?= htmlspecialchars($list['slug'], ENT_QUOTES, 'UTF-8') ?>)</small>
      </h2>

      <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['t'], ENT_QUOTES, 'UTF-8') ?>">
          <?= htmlspecialchars($flash['m'], ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <div style="margin:10px 0 18px;">
        <a class="btn btn-success" href="<?= BASE_URL ?>/modules/gestao_ativos/listas/listas-form.php?list=<?= urlencode($list['slug']) ?>">
          + Adicionar Item
        </a>
        <button class="btn btn-info" data-toggle="modal" data-target="#modalImport">
          <i class="fa fa-upload"></i> Importar (Texto/CSV)
        </button>
        <a class="btn btn-default" href="<?= BASE_URL ?>/modules/gestao_ativos/listas/listas-index.php">
          Voltar
        </a>
      </div>

      <div class="table-responsive">
        <table id="tabelaItens" class="display table table-bordered table-hover table-striped" style="width:100%">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nome</th>
              <th>Descrição</th>
              <th>Pai</th>
              <th>Nível</th>
              <th>Ordem</th>
              <th>Status</th>
              <th style="width:260px;">Ação</th>
            </tr>
          </thead>
        </table>
      </div>

      <p class="help-block" style="margin-top:10px;">
        Todas as listas suportam <strong>subcategoria</strong> via campo <strong>Pai</strong> (parent_id).
      </p>

    </div></div>
  </div>
</div>

<!-- Modal Import -->
<div class="modal fade" id="modalImport" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title"><i class="fa fa-upload"></i> Importar Itens</h4>
      </div>

      <div class="modal-body">
        <ul class="nav nav-tabs">
          <li class="active"><a href="#tabTexto" data-toggle="tab">Texto</a></li>
          <li><a href="#tabCsv" data-toggle="tab">CSV</a></li>
        </ul>

        <div class="tab-content" style="margin-top:12px;">
          <div class="tab-pane fade in active" id="tabTexto">
            <form method="post" action="<?= BASE_URL ?>/modules/gestao_ativos/listas/actions/import_text.php">
              <input type="hidden" name="list" value="<?= htmlspecialchars($list['slug'], ENT_QUOTES, 'UTF-8') ?>">

              <div class="form-group">
                <label>Colar lista (separado por vírgula, ponto-e-vírgula ou quebra de linha)</label>
                <textarea class="form-control" name="bulk_text" rows="6" placeholder="Ex: TI, RH, Financeiro&#10;ou TI; RH; Financeiro"></textarea>
              </div>

              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Descrição padrão (opcional)</label>
                    <input class="form-control" name="description_base" placeholder="Se preenchido, aplica a todos (você edita depois).">
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="form-group">
                    <label>Adicionar como subitens de (Pai)</label>
                    <select class="form-control" name="parent_id" id="importParentSelect">
                      <option value="">— Sem pai (nível raiz) —</option>
                      <!-- carregado via AJAX (opcional) -->
                    </select>
                  </div>
                </div>
              </div>

              <button class="btn btn-primary"><i class="fa fa-check"></i> Importar Texto</button>
            </form>
          </div>

          <div class="tab-pane fade" id="tabCsv">
            <div class="alert alert-warning">
              <strong>Formato sugerido:</strong> name, description, parent (opcional)
              <br>Exemplo: <code>TI,Setor de tecnologia,Administração</code>
            </div>

            <form method="post" enctype="multipart/form-data" action="<?= BASE_URL ?>/modules/gestao_ativos/listas/actions/import_csv.php">
              <input type="hidden" name="list" value="<?= htmlspecialchars($list['slug'], ENT_QUOTES, 'UTF-8') ?>">

              <div class="form-group">
                <label>Arquivo CSV</label>
                <input type="file" name="csv" class="form-control" required>
              </div>

              <button class="btn btn-primary"><i class="fa fa-upload"></i> Enviar CSV</button>
            </form>
          </div>
        </div>

      </div>

      <div class="modal-footer">
        <button class="btn btn-default" data-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<?php include_once ROOT_PATH . 'system/includes/code_footer.php'; ?>

<script>
$(function () {
  var listSlug = '<?= htmlspecialchars($list['slug'], ENT_QUOTES, 'UTF-8') ?>';
  var $t = $('#tabelaItens');

  if ($.fn.DataTable.isDataTable($t)) {
    $t.DataTable().clear().destroy();
    $t.find('tbody').empty();
  }

  var dt = $t.DataTable({
    ajax: {
      url: '<?= BASE_URL ?>/modules/gestao_ativos/listas/actions/itens_list.php?list=' + encodeURIComponent(listSlug),
      dataSrc: function (json) {
        if (Array.isArray(json)) return json;
        if (json && Array.isArray(json.data)) return json.data;
        console.warn('Formato inesperado em itens_list.php:', json);
        return [];
      },
      error: function(xhr){
        console.error('AJAX itens_list falhou:', xhr.status, xhr.responseText);
        alert('Falha ao carregar itens. Verifique o console (F12 > Network).');
      }
    },
    columns: [
      { data: 'id' },
      { data: 'name',
        render: function (v) { return v ? $('<div>').text(v).html() : ''; }
      },
      { data: 'description',
        render: function (v) { return v ? $('<div>').text(v).html() : ''; }
      },
      { data: 'parent_name',
        render: function (v) { return v ? $('<div>').text(v).html() : '—'; }
      },
      { data: 'level', className:'text-center' },
      { data: 'sort_order', className:'text-center' },
      { data: 'active',
        render: function (v) {
          var ok = (String(v)==='1' || v===1);
          return ok ? '<span class="label label-success">Ativo</span>'
                    : '<span class="label label-default">Inativo</span>';
        }
      },
      { data: null, orderable:false, searchable:false,
        render: function (row) {
          var id = Number(row.id);
          var ativo = (String(row.active)==='1' || row.active===1);

          var href = '<?= BASE_URL ?>/modules/gestao_ativos/listas/listas-form.php?list='
                   + encodeURIComponent(listSlug) + '&id=' + id;

          var btnAtualizar = '<a class="btn btn-primary btn-xs" href="' + href + '">Atualizar</a>';
          var btnToggle = '<button class="btn btn-warning btn-xs" onclick="toggleItem(' + id + ',' + (ativo?1:0) + ')">' + (ativo ? 'Inativar' : 'Ativar') + '</button>';
          var btnExcluir = '<button class="btn btn-danger btn-xs" onclick="deleteItem(' + id + ')">Excluir</button>';

          return btnAtualizar + ' ' + btnToggle + ' ' + btnExcluir;
        }
      }
    ],
    order: [[4,'asc'], [3,'asc'], [1,'asc']], // level, parent, name
    pageLength: 50,
    responsive: true,
    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' }
  });

  // Toggle
  window.toggleItem = function(id, atual) {
    var acao = atual ? 'inativar' : 'ativar';
    if (!confirm('Confirma ' + acao + ' o item ' + id + '?')) return;

    $.post('<?= BASE_URL ?>/modules/gestao_ativos/listas/actions/item_toggle.php',
      { list: listSlug, id: id },
      function (res) {
        alert((res && res.mensagem) ? res.mensagem : 'Status atualizado');
        dt.ajax.reload(null, false);
      },
      'json'
    ).fail(function(xhr){
      alert('Falha ao alterar status: ' + (xhr.responseText || xhr.status));
    });
  };

  // Delete
  window.deleteItem = function(id) {
    var check = prompt('Para confirmar a EXCLUSÃO do item ' + id + ', digite EXCLUIR:');
    if (check !== 'EXCLUIR') return;

    $.post('<?= BASE_URL ?>/modules/gestao_ativos/listas/actions/item_delete.php',
      { list: listSlug, id: id },
      function (res) {
        alert((res && res.mensagem) ? res.mensagem : 'Item excluído');
        dt.ajax.reload(null, false);
      },
      'json'
    ).fail(function(xhr){
      alert('Falha ao excluir: ' + (xhr.responseText || xhr.status));
    });
  };

  // (Opcional) Carregar select de "Pai" para import (apenas itens raiz)
  // Reusa o mesmo endpoint itens_list com filtro root=1 (vamos implementar no action)
  $.getJSON('<?= BASE_URL ?>/modules/gestao_ativos/listas/actions/itens_list.php?list=' + encodeURIComponent(listSlug) + '&root=1', function(resp){
    var arr = Array.isArray(resp) ? resp : (resp && resp.data ? resp.data : []);
    var $sel = $('#importParentSelect');
    arr.forEach(function(it){
      $sel.append('<option value="'+ it.id +'">'+ $('<div>').text(it.name).html() +'</option>');
    });
  });

});
</script>

<?php include_once ROOT_PATH . 'system/includes/footer.php'; ?>
