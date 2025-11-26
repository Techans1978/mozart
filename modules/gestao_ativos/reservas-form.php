<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start(); proteger_pagina();
$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* MIGRAÇÕES (idempotentes) */
$dbc->multi_query("
CREATE TABLE IF NOT EXISTS moz_reserva ( 
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  solicitante VARCHAR(160) NOT NULL,
  entidade VARCHAR(160) NOT NULL,
  setor VARCHAR(160) NULL,
  inicio DATETIME NOT NULL,
  fim DATETIME NOT NULL,
  recorrencia ENUM('NENHUMA','DIARIA','SEMANAL','MENSAL') NOT NULL DEFAULT 'NENHUMA',
  precisa_aprovacao TINYINT(1) NOT NULL DEFAULT 0,
  prioridade ENUM('NORMAL','ALTA','CRITICA') NOT NULL DEFAULT 'NORMAL',
  retirada_local VARCHAR(200) NULL,
  devolucao_local VARCHAR(200) NULL,
  transporte ENUM('RETIRADA','INTERNO','TRANSPORTADORA') DEFAULT 'RETIRADA',
  status ENUM('AGUARDANDO','APROVADA','EM_USO','CONCLUIDA','ATRASADA','CANCELADA') NOT NULL DEFAULT 'AGUARDANDO',
  termos_flags JSON NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_mr_status (status), KEY idx_mr_periodo (inicio,fim), KEY idx_mr_entidade (entidade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS moz_reserva_item (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reserva_id BIGINT UNSIGNED NOT NULL,
  tipo ENUM('CATEGORIA','MODELO','ATIVO') NOT NULL,
  ref_id BIGINT UNSIGNED NULL,
  descricao VARCHAR(255) NULL,
  qtd INT UNSIGNED NOT NULL DEFAULT 1,
  CONSTRAINT fk_mri_r FOREIGN KEY (reserva_id) REFERENCES moz_reserva(id) ON DELETE CASCADE,
  KEY idx_mri_res (reserva_id), KEY idx_mri_tipo_ref (tipo, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
"); while($dbc->more_results() && $dbc->next_result()){} // flush

$err=''; $ok='';
/** Verifica conflito para itens ATIVO: se existe outra reserva intersectando o período, em status que bloqueia */
function has_conflict(mysqli $db, string $inicio, string $fim, array $ativos, ?int $ignore_reserva_id=null): array {
  if(!$ativos) return [false,[]];
  $in = implode(',', array_map('intval',$ativos));
  $sql = "SELECT DISTINCT ri.ref_id AS ativo_id, r.id AS reserva_id, r.inicio, r.fim, r.status
          FROM moz_reserva r
          JOIN moz_reserva_item ri ON ri.reserva_id=r.id AND ri.tipo='ATIVO'
          WHERE ri.ref_id IN ($in)
            AND r.status IN ('AGUARDANDO','APROVADA','EM_USO')
            AND NOT (r.fim <= ? OR r.inicio >= ?)";
  if($ignore_reserva_id){ $sql .= " AND r.id<>".(int)$ignore_reserva_id; }
  $st=$db->prepare($sql); $st->bind_param('ss',$inicio,$fim); $st->execute();
  $res=$st->get_result(); $data=$res->fetch_all(MYSQLI_ASSOC); $st->close();
  return [!empty($data), $data];
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  // coleta
  $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
  $sol = trim($_POST['solicitante']??'');
  $ent = trim($_POST['entidade']??'');
  $set = trim($_POST['setor']??'');
  $inicio = trim($_POST['inicio']??'') . ' ' . (trim($_POST['hora_ini']??'00:00'));
  $fim    = trim($_POST['fim']??'')    . ' ' . (trim($_POST['hora_fim']??'23:59'));
  $rec = strtoupper($_POST['recorrencia']??'NENHUMA');
  $aprov = (int)($_POST['precisa_aprovacao']??0);
  $prio = strtoupper($_POST['prioridade']??'NORMAL');
  $ret = trim($_POST['retirada']??'');
  $dev = trim($_POST['devolucao']??'');
  $trans = strtoupper($_POST['transporte']??'RETIRADA');
  $status = strtoupper($_POST['status']??'AGUARDANDO');

  // itens (espera arrays paralelos)
  $tipos    = $_POST['it_tipo'] ?? [];
  $refs     = $_POST['it_ref'] ?? [];
  $qtds     = $_POST['it_qtd'] ?? [];
  $descrs   = $_POST['it_desc'] ?? [];

  if(!$sol || !$ent || !trim($_POST['inicio']??'') || !trim($_POST['fim']??'')){
    $err='Preencha solicitante, entidade e período.'; 
  } else {
    // Confere conflitos só para itens ATIVO
    $ativos=[]; foreach($tipos as $i=>$t){ if(($t??'')==='ATIVO' && !empty($refs[$i])) $ativos[]=(int)$refs[$i]; }
    [$conf,$list] = has_conflict($dbc,$inicio,$fim,$ativos,$id);
    if($conf){
      $ids = implode(', ', array_map(fn($r)=>'#'.$r['reserva_id'].'(A'.$r['ativo_id'].')', $list));
      $err = "Conflito com reservas existentes: ".$ids;
    } else {
      // grava
      $dbc->begin_transaction();
      try{
        if($id){
          $st=$dbc->prepare("UPDATE moz_reserva SET solicitante=?,entidade=?,setor=?,inicio=?,fim=?,recorrencia=?,precisa_aprovacao=?,prioridade=?,retirada_local=?,devolucao_local=?,transporte=?,status=? WHERE id=?");
          $st->bind_param('ssssssisssssi',$sol,$ent,$set,$inicio,$fim,$rec,$aprov,$prio,$ret,$dev,$trans,$status,$id);
          $st->execute(); $st->close();
          $dbc->query("DELETE FROM moz_reserva_item WHERE reserva_id=".$id);
          $reserva_id=$id;
        }else{
          $st=$dbc->prepare("INSERT INTO moz_reserva (solicitante,entidade,setor,inicio,fim,recorrencia,precisa_aprovacao,prioridade,retirada_local,devolucao_local,transporte,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
          $st->bind_param('ssssssisssss',$sol,$ent,$set,$inicio,$fim,$rec,$aprov,$prio,$ret,$dev,$trans,$status);
          $st->execute(); $reserva_id=$st->insert_id; $st->close();
        }
        // itens
        if($tipos){
          $ins=$dbc->prepare("INSERT INTO moz_reserva_item (reserva_id,tipo,ref_id,descricao,qtd) VALUES (?,?,?,?,?)");
          foreach($tipos as $i=>$t){
            $T = strtoupper($t?:'ATIVO'); $R = !empty($refs[$i])?(int)$refs[$i]:null;
            $D = trim($descrs[$i]??''); $Q = max(1,(int)($qtds[$i]??1));
            $ins->bind_param('isisi',$reserva_id,$T,$R,$D,$Q); $ins->execute();
          }
          $ins->close();
        }
        $dbc->commit(); $ok='Reserva salva com sucesso.';
      }catch(Throwable $e){ $dbc->rollback(); $err='Falha ao salvar: '.$e->getMessage(); }
    }
  }
}

// combos “mock” (use suas tabelas reais)
$entidades = ['Matriz','Filial 01','Filial 02'];
include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header">Reservas — Cadastro</h1></div></div>

  <?php if($err): ?><div class="alert alert-danger"><?=$err?></div><?php endif; ?>
  <?php if($ok):  ?><div class="alert alert-success"><?=$ok?></div><?php endif; ?>

  <form class="card" method="post" autocomplete="off" novalidate>
    <p class="subtitle">Dados da reserva</p>
    <div class="grid cols-3">
      <div><label>Solicitante *</label><input name="solicitante" type="text" required/></div>
      <div><label>Empresa/Entidade *</label>
        <select name="entidade" required><?php foreach($entidades as $e) echo "<option>".h($e)."</option>"; ?></select>
      </div>
      <div><label>Setor</label><input name="setor" type="text"/></div>
    </div>

    <div class="grid cols-4">
      <div><label>Início *</label><input name="inicio" type="date" required/></div>
      <div><label>Hora início</label><input name="hora_ini" type="time" value="08:00"/></div>
      <div><label>Fim *</label><input name="fim" type="date" required/></div>
      <div><label>Hora fim</label><input name="hora_fim" type="time" value="18:00"/></div>
    </div>

    <div class="grid cols-3">
      <div><label>Recorrência</label>
        <select name="recorrencia"><option value="NENHUMA">Nenhuma</option><option value="DIARIA">Diária</option><option value="SEMANAL">Semanal</option><option value="MENSAL">Mensal</option></select>
      </div>
      <div><label>Necessita aprovação?</label><select name="precisa_aprovacao"><option value="0">Não</option><option value="1">Sim</option></select></div>
      <div><label>Prioridade</label><select name="prioridade"><option>NORMAL</option><option>ALTA</option><option>CRITICA</option></select></div>
    </div>

    <div class="grid cols-3">
      <div><label>Retirada em</label><input name="retirada" type="text"/></div>
      <div><label>Devolução em</label><input name="devolucao" type="text"/></div>
      <div><label>Transporte</label><select name="transporte"><option value="RETIRADA">Retirada pelo solicitante</option><option value="INTERNO">Entrega interna</option><option value="TRANSPORTADORA">Transportadora</option></select></div>
    </div>

    <div class="divider"></div>
    <p class="subtitle">Itens reservados</p>
    <div id="res-itens" class="stack"></div>
    <button type="button" class="btn small" id="res-add-item">+ Adicionar item</button>

    <div class="divider"></div>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <a class="btn" href="reservas-listar.php">Cancelar</a>
      <button class="btn primary">Salvar</button>
    </div>
  </form>
</div></div>

<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
<script>
function resItemRow(){
  const el=document.createElement('div'); el.className='grid cols-4'; el.style.alignItems='end';
  el.innerHTML=`
    <div><label>Tipo</label>
      <select name="it_tipo[]"><option>CATEGORIA</option><option>MODELO</option><option selected>ATIVO</option></select></div>
    <div><label>Ref/Descrição</label>
      <input name="it_ref[]" type="number" placeholder="ID (para ATIVO)" />
      <input name="it_desc[]" type="text" placeholder="Descrição opcional"/></div>
    <div><label>Qtd</label><input name="it_qtd[]" type="number" min="1" value="1"/></div>
    <div class="row"><button type="button" class="btn small danger">Remover</button></div>
    `;
  el.querySelector('.btn.danger').addEventListener('click',()=>el.remove()); return el;
}
const resList=document.getElementById('res-itens');
document.getElementById('res-add-item').addEventListener('click',()=>resList.appendChild(resItemRow()));
resList.appendChild(resItemRow());
</script>
<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
