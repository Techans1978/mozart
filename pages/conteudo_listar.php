<?php
// pages/conteudo_listar.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();

$EDIT_PAGE = BASE_URL . '/pages/conteudo_editar.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function flash($k,$v=null){ if($v===null){$m=$_SESSION['flash'][$k]??null; unset($_SESSION['flash'][$k]); return $m;} $_SESSION['flash'][$k]=$v; }
function token(){ if(empty($_SESSION['csrf_cnt'])) $_SESSION['csrf_cnt']=bin2hex(random_bytes(16)); return $_SESSION['csrf_cnt']; }
function check_token($t){ return !empty($_SESSION['csrf_cnt']) && hash_equals($_SESSION['csrf_cnt'], (string)$t); }
function redirect_self(){ $u=strtok($_SERVER['REQUEST_URI'],'?'); if(!empty($_GET)) $u.='?'.http_build_query($_GET); header("Location: $u",true,303); exit; }

# AÇÕES
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=(int)($_POST['id']??0);
  $acao=$_POST['acao']??'';
  $csrf=$_POST['csrf']??'';
  if(!$id || !check_token($csrf)){ flash('erro','Requisição inválida.'); redirect_self(); }

  $map = [
    'publicar'   => "UPDATE content_articles SET status='publicado', modified_at=NOW() WHERE id=? LIMIT 1",
    'despublicar'=> "UPDATE content_articles SET status='nao_publicado', modified_at=NOW() WHERE id=? LIMIT 1",
    'arquivar'   => "UPDATE content_articles SET status='arquivado', modified_at=NOW() WHERE id=? LIMIT 1",
    'lixeira'    => "UPDATE content_articles SET status='lixeira', modified_at=NOW() WHERE id=? LIMIT 1",
    'destacar'   => "UPDATE content_articles SET featured=1, featured_until=NULL WHERE id=? LIMIT 1",
    'tirar_destaque' => "UPDATE content_articles SET featured=0, featured_until=NULL WHERE id=? LIMIT 1",
  ];
  if(isset($map[$acao])){
    $st=$conn->prepare($map[$acao]);
    if($st){ $st->bind_param('i',$id); $ok=$st->execute(); $st->close(); }
    flash($ok?'ok':'erro', $ok?'Ação aplicada.':'Falha ao aplicar ação.');
  }else{
    flash('erro','Ação desconhecida.');
  }
  redirect_self();
}

# FILTROS
$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'publicado'; // publicado | nao_publicado | arquivado | lixeira | todos
$cat = (int)($_GET['cat'] ?? 0);
$feat = $_GET['feat'] ?? '';              // '' | 1 | 0

$where=[]; $types=''; $params=[];
if($q!==''){
  $where[]="(a.titulo LIKE CONCAT('%',?,'%') OR a.apelido LIKE CONCAT('%',?,'%') OR a.introtext LIKE CONCAT('%',?,'%'))";
  $params[]=$q; $params[]=$q; $params[]=$q; $types.='sss';
}
if($status!=='todos'){ $where[]="a.status=?"; $params[]=$status; $types.='s'; }
if($cat>0){ $where[]="a.categoria_id=?"; $params[]=$cat; $types.='i'; }
if($feat==='1' || $feat==='0'){ $where[]="a.featured=?"; $params[]=(int)$feat; $types.='i'; }

$sqlCats="SELECT id,titulo FROM content_categories WHERE published=1 ORDER BY titulo";
$cats=[]; if($r=$conn->query($sqlCats)){ while($c=$r->fetch_assoc()) $cats[]=$c; }

$sql="SELECT a.id,a.titulo,a.apelido,a.status,a.featured,a.created_at,a.modified_at,a.publish_up,a.publish_down,
             c.titulo AS categoria
      FROM content_articles a
      LEFT JOIN content_categories c ON c.id=a.categoria_id";
if($where) $sql.=" WHERE ".implode(" AND ",$where);
$sql.=" ORDER BY a.created_at DESC LIMIT 1000";

$rows=[];
if($types){
  $st=$conn->prepare($sql);
  $st->bind_param($types, ...$params);
  $st->execute(); $res=$st->get_result(); while($x=$res->fetch_assoc()) $rows[]=$x; $st->close();
}else{
  $res=$conn->query($sql); while($x=$res->fetch_assoc()) $rows[]=$x;
}

include_once ROOT_PATH.'/system/includes/head.php';
include_once ROOT_PATH.'/system/includes/navbar.php';
?>
<style>
.badge-pill{border-radius:999px;padding:4px 10px;font-size:12px}
.badge-pub{background:#e6ffed;color:#177245;border:1px solid #c1f2ce}
.badge-npub{background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe}
.badge-arq{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa}
.badge-lix{background:#fef2f2;color:#7f1d1d;border:1px solid #fecaca}
.table-actions .btn{margin-right:6px}
</style>

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header">Conteúdo</h1></div></div>

  <?php if($m=flash('ok')):?><div class="alert alert-success"><?=h($m)?></div><?php endif;?>
  <?php if($m=flash('erro')):?><div class="alert alert-danger"><?=h($m)?></div><?php endif;?>

  <h2>Artigos</h2>

  <form class="form-inline" method="get" style="margin-bottom:12px">
    <div class="form-group"><input class="form-control" type="text" name="q" placeholder="Buscar por título, apelido, intro..." value="<?=h($q)?>" style="min-width:320px"></div>
    <div class="form-group" style="margin-left:8px">
      <select class="form-control" name="status">
        <?php foreach(['todos'=>'Todos','publicado'=>'Publicado','nao_publicado'=>'Não Publicado','arquivado'=>'Arquivado','lixeira'=>'No Lixo'] as $k=>$v): ?>
          <option value="<?=$k?>" <?=$status===$k?'selected':''?>><?=$v?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin-left:8px">
      <select class="form-control" name="cat">
        <option value="0">Todas as Categorias</option>
        <?php foreach($cats as $c): ?>
          <option value="<?=$c['id']?>" <?=$cat===$c['id']?'selected':''?>><?=h($c['titulo'])?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin-left:8px">
      <select class="form-control" name="feat">
        <option value="" <?=$feat===''?'selected':''?>>Todos</option>
        <option value="1" <?=$feat==='1'?'selected':''?>>Destaque</option>
        <option value="0" <?=$feat==='0'?'selected':''?>>Sem destaque</option>
      </select>
    </div>
    <button class="btn btn-primary" style="margin-left:8px">Filtrar</button>
    <a class="btn btn-default" href="<?=h(strtok($_SERVER['REQUEST_URI'],'?'))?>" style="margin-left:4px">Limpar</a>
    <a class="btn btn-success" href="<?=$EDIT_PAGE?>" style="margin-left:8px">➕ Novo Artigo</a>
  </form>

  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead><tr>
        <th style="width:70px">ID</th><th>Título</th><th>Categoria</th><th>Status</th><th>Destaque</th><th>Publicação</th><th style="width:280px">Ações</th>
      </tr></thead>
      <tbody>
        <?php if(!$rows): ?>
          <tr><td colspan="7" class="text-muted">Nenhum artigo encontrado.</td></tr>
        <?php else: foreach($rows as $r): ?>
          <tr>
            <td><?=$r['id']?></td>
            <td><strong><?=h($r['titulo'])?></strong><br><small class="text-muted"><?=h($r['apelido'])?></small></td>
            <td><?=h($r['categoria']??'-')?></td>
            <td>
              <?php
              $b = ['publicado'=>'badge-pub','nao_publicado'=>'badge-npub','arquivado'=>'badge-arq','lixeira'=>'badge-lix'];
              $t = ['publicado'=>'Publicado','nao_publicado'=>'Não Publicado','arquivado'=>'Arquivado','lixeira'=>'No Lixo'];
              ?>
              <span class="badge-pill <?=$b[$r['status']]?>"><?=$t[$r['status']]?></span>
            </td>
            <td><?=$r['featured']?'⭐':''?></td>
            <td><small><?=h($r['publish_up']?:'-')?> → <?=h($r['publish_down']?:'∞')?></small></td>
            <td class="table-actions">
              <a class="btn btn-xs btn-primary" href="<?=$EDIT_PAGE.'?id='.$r['id']?>">Editar</a>

              <?php if($r['status']!=='publicado'): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Publicar este artigo?')">
                  <input type="hidden" name="csrf" value="<?=h(token())?>">
                  <input type="hidden" name="acao" value="publicar">
                  <input type="hidden" name="id" value="<?=$r['id']?>">
                  <button class="btn btn-xs btn-success">Publicar</button>
                </form>
              <?php else: ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Despublicar este artigo?')">
                  <input type="hidden" name="csrf" value="<?=h(token())?>">
                  <input type="hidden" name="acao" value="despublicar">
                  <input type="hidden" name="id" value="<?=$r['id']?>">
                  <button class="btn btn-xs btn-warning">Despublicar</button>
                </form>
              <?php endif; ?>

              <form method="post" style="display:inline" onsubmit="return confirm('Arquivar este artigo?')">
                <input type="hidden" name="csrf" value="<?=h(token())?>">
                <input type="hidden" name="acao" value="arquivar">
                <input type="hidden" name="id" value="<?=$r['id']?>">
                <button class="btn btn-xs btn-default">Arquivar</button>
              </form>

              <form method="post" style="display:inline" onsubmit="return confirm('Mover para a lixeira?')">
                <input type="hidden" name="csrf" value="<?=h(token())?>">
                <input type="hidden" name="acao" value="lixeira">
                <input type="hidden" name="id" value="<?=$r['id']?>">
                <button class="btn btn-xs btn-danger">Lixeira</button>
              </form>

              <?php if(!$r['featured']): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Marcar como destaque?')">
                  <input type="hidden" name="csrf" value="<?=h(token())?>">
                  <input type="hidden" name="acao" value="destacar">
                  <input type="hidden" name="id" value="<?=$r['id']?>">
                  <button class="btn btn-xs btn-info">Destacar</button>
                </form>
              <?php else: ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Remover destaque?')">
                  <input type="hidden" name="csrf" value="<?=h(token())?>">
                  <input type="hidden" name="acao" value="tirar_destaque">
                  <input type="hidden" name="id" value="<?=$r['id']?>">
                  <button class="btn btn-xs btn-default">Tirar Destaque</button>
                </form>
              <?php endif; ?>

            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <p class="text-muted"><small>Exibindo até 1000 registros. Use os filtros.</small></p>
</div></div>
<?php include_once ROOT_PATH.'/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'/system/includes/footer.php'; ?>
