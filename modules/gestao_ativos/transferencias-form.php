<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start(); proteger_pagina();
$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

$dbc->multi_query("
CREATE TABLE IF NOT EXISTS moz_transfer (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('TEMPORARIA','DEFINITIVA','EMPRESTIMO') NOT NULL,
  origem VARCHAR(200) NOT NULL,
  destino VARCHAR(200) NOT NULL,
  data_mov DATE NULL, resp_origem VARCHAR(160) NULL, resp_destino VARCHAR(160) NULL,
  transportador VARCHAR(160) NULL, motivo VARCHAR(200) NULL,
  previsao_devolucao DATE NULL,
  status ENUM('PREPARACAO','TRANSITO','CONCLUIDA','CANCELADA') NOT NULL DEFAULT 'PREPARACAO',
  checklist_envio JSON NULL, checklist_receb JSON NULL, anexos_info JSON NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS moz_transfer_item (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transfer_id BIGINT UNSIGNED NOT NULL,
  tipo ENUM('CATEGORIA','MODELO','ATIVO') NOT NULL,
  ref_id BIGINT UNSIGNED NULL, descricao VARCHAR(255) NULL,
  qtd INT UNSIGNED NOT NULL DEFAULT 1, obs VARCHAR(255) NULL,
  CONSTRAINT fk_mti_t FOREIGN KEY (transfer_id) REFERENCES moz_transfer(id) ON DELETE CASCADE,
  KEY idx_mti_trans (transfer_id), KEY idx_mti_tipo_ref (tipo, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
"); while($dbc->more_results() && $dbc->next_result()){}

$err=''; $ok='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id = isset($_POST['id'])?(int)$_POST['id']:null;
  $tipo = strtoupper($_POST['tipo']??'TEMPORARIA');
  $origem = trim($_POST['origem']??''); $destino = trim($_POST['destino']??'');
  $data_mov = $_POST['data_mov'] ?? null;
  $resp_o = trim($_POST['resp_origem']??''); $resp_d = trim($_POST['resp_destino']??'');
  $transp = trim($_POST['transportador']??''); $motivo=trim($_POST['motivo']??'');
  $prev_dev = $_POST['prev_devolucao'] ?? null;
  $status = strtoupper($_POST['status']??'PREPARACAO');

  $tipos=$_POST['it_tipo']??[]; $refs=$_POST['it_ref']??[]; $qtds=$_POST['it_qtd']??[]; $descs=$_POST['it_desc']??[]; $obs=$_POST['it_obs']??[];

  if(!$origem||!$destino){ $err='Informe origem e destino.'; }
  else{
    $dbc->begin_transaction();
    try{
      if($id){
        $st=$dbc->prepare("UPDATE moz_transfer SET tipo=?,origem=?,destino=?,data_mov=?,resp_origem=?,resp_destino=?,transportador=?,motivo=?,previsao_devolucao=?,status=? WHERE id=?");
        $st->bind_param('ssssssssssi',$tipo,$origem,$destino,$data_mov,$resp_o,$resp_d,$transp,$motivo,$prev_dev,$status,$id);
        $st->execute(); $st->close();
        $dbc->query("DELETE FROM moz_transfer_item WHERE transfer_id=".$id);
        $tid=$id;
      }else{
        $st=$dbc->prepare("INSERT INTO moz_transfer (tipo,origem,destino,data_mov,resp_origem,resp_destino,transportador,motivo,previsao_devolucao,status) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $st->bind_param('ssssssssss',$tipo,$origem,$destino,$data_mov,$resp_o,$resp_d,$transp,$motivo,$prev_dev,$status);
        $st->execute(); $tid=$st->insert_id; $st->close();
      }
      if($tipos){
        $ins=$dbc->prepare("INSERT INTO moz_transfer_item (transfer_id,tipo,ref_id,descricao,qtd,obs) VALUES (?,?,?,?,?,?)");
        foreach($tipos as $i=>$t){
          $T=strtoupper($t?:'ATIVO'); $R=!empty($refs[$i])?(int)$refs[$i]:null; $D=trim($descs[$i]??''); $Q=max(1,(int)($qtds[$i]??1)); $O=trim($obs[$i]??'');
          $ins->bind_param('isisis',$tid,$T,$R,$D,$Q,$O); $ins->execute();
        }
        $ins->close();
      }
      $dbc->commit(); $ok='Transferência salva.';
    }catch(Throwable $e){ $dbc->rollback(); $err='Erro: '.$e->getMessage(); }
  }
}

include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header">Transferências — Cadastro</h1></div></div>
  <?php if($err): ?><div class="alert alert-danger"><?=$err?></div><?php endif; ?>
  <?php if($ok):  ?><div class="alert alert-success"><?=$ok?></div><?php endif; ?>

  <form class="card" method="post" autocomplete="off" novalidate>
    <p class="subtitle">Dados da transferência</p>
    <div class="grid cols-4">
      <div><label>Tipo *</label><select name="tipo"><option value="TEMPORARIA">Temporária</option><option value="DEFINITIVA">Definitiva</option><option value="EMPRESTIMO">Empréstimo</option></select></div>
      <div><label>Origem *</label><input name="origem" required/></div>
      <div><label>Destino *</label><input name="destino" required/></div>
      <div><label>Data</label><input name="data_mov" type="date"/></div>
    </div>

    <div class="grid cols-3">
      <div><label>Responsável na origem</label><input name="resp_origem"/></div>
      <div><label>Responsável no destino</label><input name="resp_destino"/></div>
      <div><label>Transportador</label><input name="transportador"/></div>
    </div>

    <div class="grid cols-3">
      <div><label>Motivo</label><input name="motivo"/></div>
      <div><label>Previsão de devolução</label><input name="prev_devolucao" type="date"/></div>
      <div><label>Status *</label><select name="status"><option value="PREPARACAO">Em preparação</option><option value="TRANSITO">Em trânsito</option><option value="CONCLUIDA">Concluída</option><option value="CANCELADA">Cancelada</option></select></div>
    </div>

    <div class="divider"></div>
    <p class="subtitle">Itens da transferência</p>
    <div id="tr-itens" class="stack"></div>
    <button type="button" class="btn small" id="tr-add-item">+ Adicionar item</button>

    <div class="divider"></div>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <a class="btn" href="transferencias-listar.php">Cancelar</a>
      <button class="btn primary">Salvar</button>
    </div>
  </form>
</div></div>

<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
<script>
function trItemRow(){
  const el=document.createElement('div'); el.className='grid cols-4'; el.style.alignItems='end';
  el.innerHTML=`
    <div><label>Tipo</label><select name="it_tipo[]"><option>CATEGORIA</option><option>MODELO</option><option selected>ATIVO</option></select></div>
    <div><label>Ref/Descrição</label><input name="it_ref[]" type="number" placeholder="ID (ATIVO)"/><input name="it_desc[]" type="text" placeholder="Descrição"/></div>
    <div><label>Qtd</label><input name="it_qtd[]" type="number" min="1" value="1"/></div>
    <div class="row"><button type="button" class="btn small danger">Remover</button></div>
    <div class="cols-span-4"><label>Obs</label><input name="it_obs[]" type="text"/></div>`;
  el.querySelector('.btn.danger').addEventListener('click',()=>el.remove()); return el;
}
const trList=document.getElementById('tr-itens');
document.getElementById('tr-add-item').addEventListener('click',()=>trList.appendChild(trItemRow()));
trList.appendChild(trItemRow());
</script>
<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
