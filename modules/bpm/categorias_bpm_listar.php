<?php
// modules/bpm/categorias_bpm_listar.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (!isset($conn) && isset($mysqli)) { $conn = $mysqli; }
if (!($conn instanceof mysqli)) { die('Conexão MySQLi $conn não encontrada.'); }
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }


function q_all($sql,$types='',$params=[]){ global $conn; $st=$conn->prepare($sql); if(!$st){die('prepare:'.$conn->error);}
  if($types&&$params){$refs=[];foreach($params as $k=>$v){$refs[$k]=&$params[$k];}$st->bind_param($types,...$refs);} $st->execute();
  $rs=$st->get_result(); $rows=[]; if($rs){while($r=$rs->fetch_assoc()){$rows[]=$r;}} $st->close(); return $rows; }
function table_exists($t){ global $conn; $t=$conn->real_escape_string($t); $rs=$conn->query("SHOW TABLES LIKE '$t'"); return $rs && $rs->num_rows>0; }

$flash = $_SESSION['__flash'] ?? null; unset($_SESSION['__flash']);
$q = isset($_GET['q'])?trim($_GET['q']):'';
$ativos = isset($_GET['ativos']) ? (int)$_GET['ativos'] : -1;

if (!table_exists('bpm_categorias') || !table_exists('bpm_categorias_paths')) {
  include_once ROOT_PATH.'system/includes/head.php';
  include_once ROOT_PATH.'system/includes/navbar.php';
  echo '<div id="page-wrapper"><div class="container-fluid"><div class="row"><div class="col-lg-12"><h3 class="page-header">Categorias BPM</h3><div class="alert alert-danger">Tabelas bpm_categorias / bpm_categorias_paths não encontradas no banco.</div></div></div></div></div>';
  include_once ROOT_PATH.'system/includes/footer.php';
  exit;
}

$sql = "SELECT c.id, c.nome, c.codigo, c.ativo, c.sort_order, c.parent_id,
               GROUP_CONCAT(a.nome ORDER BY p.depth SEPARATOR ' / ') AS full_path
        FROM bpm_categorias c
        JOIN bpm_categorias_paths p ON p.descendant_id=c.id
        JOIN bpm_categorias a ON a.id=p.ancestor_id
        WHERE 1=1";
$types=''; $params=[];
if($q!==''){ $sql.=" AND (c.nome LIKE ? OR c.codigo LIKE ?)"; $types.='ss'; $like="%".$q."%"; $params[]=$like; $params[]=$like; }
if($ativos===0 || $ativos===1){ $sql.=" AND c.ativo=?"; $types.='i'; $params[]=$ativos; }
$sql.=" GROUP BY c.id, c.nome, c.codigo, c.ativo, c.sort_order, c.parent_id
        ORDER BY full_path, c.sort_order, c.id";

$rows = q_all($sql,$types,$params);

include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header">Categorias BPM</h1></div></div>

    <?php if($flash): ?><div class="alert alert-info"><?= htmlspecialchars($flash['m']) ?></div><?php endif; ?>

    <form class="form-inline" method="get" style="margin-bottom:10px;">
      <input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar por nome/código">
      <label class="checkbox-inline" style="margin-left:8px;">
        <input type="checkbox" name="ativos" value="1" <?= $ativos===1?'checked':''; ?>> Somente ativas
      </label>
      <button class="btn btn-primary" type="submit">Filtrar</button>
      <a class="btn btn-success" href="<?= BASE_URL ?>/modules/bpm/categorias_bpm_form.php">+ Nova Categoria</a>
    </form>

    <div class="table-responsive">
      <table class="table table-striped table-bordered">
        <thead><tr>
          <th>#</th><th>Caminho</th><th>Código</th><th>Ativa</th><th>Ordem</th><th style="width:200px;">Ações</th>
        </tr></thead>
        <tbody>
        <?php if(!$rows): ?>
          <tr><td colspan="6" class="text-center text-muted">Nenhuma categoria encontrada.</td></tr>
        <?php else: foreach($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['full_path']) ?></td>
            <td><?= htmlspecialchars($r['codigo'] ?? '') ?></td>
            <td><?= ((int)$r['ativo']===1?'Ativa':'Inativa') ?></td>
            <td><?= (int)$r['sort_order'] ?></td>
            <td>
              <a class="btn btn-xs btn-default" href="<?= BASE_URL ?>/modules/bpm/categorias_bpm_form.php?id=<?= (int)$r['id'] ?>">Editar</a>
              <a class="btn btn-xs btn-warning" href="<?= BASE_URL ?>/modules/bpm/actions/categorias_bpm_toggle.php?id=<?= (int)$r['id'] ?>">Ativar/Desativar</a>
              <a class="btn btn-xs btn-danger" href="<?= BASE_URL ?>/modules/bpm/actions/categorias_bpm_delete.php?id=<?= (int)$r['id'] ?>" onclick="return confirm('Excluir? Apenas categorias sem filhos podem ser removidas.');">Excluir</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
