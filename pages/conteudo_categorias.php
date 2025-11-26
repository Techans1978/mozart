<?php
// pages/conteudo_categorias.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function t(){ if(empty($_SESSION['csrf_cat'])) $_SESSION['csrf_cat']=bin2hex(random_bytes(16)); return $_SESSION['csrf_cat']; }
function okT($v){ return !empty($_SESSION['csrf_cat']) && hash_equals($_SESSION['csrf_cat'],(string)$v); }
function red(){ header('Location: '.$_SERVER['REQUEST_URI'], true, 303); exit; }

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!okT($_POST['csrf']??'')) die('Token inválido.');
  $acao=$_POST['acao']??'';
  if($acao==='salvar'){
    $id=(int)($_POST['id']??0);
    $titulo=trim($_POST['titulo']??'');
    $apelido=trim($_POST['apelido']??'');
    $parent=(int)($_POST['parent_id']??0) ?: null;
    $pub=isset($_POST['published'])?1:0;
    if($titulo===''||$apelido==='') die('Campos obrigatórios.');
    if($id>0){
      $st=$conn->prepare("UPDATE content_categories SET titulo=?, apelido=?, parent_id=?, published=?, updated_at=NOW() WHERE id=?");
      $st->bind_param('ssiii',$titulo,$apelido,$parent,$pub,$id); $st->execute(); $st->close();
    }else{
      $st=$conn->prepare("INSERT INTO content_categories(titulo,apelido,parent_id,published,created_at) VALUES(?,?,?,?,NOW())");
      $st->bind_param('ssii',$titulo,$apelido,$parent,$pub); $st->execute(); $st->close();
    }
    red();
  }
  if($acao==='excluir'){
    $id=(int)($_POST['id']??0);
    // impede excluir se tiver artigos
    $c=$conn->query("SELECT COUNT(*) n FROM content_articles WHERE categoria_id={$id}")->fetch_assoc()['n']??0;
    if($c>0) die('Categoria possui artigos.');
    $conn->query("DELETE FROM content_categories WHERE id={$id} LIMIT 1");
    red();
  }
}

$cats=[]; if($r=$conn->query("SELECT * FROM content_categories ORDER BY parent_id IS NOT NULL, titulo")) while($x=$r->fetch_assoc()) $cats[]=$x;

include_once ROOT_PATH.'/system/includes/head.php';
include_once ROOT_PATH.'/system/includes/navbar.php';
?>
<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header">Categorias</h1></div></div>

  <div class="panel panel-default">
    <div class="panel-body">
      <form class="form-inline" method="post" style="margin-bottom:10px">
        <input type="hidden" name="csrf" value="<?=h(t())?>">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" value="0">
        <div class="form-group"><input class="form-control" name="titulo" placeholder="Título" required></div>
        <div class="form-group" style="margin-left:8px"><input class="form-control" name="apelido" placeholder="apelido-slug" required></div>
        <div class="form-group" style="margin-left:8px">
          <select class="form-control" name="parent_id">
            <option value="">Sem pai</option>
            <?php foreach($cats as $c): ?>
              <option value="<?=$c['id']?>"><?=h($c['titulo'])?></option>
            <?php endforeach;?>
          </select>
        </div>
        <label style="margin-left:8px"><input type="checkbox" name="published" checked> Publicada</label>
        <button class="btn btn-success" style="margin-left:8px">➕ Nova Categoria</button>
      </form>

      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead><tr><th>ID</th><th>Título</th><th>Apelido</th><th>Pai</th><th>Publicado</th><th style="width:180px">Ações</th></tr></thead>
          <tbody>
            <?php foreach($cats as $c): ?>
              <tr>
                <td><?=$c['id']?></td>
                <td><?=h($c['titulo'])?></td>
                <td><?=h($c['apelido'])?></td>
                <td><?=$c['parent_id']?:'-'?></td>
                <td><?=$c['published']?'Sim':'Não'?></td>
                <td>
                  <form method="post" style="display:inline" onsubmit="return editarCat(this, <?= $c['id']?>);">
                    <input type="hidden" name="csrf" value="<?=h(t())?>">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="id" value="<?=$c['id']?>">
                    <input type="hidden" name="titulo" value="<?=h($c['titulo'])?>">
                    <input type="hidden" name="apelido" value="<?=h($c['apelido'])?>">
                    <input type="hidden" name="parent_id" value="<?=$c['parent_id']?>">
                    <input type="hidden" name="published" value="<?=$c['published']?>">
                    <button class="btn btn-xs btn-primary">Editar</button>
                  </form>
                  <form method="post" style="display:inline" onsubmit="return confirm('Excluir categoria? (somente se vazia)')">
                    <input type="hidden" name="csrf" value="<?=h(t())?>">
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="id" value="<?=$c['id']?>">
                    <button class="btn btn-xs btn-danger">Excluir</button>
                  </form>
                </td>
              </tr>
            <?php endforeach;?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div></div>

<script>
// prompt simples p/ edição inline (pode trocar por modal depois)
function editarCat(form, id){
  var t = prompt('Título:', form.elements['titulo'].value); if(t===null) return false;
  var s = prompt('Apelido (slug):', form.elements['apelido'].value); if(s===null) return false;
  form.elements['titulo'].value=t.trim(); form.elements['apelido'].value=s.trim();
  return true;
}
</script>

<?php include_once ROOT_PATH.'/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'/system/includes/footer.php'; ?>
