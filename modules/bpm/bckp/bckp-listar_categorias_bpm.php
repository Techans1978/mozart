<?php
// modules/bpm/bpm_designer.php
// Mozart BPM — Modeler com Properties + Element Templates (CDN + fallback local)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';


// Helper: flash em sessão
if (session_status() === PHP_SESSION_NONE) { session_start(); }
function flash_set($t,$m){ $_SESSION['__flash']=['t'=>$t,'m'=>$m]; }
function flash_take(){ $f = $_SESSION['__flash'] ?? null; unset($_SESSION['__flash']); return $f; }

// Verifica PDO
if (!isset($pdo) || !($pdo instanceof PDO)) {
  die("Conexão PDO \$pdo não encontrada. Ajuste system/config/connect.php para expor \$pdo (PDO MySQL).");
}

// DELETE
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_id'])) {
  $id = (int)$_POST['delete_id'];
  try {
    $stmt = $pdo->prepare("DELETE FROM bpm_categorias WHERE id=:id");
    $stmt->execute([':id'=>$id]);
    flash_set('success','Categoria removida com sucesso.');
  } catch (PDOException $e) {
    flash_set('danger','Não foi possível excluir: '.$e->getMessage());
  }
  header('Location: listar_categorias_bpm.php');
  exit;
}

// Filtros
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$ativos = isset($_GET['ativos']) ? (int)$_GET['ativos'] : 0;

// Query hierárquica
$sql = "
SELECT
  c.id, c.nome, c.codigo, c.ativo, c.sort_order, c.parent_id,
  GROUP_CONCAT(a.nome ORDER BY p.depth SEPARATOR ' / ') AS full_path,
  MAX(p.depth) AS depth
FROM bpm_categorias c
JOIN bpm_categorias_paths p ON p.descendant_id = c.id
JOIN bpm_categorias a       ON a.id = p.ancestor_id
WHERE 1=1
";
$params = [];
if ($q!=='') { $sql.=" AND (c.nome LIKE :q OR c.codigo LIKE :q) "; $params[':q']="%{$q}%"; }
if ($ativos===1){ $sql.=" AND c.ativo=1 "; }
$sql .= "
GROUP BY c.id, c.nome, c.codigo, c.ativo, c.sort_order, c.parent_id
ORDER BY full_path, c.sort_order, c.id
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$f = flash_take();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Categorias BPM — Listar</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .table thead th { white-space: nowrap; }
    .path { color:#6c757d; font-size: .925rem; }
    .badge-on { background:#198754; }
    .badge-off{ background:#6c757d; }
    .actions{ white-space:nowrap; }
  </style>
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex align-items-center mb-3">
    <h3 class="me-auto mb-0">Categorias BPM</h3>
    <a href="form_categorias_bpm.php" class="btn btn-primary">+ Nova Categoria</a>
  </div>

  <?php if($f): ?>
    <div class="alert alert-<?=htmlspecialchars($f['t'])?>"><?=htmlspecialchars($f['m'])?></div>
  <?php endif; ?>

  <form class="row g-2 mb-3" method="get">
    <div class="col-md-6">
      <input type="text" class="form-control" name="q" placeholder="Buscar por nome/código..." value="<?=htmlspecialchars($q)?>">
    </div>
    <div class="col-md-2 form-check d-flex align-items-center">
      <input class="form-check-input me-2" type="checkbox" value="1" id="ativos" name="ativos" <?= $ativos===1?'checked':''?>>
      <label class="form-check-label" for="ativos">Somente ativas</label>
    </div>
    <div class="col-md-4 text-end">
      <button class="btn btn-outline-secondary" type="submit">Filtrar</button>
      <a class="btn btn-outline-dark" href="listar_categorias_bpm.php">Limpar</a>
    </div>
  </form>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Caminho</th>
            <th>Código</th>
            <th>Ativa</th>
            <th>Ordem</th>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php if(!$rows): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma categoria encontrada.</td></tr>
        <?php else: foreach($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><span class="path"><?= htmlspecialchars($r['full_path']) ?></span></td>
            <td><?= htmlspecialchars($r['codigo'] ?? '') ?></td>
            <td>
              <?php if((int)$r['ativo']===1): ?>
                <span class="badge badge-on">Ativa</span>
              <?php else: ?>
                <span class="badge badge-off">Inativa</span>
              <?php endif; ?>
            </td>
            <td><?= (int)$r['sort_order'] ?></td>
            <td class="text-end actions">
              <a class="btn btn-sm btn-outline-primary" href="form_categorias_bpm.php?id=<?= (int)$r['id']?>">Editar</a>
              <form class="d-inline" method="post" onsubmit="return confirm('Excluir esta categoria? Se possuir subcategorias, mova-as antes.');">
                <input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
