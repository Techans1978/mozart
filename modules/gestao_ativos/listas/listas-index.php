<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

include_once ROOT_PATH . 'system/includes/head.php';
include_once ROOT_PATH . 'system/includes/navbar.php';

if (!isset($conn) && isset($mysqli)) { $conn = $mysqli; }
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12">
      <h1 class="page-header"><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
    </div></div>

    <div class="row"><div class="col-lg-12">
      <h2>Listas Padrão (Ativos)</h2>

      <?php
        if (session_status()===PHP_SESSION_NONE) session_start();
        if (!empty($_SESSION['__flash'])) {
          $f = $_SESSION['__flash']; unset($_SESSION['__flash']);
          $type = htmlspecialchars($f['t'] ?? 'info', ENT_QUOTES, 'UTF-8');
          $msg  = htmlspecialchars($f['m'] ?? '', ENT_QUOTES, 'UTF-8');
          echo '<div class="alert alert-'.$type.'">'.$msg.'</div>';
        }
      ?>

      <div class="table-responsive">
        <table id="tabelaListas" class="display table table-bordered table-hover table-striped" style="width:100%">
          <thead>
            <tr>
              <th>ID</th>
              <th>Lista</th>
              <th>Slug</th>
              <th>Ativos</th>
              <th>Inativos</th>
              <th>Total</th>
              <th>Status</th>
              <th style="width:230px;">Ação</th>
            </tr>
          </thead>
        </table>
      </div>

      <p class="help-block" style="margin-top:10px;">
        Dica: clique em <strong>Gerenciar Itens</strong> para editar/inativar/excluir e importar (texto/CSV).
      </p>

    </div></div>
  </div>
</div>

<?php include_once ROOT_PATH . 'system/includes/code_footer.php'; ?>

<script>
$(function () {
  var $t = $('#tabelaListas');
  if ($.fn.DataTable.isDataTable($t)) {
    $t.DataTable().clear().destroy();
    $t.find('tbody').empty();
  }

  var dt = $t.DataTable({
    ajax: {
      url: '<?= BASE_URL ?>/modules/gestao_ativos/listas/actions/listas_list.php',
      dataSrc: function (json) {
        if (Array.isArray(json)) return json;
        if (json && Array.isArray(json.data)) return json.data;
        console.warn('Formato inesperado em listas_list.php:', json);
        return [];
      },
      error: function(xhr){
        console.error('AJAX listas_list falhou:', xhr.status, xhr.responseText);
        alert('Falha ao carregar as listas. Verifique o console (F12 > Network).');
      }
    },
    columns: [
      { data: 'id' },
      { data: 'title',
        render: function (v) { return v ? $('<div>').text(v).html() : ''; }
      },
      { data: 'slug',
        render: function (v) { return v ? $('<div>').text(v).html() : ''; }
      },
      { data: 'active_count', className:'text-center' },
      { data: 'inactive_count', className:'text-center' },
      { data: 'total_count', className:'text-center' },
      { data: 'active',
        render: function (v) {
          var ok = (String(v)==='1' || v===1);
          return ok ? '<span class="label label-success">Ativo</span>'
                    : '<span class="label label-default">Inativo</span>';
        }
      },
      { data: null, orderable:false, searchable:false,
        render: function (row) {
          var slug = row.slug ? encodeURIComponent(row.slug) : '';
          var href = '<?= BASE_URL ?>/modules/gestao_ativos/listas/listas-itens.php?list=' + slug;
          var btn = '<a class="btn btn-primary btn-xs" href="' + href + '"><i class="fa fa-cogs"></i> Gerenciar Itens</a>';
          return btn;
        }
      }
    ],
    order: [[1,'asc']],
    pageLength: 50,
    responsive: true,
    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' }
  });
});
</script>

<?php include_once ROOT_PATH . 'system/includes/footer.php'; ?>
