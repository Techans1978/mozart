<?php
// Mostrar erros (dev)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php'; // expõe $conn (mysqli)

// --- helpers mínimos ---
function table_exists(mysqli $conn, string $table): bool {
  $t = $conn->real_escape_string($table);
  $r = $conn->query("SHOW TABLES LIKE '{$t}'");
  return ($r && $r->num_rows > 0);
}
function column_exists(mysqli $conn, string $table, string $column): bool {
  $t = $conn->real_escape_string($table);
  $c = $conn->real_escape_string($column);
  $r = $conn->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
  return ($r && $r->num_rows > 0);
}

// --- carregar opções de PAI (grupos existentes) ---
// prioridade: path_cache -> closure table -> nome simples
$grupos_pai = [];
if (column_exists($conn, 'grupos', 'path_cache')) {
  $sql = "SELECT id, path_cache AS label FROM grupos ORDER BY path_cache ASC, nome ASC";
} elseif (table_exists($conn, 'grupos_paths')) {
  // montagem de caminho por closure table (se existir)
  $sql = "
    SELECT g.id,
           COALESCE((
             SELECT GROUP_CONCAT(ga.nome ORDER BY gp.depth SEPARATOR ' -> ')
             FROM grupos_paths gp
             JOIN grupos ga ON ga.id = gp.ancestor_id
             WHERE gp.descendant_id = g.id
           ), g.nome) AS label
    FROM grupos g
    ORDER BY label ASC
  ";
} else {
  $sql = "SELECT id, nome AS label FROM grupos ORDER BY nome ASC";
}
if ($res = $conn->query($sql)) {
  while ($r = $res->fetch_assoc()) $grupos_pai[] = $r;
}

// --- modo edição ---
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// --- repopular em caso de postback ---
$old = $_POST ?? [];

// Se NÃO é postback e tem id, carrega do banco
if (empty($old) && $id > 0) {
  $stmt = $conn->prepare("SELECT id, nome, codigo, parent_id, ativo, obs FROM grupos WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($row) {
    $old = $row;
    $old['id'] = (int)$row['id'];
  } else {
    $id = 0;
  }
}

// NÃO sobrescreva $old aqui de novo

include_once ROOT_PATH . '/system/includes/head.php';
include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

    <div class="row">
      <div class="col-lg-12">
        <h2><?= ($id>0 ? 'Editar Grupo' : 'Cadastrar Grupo') ?></h2>

        <!-- ALERTAS opcionais: se você passar via querystring tipo ?ok=1&msg=... -->
        <?php if (!empty($_GET['msg'])): ?>
          <div class="alert <?= !empty($_GET['ok']) ? 'alert-success' : 'alert-danger' ?>">
            <?= htmlspecialchars($_GET['msg']) ?>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= BASE_URL ?>/system/actions/grupos_save.php" onsubmit="return validarGrupo();">
          <?php if ($id>0): ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
          <?php endif; ?>
          <div class="form-group">
            <label for="nome">Nome do Grupo:</label>
            <input type="text" class="form-control" id="nome" name="nome"
                   value="<?= htmlspecialchars($old['nome'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label for="codigo">Código / Abreviação (obrigatório):</label>
            <input type="text" class="form-control" id="codigo" name="codigo"
                   placeholder="Ex.: FIN-TES-PAG"
                   value="<?= htmlspecialchars($old['codigo'] ?? '') ?>" required>
            <p class="help-block">Útil para integrações externas.</p>
          </div>

          <div class="form-group">
            <label for="parent_id">Grupo Pai (opcional):</label>
            <select id="parent_id" name="parent_id" class="form-control">
              <option value="">-- nenhum (raiz) --</option>
              <?php
              $selParent = ($old['parent_id'] ?? null);
              $selParent = ($selParent === null || $selParent === '' ? 0 : (int)$selParent);

              foreach ($grupos_pai as $g):
                $gid = (int)$g['id'];
                if ($id>0 && $gid === (int)$id) continue; // evita pai = ele mesmo
                $sel = ($gid === $selParent) ? 'selected' : '';
              ?>
                <option value="<?= (int)$g['id'] ?>" <?= $sel ?>>
                  <?= htmlspecialchars($g['label']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="ativo">Ativo:</label>
            <select id="ativo" name="ativo" class="form-control">
              <option value="1" <?= (($old['ativo'] ?? '1')==='1')?'selected':''; ?>>Sim</option>
              <option value="0" <?= (($old['ativo'] ?? '')==='0')?'selected':''; ?>>Não</option>
            </select>
          </div>

          <div class="form-group">
            <label for="obs">Observações (opcional):</label>
            <textarea id="obs" name="obs" class="form-control" rows="3"><?= htmlspecialchars($old['obs'] ?? '') ?></textarea>
          </div>

          <button type="submit" class="btn btn-primary">Salvar</button>
          <a href="<?= BASE_URL ?>/pages/grupos_listar.php" class="btn btn-default">Voltar</a>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function validarGrupo(){
  const nome = document.getElementById('nome').value.trim();
  if (!nome) { alert('Informe o nome do grupo.'); return false; }
  return true;
}
</script>

<?php include_once ROOT_PATH . '/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>
