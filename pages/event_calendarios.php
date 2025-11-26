<?php
// pages/event_calendarios.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function tok(){ if(empty($_SESSION['csrf_evtcal'])) $_SESSION['csrf_evtcal']=bin2hex(random_bytes(16)); return $_SESSION['csrf_evtcal']; }
function chk($t){ return !empty($_SESSION['csrf_evtcal']) && hash_equals($_SESSION['csrf_evtcal'], (string)$t); }

$err=null; $ok=null;

if($_SERVER['REQUEST_METHOD']==='POST'){
  $csrf=$_POST['csrf']??''; if(!chk($csrf)){ $err='Token inválido.'; }
  else{
    $acao=$_POST['acao']??'';
    if($acao==='salvar'){
      $id=(int)($_POST['id']??0);
      $titulo=trim($_POST['titulo']??'');
      $apelido=trim($_POST['apelido']??'');
      $cor=trim($_POST['cor_hex']??'#0ea5e9');
      $published=isset($_POST['published'])?1:0;
      $ordering=(int)($_POST['ordering']??0);
      if($titulo===''||$apelido===''){ $err='Título e Apelido são obrigatórios.'; }
      else{
        if($id>0){
          $st=$conn->prepare("UPDATE event_calendars SET titulo=?,apelido=?,cor_hex=?,published=?,ordering=?,updated_at=NOW(),updated_by=? WHERE id=? LIMIT 1");
          $uid=(int)($_SESSION['user_id']??0); $st->bind_param('sssiiii',$titulo,$apelido,$cor,$published,$ordering,$uid,$id);
          $ok=$st->execute(); $st->close(); if(!$ok) $err='Falha ao atualizar.';
        }else{
          $st=$conn->prepare("INSERT INTO event_calendars(titulo,apelido,cor_hex,published,ordering,created_at,created_by) VALUES(?,?,?,?,?,NOW(),?)");
          $uid=(int)($_SESSION['user_id']??0); $st->bind_param('sssiii',$titulo,$apelido,$cor,$published,$ordering,$uid);
          $ok=$st->execute(); $st->close(); if(!$ok) $err='Falha ao inserir.';
        }
      }
    }
    if($acao==='excluir'){
      $id=(int)($_POST['id']??0);
      if($id>0){
        $st=$conn->prepare("DELETE FROM event_calendars WHERE id=? LIMIT 1");
        $st->bind_param('i',$id); $ok=$st->execute(); $st->close();
        if(!$ok) $err='Não foi possível excluir (existem eventos vinculados?).';
      }
    }
  }
}

$rows=[]; $res=$conn->query("SELECT * FROM event_calendars ORDER BY ordering,titulo");
while($x=$res->fetch_assoc()) $rows[]=$x;

include_once ROOT_PATH.'/system/includes/head.php';
include_once ROOT_PATH.'/system/includes/navbar.php';
?>
<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header">Calendários de Eventos</h1></div></div>

  <?php if($err): ?><div class="alert alert-danger"><?=h($err)?></div><?php endif;?>
  <?php if($ok && !$err): ?><div class="alert alert-success">Operação realizada com sucesso.</div><?php endif;?>

  <div class="row">
    <div class="col-md-5">
      <form method="post" class="panel panel-default" style="padding:12px">
        <input type="hidden" name="csrf" value="<?=h(tok())?>">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" id="cal_id" value="">
        <div class="form-group"><label>Título</label><input class="form-control" name="titulo" id="cal_titulo" required></div>
        <div class="form-group"><label>Apelido (slug)</label><input class="form-control" name="apelido" id="cal_apelido" required></div>
        <div class="form-group"><label>Cor (hex)</label><input class="form-control" name="cor_hex" id="cal_cor" value="#0ea5e9"></div>
        <div class="form-group"><label>Ordem</label><input type="number" class="form-control" name="ordering" id="cal_ordering" value="0"></div>
        <div class="checkbox"><label><input type="checkbox" name="published" id="cal_published" checked> Publicado</label></div>
        <button class="btn btn-primary">Salvar</button>
      </form>
    </div>
    <div class="col-md-7">
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead><tr><th style="width:70px">ID</th><th>Título</th><th>Slug</th><th>Cor</th><th>Pub.</th><th style="width:200px">Ações</th></tr></thead>
          <tbody>
            <?php if(!$rows): ?><tr><td colspan="6" class="text-muted">Nenhum calendário.</td></tr>
            <?php else: foreach($rows as $r): ?>
              <tr>
                <td><?=$r['id']?></td>
                <td><?=h($r['titulo'])?></td>
                <td><?=h($r['apelido'])?></td>
                <td><span style="display:inline-block;width:18px;height:18px;border-radius:4px;background:<?=h($r['cor_hex'])?>;border:1px solid #ccc"></span> <?=h($r['cor_hex'])?></td>
                <td><?=$r['published']?'Sim':'Não'?></td>
                <td>
                  <button class="btn btn-xs btn-primary" onclick='fillCal(<?=json_encode($r,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>)'>Editar</button>
                  <form method="post" style="display:inline" onsubmit="return confirm('Excluir calendário #<?=$r['id']?>?');">
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
<script>
function fillCal(r){
  document.getElementById('cal_id').value=r.id;
  document.getElementById('cal_titulo').value=r.titulo;
  document.getElementById('cal_apelido').value=r.apelido;
  document.getElementById('cal_cor').value=r.cor_hex||'#0ea5e9';
  document.getElementById('cal_ordering').value=r.ordering||0;
  document.getElementById('cal_published').checked=(r.published==1);
}
</script>
<?php include_once ROOT_PATH.'/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'/system/includes/footer.php'; ?>
