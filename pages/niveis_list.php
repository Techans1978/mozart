<?php
// pages/niveis_list.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Helpers
if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
}

$page_title = "Níveis de Acesso";

// pega conexão
$dbc = $mysqli ?? ($conn ?? null);
if (!$dbc) {
    die('Sem conexão com o banco.');
}

// carrega níveis
$sql = "SELECT id, nome, acesso_backend, acesso_frontend, ativo, updated_at
        FROM acl_levels
        ORDER BY id DESC";
$res = $dbc->query($sql);
$rows = [];
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
}

include ROOT_PATH . 'system/includes/head.php';
include ROOT_PATH . 'system/includes/navbar.php';
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row">
      <div class="col-lg-12">
        <h1 class="page-header"><?= h($page_title) ?></h1>
      </div>
    </div>

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
              <tbody>
              <?php if ($rows): ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td><?= h($r['nome']) ?></td>
                    <td><?= $r['acesso_backend'] ? 'Sim' : 'Não' ?></td>
                    <td><?= $r['acesso_frontend'] ? 'Sim' : 'Não' ?></td>
                    <td>
                      <?php if ($r['ativo']): ?>
                        <span class="label label-success">Ativo</span>
                      <?php else: ?>
                        <span class="label label-default">Inativo</span>
                      <?php endif; ?>
                    </td>
                    <td><?= h($r['updated_at']) ?></td>
                    <td>
                      <a href="niveis_form.php?id=<?= (int)$r['id'] ?>"
                         class="btn btn-xs btn-primary">Editar</a>

                      <button type="button"
                              class="btn btn-xs btn-warning btn-toggle"
                              data-id="<?= (int)$r['id'] ?>">
                        <?= $r['ativo'] ? 'Desativar' : 'Ativar' ?>
                      </button>

                      <button type="button"
                              class="btn btn-xs btn-danger btn-excluir"
                              data-id="<?= (int)$r['id'] ?>">
                        Excluir
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
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
  // DataTables apenas client-side, sem ajax
  $('#tb-niveis').DataTable({
    order: [[0,'desc']],
    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' }
  });

  // excluir
  $(document).on('click','.btn-excluir',function(e){
    e.preventDefault();
    const id = $(this).data('id');
    if(!id) return;
    if(!confirm('Confirma excluir este nível? Esta ação não pode ser desfeita.')) return;

    $.post('<?= BASE_URL ?>/system/actions/niveis_delete.php', { id }, function(r){
      if(r && r.ok){
        location.reload();
      } else {
        alert((r && r.msg) || 'Falha ao excluir.');
      }
    }, 'json');
  });

  // toggle ativo
  $(document).on('click','.btn-toggle',function(e){
    e.preventDefault();
    const id = $(this).data('id');
    if(!id) return;

    $.post('<?= BASE_URL ?>/system/actions/niveis_toggle.php', { id }, function(r){
      if(r && r.ok){
        location.reload();
      } else {
        alert((r && r.msg) || 'Falha ao alterar status.');
      }
    }, 'json');
  });
});
</script>

<?php include ROOT_PATH . 'system/includes/footer.php'; ?>
