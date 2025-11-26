<?php
// categorias_bpm_save.php
// Handler de gravação (create/update) e também delete via POST (opcional)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php'; // deve expor $pdo

if (session_status() === PHP_SESSION_NONE) { session_start(); }
function flash_set($t,$m){ $_SESSION['__flash']=['t'=>$t,'m'=>$m]; }

if (!isset($pdo) || !($pdo instanceof PDO)) {
  die("Conexão PDO \$pdo não encontrada. Ajuste system/config/connect.php para expor \$pdo (PDO MySQL).");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: listar_categorias_bpm.php');
  exit;
}

// DELETE opcional (se postar delete_id)
if (isset($_POST['delete_id']) && $_POST['delete_id']!=='') {
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

// Create/Update
$id         = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$nome       = trim($_POST['nome'] ?? '');
$codigo     = trim($_POST['codigo'] ?? '');
$parent_id  = ($_POST['parent_id']!=='') ? (int)$_POST['parent_id'] : null;
$ativo      = isset($_POST['ativo']) ? 1 : 0;
$sort_order = (int)($_POST['sort_order'] ?? 0);

if ($nome==='') {
  flash_set('danger','Informe o nome da categoria.');
  header('Location: '.($id>0 ? 'form_categorias_bpm.php?id='.$id : 'form_categorias_bpm.php'));
  exit;
}

try {
  if ($id>0) {
    // UPDATE
    $sql = "UPDATE bpm_categorias
               SET nome=:nome, codigo=:codigo, parent_id=:parent_id, ativo=:ativo, sort_order=:sort_order
             WHERE id=:id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':nome'=>$nome,
      ':codigo'=>($codigo!==''?$codigo:null),
      ':parent_id'=>$parent_id,
      ':ativo'=>$ativo,
      ':sort_order'=>$sort_order,
      ':id'=>$id
    ]);
    flash_set('success','Categoria atualizada.');
  } else {
    // INSERT
    $sql = "INSERT INTO bpm_categorias (nome, codigo, parent_id, ativo, sort_order)
            VALUES (:nome,:codigo,:parent_id,:ativo,:sort_order)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':nome'=>$nome,
      ':codigo'=>($codigo!==''?$codigo:null),
      ':parent_id'=>$parent_id,
      ':ativo'=>$ativo,
      ':sort_order'=>$sort_order
    ]);
    $id = (int)$pdo->lastInsertId();
    flash_set('success','Categoria criada.');
  }

  header('Location: listar_categorias_bpm.php');
  exit;

} catch (PDOException $e) {
  flash_set('danger','Erro ao salvar: '.$e->getMessage());
  header('Location: '.($id>0 ? 'form_categorias_bpm.php?id='.$id : 'form_categorias_bpm.php'));
  exit;
}
