<?php
// pages/media_listar.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function tok(){ if(empty($_SESSION['csrf_media_list'])) $_SESSION['csrf_media_list']=bin2hex(random_bytes(16)); return $_SESSION['csrf_media_list']; }
function chk($t){ return !empty($_SESSION['csrf_media_list']) && hash_equals($_SESSION['csrf_media_list'], (string)$t); }
function redir(){ header('Location: '.strtok($_SERVER['REQUEST_URI'],'?').(empty($_GET)?'':'?'.http_build_query($_GET))); exit; }

$EDIT = BASE_URL.'/pages/media_editar.php';
$CATS = BASE_URL.'/pages/media_categorias.php';

/* Ações */
if($_SERVER['REQUEST_METHOD']==='POST'){
  $acao=$_POST['acao']??''; $id=(int)($_POST['id']??0); $csrf=$_POST['csrf']??'';
  if(!$id || !chk($csrf)){ $_SESSION['flash_err']='Token/ID inválido.'; redir(); }
  $sql=[
    'publicar'    => "UPDATE media_posts SET status='publicado' WHERE id=? LIMIT 1",
    'despublicar' => "UPDATE media_posts SET status='nao_publicado' WHERE id=? LIMIT 1",
    'arquivar'    => "UPDATE media_posts SET status='arquivado' WHERE id=? LIMIT 1",
    'lixeira'     => "UPDATE media_posts SET status='lixeira' WHERE id=? LIMIT 1",
  ][$acao] ?? null;
  if(!$sql){ $_SESSION['flash_err']='Ação inválida.'; redir(); }
  $st=$conn->prepare($sql); $st->bind_param('i',$id); $ok=$st->execute(); $st->close();
  $_SESSION[empty($ok)?'flash_err':'flash_ok'] = empty($ok)?'Falha ao executar.':'Ação concluída.';
  redir();
}

/* Filtros */
$q = trim($_GET['q']??''); $status=$_GET['status']??'todos'; $cat=(int)($_GET['categoria']??0);
$cats=[]; if($r=$conn->query("SELECT id,titulo FROM media_categories ORDER BY titulo")) while($x=$r->fetch_assoc()) $cats[]=$x;

$w=[]; $p=[]; $t='';
if($q!==''){ $w[]="(p.titulo LIKE CONCAT('%',?,'%') OR p.apelido LIKE CONCAT('%',?,'%'))"; $p[]=$q; $p[]=$q; $t.='ss'; }
if($status!=='todos'){ $w[]="p.status=?"; $p[]=$status; $t.='s'; }
if($cat>0){ $w[]="p.categoria_id=?"; $p[]=$cat; $t.='i'; }
$sql="SELECT p.id,p.titulo,p.apelido,p.status,p.featured,p.publish_up,p.publish_down,c.titulo AS categoria
      FROM media_posts p LEFT JOIN media_categories c ON c.id=p.categoria_id";
if($w) $sql.=" WHERE ".implode(' AND ',$w);
$sql.=" ORDER BY p.created_at DESC LIMIT 1000";
$rows=[];
if($t){ $st=$conn->prepare($sql); $st->bind_param($t, ...$p); $st->execute(); $res=$st->get_result(); while($x=$res->fetch_assoc()) $rows[]=$x; $st->close(); }
else { $res=$conn->query($sql); while($x=$res->fetch_assoc()) $rows[]=$x; }

include_once ROOT_PATH.'/system/includes/head.php';
include_once ROOT_PATH.'/system/includes/navbar.php';
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
  <div class="row"><div class="col-lg-12"><h1 class="page-header">Mídias</h1></div></div>

  <?php if(!empty($_SESSION['flash_ok'])): ?><div class="alert alert-success"><?=h($_SESSION['flash_ok'])?></div><?php unset($_SESSION['flash_ok']); endif;?>
  <?php if(!empty($_SESSION['flash_err'])): ?><div class="alert alert-danger"><?=h($_SESSION['flash_err'])?></div><?php unset($_SESSION['flash_err']); endif;?>

  <form class="form-inline" method="get" style="margin-bottom:12px">
    <div class="form-group"><input class="form-control" name="q" value="<?=h($q)?>" placeholder="Buscar por título/apelido..." style="min-width:280px"></div>
    <div class="form-group" style="margin-left:8px">
      <select class="form-control" name="status">
        <?php $ops=['todos'=>'Todos','publicado'=>'Publicado','nao_publicado'=>'Não Publicado','arquivado'=>'Arquivado','lixeira'=>'Lixeira','pendente'=>'Pendente','reprovado'=>'Reprovado'];
        foreach($ops as $k=>$v): ?><option value="<?=$k?>" <?=$status===$k?'selected':''?>><?=$v?></option><?php endforeach;?>
      </select>
    </div>
    <div class="form-group" style="margin-left:8px">
      <select class="form-control" name="categoria">
        <option value="0">Todas as categorias</option>
        <?php foreach($cats as $c): ?><option value="<?=$c['id']?>" <?=$cat==$c['id']?'selected':''?>><?=h($c['titulo'])?></option><?php endforeach;?>
      </select>
    </div>
    <button class="btn btn-primary" style="margin-left:8px">Filtrar</button>
    <a class="btn btn-default" href="<?=h(strtok($_SERVER['REQUEST_URI'],'?'))?>" style="margin-left:4px">Limpar</a>
    <a class="btn btn-success" href="<?=$EDIT?>" style="margin-left:8px">➕ Novo Post de Mídia</a>
    <a class="btn btn-default" href="<?=$CATS?>" style="margin-left:8px">Categorias</a>
  </form>

  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead><tr><th style="width:70px">ID</th><th>Título</th><th>Categoria</th><th>Status</th><th>Publicação</th><th style="width:260px">Ações</th></tr></thead>
      <tbody>
      <?php if(!$rows): ?>
        <tr><td colspan="6" class="text-muted">Nenhum registro.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= h($r['titulo']) ?></td>
          <td><?= h($r['categoria'] ?? '-') ?></td>
          <td><?php
            $b=['publicado'=>'badge-pub','nao_publicado'=>'badge-npub','arquivado'=>'badge-arq','lixeira'=>'badge-lix','pendente'=>'badge-pen','reprovado'=>'badge-rep'];
            $t=['publicado'=>'Publicado','nao_publicado'=>'Não Publicado','arquivado'=>'Arquivado','lixeira'=>'Lixeira','pendente'=>'Pendente','reprovado'=>'Reprovado'];
          ?><span class="badge-pill <?=$b[$r['status']]??'badge-npub'?>"><?=$t[$r['status']]??$r['status']?></span></td>
          <td><?php $ini=$r['publish_up']?date('d/m/Y H:i',strtotime($r['publish_up'])):'-'; $fim=$r['publish_down']?date('d/m/Y H:i',strtotime($r['publish_down'])):'-'; echo "$ini → $fim"; ?></td>
          <td class="table-actions">
            <a class="btn btn-xs btn-primary" href="<?=$EDIT.'?id='.(int)$r['id']?>">Editar</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Publicar #<?=$r['id']?>?');">
              <input type="hidden" name="csrf" value="<?=h(tok())?>"><input type="hidden" name="acao" value="publicar"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-xs btn-success">Publicar</button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Despublicar #<?=$r['id']?>?');">
              <input type="hidden" name="csrf" value="<?=h(tok())?>"><input type="hidden" name="acao" value="despublicar"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-xs btn-warning">Despublicar</button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Arquivar #<?=$r['id']?>?');">
              <input type="hidden" name="csrf" value="<?=h(tok())?>"><input type="hidden" name="acao" value="arquivar"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-xs btn-default">Arquivar</button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Mandar para a lixeira #<?=$r['id']?>?');">
              <input type="hidden" name="csrf" value="<?=h(tok())?>"><input type="hidden" name="acao" value="lixeira"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-xs btn-danger">Lixeira</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif;?>
      </tbody>
    </table>
  </div>
</div></div>

<?php include_once ROOT_PATH.'/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'/system/includes/footer.php'; ?>
