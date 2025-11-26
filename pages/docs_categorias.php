<?php
// pages/doc_categorias.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function tok(){ if(empty($_SESSION['csrf_doc_cat'])) $_SESSION['csrf_doc_cat']=bin2hex(random_bytes(16)); return $_SESSION['csrf_doc_cat']; }
function chk($t){ return !empty($_SESSION['csrf_doc_cat']) && hash_equals($_SESSION['csrf_doc_cat'], (string)$t); }

$err=null; $ok=null;

/* POST */
if($_SERVER['REQUEST_METHOD']==='POST'){
  $csrf=$_POST['csrf']??''; if(!chk($csrf)){ $err='Token inválido.'; }
  else{
    $acao=$_POST['acao']??'';
    if($acao==='salvar'){
      $id=(int)($_POST['id']??0);
      $titulo=trim($_POST['titulo']??''); $apelido=trim($_POST['apelido']??'');
      $parent_id=(int)($_POST['parent_id']??0) ?: null;
      $published=isset($_POST['published'])?1:0;
      $ordering=(int)($_POST['ordering']??0);
      $descricao=trim($_POST['descricao']??'');
      if($titulo===''||$apelido===''){ $err='Título e Apelido são obrigatórios.'; }
      else{
        if($id>0){
          $st=$conn->prepare("UPDATE doc_categories SET titulo=?,apelido=?,parent_id=?,published=?,ordering=?,descricao=?,updated_at=NOW(),updated_by=? WHERE id=? LIMIT 1");
          $uid=(int)($_SESSION['user_id']??0); $st->bind_param('sssissii',$titulo,$apelido,$parent_id,$published,$ordering,$descricao,$uid,$id);
          $ok=$st->execute(); $st->close(); if(!$ok) $err='Falha ao atualizar.';
        }else{
          $st=$conn->prepare("INSERT INTO doc_categories(titulo,apelido,parent_id,published,ordering,descricao,created_at,created_by) VALUES(?,?,?,?,?,?,NOW(),?)");
          $uid=(int)($_SESSION['user_id']??0); $st->bind_param('sssissi',$titulo,$apelido,$parent_id,$published,$ordering,$descricao,$uid);
          $ok=$st->execute(); $st->close(); if(!$ok) $err='Falha ao inserir.';
        }
      }
    }
    if($acao==='excluir'){
      $id=(int)($_POST['id']??0);
      if($id>0){ $st=$conn->prepare("DELETE FROM doc_categories WHERE id=? LIMIT 1"); $st->bind_param('i',$id); $ok=$st->execute(); $st->close(); if(!$ok) $err='Não foi possível excluir (possui filhos ou vínculos).'; }
    }
  }
}

/* listas */
$cats=[]; if($r=$conn->query("SELECT id,titulo FROM doc_categories ORDER BY titulo")) while($x=$r->fetch_assoc()) $cats[]=$x;
$rows=[]; $res=$conn->query("SELECT c.id,c.titulo,c.apelido,c.parent_id,COALESCE(p.titulo,'-') AS pai,c.published,c.ordering,c.descricao
                             FROM doc_categories c LEFT JOIN doc_categories p ON p.id=c.parent_id
                             ORDER BY c.ordering,c.titulo");
while($x=$res->fetch_assoc()) $rows[]=$x;

include_once ROOT_PATH.'/system/includes/head.php';
include_once ROOT_PATH.'/system/includes/navbar.php';
?>
<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header">Categorias — Mídias</h1></div></div>

  <?php if($err): ?><div class="alert alert-danger"><?=h($err)?></div><?php endif;?>
  <?php if($ok && !$err): ?><div class="alert alert-success">Operação realizada com sucesso.</div><?php endif;?>

  <div class="row">
    <div class="col-md-5">
      <form method="post" class="panel panel-default" style="padding:12px">
        <input type="hidden" name="csrf" value="<?=h(tok())?>">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" id="cat_id" value="">
        <div class="form-group"><label>Título</label><input class="form-control" name="titulo" id="cat_titulo" required></div>
        <div class="form-group"><label>Apelido (slug)</label><input class="form-control" name="apelido" id="cat_apelido" required></div>
        <div class="form-group">
          <label>Categoria Pai</label>
          <select class="form-control" name="parent_id" id="cat_parent">
            <option value="">— Nenhuma —</option>
            <?php foreach($cats as $c): ?><option value="<?=$c['id']?>"><?=h($c['titulo'])?></option><?php endforeach;?>
          </select>
        </div>
        <div class="form-group"><label>Ordem</label><input type="number" class="form-control" name="ordering" id="cat_ordering" value="0"></div>
        <div class="checkbox"><label><input type="checkbox" name="published" id="cat_published" checked> Publicada</label></div>
        <div class="form-group"><label>Descrição</label><textarea class="form-control js-editor-intro" name="descricao" id="cat_desc" rows="5"></textarea></div>
        <button class="btn btn-primary">Salvar</button>
      </form>
    </div>
    <div class="col-md-7">
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead><tr><th style="width:70px">ID</th><th>Título</th><th>Apelido</th><th>Pai</th><th>Pub.</th><th style="width:180px">Ações</th></tr></thead>
          <tbody>
            <?php if(!$rows): ?><tr><td colspan="6" class="text-muted">Nenhuma categoria.</td></tr>
            <?php else: foreach($rows as $r): ?>
              <tr>
                <td><?=$r['id']?></td>
                <td><?=h($r['titulo'])?></td>
                <td><?=h($r['apelido'])?></td>
                <td><?=h($r['pai'])?></td>
                <td><?=$r['published']?'Sim':'Não'?></td>
                <td>
                  <button class="btn btn-xs btn-primary" onclick='fillCatForm(<?=json_encode($r,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>)'>Editar</button>
                  <form method="post" style="display:inline" onsubmit="return confirm('Excluir categoria #<?=$r['id']?>?');">
                    <input type="hidden" name="csrf" value="<?=h(tok())?>">
                    <input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?=$r['id']?>">
                    <button class="btn btn-xs btn-danger">Excluir</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif;?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div></div>

<link rel="stylesheet" href="<?= BASE_URL ?>/system/includes/assets/summernote/summernote.css">
<script src="<?= BASE_URL ?>/system/includes/assets/summernote/summernote.min.js"></script>
<script src="<?= BASE_URL ?>/system/includes/assets/summernote/lang/summernote-pt-BR.min.js"></script>
<script>
function fillCatForm(r){
  document.getElementById('cat_id').value=r.id;
  document.getElementById('cat_titulo').value=r.titulo;
  document.getElementById('cat_apelido').value=r.apelido;
  document.getElementById('cat_parent').value=r.parent_id||'';
  document.getElementById('cat_ordering').value=r.ordering||0;
  document.getElementById('cat_published').checked=(r.published==1);
  $('#cat_desc').summernote('code', r.descricao || '');
}
$(function(){
  $('.js-editor-intro').summernote({ lang:'pt-BR', height:160,
    toolbar:[['font',['bold','italic','underline','clear']],['para',['ul','ol','paragraph']],['insert',['link','hr']],['view',['codeview']]] });
});
</script>

<?php include_once ROOT_PATH.'/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'/system/includes/footer.php'; ?>
