<?php
// pages/dicas_editar.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function token(){ if(empty($_SESSION['csrf_dicas_edit'])) $_SESSION['csrf_dicas_edit']=bin2hex(random_bytes(16)); return $_SESSION['csrf_dicas_edit']; }
function check_token($t){ return !empty($_SESSION['csrf_dicas_edit']) && hash_equals($_SESSION['csrf_dicas_edit'], (string)$t); }

$id = (int)($_GET['id'] ?? 0);
$erros=[]; $ok=null;

/* listas auxiliares */
$categorias=[]; if($r=$conn->query("SELECT id,titulo FROM dicas_categories WHERE published=1 ORDER BY titulo")) while($x=$r->fetch_assoc()) $categorias[]=$x;
$grupos=[];     if($r=$conn->query("SELECT id,nome FROM grupos ORDER BY nome")) while($x=$r->fetch_assoc()) $grupos[]=$x;
$perfis_db=[];  if($r=$conn->query("SELECT id,nome FROM perfis ORDER BY nome")) while($x=$r->fetch_assoc()) $perfis_db[]=$x; // opcional
$roles = ['bigboss','admin','gerente','usuario']; // nível simples, igual artigos

$art = [
  'titulo'=>'','apelido'=>'','introtext'=>'','fulltext'=>'','status'=>'nao_publicado','categoria_id'=>null,
  'nota_interna'=>'','versao_obs'=>'','meta_desc'=>'','meta_keywords'=>'','meta_robots'=>'',
  'meta_author'=>'','meta_rights'=>'','publish_up'=>'','publish_down'=>'','featured'=>0,'featured_until'=>'',
  'acesso_publico'=>1
];
$sel_groups=[]; $sel_roles=[]; $sel_perfis=[];

/* carregar se edição */
if($id>0){
  $st=$conn->prepare("SELECT * FROM dicas_articles WHERE id=? LIMIT 1");
  $st->bind_param('i',$id); $st->execute(); $res=$st->get_result(); $row=$res->fetch_assoc(); $st->close();
  if(!$row) die('Dica não encontrada.');
  $art=array_merge($art,$row);

  $rg=$conn->query("SELECT group_id FROM dicas_article_groups WHERE article_id={$id}");
  while($x=$rg->fetch_assoc()) $sel_groups[]=(int)$x['group_id'];

  $rr=$conn->query("SELECT role FROM dicas_article_roles WHERE article_id={$id}");
  while($x=$rr->fetch_assoc()) $sel_roles[]=$x['role'];

  $rp=$conn->query("SELECT perfil_id FROM dicas_article_profiles WHERE article_id={$id}");
  while($x=$rp->fetch_assoc()) $sel_perfis[]=(int)$x['perfil_id'];
}

/* salvar */
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!check_token($_POST['csrf']??'')) $erros[]='Token inválido. Recarregue.';
  $art['titulo']        = trim($_POST['titulo'] ?? '');
  $art['apelido']       = trim($_POST['apelido'] ?? '');
  $art['introtext']     = trim($_POST['introtext'] ?? '');
  $art['fulltext']      = trim($_POST['fulltext'] ?? '');
  $art['status']        = $_POST['status'] ?? 'nao_publicado';
  $art['categoria_id']  = (int)($_POST['categoria_id'] ?? 0) ?: null;
  $art['nota_interna']  = trim($_POST['nota_interna'] ?? '');
  $art['versao_obs']    = trim($_POST['versao_obs'] ?? '');
  $art['meta_desc']     = trim($_POST['meta_desc'] ?? '');
  $art['meta_keywords'] = trim($_POST['meta_keywords'] ?? '');
  $art['meta_robots']   = trim($_POST['meta_robots'] ?? '');
  $art['meta_author']   = trim($_POST['meta_author'] ?? '');
  $art['meta_rights']   = trim($_POST['meta_rights'] ?? '');
  $art['publish_up']    = ($_POST['publish_up']??'') ?: null;
  $art['publish_down']  = ($_POST['publish_down']??'') ?: null;
  $art['featured']      = isset($_POST['featured']) ? 1 : 0;
  $art['featured_until']= ($_POST['featured_until']??'') ?: null;
  $art['acesso_publico']= isset($_POST['acesso_publico']) ? 1 : 0;

  $sel_groups = array_map('intval', $_POST['groups'] ?? []);
  $sel_roles  = array_map('trim',   $_POST['roles'] ?? []);
  $sel_perfis = array_map('intval', $_POST['perfis'] ?? []);

  if($art['titulo']==='')  $erros[]='Título é obrigatório.';
  if($art['apelido']==='') $erros[]='Apelido (slug) é obrigatório.';

  if(!$erros){
    if($id>0){
      $st=$conn->prepare("UPDATE dicas_articles SET
          titulo=?, apelido=?, introtext=?, fulltext=?, status=?, categoria_id=?,
          nota_interna=?, versao_obs=?, meta_desc=?, meta_keywords=?, meta_robots=?,
          meta_author=?, meta_rights=?, publish_up=?, publish_down=?, featured=?,
          featured_until=?, modified_at=NOW(), modified_by=? , acesso_publico=?
        WHERE id=? LIMIT 1");
      $uid=(int)($_SESSION['user_id']??0);
      $st->bind_param('sssssiissssssssiiii',
        $art['titulo'],$art['apelido'],$art['introtext'],$art['fulltext'],$art['status'],$art['categoria_id'],
        $art['nota_interna'],$art['versao_obs'],$art['meta_desc'],$art['meta_keywords'],$art['meta_robots'],
        $art['meta_author'],$art['meta_rights'],$art['publish_up'],$art['publish_down'],$art['featured'],
        $art['featured_until'],$uid,$art['acesso_publico'],$id);
      $ok=$st->execute(); $st->close();
    }else{
      $st=$conn->prepare("INSERT INTO dicas_articles
        (titulo,apelido,introtext,fulltext,status,categoria_id,nota_interna,versao_obs,
         meta_desc,meta_keywords,meta_robots,meta_author,meta_rights,
         publish_up,publish_down,featured,featured_until,created_at,created_by,acesso_publico)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?)");
      $uid=(int)($_SESSION['user_id']??0);
      $st->bind_param('sssssiissssssssiii',
        $art['titulo'],$art['apelido'],$art['introtext'],$art['fulltext'],$art['status'],$art['categoria_id'],
        $art['nota_interna'],$art['versao_obs'],$art['meta_desc'],$art['meta_keywords'],$art['meta_robots'],
        $art['meta_author'],$art['meta_rights'],$art['publish_up'],$art['publish_down'],$art['featured'],
        $art['featured_until'],$uid,$art['acesso_publico']);
      $ok=$st->execute(); if($ok) $id=$conn->insert_id; $st->close();
    }

    if(!empty($ok)){
      // sincroniza pivôs
      $conn->query("DELETE FROM dicas_article_groups   WHERE article_id={$id}");
      if($sel_groups){
        $ins=$conn->prepare("INSERT INTO dicas_article_groups(article_id,group_id) VALUES(?,?)");
        foreach($sel_groups as $g){ $ins->bind_param('ii',$id,$g); $ins->execute(); }
        $ins->close();
      }
      $conn->query("DELETE FROM dicas_article_roles    WHERE article_id={$id}");
      if($sel_roles){
        $ins=$conn->prepare("INSERT INTO dicas_article_roles(article_id,role) VALUES(?,?)");
        foreach($sel_roles as $r){ $ins->bind_param('is',$id,$r); $ins->execute(); }
        $ins->close();
      }
      $conn->query("DELETE FROM dicas_article_profiles WHERE article_id={$id}");
      if($sel_perfis){
        $ins=$conn->prepare("INSERT INTO dicas_article_profiles(article_id,perfil_id) VALUES(?,?)");
        foreach($sel_perfis as $p){ $ins->bind_param('ii',$id,$p); $ins->execute(); }
        $ins->close();
      }
      $ok='Dica salva com sucesso.';
    }else{
      $erros[]='Falha ao salvar.';
    }
  }
}

include_once ROOT_PATH.'/system/includes/head.php';
include_once ROOT_PATH.'/system/includes/navbar.php';
?>
<style>
.card-pane{background:var(--surface);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);padding:16px;margin-bottom:12px}
.smallmuted{font-size:12px;color:var(--text-muted)}
</style>

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= $id?'Editar Dica':'Nova Dica' ?></h1></div></div>

  <?php if($erros): ?><div class="alert alert-danger"><strong>Verifique:</strong><ul style="margin:0;padding-left:18px"><?php foreach($erros as $e):?><li><?=h($e)?></li><?php endforeach;?></ul></div>
  <?php elseif($ok): ?><div class="alert alert-success"><?=h($ok)?></div><?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf" value="<?=h(token())?>">

    <div class="card-pane">
      <div class="row">
        <div class="col-md-8">
          <div class="form-group"><label>Título</label><input class="form-control" name="titulo" value="<?=h($art['titulo'])?>" required></div>
          <div class="form-group"><label>Apelido (slug)</label><input class="form-control" name="apelido" value="<?=h($art['apelido'])?>" required><div class="smallmuted">use hífens (ex.: minha-dica-top)</div></div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label>Status</label>
            <select class="form-control" name="status">
              <?php foreach(['publicado'=>'Publicado','nao_publicado'=>'Não Publicado','arquivado'=>'Arquivado','lixeira'=>'Lixeira','pendente'=>'Pendente','reprovado'=>'Reprovado'] as $k=>$v):?>
                <option value="<?=$k?>" <?=$art['status']===$k?'selected':''?>><?=$v?></option>
              <?php endforeach;?>
            </select>
          </div>
          <div class="form-group">
            <label>Categoria</label>
            <select class="form-control" name="categoria_id">
              <option value="">-- Sem categoria --</option>
              <?php foreach($categorias as $c): ?>
                <option value="<?=$c['id']?>" <?=$art['categoria_id']==$c['id']?'selected':''?>><?=h($c['titulo'])?></option>
              <?php endforeach;?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="card-pane">
      <div class="form-group">
        <label>Texto de Introdução (feed)</label>
        <textarea class="form-control js-editor-intro" name="introtext" rows="4"><?=h($art['introtext'])?></textarea>
      </div>
      <div class="form-group">
        <label>Conteúdo da Dica</label>
        <textarea class="form-control js-editor" name="fulltext" rows="14"><?=h($art['fulltext'])?></textarea>
      </div>
    </div>

    <div class="card-pane">
      <div class="row">
        <div class="col-md-6">
          <h4>Metadados</h4>
          <div class="form-group"><label>Meta Descrição</label><input class="form-control" name="meta_desc" value="<?=h($art['meta_desc'])?>"></div>
          <div class="form-group"><label>Palavras-chave</label><input class="form-control" name="meta_keywords" value="<?=h($art['meta_keywords'])?>"></div>
          <div class="form-group"><label>Robôs de busca</label><input class="form-control" name="meta_robots" value="<?=h($art['meta_robots'])?>" placeholder="index,follow"></div>
          <div class="form-group"><label>Autor</label><input class="form-control" name="meta_author" value="<?=h($art['meta_author'])?>"></div>
          <div class="form-group"><label>Direitos do Conteúdo</label><input class="form-control" name="meta_rights" value="<?=h($art['meta_rights'])?>"></div>
        </div>
        <div class="col-md-6">
          <h4>Publicação</h4>
          <div class="form-group"><label>Início</label><input class="form-control" type="datetime-local" name="publish_up" value="<?= $art['publish_up']?date('Y-m-d\TH:i', strtotime($art['publish_up'])):'' ?>"></div>
          <div class="form-group"><label>Término</label><input class="form-control" type="datetime-local" name="publish_down" value="<?= $art['publish_down']?date('Y-m-d\TH:i', strtotime($art['publish_down'])):'' ?>"></div>
          <div class="form-group"><label><input type="checkbox" name="featured" <?=$art['featured']?'checked':''?>> Destaque no Feed</label></div>
          <div class="form-group"><label>Finalizar Destaque</label><input class="form-control" type="datetime-local" name="featured_until" value="<?= $art['featured_until']?date('Y-m-d\TH:i', strtotime($art['featured_until'])):'' ?>"></div>
          <div class="form-group"><label><input type="checkbox" name="acesso_publico" <?=$art['acesso_publico']?'checked':''?>> Acesso público (ignora restrições)</label></div>
        </div>
      </div>
    </div>

    <div class="card-pane">
      <div class="row">
        <div class="col-md-4">
          <h4>Acesso por Grupos</h4>
          <select class="form-control" name="groups[]" multiple size="6">
            <?php foreach($grupos as $g): ?>
              <option value="<?=$g['id']?>" <?= in_array((int)$g['id'],$sel_groups,true)?'selected':'' ?>><?=h($g['nome'])?></option>
            <?php endforeach;?>
          </select>
          <div class="smallmuted">Selecione grupos que podem visualizar.</div>
        </div>
        <div class="col-md-4">
          <h4>Acesso por Perfis</h4>
          <select class="form-control" name="perfis[]" multiple size="6">
            <?php foreach($perfis_db as $p): ?>
              <option value="<?=$p['id']?>" <?= in_array((int)$p['id'],$sel_perfis,true)?'selected':'' ?>><?=h($p['nome'])?></option>
            <?php endforeach;?>
          </select>
          <div class="smallmuted">Perfis cadastrados no sistema.</div>
        </div>
        <div class="col-md-4">
          <h4>Acesso por Nível</h4>
          <select class="form-control" name="roles[]" multiple size="6">
            <?php foreach($roles as $r): ?>
              <option value="<?=$r?>" <?= in_array($r,$sel_roles,true)?'selected':'' ?>><?=h(ucfirst($r))?></option>
            <?php endforeach;?>
          </select>
          <div class="smallmuted">Níveis simples (igual a Artigos).</div>
        </div>
      </div>
    </div>

    <div class="card-pane">
      <div class="row">
        <div class="col-md-6"><div class="form-group"><label>Nota (interna)</label><input class="form-control" name="nota_interna" value="<?=h($art['nota_interna'])?>"></div></div>
        <div class="col-md-6"><div class="form-group"><label>Observação da Versão</label><input class="form-control" name="versao_obs" value="<?=h($art['versao_obs'])?>"></div></div>
      </div>
    </div>

    <button class="btn btn-primary">Salvar</button>
    <a class="btn btn-default" href="<?= BASE_URL.'/pages/dicas_listar.php'?>">Voltar</a>
  </form>
</div></div>

<!-- Summernote BS3 -->
<link rel="stylesheet" href="<?= BASE_URL ?>/system/includes/assets/summernote/summernote.css">
<script src="<?= BASE_URL ?>/system/includes/assets/summernote/summernote.min.js"></script>
<script src="<?= BASE_URL ?>/system/includes/assets/summernote/lang/summernote-pt-BR.min.js"></script>
<script>
$(function(){
  $('textarea.js-editor-intro').summernote({
    lang:'pt-BR', height:180,
    toolbar:[['font',['bold','italic','underline','clear']],['para',['ul','ol','paragraph']],['insert',['link','hr']],['view',['codeview']]]
  });
  $('textarea.js-editor').summernote({
    lang:'pt-BR', height:420, styleTags:['p','blockquote','pre','h1','h2','h3','h4'],
    toolbar:[['style',['style']],['font',['bold','italic','underline','clear']],['para',['ul','ol','paragraph']],['insert',['link','picture','video','table','hr']],['view',['fullscreen','codeview','help']]]
  });
});
</script>

<?php include_once ROOT_PATH.'/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'/system/includes/footer.php'; ?>
