<?php
// Mostrar erros (dev)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

function getPerfisOptions(mysqli $conn, $ignoreId = null) {
  $sql = "SELECT id, nome, parent_id FROM perfis ORDER BY nome ASC";
  $res = $conn->query($sql);
  $rows = [];
  $byId = [];
  while ($r = $res->fetch_assoc()) {
    $r['id']        = (int)$r['id'];
    $r['parent_id'] = isset($r['parent_id']) ? (int)$r['parent_id'] : null;
    $rows[] = $r;
    $byId[$r['id']] = $r;
  }
  // monta caminho simples subindo pelo parent
  $out = [];
  foreach ($rows as $p) {
    if ($ignoreId && $p['id'] === (int)$ignoreId) continue;
    $names = [$p['nome']];
    $cur = $p;
    $guard = 0;
    while ($cur && $cur['parent_id'] && isset($byId[$cur['parent_id']]) && $guard++ < 50) {
      $cur = $byId[$cur['parent_id']];
      array_unshift($names, $cur['nome']);
    }
    $out[] = ['id'=>$p['id'], 'label'=>implode(' -> ', $names)];
  }
  usort($out, function($a,$b){ return strcasecmp($a['label'],$b['label']); });
  return $out;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$perfil = null;
if ($id > 0) {
  $st = $conn->prepare("SELECT id, nome, codigo, parent_id, ativo FROM perfis WHERE id=? LIMIT 1");
  $st->bind_param('i', $id);
  $st->execute();
  $perfil = $st->get_result()->fetch_assoc();
  $st->close();
  if (!$perfil) { die('Perfil não encontrado.'); }
}
$parentOpts = getPerfisOptions($conn, $id);

// Cabeçalhos e navbar
include_once ROOT_PATH . '/system/includes/head.php';
include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12">
      <h1 class="page-header"><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
    </div></div>

    <div class="row"><div class="col-lg-12">
      <h2><?= $id ? 'Atualizar Perfil' : 'Cadastrar Perfil' ?></h2>

      <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-<?= (!empty($_GET['ok']))?'success':'danger' ?>">
          <?= htmlspecialchars($_GET['msg']) ?>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= BASE_URL ?>/system/actions/perfis_save.php">
        <?php if ($id): ?>
          <input type="hidden" name="id" value="<?= (int)$id ?>">
        <?php endif; ?>

        <div class="form-group">
          <label for="nome">Nome</label>
          <input class="form-control" type="text" id="nome" name="nome"
                 value="<?= htmlspecialchars($perfil['nome'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label for="codigo">Código/Abreviação (obrigatório)
          </label>
          <input class="form-control" type="text" id="codigo" name="codigo"
                 value="<?= htmlspecialchars($perfil['codigo'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label for="parent_id">Perfil Pai (opcional)</label>
          <select class="form-control" id="parent_id" name="parent_id">
            <option value="">— Raiz —</option>
            <?php foreach($parentOpts as $o): ?>
              <option value="<?= (int)$o['id'] ?>" <?= (!empty($perfil['parent_id']) && (int)$perfil['parent_id']===(int)$o['id'])?'selected':''; ?>>
                <?= htmlspecialchars($o['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="ativo">Ativo</label>
          <select class="form-control" id="ativo" name="ativo">
            <option value="1" <?= ((int)($perfil['ativo'] ?? 1)===1)?'selected':''; ?>>Sim</option>
            <option value="0" <?= ((int)($perfil['ativo'] ?? 1)===0)?'selected':''; ?>>Não</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary"><?= $id ? 'Salvar Alterações' : 'Cadastrar Perfil' ?></button>
        <a class="btn btn-default" href="<?= BASE_URL ?>/pages/perfis_listar.php">Voltar</a>
      </form>
    </div></div>
  </div>
</div>

<?php include_once ROOT_PATH . '/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>
