<?php
// Mostrar erros (dev)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

include_once ROOT_PATH . 'system/includes/head.php';
include_once ROOT_PATH . 'system/includes/navbar.php';
?>
<!-- Page Content -->
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

    <div class="row">
      <div class="col-lg-12">
        <h2>Gerenciar Usuários</h2>

        <div class="table-responsive">
          <table id="tabelaUsuarios" class="display table table-bordered table-hover table-striped" style="width:100%">
            <thead>
              <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Status</th>
                <th>Ação</th>
              </tr>
            </thead>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>

<?php include_once ROOT_PATH . 'system/includes/code_footer.php'; ?>

<script>
$(function () {
  const $t = $('#tabelaUsuarios');

  // garante init único
  if ($.fn.DataTable.isDataTable($t)) {
    $t.DataTable().clear().destroy();
    $t.find('tbody').empty();
  }

  $t.DataTable({
    ajax: {
      url: '<?= BASE_URL ?>/system/actions/usuarios_list.php',
      // aceita tanto [] quanto {data: []}
      dataSrc: function (json) {
        if (Array.isArray(json)) return json;
        if (json && Array.isArray(json.data)) return json.data;
        console.warn('Formato inesperado de resposta em usuarios_list.php:', json);
        return [];
      }
    },
    columns: [
      { data: 'id' },
      { data: 'nome_completo' },
      { data: 'email' },
      { data: 'ativo',
        render: function (v) {
          return (String(v) === '1' || v === 1)
            ? '<span class="label label-success">Ativo</span>'
            : '<span class="label label-default">Inativo</span>';
        }
      },
      { data: 'id', orderable: false, searchable: false,
        render: function (id) {
          return `<a class="btn btn-primary btn-xs"
                     href="<?= BASE_URL ?>/pages/alterar_usuario.php?id=${id}">Atualizar</a>`;
        }
      }
    ],
    pageLength: 25,
    responsive: true,
    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' }
  });
});
</script>

<?php include_once ROOT_PATH . 'system/includes/footer.php'; ?>
