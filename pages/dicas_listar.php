<?php
// pages/dicas_listar.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function tok(){ if(empty($_SESSION['csrf_dicas_list'])) $_SESSION['csrf_dicas_list']=bin2hex(random_bytes(16)); return $_SESSION['csrf_dicas_list']; }
function check_tok($t){ return !empty($_SESSION['csrf_dicas_list']) && hash_equals($_SESSION['csrf_dicas_list'], (string)$t); }
function redirect_self(){
  $url = strtok($_SERVER['REQUEST_URI'], '?'); if (!empty($_GET)) $url.="?".http_build_query($_GET); header("Location: $url", true, 303); exit;
}

$EDIT_PAGE   = BASE_URL.'/pages/dicas_editar.php';
$CATS_PAGE   = BASE_URL.'/pages/dicas_categorias.php';

/* Ações POST (publicar, despublicar, arquivar, lixeira) */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $acao = $_POST['acao'] ?? '';
  $id   = (int)($_POST['id'] ?? 0);
  $csrf = $_POST['csrf'] ?? '';
  if (!$id || !check_tok($csrf)) { $_SESSION['flash_err']='Token/ID inválido.'; redirect_self(); }

  $map = [
    'publicar'      => "UPDATE dicas_articles SET status='publicado'     WHERE id=? LIMIT 1",
    'despublicar'   => "UPDATE dicas_articles SET status='nao_publicado' WHERE id=? LIMIT 1",
    'arquivar'      => "UPDATE dicas_articles SET status='arquivado'     WHERE id=? LIMIT 1",
    'lixeira'       => "UPDATE dicas_articles SET status='lixeira'       WHERE id=? LIMIT 1",
    'excluir'       => "DELETE FROM dicas_articles WHERE id=? LIMIT 1", // use com cuidado
  ];
  if (!isset($map[$acao])) { $_SESSION['flash_err']='Ação inválida.'; redirect_self(); }

  $st = $conn->prepare($map[$acao]);
  if ($st){ $st->bind_param('i',$id); $ok=$st->execute(); $st->close(); }
  if (!empty($ok)) $_SESSION['flash_ok']='Ação concluída.'; else $_SESSION['flash_err']='Falha ao executar ação.';
  redirect_self();
}

/* Filtros */
$q       = trim($_GET['q'] ?? '');
$status  = $_GET['status'] ?? 'todos'; // todos, publicado, nao_publicado, arquivado, lixeira, pendente, reprovado
$categoria = (int)($_GET['categoria'] ?? 0);

$cats=[]; if($r=$conn->query("SELECT id,titulo FROM dicas_categories ORDER BY titulo")) while($x=$r->fetch_assoc()) $cats[]=$x;

$where=[]; $types=''; $params=[];
if ($q!==''){ $where[]="(a.titulo LIKE CONCAT('%',?,'%') OR a.apelido LIKE CONCAT('%',?,'%'))"; $params[]=$q; $params[]=$q; $types.='ss'; }
if ($status!=='todos'){ $where[]="a.status=?"; $params[]=$status; $types.='s'; }
if ($categoria>0){ $where[]="a.categoria_id=?"; $params[]=$categoria; $types.='i'; }

$sql="SELECT a.id,a.titulo,a.apelido,a.status,a.featured,a.created_at,a.publish_up,a.publish_down,
             c.titulo AS categoria
      FROM dicas_articles a
      LEFT JOIN dicas_categories c ON c.id=a.categoria_id";
if($where) $sql.=" WHERE ".implode(' AND ',$where);
$sql.=" ORDER BY a.created_at DESC LIMIT 1000";

$rows=[];
if ($types) { $st=$conn->prepare($sql); $st->bind_param($types, ...$params); $st->execute(); $res=$st->get_result(); while($x=$res->fetch_assoc()) $rows[]=$x; $st->close(); }
else { $res=$conn->query($sql); while($x=$res->fetch_assoc()) $rows[]=$x; }

include_once ROOT_PATH . '/system/includes/head.php';
include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<style>
.table-actions .btn{ margin-right:6px }
.badge-pill{ border-radius:999px; padding:4px 10px; font-size:12px }
.badge-pub{ background:#e6ffed; color:#177245; border:1px solid #c1f2ce }
.badge-npub{ background:#eef2ff; color:#3730a3; border:1px solid #c7d2fe }
.badge-arq{ background:#f1f5f9; color:#334155; border:1px solid #cbd5e1 }
.badge-lix{ background:#fee2e2; color:#991b1b; border:1px solid #fecaca }
.badge-pen{ background:#fff7ed; color:#9a3412; border:1px solid #fed7aa }
.badge-rep{ background:#fef3c7; color:#854d0e; border:1px solid #fde68a }
</style>

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header">Dicas</h1></div></div>

  <?php if(!empty($_SESSION['flash_ok'])): ?><div class="alert alert-success"><?=h($_SESSION['flash_ok'])?></div><?php unset($_SESSION['flash_ok']); endif;?>
  <?php if(!empty($_SESSION['flash_err'])): ?><div class="alert alert-danger"><?=h($_SESSION['flash_err'])?></div><?php unset($_SESSION['flash_err']); endif;?>

  <form class="form-inline" method="get" style="margin-bottom:12px">
    <div class="form-group">
      <input class="form-control" name="q" value="<?=h($q)?>" placeholder="Buscar por título/apelido..." style="min-width:280px">
    </div>
    <div class="form-group" style="margin-left:8px">
      <select class="form-control" name="status">
        <?php
          $ops = ['todos'=>'Todos','publicado'=>'Publicado','nao_publicado'=>'Não Publicado','arquivado'=>'Arquivado','lixeira'=>'Lixeira','pendente'=>'Pendente','reprovado'=>'Reprovado'];
          foreach($ops as $k=>$v): ?>
          <option value="<?=$k?>" <?=$status===$k?'selected':''?>><?=$v?></option>
        <?php endforeach;?>
      </select>
    </div>
    <div class="form-group" style="margin-left:8px">
      <select class="form-control" name="categoria">
        <option value="0">Todas as categorias</option>
        <?php foreach($cats as $c): ?>
          <option value="<?=$c['id']?>" <?=$categoria==$c['id']?'selected':''?>><?=h($c['titulo'])?></option>
        <?php endforeach;?>
      </select>
    </div>
    <button class="btn btn-primary" style="margin-left:8px">Filtrar</button>
    <a class="btn btn-default" href="<?=h(strtok($_SERVER['REQUEST_URI'],'?'))?>" style="margin-left:4px">Limpar</a>
    <a class="btn btn-success" href="<?=$EDIT_PAGE?>" style="margin-left:8px">➕ Nova Dica</a>
    <a class="btn btn-default" href="<?=$CATS_PAGE?>" style="margin-left:8px">Categorias</a>
  </form>

  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th style="width:70px">ID</th>
          <th>Título</th>
          <th>Categoria</th>
          <th>Status</th>
          <th>Publicação</th>
          <th style="width:260px">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!$rows): ?>
          <tr><td colspan="6" class="text-muted">Nenhum registro.</td></tr>
        <?php else: foreach($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= h($r['titulo']) ?></td>
            <td><?= h($r['categoria'] ?? '-') ?></td>
            <td>
              <?php
                $b = ['publicado'=>'badge-pub','nao_publicado'=>'badge-npub','arquivado'=>'badge-arq','lixeira'=>'badge-lix','pendente'=>'badge-pen','reprovado'=>'badge-rep'];
                $t = ['publicado'=>'Publicado','nao_publicado'=>'Não Publicado','arquivado'=>'Arquivado','lixeira'=>'Lixeira','pendente'=>'Pendente','reprovado'=>'Reprovado'];
              ?>
              <span class="badge-pill <?=$b[$r['status']]??'badge-npub'?>"><?=$t[$r['status']]??$r['status']?></span>
            </td>
            <td>
              <?php
                $ini = $r['publish_up'] ? date('d/m/Y H:i', strtotime($r['publish_up'])) : '-';
                $fim = $r['publish_down'] ? date('d/m/Y H:i', strtotime($r['publish_down'])) : '-';
                echo "$ini → $fim";
              ?>
            </td>
            <td class="table-actions">
              <a class="btn btn-xs btn-primary" href="<?=$EDIT_PAGE.'?id='.(int)$r['id']?>">Editar</a>

              <form method="post" style="display:inline" onsubmit="return confirm('Publicar dica #<?=$r['id']?>?');">
                <input type="hidden" name="csrf" value="<?=h(tok())?>">
                <input type="hidden" name="acao" value="publicar"><input type="hidden" name="id" value="<?=$r['id']?>">
                <button class="btn btn-xs btn-success" type="submit">Publicar</button>
              </form>

              <form method="post" style="display:inline" onsubmit="return confirm('Despublicar dica #<?=$r['id']?>?');">
                <input type="hidden" name="csrf" value="<?=h(tok())?>">
                <input type="hidden" name="acao" value="despublicar"><input type="hidden" name="id" value="<?=$r['id']?>">
                <button class="btn btn-xs btn-warning" type="submit">Despublicar</button>
              </form>

              <form method="post" style="display:inline" onsubmit="return confirm('Arquivar dica #<?=$r['id']?>?');">
                <input type="hidden" name="csrf" value="<?=h(tok())?>">
                <input type="hidden" name="acao" value="arquivar"><input type="hidden" name="id" value="<?=$r['id']?>">
                <button class="btn btn-xs btn-default" type="submit">Arquivar</button>
              </form>

              <form method="post" style="display:inline" onsubmit="return confirm('Enviar para a lixeira a dica #<?=$r['id']?>?');">
                <input type="hidden" name="csrf" value="<?=h(tok())?>">
                <input type="hidden" name="acao" value="lixeira"><input type="hidden" name="id" value="<?=$r['id']?>">
                <button class="btn btn-xs btn-danger" type="submit">Lixeira</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif;?>
      </tbody>
    </table>
  </div>
</div></div>

<?php include_once ROOT_PATH . '/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>
