<?php
// pages/docs_editar.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function token(){ if(empty($_SESSION['csrf_docs_edit'])) $_SESSION['csrf_docs_edit']=bin2hex(random_bytes(16)); return $_SESSION['csrf_docs_edit']; }
function check_token($t){ return !empty($_SESSION['csrf_docs_edit']) && hash_equals($_SESSION['csrf_docs_edit'], (string)$t); }

$UPLOAD_DIR = ROOT_PATH.'uploads/docs/';

function ensure_dir($base){
  if(!is_dir($base)) mkdir($base,0775,true);
  $y=date('Y'); $m=date('m');
  if(!is_dir($base.$y)) mkdir($base.$y,0775,true);
  if(!is_dir($base.$y.'/'.$m)) mkdir($base.$y.'/'.$m,0775,true);
  return [$base.$y.'/'.$m.'/', $y.'/'.$m.'/'];
}

$categorias=[]; if($r=$conn->query("SELECT id,titulo FROM doc_categories WHERE published=1 ORDER BY titulo")) while($x=$r->fetch_assoc()) $categorias[]=$x;
$grupos=[];     if($r=$conn->query("SELECT id,nome FROM grupos ORDER BY nome")) while($x=$r->fetch_assoc()) $grupos[]=$x;
$perfis_db=[];  if($r=$conn->query("SELECT id,nome FROM perfis ORDER BY nome")) while($x=$r->fetch_assoc()) $perfis_db[]=$x;
$roles=['bigboss','admin','gerente','usuario'];

$id=(int)($_GET['id']??0); $erros=[]; $ok=null;

$item=['titulo'=>'','apelido'=>'','descricao_curta'=>'','status'=>'nao_publicado','categoria_id'=>null,
       'show_in_feed'=>1,'meta_desc'=>'','meta_keywords'=>'','meta_robots'=>'',
       'publish_up'=>'','publish_down'=>'','featured'=>0,'featured_until'=>'',
       'acesso_publico'=>0,'nota_interna'=>'','versao_obs'=>''];
$sel_groups=[]; $sel_roles=[]; $sel_perfis=[]; $files=[];

if($id>0){
  $st=$conn->prepare("SELECT * FROM doc_items WHERE id=? LIMIT 1");
  $st->bind_param('i',$id); $st->execute(); $res=$st->get_result(); $row=$res->fetch_assoc(); $st->close();
  if(!$row) die('Documento não encontrado.');
  $item=array_merge($item,$row);
  $rf=$conn->query("SELECT * FROM doc_files WHERE item_id={$id} ORDER BY ordering,id"); while($x=$rf->fetch_assoc()) $files[]=$x;
  $rg=$conn->query("SELECT group_id FROM doc_item_groups WHERE item_id={$id}"); while($x=$rg->fetch_assoc()) $sel_groups[]=(int)$x['group_id'];
  $rr=$conn->query("SELECT role FROM doc_item_roles WHERE item_id={$id}"); while($x=$rr->fetch_assoc()) $sel_roles[]=$x['role'];
  $rp=$conn->query("SELECT perfil_id FROM doc_item_profiles WHERE item_id={$id}"); while($x=$rp->fetch_assoc()) $sel_perfis[]=(int)$x['perfil_id'];
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!check_token($_POST['csrf']??'')) $erros[]='Token inválido.';

  $item['titulo']         = trim($_POST['titulo'] ?? '');
  $item['apelido']        = trim($_POST['apelido'] ?? '');
  $item['descricao_curta']= trim($_POST['descricao_curta'] ?? '');
  $item['status']         = $_POST['status'] ?? 'nao_publicado';
  $item['categoria_id']   = (int)($_POST['categoria_id'] ?? 0) ?: null;
  $item['show_in_feed']   = isset($_POST['show_in_feed']) ? 1 : 0;
  $item['meta_desc']      = trim($_POST['meta_desc'] ?? '');
  $item['meta_keywords']  = trim($_POST['meta_keywords'] ?? '');
  $item['meta_robots']    = trim($_POST['meta_robots'] ?? '');
  $item['publish_up']     = ($_POST['publish_up']??'') ?: null;
  $item['publish_down']   = ($_POST['publish_down']??'') ?: null;
  $item['featured']       = isset($_POST['featured']) ? 1 : 0;
  $item['featured_until'] = ($_POST['featured_until']??'') ?: null;
  $item['acesso_publico'] = isset($_POST['acesso_publico']) ? 1 : 0;
  $item['nota_interna']   = trim($_POST['nota_interna'] ?? '');
  $item['versao_obs']     = trim($_POST['versao_obs'] ?? '');

  $sel_groups = array_map('intval', $_POST['groups'] ?? []);
  $sel_roles  = array_map('trim',   $_POST['roles'] ?? []);
  $sel_perfis = array_map('intval', $_POST['perfis'] ?? []);

  if($item['titulo']==='')  $erros[]='Título é obrigatório.';
  if($item['apelido']==='') $erros[]='Apelido (slug) é obrigatório.';

  if(!$erros){
    $uid=(int)($_SESSION['user_id']??0);
    if($id>0){
      $st=$conn->prepare("UPDATE doc_items SET
        titulo=?, apelido=?, descricao_curta=?, status=?, categoria_id=?, show_in_feed=?, meta_desc=?, meta_keywords=?, meta_robots=?,
        publish_up=?, publish_down=?, featured=?, featured_until=?, modified_at=NOW(), modified_by=?, acesso_publico=?, nota_interna=?, versao_obs=?
        WHERE id=? LIMIT 1");
      $st->bind_param('ssssiiissssiiiissi',
        $item['titulo'],$item['apelido'],$item['descricao_curta'],$item['status'],$item['categoria_id'],$item['show_in_feed'],
        $item['meta_desc'],$item['meta_keywords'],$item['meta_robots'],$item['publish_up'],$item['publish_down'],
        $item['featured'],$item['featured_until'],$uid,$item['acesso_publico'],$item['nota_interna'],$item['versao_obs'],$id);
      $ok=$st->execute(); $st->close(); $item_id=$id;
    }else{
      $st=$conn->prepare("INSERT INTO doc_items
        (titulo,apelido,descricao_curta,status,categoria_id,show_in_feed,meta_desc,meta_keywords,meta_robots,
         publish_up,publish_down,featured,featured_until,created_at,created_by,acesso_publico,nota_interna,versao_obs)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?)");
      $st->bind_param('ssssiiissssiiiss',
        $item['titulo'],$item['apelido'],$item['descricao_curta'],$item['status'],$item['categoria_id'],$item['show_in_feed'],
        $item['meta_desc'],$item['meta_keywords'],$item['meta_robots'],$item['publish_up'],$item['publish_down'],
        $item['featured'],$item['featured_until'],$uid,$item['acesso_publico'],$item['nota_interna'],$item['versao_obs']);
      $ok=$st->execute(); $item_id=$ok?$conn->insert_id:0; $st->close(); $id=$item_id;
    }

    if(!empty($ok) && $item_id>0){
      /* pivôs */
      $conn->query("DELETE FROM doc_item_groups WHERE item_id={$item_id}");
      if($sel_groups){ $ins=$conn->prepare("INSERT INTO doc_item_groups(item_id,group_id) VALUES(?,?)"); foreach($sel_groups as $g){ $ins->bind_param('ii',$item_id,$g); $ins->execute(); } $ins->close(); }
      $conn->query("DELETE FROM doc_item_roles WHERE item_id={$item_id}");
      if($sel_roles){ $ins=$conn->prepare("INSERT INTO doc_item_roles(item_id,role) VALUES(?,?)"); foreach($sel_roles as $r){ $ins->bind_param('is',$item_id,$r); $ins->execute(); } $ins->close(); }
      $conn->query("DELETE FROM doc_item_profiles WHERE item_id={$item_id}");
      if($sel_perfis){ $ins=$conn->prepare("INSERT INTO doc_item_profiles(item_id,perfil_id) VALUES(?,?)"); foreach($sel_perfis as $p){ $ins->bind_param('ii',$item_id,$p); $ins->execute(); } $ins->close(); }

      /* uploads */
      if(!empty($_FILES['files']['name'][0])){
        list($dir_abs,$dir_rel)=ensure_dir($UPLOAD_DIR);
        $total=count($_FILES['files']['name']);
        for($i=0;$i<$total;$i++){
          if($_FILES['files']['error'][$i]!==UPLOAD_ERR_OK) continue;
          $name=basename($_FILES['files']['name'][$i]); $tmp=$_FILES['files']['tmp_name'][$i];
          $mime=mime_content_type($tmp) ?: $_FILES['files']['type'][$i];
          $ext=pathinfo($name, PATHINFO_EXTENSION);
          $safe=bin2hex(random_bytes(8)).'.'.$ext; $dest=$dir_abs.$safe;
          if(move_uploaded_file($tmp,$dest)){
            $size=filesize($dest);
            $file_rel='/uploads/docs/'.$dir_rel.$safe;
            $ins=$conn->prepare("INSERT INTO doc_files(item_id,origem,file_path,file_name,mime,size_bytes,ordering,created_by)
                                 VALUES(?,?,?,?,?,?,(SELECT IFNULL(MAX(ordering),0)+1 FROM doc_files WHERE item_id=?),?)");
            $ins->bind_param('isssssii',$item_id,'upload',$file_rel,$name,$mime,$size,$item_id,$uid);
            $ins->execute(); $ins->close();
          }
        }
      }

      /* links externos */
      if(!empty($_POST['ext_url'])){
        $urls=$_POST['ext_url']; $caps=$_POST['ext_legenda']??[];
        foreach($urls as $k=>$url){
          $u=trim($url); if($u==='') continue; $cap=trim($caps[$k]??'');
          $ins=$conn->prepare("INSERT INTO doc_files(item_id,origem,external_url,legenda,ordering,created_by)
                               VALUES(?,?,?,?,(SELECT IFNULL(MAX(ordering),0)+1 FROM doc_files WHERE item_id=?),?)");
          $ins->bind_param('isssii',$item_id,'externo',$u,$cap,$item_id,$uid);
          $ins->execute(); $ins->close();
        }
      }

      /* atualizar/remoção de files */
      if(!empty($_POST['file_order'])||!empty($_POST['file_delete'])||!empty($_POST['file_legenda'])){
        foreach($_POST['file_order'] ?? [] as $fid=>$ord){
          $fid=(int)$fid; $ord=(int)$ord;
          if(isset($_POST['file_delete'][$fid])){
            $res=$conn->query("SELECT origem,file_path FROM doc_files WHERE id={$fid} AND item_id={$item_id} LIMIT 1");
            if($res && $row=$res->fetch_assoc()){
              if($row['origem']==='upload' && !empty($row['file_path'])){
                $abs=ROOT_PATH.ltrim($row['file_path'],'/'); if(is_file($abs)) @unlink($abs);
              }
            }
            $conn->query("DELETE FROM doc_files WHERE id={$fid} AND item_id={$item_id} LIMIT 1");
          }else{
            $leg=trim($_POST['file_legenda'][$fid] ?? '');
            $st=$conn->prepare("UPDATE doc_files SET ordering=?, legenda=? WHERE id=? AND item_id=?");
            $st->bind_param('isii',$ord,$leg,$fid,$item_id); $st->execute(); $st->close();
          }
        }
      }

      $ok='Documento salvo com sucesso.';
      // recarrega
      $files=[]; $rf=$conn->query("SELECT * FROM doc_files WHERE item_id={$item_id} ORDER BY ordering,id"); while($x=$rf->fetch_assoc()) $files[]=$x;
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
  <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= $id?'Editar Documento':'Novo Documento' ?></h1></div></div>

  <?php if($erros): ?><div class="alert alert-danger"><strong>Verifique:</strong><ul style="margin:0;padding-left:18px"><?php foreach($erros as $e):?><li><?=h($e)?></li><?php endforeach;?></ul></div>
  <?php elseif($ok): ?><div class="alert alert-success"><?=h($ok)?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=h(token())?>">

    <div class="card-pane">
      <div class="row">
        <div class="col-md-8">
          <div class="form-group"><label>Título</label><input class="form-control" name="titulo" value="<?=h($item['titulo'])?>" required></div>
          <div class="form-group"><label>Apelido (slug)</label><input class="form-control" name="apelido" value="<?=h($item['apelido'])?>" required></div>
          <div class="form-group"><label>Descrição curta</label><textarea class="form-control" name="descricao_curta" rows="3"><?=h($item['descricao_curta'])?></textarea></div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label>Status</label>
            <select class="form-control" name="status">
              <?php foreach(['publicado'=>'Publicado','nao_publicado'=>'Não Publicado','arquivado'=>'Arquivado','lixeira'=>'Lixeira','pendente'=>'Pendente','reprovado'=>'Reprovado'] as $k=>$v):?>
                <option value="<?=$k?>" <?=$item['status']===$k?'selected':''?>><?=$v?></option>
              <?php endforeach;?>
            </select>
          </div>
          <div class="form-group">
            <label>Categoria</label>
            <select class="form-control" name="categoria_id">
              <option value="">-- Sem categoria --</option>
              <?php foreach($categorias as $c): ?><option value="<?=$c['id']?>" <?=$item['categoria_id']==$c['id']?'selected':''?>><?=h($c['titulo'])?></option><?php endforeach;?>
            </select>
          </div>
          <div class="checkbox"><label><input type="checkbox" name="show_in_feed" <?=$item['show_in_feed']?'checked':''?>> Exibir no Feed</label></div>
          <div class="checkbox"><label><input type="checkbox" name="acesso_publico" <?=$item['acesso_publico']?'checked':''?>> Acesso público (sem login/regras)</label></div>
        </div>
      </div>
    </div>

    <div class="card-pane">
      <h4>Arquivos do Documento (pasta/conjunto)</h4>
      <div class="form-group">
        <label>Adicionar arquivos (upload)</label>
        <input type="file" name="files[]" multiple class="form-control">
        <div class="smallmuted">Qualquer tipo suportado pelo servidor. Tamanho conforme PHP.</div>
      </div>
      <div class="form-group">
        <label>Adicionar links externos</label>
        <div id="exts">
          <div class="row ext-row" style="margin-bottom:8px">
            <div class="col-sm-8"><input class="form-control" name="ext_url[]" placeholder="URL do arquivo"></div>
            <div class="col-sm-4"><input class="form-control" name="ext_legenda[]" placeholder="Legenda (opcional)"></div>
          </div>
        </div>
        <button type="button" class="btn btn-default" id="addExt">+ Outra linha</button>
      </div>

      <?php if($files): ?>
      <h5>Já adicionados</h5>
      <div class="table-responsive">
        <table class="table table-striped">
          <thead><tr><th style="width:60px">#</th><th>Origem</th><th>Arquivo/URL</th><th>Legenda</th><th style="width:90px">Ordem</th><th style="width:100px">Ações</th></tr></thead>
          <tbody>
            <?php foreach($files as $f): ?>
              <tr>
                <td><?=$f['id']?></td>
                <td><?=$f['origem']?></td>
                <td>
                  <?php if($f['origem']==='upload'): ?>
                    <a href="<?= BASE_URL.'/pages/docs_gettoken.php?item=<?=$id?>&file=<?=$f['id']?>' ?>" target="_blank"><?=h($f['file_name'])?></a>
                  <?php else: ?>
                    <a href="<?=h($f['external_url'])?>" target="_blank"><?=h($f['external_url'])?></a>
                  <?php endif; ?>
                </td>
                <td><input class="form-control input-sm" name="file_legenda[<?=$f['id']?>]" value="<?=h($f['legenda'])?>"></td>
                <td><input type="number" class="form-control input-sm" name="file_order[<?=$f['id']?>]" value="<?= (int)$f['ordering'] ?>"></td>
                <td><label><input type="checkbox" name="file_delete[<?=$f['id']?>]"> Remover</label></td>
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
          <div class="form-group"><label>Meta Descrição</label><input class="form-control" name="meta_desc" value="<?=h($item['meta_desc'])?>"></div>
          <div class="form-group"><label>Palavras-chave</label><input class="form-control" name="meta_keywords" value="<?=h($item['meta_keywords'])?>"></div>
          <div class="form-group"><label>Robôs</label><input class="form-control" name="meta_robots" value="<?=h($item['meta_robots'])?>" placeholder="noindex,nofollow ou index,follow"></div>
        </div>
        <div class="col-md-6">
          <h4>Publicação</h4>
          <div class="form-group"><label>Início</label><input class="form-control" type="datetime-local" name="publish_up" value="<?= $item['publish_up']?date('Y-m-d\TH:i', strtotime($item['publish_up'])):'' ?>"></div>
          <div class="form-group"><label>Término</label><input class="form-control" type="datetime-local" name="publish_down" value="<?= $item['publish_down']?date('Y-m-d\TH:i', strtotime($item['publish_down'])):'' ?>"></div>
          <div class="form-group"><label><input type="checkbox" name="featured" <?=$item['featured']?'checked':''?>> Destaque no Feed</label></div>
          <div class="form-group"><label>Finalizar Destaque</label><input class="form-control" type="datetime-local" name="featured_until" value="<?= $item['featured_until']?date('Y-m-d\TH:i', strtotime($item['featured_until'])):'' ?>"></div>
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
        </div>
        <div class="col-md-4">
          <h4>Acesso por Perfis</h4>
          <select class="form-control" name="perfis[]" multiple size="6">
            <?php foreach($perfis_db as $p): ?>
              <option value="<?=$p['id']?>" <?= in_array((int)$p['id'],$sel_perfis,true)?'selected':'' ?>><?=h($p['nome'])?></option>
            <?php endforeach;?>
          </select>
        </div>
        <div class="col-md-4">
          <h4>Acesso por Nível</h4>
          <select class="form-control" name="roles[]" multiple size="6">
            <?php foreach($roles as $r): ?>
              <option value="<?=$r?>" <?= in_array($r,$sel_roles,true)?'selected':'' ?>><?=h(ucfirst($r))?></option>
            <?php endforeach;?>
          </select>
        </div>
      </div>
    </div>

    <div class="card-pane">
      <div class="row">
        <div class="col-md-6"><div class="form-group"><label>Nota (interna)</label><input class="form-control" name="nota_interna" value="<?=h($item['nota_interna'])?>"></div></div>
        <div class="col-md-6"><div class="form-group"><label>Observação da Versão</label><input class="form-control" name="versao_obs" value="<?=h($item['versao_obs'])?>"></div></div>
      </div>
    </div>

    <button class="btn btn-primary">Salvar</button>
    <a class="btn btn-default" href="<?= BASE_URL.'/pages/docs_listar.php'?>">Voltar</a>
  </form>
</div></div>

<script>
document.getElementById('addExt').addEventListener('click', function(){
  var row=document.querySelector('.ext-row').cloneNode(true);
  row.querySelectorAll('input').forEach(i=>i.value='');
  document.getElementById('exts').appendChild(row);
});
</script>

<?php include_once ROOT_PATH.'/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'/system/includes/footer.php'; ?>
