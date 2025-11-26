<?php
// pages/media_editar.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function token(){ if(empty($_SESSION['csrf_media_edit'])) $_SESSION['csrf_media_edit']=bin2hex(random_bytes(16)); return $_SESSION['csrf_media_edit']; }
function check_token($t){ return !empty($_SESSION['csrf_media_edit']) && hash_equals($_SESSION['csrf_media_edit'], (string)$t); }

$UPLOAD_DIR = ROOT_PATH.'uploads/media/';
$URL_UPLOAD = BASE_URL .'/uploads/media/';

function ensure_dir($base){
  if(!is_dir($base)) mkdir($base,0775,true);
  $y=date('Y'); $m=date('m');
  if(!is_dir($base.$y)) mkdir($base.$y,0775,true);
  if(!is_dir($base.$y.'/'.$m)) mkdir($base.$y.'/'.$m,0775,true);
  return [$base.$y.'/'.$m.'/', $y.'/'.$m.'/'];
}

$tipos_img=['image/jpeg','image/png','image/gif','image/webp'];
$tipos_vid=['video/mp4','video/webm','video/ogg'];
$tipos_aud=['audio/mpeg','audio/ogg','audio/wav','audio/webm'];

function guess_tipo($mime,$origem){
  if($origem==='embed') return 'embed';
  if(strpos($mime,'image/')===0) return 'imagem';
  if(strpos($mime,'video/')===0) return 'video';
  if(strpos($mime,'audio/')===0) return 'audio';
  return 'embed';
}

/* listas auxiliares */
$categorias=[]; if($r=$conn->query("SELECT id,titulo FROM media_categories WHERE published=1 ORDER BY titulo")) while($x=$r->fetch_assoc()) $categorias[]=$x;
$grupos=[];     if($r=$conn->query("SELECT id,nome FROM grupos ORDER BY nome")) while($x=$r->fetch_assoc()) $grupos[]=$x;
$perfis_db=[];  if($r=$conn->query("SELECT id,nome FROM perfis ORDER BY nome")) while($x=$r->fetch_assoc()) $perfis_db[]=$x;
$roles=['bigboss','admin','gerente','usuario'];

$id=(int)($_GET['id']??0); $erros=[]; $ok=null;

$post=['titulo'=>'','apelido'=>'','descricao'=>'','status'=>'nao_publicado','categoria_id'=>null,
       'meta_desc'=>'','meta_keywords'=>'','meta_robots'=>'','meta_author'=>'','meta_rights'=>'',
       'publish_up'=>'','publish_down'=>'','featured'=>0,'featured_until'=>'','acesso_publico'=>1,
       'nota_interna'=>'','versao_obs'=>''];
$sel_groups=[];$sel_roles=[];$sel_perfis=[];$assets=[];

/* carregar se edição */
if($id>0){
  $st=$conn->prepare("SELECT * FROM media_posts WHERE id=? LIMIT 1");
  $st->bind_param('i',$id); $st->execute(); $res=$st->get_result(); $row=$res->fetch_assoc(); $st->close();
  if(!$row) die('Post não encontrado.');
  $post=array_merge($post,$row);
  $ra=$conn->query("SELECT * FROM media_assets WHERE post_id={$id} ORDER BY ordering,id");
  while($x=$ra->fetch_assoc()) $assets[]=$x;
  $rg=$conn->query("SELECT group_id FROM media_post_groups WHERE post_id={$id}"); while($x=$rg->fetch_assoc()) $sel_groups[]=(int)$x['group_id'];
  $rr=$conn->query("SELECT role FROM media_post_roles WHERE post_id={$id}"); while($x=$rr->fetch_assoc()) $sel_roles[]=$x['role'];
  $rp=$conn->query("SELECT perfil_id FROM media_post_profiles WHERE post_id={$id}"); while($x=$rp->fetch_assoc()) $sel_perfis[]=(int)$x['perfil_id'];
}

/* salvar */
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!check_token($_POST['csrf']??'')) $erros[]='Token inválido. Recarregue.';

  $post['titulo']        = trim($_POST['titulo'] ?? '');
  $post['apelido']       = trim($_POST['apelido'] ?? '');
  $post['descricao']     = trim($_POST['descricao'] ?? '');
  $post['status']        = $_POST['status'] ?? 'nao_publicado';
  $post['categoria_id']  = (int)($_POST['categoria_id'] ?? 0) ?: null;
  $post['meta_desc']     = trim($_POST['meta_desc'] ?? '');
  $post['meta_keywords'] = trim($_POST['meta_keywords'] ?? '');
  $post['meta_robots']   = trim($_POST['meta_robots'] ?? '');
  $post['meta_author']   = trim($_POST['meta_author'] ?? '');
  $post['meta_rights']   = trim($_POST['meta_rights'] ?? '');
  $post['publish_up']    = ($_POST['publish_up']??'') ?: null;
  $post['publish_down']  = ($_POST['publish_down']??'') ?: null;
  $post['featured']      = isset($_POST['featured']) ? 1 : 0;
  $post['featured_until']= ($_POST['featured_until']??'') ?: null;
  $post['acesso_publico']= isset($_POST['acesso_publico']) ? 1 : 0;
  $post['nota_interna']  = trim($_POST['nota_interna'] ?? '');
  $post['versao_obs']    = trim($_POST['versao_obs'] ?? '');

  $sel_groups = array_map('intval', $_POST['groups'] ?? []);
  $sel_roles  = array_map('trim',   $_POST['roles'] ?? []);
  $sel_perfis = array_map('intval', $_POST['perfis'] ?? []);

  if($post['titulo']==='')  $erros[]='Título é obrigatório.';
  if($post['apelido']==='') $erros[]='Apelido (slug) é obrigatório.';

  if(!$erros){
    $uid=(int)($_SESSION['user_id']??0);
    if($id>0){
      $st=$conn->prepare("UPDATE media_posts SET
        titulo=?, apelido=?, descricao=?, status=?, categoria_id=?, meta_desc=?, meta_keywords=?, meta_robots=?,
        meta_author=?, meta_rights=?, publish_up=?, publish_down=?, featured=?, featured_until=?, modified_at=NOW(),
        modified_by=?, acesso_publico=?, nota_interna=?, versao_obs=? WHERE id=? LIMIT 1");
      $st->bind_param('ssssissssssssiiiissi',
        $post['titulo'],$post['apelido'],$post['descricao'],$post['status'],$post['categoria_id'],
        $post['meta_desc'],$post['meta_keywords'],$post['meta_robots'],$post['meta_author'],$post['meta_rights'],
        $post['publish_up'],$post['publish_down'],$post['featured'],$post['featured_until'],
        $uid,$post['acesso_publico'],$post['nota_interna'],$post['versao_obs'],$id);
      $ok=$st->execute(); $st->close();
      $post_id=$id;
    }else{
      $st=$conn->prepare("INSERT INTO media_posts
        (titulo,apelido,descricao,status,categoria_id,meta_desc,meta_keywords,meta_robots,meta_author,meta_rights,
         publish_up,publish_down,featured,featured_until,created_at,created_by,acesso_publico,nota_interna,versao_obs)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?)");
      $st->bind_param('ssssissssssssiiiss',
        $post['titulo'],$post['apelido'],$post['descricao'],$post['status'],$post['categoria_id'],
        $post['meta_desc'],$post['meta_keywords'],$post['meta_robots'],$post['meta_author'],$post['meta_rights'],
        $post['publish_up'],$post['publish_down'],$post['featured'],$post['featured_until'],
        $uid,$post['acesso_publico'],$post['nota_interna'],$post['versao_obs']);
      $ok=$st->execute(); $post_id=$ok?$conn->insert_id:0; $st->close();
      $id=$post_id;
    }

    if(!empty($ok) && $post_id>0){
      /* pivôs */
      $conn->query("DELETE FROM media_post_groups WHERE post_id={$post_id}");
      if($sel_groups){ $ins=$conn->prepare("INSERT INTO media_post_groups(post_id,group_id) VALUES(?,?)"); foreach($sel_groups as $g){ $ins->bind_param('ii',$post_id,$g); $ins->execute(); } $ins->close(); }
      $conn->query("DELETE FROM media_post_roles WHERE post_id={$post_id}");
      if($sel_roles){ $ins=$conn->prepare("INSERT INTO media_post_roles(post_id,role) VALUES(?,?)"); foreach($sel_roles as $r){ $ins->bind_param('is',$post_id,$r); $ins->execute(); } $ins->close(); }
      $conn->query("DELETE FROM media_post_profiles WHERE post_id={$post_id}");
      if($sel_perfis){ $ins=$conn->prepare("INSERT INTO media_post_profiles(post_id,perfil_id) VALUES(?,?)"); foreach($sel_perfis as $p){ $ins->bind_param('ii',$post_id,$p); $ins->execute(); } $ins->close(); }

      /* ===== uploads ===== */
      if(!empty($_FILES['files']['name'][0])){
        list($dir_abs,$dir_rel)=ensure_dir($UPLOAD_DIR);
        $total=count($_FILES['files']['name']);
        for($i=0;$i<$total;$i++){
          if($_FILES['files']['error'][$i]!==UPLOAD_ERR_OK) continue;
          $name=basename($_FILES['files']['name'][$i]);
          $tmp =$_FILES['files']['tmp_name'][$i];
          $mime=mime_content_type($tmp) ?: $_FILES['files']['type'][$i];
          if(!in_array($mime, array_merge($tipos_img,$tipos_vid,$tipos_aud), true)) continue;

          $ext=pathinfo($name, PATHINFO_EXTENSION);
          $safe=bin2hex(random_bytes(8)).'.'.$ext;
          $dest=$dir_abs.$safe;
          if(move_uploaded_file($tmp,$dest)){
            $size=filesize($dest);
            $width=$height=NULL;
            if(strpos($mime,'image/')===0){ $info=@getimagesize($dest); if($info){ $width=$info[0]; $height=$info[1]; } }
            $tipo=guess_tipo($mime,'upload');
            $file_rel='/uploads/media/'.$dir_rel.$safe;

            $ins=$conn->prepare("INSERT INTO media_assets(post_id,tipo,origem,file_path,file_name,mime,size_bytes,width,height,ordering,created_by)
                                 VALUES(?,?,?,?,?,?,?,?,?,(SELECT IFNULL(MAX(ordering),0)+1 FROM media_assets WHERE post_id=?),?)");
            $ins->bind_param('isssssiiiis', $post_id,$tipo,'upload',$file_rel,$name,$mime,$size,$width,$height,$post_id,$uid);
            $ins->execute(); $ins->close();
          }
        }
      }

      /* ===== embeds ===== */
      if(!empty($_POST['embed_url'])){
        $urls=$_POST['embed_url']; $types=$_POST['embed_tipo']??[]; $caps=$_POST['embed_legenda']??[];
        foreach($urls as $k=>$url){
          $u=trim($url); if($u==='') continue;
          $t=trim($types[$k]??'embed'); $cap=trim($caps[$k]??'');
          $prov = (strpos($u,'youtube')!==false||strpos($u,'youtu.be')!==false)?'youtube':
                  ((strpos($u,'vimeo')!==false)?'vimeo':
                  ((strpos($u,'soundcloud')!==false)?'soundcloud':NULL));
          $ins=$conn->prepare("INSERT INTO media_assets(post_id,tipo,origem,external_url,provider,legenda,ordering,created_by)
                               VALUES(?,?,?,?,?,?,(SELECT IFNULL(MAX(ordering),0)+1 FROM media_assets WHERE post_id=?),?)");
          $ins->bind_param('isssssii', $post_id,$t,'embed',$u,$prov,$cap,$post_id,$uid);
          $ins->execute(); $ins->close();
        }
      }

      /* atualizar/remoção de assets */
      if(!empty($_POST['asset_order'])||!empty($_POST['asset_delete'])||!empty($_POST['asset_legenda'])||!empty($_POST['asset_alt'])){
        foreach($_POST['asset_order'] ?? [] as $aid=>$ord){
          $aid=(int)$aid; $ord=(int)$ord;
          if(isset($_POST['asset_delete'][$aid])){
            $res=$conn->query("SELECT file_path,origem FROM media_assets WHERE id={$aid} AND post_id={$post_id} LIMIT 1");
            if($res && $row=$res->fetch_assoc()){
              if($row['origem']==='upload' && !empty($row['file_path'])){
                $abs=ROOT_PATH.ltrim($row['file_path'],'/');
                if(is_file($abs)) @unlink($abs);
              }
            }
            $conn->query("DELETE FROM media_assets WHERE id={$aid} AND post_id={$post_id} LIMIT 1");
          }else{
            $leg=trim($_POST['asset_legenda'][$aid] ?? '');
            $alt=trim($_POST['asset_alt'][$aid] ?? '');
            $st=$conn->prepare("UPDATE media_assets SET ordering=?, legenda=?, alt_text=? WHERE id=? AND post_id=?");
            $st->bind_param('issii',$ord,$leg,$alt,$aid,$post_id); $st->execute(); $st->close();
          }
        }
      }

      $ok='Mídia salva com sucesso.';
      // recarrega assets
      $assets=[]; $ra=$conn->query("SELECT * FROM media_assets WHERE post_id={$post_id} ORDER BY ordering,id"); while($x=$ra->fetch_assoc()) $assets[]=$x;
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
.table td input.input-sm{height:30px;padding:4px 8px}
</style>

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= $id?'Editar Post de Mídia':'Novo Post de Mídia' ?></h1></div></div>

  <?php if($erros): ?><div class="alert alert-danger"><strong>Verifique:</strong><ul style="margin:0;padding-left:18px"><?php foreach($erros as $e):?><li><?=h($e)?></li><?php endforeach;?></ul></div>
  <?php elseif($ok): ?><div class="alert alert-success"><?=h($ok)?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=h(token())?>">

    <div class="card-pane">
      <div class="row">
        <div class="col-md-8">
          <div class="form-group"><label>Título</label><input class="form-control" name="titulo" value="<?=h($post['titulo'])?>" required></div>
          <div class="form-group"><label>Apelido (slug)</label><input class="form-control" name="apelido" value="<?=h($post['apelido'])?>" required><div class="smallmuted">use hífens (ex.: evento-superabertura)</div></div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label>Status</label>
            <select class="form-control" name="status">
              <?php foreach(['publicado'=>'Publicado','nao_publicado'=>'Não Publicado','arquivado'=>'Arquivado','lixeira'=>'Lixeira','pendente'=>'Pendente','reprovado'=>'Reprovado'] as $k=>$v):?>
                <option value="<?=$k?>" <?=$post['status']===$k?'selected':''?>><?=$v?></option>
              <?php endforeach;?>
            </select>
          </div>
          <div class="form-group">
            <label>Categoria</label>
            <select class="form-control" name="categoria_id">
              <option value="">-- Sem categoria --</option>
              <?php foreach($categorias as $c): ?><option value="<?=$c['id']?>" <?=$post['categoria_id']==$c['id']?'selected':''?>><?=h($c['titulo'])?></option><?php endforeach;?>
            </select>
          </div>
        </div>
      </div>
      <div class="form-group"><label>Descrição</label><textarea class="form-control js-editor" name="descricao" rows="8"><?=h($post['descricao'])?></textarea></div>
    </div>

    <div class="card-pane">
      <h4>Mídias do Post</h4>

      <div class="form-group">
        <label>Adicionar arquivos (imagens, vídeos, áudios)</label>
        <input type="file" name="files[]" multiple class="form-control">
        <div class="smallmuted">Formatos: JPG/PNG/WEBP, MP4/WEBM, MP3/OGG/WAV/WebM. Tamanho conforme PHP.</div>
      </div>

      <div class="form-group">
        <label>Gravar áudio (beta)</label><br>
        <button type="button" id="recBtn" class="btn btn-default">● Gravar</button>
        <button type="button" id="stopBtn" class="btn btn-default" disabled>■ Parar</button>
        <small class="smallmuted">Usa microfone do navegador (MediaRecorder). O arquivo aparece junto com os uploads.</small>
      </div>

      <div class="form-group">
        <label>Adicionar embeds</label>
        <div id="embeds">
          <div class="row embed-row" style="margin-bottom:8px">
            <div class="col-sm-6"><input class="form-control" name="embed_url[]" placeholder="URL (YouTube, Vimeo, SoundCloud, link de imagem, etc.)"></div>
            <div class="col-sm-3">
              <select class="form-control" name="embed_tipo[]">
                <option value="embed">Detectar</option>
                <option value="imagem">Imagem</option>
                <option value="video">Vídeo</option>
                <option value="audio">Áudio</option>
              </select>
            </div>
            <div class="col-sm-3"><input class="form-control" name="embed_legenda[]" placeholder="Legenda (opcional)"></div>
          </div>
        </div>
        <button type="button" class="btn btn-default" id="addEmbed">+ Outra linha</button>
      </div>

      <?php if($assets): ?>
      <h5>Já adicionados</h5>
      <div class="table-responsive">
        <table class="table table-striped">
          <thead><tr><th style="width:60px">#</th><th>Tipo</th><th>Origem</th><th>Preview/URL</th><th>Legenda</th><th>Alt (img)</th><th style="width:90px">Ordem</th><th style="width:100px">Ações</th></tr></thead>
          <tbody>
            <?php foreach($assets as $a): ?>
              <tr>
                <td><?=$a['id']?></td>
                <td><?=$a['tipo']?></td>
                <td><?=$a['origem']?></td>
                <td>
                  <?php if($a['origem']==='upload'): ?>
                    <a href="<?=h($a['file_path'])?>" target="_blank"><?=h($a['file_name'])?></a>
                  <?php else: ?>
                    <a href="<?=h($a['external_url'])?>" target="_blank"><?=h($a['external_url'])?></a>
                  <?php endif; ?>
                </td>
                <td><input class="form-control input-sm" name="asset_legenda[<?=$a['id']?>]" value="<?=h($a['legenda'])?>"></td>
                <td><input class="form-control input-sm" name="asset_alt[<?=$a['id']?>]" value="<?=h($a['alt_text'])?>"></td>
                <td><input type="number" class="form-control input-sm" name="asset_order[<?=$a['id']?>]" value="<?= (int)$a['ordering'] ?>"></td>
                <td><label><input type="checkbox" name="asset_delete[<?=$a['id']?>]"> Remover</label></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <div class="card-pane">
      <div class="row">
        <div class="col-md-6">
          <h4>Metadados</h4>
          <div class="form-group"><label>Meta Descrição</label><input class="form-control" name="meta_desc" value="<?=h($post['meta_desc'])?>"></div>
          <div class="form-group"><label>Palavras-chave</label><input class="form-control" name="meta_keywords" value="<?=h($post['meta_keywords'])?>"></div>
          <div class="form-group"><label>Robôs de busca</label><input class="form-control" name="meta_robots" value="<?=h($post['meta_robots'])?>" placeholder="index,follow"></div>
          <div class="form-group"><label>Autor</label><input class="form-control" name="meta_author" value="<?=h($post['meta_author'])?>"></div>
          <div class="form-group"><label>Direitos do Conteúdo</label><input class="form-control" name="meta_rights" value="<?=h($post['meta_rights'])?>"></div>
        </div>
        <div class="col-md-6">
          <h4>Publicação</h4>
          <div class="form-group"><label>Início</label><input class="form-control" type="datetime-local" name="publish_up" value="<?= $post['publish_up']?date('Y-m-d\TH:i', strtotime($post['publish_up'])):'' ?>"></div>
          <div class="form-group"><label>Término</label><input class="form-control" type="datetime-local" name="publish_down" value="<?= $post['publish_down']?date('Y-m-d\TH:i', strtotime($post['publish_down'])):'' ?>"></div>
          <div class="form-group"><label><input type="checkbox" name="featured" <?=$post['featured']?'checked':''?>> Destaque no Feed</label></div>
          <div class="form-group"><label>Finalizar Destaque</label><input class="form-control" type="datetime-local" name="featured_until" value="<?= $post['featured_until']?date('Y-m-d\TH:i', strtotime($post['featured_until'])):'' ?>"></div>
          <div class="form-group"><label><input type="checkbox" name="acesso_publico" <?=$post['acesso_publico']?'checked':''?>> Acesso público (ignora restrições)</label></div>
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
          <div class="smallmuted">Níveis simples (igual aos outros módulos).</div>
        </div>
      </div>
    </div>

    <div class="card-pane">
      <div class="row">
        <div class="col-md-6"><div class="form-group"><label>Nota (interna)</label><input class="form-control" name="nota_interna" value="<?=h($post['nota_interna'])?>"></div></div>
        <div class="col-md-6"><div class="form-group"><label>Observação da Versão</label><input class="form-control" name="versao_obs" value="<?=h($post['versao_obs'])?>"></div></div>
      </div>
    </div>

    <button class="btn btn-primary">Salvar</button>
    <a class="btn btn-default" href="<?= BASE_URL.'/pages/media_listar.php'?>">Voltar</a>
  </form>
</div></div>

<link rel="stylesheet" href="<?= BASE_URL ?>/system/includes/assets/summernote/summernote.css">
<script src="<?= BASE_URL ?>/system/includes/assets/summernote/summernote.min.js"></script>
<script src="<?= BASE_URL ?>/system/includes/assets/summernote/lang/summernote-pt-BR.min.js"></script>
<script>
$(function(){
  $('textarea.js-editor').summernote({
    lang:'pt-BR', height:240, styleTags:['p','blockquote','pre','h1','h2','h3','h4'],
    toolbar:[['style',['style']],['font',['bold','italic','underline','clear']],['para',['ul','ol','paragraph']],['insert',['link','table','hr']],['view',['fullscreen','codeview','help']]]
  });
  $('#addEmbed').on('click', function(){
    var row=$('.embed-row').first().clone();
    row.find('input').val(''); row.find('select')[0].selectedIndex=0;
    $('#embeds').append(row);
  });
});

// Gravador de áudio → injeta no <input type="file" multiple>
(function(){
  if(!('MediaRecorder' in window)) return;
  let mr, chunks=[];
  const rec=document.getElementById('recBtn'), stop=document.getElementById('stopBtn');
  rec.onclick=async ()=>{
    try{
      const s=await navigator.mediaDevices.getUserMedia({audio:true});
      mr=new MediaRecorder(s); chunks=[];
      mr.ondataavailable=e=>chunks.push(e.data);
      mr.onstop=()=>{
        const blob=new Blob(chunks,{type:'audio/webm'});
        const file=new File([blob],'gravacao-'+Date.now()+'.webm',{type:'audio/webm'});
        const dt=new DataTransfer(); const inp=document.querySelector('input[name="files[]"]');
        if(inp.files.length){ for(let f of inp.files) dt.items.add(f); }
        dt.items.add(file); inp.files=dt.files;
      };
      mr.start(); rec.disabled=true; stop.disabled=false;
    }catch(e){ alert('Não foi possível acessar o microfone.'); }
  };
  stop.onclick=()=>{ if(mr && mr.state!=='inactive'){ mr.stop(); rec.disabled=false; stop.disabled=true; } };
})();
</script>

<?php include_once ROOT_PATH.'/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'/system/includes/footer.php'; ?>
