<?php
// modules/bpm/bpm_designer.php
// Mozart BPM — Modeler com Properties + Element Templates (CDN + fallback local)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
function flash_set($t,$m){ $_SESSION['__flash']=['t'=>$t,'m'=>$m]; }
function flash_take(){ $f = $_SESSION['__flash'] ?? null; unset($_SESSION['__flash']); return $f; }

if (!isset($pdo) || !($pdo instanceof PDO)) {
  die("Conexão PDO \$pdo não encontrada. Ajuste system/config/connect.php para expor \$pdo (PDO MySQL).");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Carrega registro
$cat = ['id'=>0,'nome'=>'','codigo'=>'','parent_id'=>null,'ativo'=>1,'sort_order'=>0];
if ($id>0) {
  $stmt = $pdo->prepare("SELECT * FROM bpm_categorias WHERE id=:id");
  $stmt->execute([':id'=>$id]);
  $cat = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$cat) { flash_set('danger','Categoria não encontrada.'); header('Location: listar_categorias_bpm.php'); exit; }
}

// Opções de pai (exclui subárvore da própria)
$params = [];
$sqlParents = "
SELECT c.id,
       GROUP_CONCAT(a.nome ORDER BY p.depth SEPARATOR ' / ') AS path
FROM bpm_categorias c
JOIN bpm_categorias_paths p ON p.descendant_id=c.id
JOIN bpm_categorias a ON a.id=p.ancestor_id
WHERE 1=1
";
if ($id>0) {
  $sqlParents .= " AND c.id NOT IN (SELECT descendant_id FROM bpm_categorias_paths WHERE ancestor_id=:self) ";
  $params[':self']=$id;
}
$sqlParents .= " GROUP BY c.id ORDER BY path";
$stmt = $pdo->prepare($sqlParents);
$stmt->execute($params);
$parentOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mensagens
$f = flash_take();
$err = '';

// Render
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title><?= $id>0 ? 'Editar' : 'Nova' ?> Categoria BPM</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .card { max-width: 820px; margin: 0 auto; }
    .hint { color:#6c757d; font-size:.9rem; }
  </style>
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex align-items-center mb-3">
    <h3 class="me-auto mb-0"><?= $id>0 ? 'Editar' : 'Nova' ?> Categoria</h3>
    <a href="listar_categorias_bpm.php" class="btn btn-outline-secondary">Voltar</a>
  </div>

  <?php if($f): ?>
    <div class="alert alert-<?=htmlspecialchars($f['t'])?>"><?=htmlspecialchars($f['m'])?></div>
  <?php endif; ?>

  <form method="post" action="categorias_bpm_save.php" class="card shadow-sm">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Nome *</label>
          <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($cat['nome']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Código</label>
          <input type="text" name="codigo" class="form-control" maxlength="40" value="<?= htmlspecialchars($cat['codigo'] ?? '') ?>">
          <div class="hint">Opcional, único (ex.: <code>fin</code>, <code>ops</code>).</div>
        </div>

        <div class="col-md-8">
          <label class="form-label">Categoria Pai</label>
          <select name="parent_id" class="form-select">
            <option value="">— (raiz) —</option>
            <?php foreach($parentOptions as $op): ?>
              <option value="<?= (int)$op['id'] ?>" <?= ((string)$cat['parent_id']===(string)$op['id']?'selected':'') ?>>
                <?= htmlspecialchars($op['path']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="ativo" id="ativo" <?= ((int)$cat['ativo']===1?'checked':'') ?>>
            <label class="form-check-label" for="ativo">Ativa</label>
          </div>
        </div>
        <div class="col-md-2">
          <label class="form-label">Ordem</label>
          <input type="number" name="sort_order" class="form-control" value="<?= (int)$cat['sort_order'] ?>">
        </div>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
      <a href="listar_categorias_bpm.php" class="btn btn-outline-secondary">Cancelar</a>
      <button class="btn btn-primary" type="submit">Salvar</button>
    </div>
  </form>
</div>
</body>
</html>
