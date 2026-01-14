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

$id = ga_int($_GET['id'] ?? 0);
$item = [
  'id'=>0,'parent_id'=>null,'name'=>'','description'=>'','active'=>1,'sort_order'=>0
];

if ($id > 0) {
  $st = $conn->prepare("SELECT id, parent_id, name, description, active, sort_order
                        FROM ga_list_item WHERE id=? AND list_id=? LIMIT 1");
  $st->bind_param("ii", $id, $list['id']);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();
  if (!$row) {
    ga_flash_set("Item não encontrado.", "warning");
    ga_redirect(BASE_URL . "/modules/gestao_ativos/listas/listas-itens.php?list=".$list['slug']);
  }
  $item = $row;
}

$flash = ga_flash_get();

// Pais (itens raiz + demais, exceto ele mesmo)
$parents = [];
$stp = $conn->prepare("SELECT id, name FROM ga_list_item WHERE list_id=? AND id<>? ORDER BY name ASC");
$stp->bind_param("ii", $list['id'], $id);
$stp->execute();
$rs = $stp->get_result();
while ($r = $rs->fetch_assoc()) $parents[] = $r;
$stp->close();

include_once ROOT_PATH . 'system/includes/head.php';
include_once ROOT_PATH . 'system/includes/navbar.php';
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12">
      <h1 class="page-header"><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
    </div></div>

    <div class="row"><div class="col-lg-12">
      <h2>
        <?= $id>0 ? 'Editar Item' : 'Adicionar Item' ?>
        <small class="text-muted">Lista: <?= htmlspecialchars($list['title'], ENT_QUOTES, 'UTF-8') ?></small>
      </h2>

      <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['t'], ENT_QUOTES, 'UTF-8') ?>">
          <?= htmlspecialchars($flash['m'], ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <div class="panel panel-default">
        <div class="panel-heading">
          <i class="fa fa-pencil"></i> Dados do Item
        </div>
        <div class="panel-body">

          <form method="post" action="<?= BASE_URL ?>/modules/gestao_ativos/listas/actions/item_save.php">
            <input type="hidden" name="list" value="<?= htmlspecialchars($list['slug'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id" value="<?= (int)$id ?>">

            <div class="form-group">
              <label>Nome *</label>
              <input class="form-control" name="name" required value="<?= htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group">
              <label>Descrição (opcional)</label>
              <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Pai (subcategoria)</label>
                  <select class="form-control" name="parent_id">
                    <option value="">— Sem pai (nível raiz) —</option>
                    <?php foreach ($parents as $p): ?>
                      <option value="<?= (int)$p['id'] ?>" <?= ((string)$item['parent_id'] === (string)$p['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <p class="help-block">Use para criar subitens (subsetor, substatus, etc.).</p>
                </div>
              </div>

              <div class="col-md-3">
                <div class="form-group">
                  <label>Status</label>
                  <select class="form-control" name="active">
                    <option value="1" <?= (String($item['active'])==='1')?'selected':''; ?>>Ativo</option>
                    <option value="0" <?= (String($item['active'])==='0')?'selected':''; ?>>Inativo</option>
                  </select>
                </div>
              </div>

              <div class="col-md-3">
                <div class="form-group">
                  <label>Ordem</label>
                  <input type="number" class="form-control" name="sort_order" value="<?= (int)($item['sort_order'] ?? 0) ?>">
                </div>
              </div>
            </div>

            <button class="btn btn-success"><i class="fa fa-save"></i> Salvar</button>
            <a class="btn btn-default" href="<?= BASE_URL ?>/modules/gestao_ativos/listas/listas-itens.php?list=<?= urlencode($list['slug']) ?>">Cancelar</a>

          </form>

        </div>
      </div>

    </div></div>
  </div>
</div>

<?php include_once ROOT_PATH . 'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . 'system/includes/footer.php'; ?>
