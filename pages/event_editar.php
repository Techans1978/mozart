<?php
// pages/event_editar.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function token(){ if(empty($_SESSION['csrf_evt_edit'])) $_SESSION['csrf_evt_edit']=bin2hex(random_bytes(16)); return $_SESSION['csrf_evt_edit']; }
function check_token($t){ return !empty($_SESSION['csrf_evt_edit']) && hash_equals($_SESSION['csrf_evt_edit'], (string)$t); }

$cals=[]; if($r=$conn->query("SELECT id,titulo FROM event_calendars WHERE published=1 ORDER BY ordering,titulo")) while($x=$r->fetch_assoc()) $cals[]=$x;
$grupos=[]; if($r=$conn->query("SELECT id,nome FROM grupos ORDER BY nome")) while($x=$r->fetch_assoc()) $grupos[]=$x;
$perfis=[]; if($r=$conn->query("SELECT id,nome FROM perfis ORDER BY nome")) while($x=$r->fetch_assoc()) $perfis[]=$x;
$roles=['bigboss','admin','gerente','usuario'];

$id=(int)($_GET['id']??0); $erros=[]; $ok=null;
$evt=['titulo'=>'','descricao_curta'=>'','local'=>'','calendario_id'=>null,'status'=>'nao_publicado',
      'start_at'=>date('Y-m-d\T09:00'), 'end_at'=>date('Y-m-d\T10:00'), 'dia_todo'=>0,
      'rrule'=>'', 'exdates_json'=>'[]',
      'publish_up'=>'', 'publish_down'=>'', 'featured'=>0, 'featured_until'=>'',
      'acesso_publico'=>1, 'nota_interna'=>''];
$sel_groups=[]; $sel_roles=[]; $sel_perfis=[];

if($id>0){
  $st=$conn->prepare("SELECT * FROM event_items WHERE id=? LIMIT 1");
  $st->bind_param('i',$id); $st->execute(); $res=$st->get_result(); $row=$res->fetch_assoc(); $st->close();
  if(!$row) die('Evento não encontrado.');
  $evt=array_merge($evt,$row);
  $evt['start_at']= date('Y-m-d\TH:i', strtotime($evt['start_at']));
  $evt['end_at']  = $evt['end_at']?date('Y-m-d\TH:i', strtotime($evt['end_at'])):'';
  $evt['publish_up']= $evt['publish_up']?date('Y-m-d\TH:i', strtotime($evt['publish_up'])):'';
  $evt['publish_down']= $evt['publish_down']?date('Y-m-d\TH:i', strtotime($evt['publish_down'])):'';
  $evt['featured_until']= $evt['featured_until']?date('Y-m-d\TH:i', strtotime($evt['featured_until'])):'';
  $rg=$conn->query("SELECT group_id FROM event_item_groups WHERE item_id={$id}"); while($x=$rg->fetch_assoc()) $sel_groups[]=(int)$x['group_id'];
  $rr=$conn->query("SELECT role FROM event_item_roles WHERE item_id={$id}"); while($x=$rr->fetch_assoc()) $sel_roles[]=$x['role'];
  $rp=$conn->query("SELECT perfil_id FROM event_item_profiles WHERE item_id={$id}"); while($x=$rp->fetch_assoc()) $sel_perfis[]=(int)$x['perfil_id'];
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!check_token($_POST['csrf']??'')) $erros[]='Token inválido.';
  $evt['titulo']         = trim($_POST['titulo'] ?? '');
  $evt['descricao_curta']= trim($_POST['descricao_curta'] ?? '');
  $evt['local']          = trim($_POST['local'] ?? '');
  $evt['calendario_id']  = (int)($_POST['calendario_id'] ?? 0) ?: null;
  $evt['status']         = $_POST['status'] ?? 'nao_publicado';
  $evt['start_at']       = $_POST['start_at'] ?? '';
  $evt['end_at']         = ($_POST['end_at'] ?? '') ?: null;
  $evt['dia_todo']       = isset($_POST['dia_todo']) ? 1 : 0;
  $evt['rrule']          = trim($_POST['rrule'] ?? '');
  $evt['exdates_json']   = trim($_POST['exdates_json'] ?? '[]');
  $evt['publish_up']     = ($_POST['publish_up']??'') ?: null;
  $evt['publish_down']   = ($_POST['publish_down']??'') ?: null;
  $evt['featured']       = isset($_POST['featured']) ? 1 : 0;
  $evt['featured_until'] = ($_POST['featured_until']??'') ?: null;
  $evt['acesso_publico'] = isset($_POST['acesso_publico']) ? 1 : 0;
  $evt['nota_interna']   = trim($_POST['nota_interna'] ?? '');

  $sel_groups = array_map('intval', $_POST['groups'] ?? []);
  $sel_roles  = array_map('trim',   $_POST['roles'] ?? []);
  $sel_perfis = array_map('intval', $_POST['perfis'] ?? []);

  if($evt['titulo']==='') $erros[]='Título é obrigatório.';
  if($evt['start_at']==='') $erros[]='Início é obrigatório.';

  // valida exdates JSON
  if($evt['exdates_json']!==''){
    json_decode($evt['exdates_json'], true);
    if(json_last_error()!==JSON_ERROR_NONE) $erros[]='EXDATEs inválidos (JSON).';
  }

  if(!$erros){
    $uid=(int)($_SESSION['user_id']??0);
    if($id>0){
      $st=$conn->prepare("UPDATE event_items SET
        titulo=?,descricao_curta=?,local=?,calendario_id=?,status=?,start_at=?,end_at=?,dia_todo=?,
        rrule=?,exdates_json=?,publish_up=?,publish_down=?,featured=?,featured_until=?,acesso_publico=?,nota_interna=?,
        modified_at=NOW(),modified_by=?
        WHERE id=? LIMIT 1");
      $st->bind_param('sssisssssssiiisiii',
        $evt['titulo'],$evt['descricao_curta'],$evt['local'],$evt['calendario_id'],$evt['status'],
        $evt['start_at'],$evt['end_at'],$evt['dia_todo'],$evt['rrule'],$evt['exdates_json'],
        $evt['publish_up'],$evt['publish_down'],$evt['featured'],$evt['featured_until'],$evt['acesso_publico'],$evt['nota_interna'],$uid,$id);
      $ok=$st->execute(); $st->close(); $item_id=$id;
    }else{
      $st=$conn->prepare("INSERT INTO event_items
       (titulo,descricao_curta,local,calendario_id,status,start_at,end_at,dia_todo,rrule,exdates_json,
        publish_up,publish_down,featured,featured_until,acesso_publico,nota_interna,created_at,created_by)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?)");
      $st->bind_param('sssisssssssiiisi',
        $evt['titulo'],$evt['descricao_curta'],$evt['local'],$evt['calendario_id'],$evt['status'],
        $evt['start_at'],$evt['end_at'],$evt['dia_todo'],$evt['rrule'],$evt['exdates_json'],
        $evt['publish_up'],$evt['publish_down'],$evt['featured'],$evt['featured_until'],$evt['acesso_publico'],$evt['nota_interna'],$uid);
      $ok=$st->execute(); $item_id=$ok?$conn->insert_id:0; $st->close(); $id=$item_id;
    }

    if(!empty($ok) && $item_id>0){
      $conn->query("DELETE FROM event_item_groups WHERE item_id={$item_id}");
      if($sel_groups){ $ins=$conn->prepare("INSERT INTO event_item_groups(item_id,group_id) VALUES(?,?)"); foreach($sel_groups as $g){ $ins->bind_param('ii',$item_id,$g); $ins->execute(); } $ins->close(); }
      $conn->query("DELETE FROM event_item_roles WHERE item_id={$item_id}");
      if($sel_roles){ $ins=$conn->prepare("INSERT INTO event_item_roles(item_id,role) VALUES(?,?)"); foreach($sel_roles as $r){ $ins->bind_param('is',$item_id,$r); $ins->execute(); } $ins->close(); }
      $conn->query("DELETE FROM event_item_profiles WHERE item_id={$item_id}");
      if($sel_perfis){ $ins=$conn->prepare("INSERT INTO event_item_profiles(item_id,perfil_id) VALUES(?,?)"); foreach($sel_perfis as $p){ $ins->bind_param('ii',$item_id,$p); $ins->execute(); } $ins->close(); }
      $ok='Evento salvo com sucesso.';
    }else{
      $erros[]='Falha ao salvar.';
    }
  }
}

include_once ROOT_PATH.'/system/includes/head.php';
include_once ROOT_PATH.'/system/includes/navbar.php';
?>
<style>.card-pane{background:var(--surface);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);padding:16px;margin-bottom:12px}.smallmuted{font-size:12px;color:var(--text-muted)}</style>
<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= $id?'Editar Evento':'Novo Evento' ?></h1></div></div>

  <?php if($erros): ?><div class="alert alert-danger"><strong>Verifique:</strong><ul style="margin:0;padding-left:18px"><?php foreach($erros as $e):?><li><?=h($e)?></li><?php endforeach;?></ul></div>
  <?php elseif($ok): ?><div class="alert alert-success"><?=h($ok)?></div><?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf" value="<?=h(token())?>">
    <div class="card-pane">
      <div class="row">
        <div class="col-md-7">
          <div class="form-group"><label>Título</label><input class="form-control" name="titulo" value="<?=h($evt['titulo'])?>" required></div>
          <div class="form-group"><label>Descrição curta</label><textarea class="form-control" name="descricao_curta" rows="3"><?=h($evt['descricao_curta'])?></textarea></div>
          <div class="form-group"><label>Local</label><input class="form-control" name="local" value="<?=h($evt['local'])?>"></div>
        </div>
        <div class="col-md-5">
          <div class="form-group">
            <label>Calendário</label>
            <select class="form-control" name="calendario_id">
              <option value="">— Geral —</option>
              <?php foreach($cals as $c): ?><option value="<?=$c['id']?>" <?=$evt['calendario_id']==$c['id']?'selected':''?>><?=h($c['titulo'])?></option><?php endforeach;?>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select class="form-control" name="status">
              <?php foreach(['publicado'=>'Publicado','nao_publicado'=>'Não Publicado','arquivado'=>'Arquivado','lixeira'=>'Lixeira','pendente'=>'Pendente','reprovado'=>'Reprovado'] as $k=>$v):?>
                <option value="<?=$k?>" <?=$evt['status']===$k?'selected':''?>><?=$v?></option>
              <?php endforeach;?>
            </select>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-sm-6"><div class="form-group"><label>Início</label><input class="form-control" type="datetime-local" name="start_at" value="<?=h($evt['start_at'])?>" required></div></div>
        <div class="col-sm-6"><div class="form-group"><label>Fim</label><input class="form-control" type="datetime-local" name="end_at" value="<?=h($evt['end_at'])?>"></div></div>
      </div>
      <div class="checkbox"><label><input type="checkbox" name="dia_todo" <?=$evt['dia_todo']?'checked':''?>> Evento de dia todo</label></div>
    </div>

    <div class="card-pane">
      <h4>Recorrência</h4>
      <div class="form-group"><label>RRULE (iCal)</label><input class="form-control" name="rrule" id="rrule" value="<?=h($evt['rrule'])?>" placeholder="FREQ=YEARLY;INTERVAL=1;BYMONTH=9;BYMONTHDAY=7"></div>
      <div class="smallmuted">Suportado: FREQ (DAILY|WEEKLY|MONTHLY|YEARLY), INTERVAL, BYDAY (MO,TU,WE,TH,FR,SA,SU), BYMONTHDAY, BYMONTH, UNTIL (YYYY-MM-DD).</div>
      <div class="form-group"><label>EXDATEs (JSON array)</label><input class="form-control" name="exdates_json" value="<?=h($evt['exdates_json'])?>" placeholder='["2025-05-01","2025-05-15T09:00:00"]'></div>

      <div class="well" style="padding:10px">
        <strong>Assistente rápido:</strong>
        <div class="form-inline" style="margin-top:8px">
          <select id="freq" class="form-control">
            <option value="">— FREQ —</option>
            <option value="DAILY">Diário</option>
            <option value="WEEKLY">Semanal</option>
            <option value="MONTHLY">Mensal</option>
            <option value="YEARLY">Anual</option>
          </select>
          <input id="interval" type="number" class="form-control" placeholder="INTERVAL" style="width:110px;margin-left:6px">
          <input id="bymonth" type="text" class="form-control" placeholder="BYMONTH (1,12)" style="width:140px;margin-left:6px">
          <input id="bymday" type="text" class="form-control" placeholder="BYMONTHDAY (1..31)" style="width:160px;margin-left:6px">
          <input id="byday" type="text" class="form-control" placeholder="BYDAY (MO,TU,..)" style="width:180px;margin-left:6px">
          <input id="until" type="date" class="form-control" style="margin-left:6px">
          <button type="button" class="btn btn-default" onclick="montaRR()">Montar</button>
        </div>
      </div>
    </div>

    <div class="card-pane">
      <div class="row">
        <div class="col-md-6">
          <h4>Publicação</h4>
          <div class="form-group"><label>Início (card)</label><input class="form-control" type="datetime-local" name="publish_up" value="<?=h($evt['publish_up'])?>"></div>
          <div class="form-group"><label>Término (card)</label><input class="form-control" type="datetime-local" name="publish_down" value="<?=h($evt['publish_down'])?>"></div>
          <div class="checkbox"><label><input type="checkbox" name="featured" <?=$evt['featured']?'checked':''?>> Destaque no Feed</label></div>
          <div class="form-group"><label>Finalizar Destaque</label><input class="form-control" type="datetime-local" name="featured_until" value="<?=h($evt['featured_until'])?>"></div>
        </div>
        <div class="col-md-6">
          <h4>Acesso</h4>
          <div class="checkbox"><label><input type="checkbox" name="acesso_publico" <?=$evt['acesso_publico']?'checked':''?>> Acesso público (ignora regras)</label></div>
          <div class="form-group"><label>Nota interna</label><input class="form-control" name="nota_interna" value="<?=h($evt['nota_interna'])?>"></div>
        </div>
      </div>

      <div class="row" style="margin-top:8px">
        <div class="col-md-4">
          <h4>Grupos</h4>
          <select class="form-control" name="groups[]" multiple size="6">
            <?php foreach($grupos as $g): ?>
              <option value="<?=$g['id']?>" <?= in_array((int)$g['id'],$sel_groups,true)?'selected':'' ?>><?=h($g['nome'])?></option>
            <?php endforeach;?>
          </select>
        </div>
        <div class="col-md-4">
          <h4>Perfis</h4>
          <select class="form-control" name="perfis[]" multiple size="6">
            <?php foreach($perfis as $p): ?>
              <option value="<?=$p['id']?>" <?= in_array((int)$p['id'],$sel_perfis,true)?'selected':'' ?>><?=h($p['nome'])?></option>
            <?php endforeach;?>
          </select>
        </div>
        <div class="col-md-4">
          <h4>Níveis</h4>
          <select class="form-control" name="roles[]" multiple size="6">
            <?php foreach($roles as $r): ?>
              <option value="<?=$r?>" <?= in_array($r,$sel_roles,true)?'selected':'' ?>><?=h(ucfirst($r))?></option>
            <?php endforeach;?>
          </select>
        </div>
      </div>
    </div>

    <button class="btn btn-primary">Salvar</button>
    <a class="btn btn-default" href="<?= BASE_URL.'/pages/event_listar.php'?>">Voltar</a>
  </form>
</div></div>
<script>
function montaRR(){
  var out=[], v='';
  v=document.getElementById('freq').value; if(v) out.push('FREQ='+v);
  v=document.getElementById('interval').value; if(v) out.push('INTERVAL='+v);
  v=document.getElementById('bymonth').value; if(v) out.push('BYMONTH='+v);
  v=document.getElementById('bymday').value; if(v) out.push('BYMONTHDAY='+v);
  v=document.getElementById('byday').value; if(v) out.push('BYDAY='+v);
  v=document.getElementById('until').value; if(v) out.push('UNTIL='+v);
  document.getElementById('rrule').value = out.join(';');
}
</script>
<?php include_once ROOT_PATH.'/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'/system/includes/footer.php'; ?>
