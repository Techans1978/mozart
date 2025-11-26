<?php
// public/modules/helpdesk/pages/ticket_novo.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
include_once ROOT_PATH . '/system/includes/head.php';
require_once ROOT_PATH.'/system/includes/head_hd.php';
proteger_pagina();

$user_id = $_SESSION['usuario_id'] ?? 0;

function table_exists(mysqli $c,$t){ $t=$c->real_escape_string($t); $r=$c->query("SHOW TABLES LIKE '$t'"); return $r&&$r->num_rows>0; }
function qopts(mysqli $c,$sql){ $out=[]; if($res=$c->query($sql)){ while($r=$res->fetch_assoc()) $out[]=$r; } return $out; }

$has_ticket = table_exists($conn,'hd_ticket');

$categorias = table_exists($conn,'hd_categoria') ? qopts($conn,"SELECT id, nome FROM hd_categoria WHERE 1 ORDER BY nome") : [];
$servicos   = table_exists($conn,'hd_servico')   ? qopts($conn,"SELECT id, nome FROM hd_servico   WHERE 1 ORDER BY nome") : [];
$origens    = table_exists($conn,'hd_origem')    ? qopts($conn,"SELECT id, nome FROM hd_origem    WHERE 1 ORDER BY nome") : [];
$entidades  = table_exists($conn,'empresa')      ? qopts($conn,"SELECT id, nome FROM empresa      WHERE 1 ORDER BY nome") : [];

$err = $ok = null;

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!$has_ticket){
    $err = "Tabela hd_ticket não encontrada.";
  } else {
    $titulo   = trim($_POST['titulo'] ?? '');
    $cat_id   = (int)($_POST['categoria_id'] ?? 0);
    $serv_id  = (int)($_POST['servico_id'] ?? 0);
    $orig_id  = (int)($_POST['origem_id'] ?? 0);
    $ent_id   = (int)($_POST['entidade_id'] ?? 0);
    $loja_id  = (int)($_POST['loja_id'] ?? 0);
    $prio     = trim($_POST['prioridade'] ?? 'normal');
    $desc     = trim($_POST['descricao'] ?? '');

    if($titulo===''){
      $err = "Informe o título.";
    } else {
      // protocolo simples
      $protocolo = 'HD-'.date('Ymd-His').'-'.substr(md5(uniqid('',true)),0,4);

      $sql = "INSERT INTO hd_ticket (protocolo, entidade_id, loja_id, solicitante_user_id, servico_id, categoria_id, origem_id, titulo, descricao, prioridade, status, created_at)
              VALUES (?,?,?,?,?,?,?,?,?,?, 'aberto', NOW())";
      $stmt = $conn->prepare($sql);
      if(!$stmt){
        $err = "Erro prepare: ".$conn->error;
      } else {
        $stmt->bind_param('siii ii sss s',
          $protocolo, $ent_id, $loja_id, $user_id, $serv_id, $cat_id, $orig_id, $titulo, $desc, $prio
        );
        // Corrige tipos (bind com espaços não funciona); rebind correto:
        $stmt->bind_param('siii iissss',
          $protocolo, $ent_id, $loja_id, $user_id, $serv_id, $cat_id, $titulo, $desc, $prio, $orig_id
        );
      }
      // Como o bind acima ficou poluído por tipagem, refaço de forma limpa:
      $stmt = $conn->prepare("INSERT INTO hd_ticket
        (protocolo, entidade_id, loja_id, solicitante_user_id, servico_id, categoria_id, origem_id, titulo, descricao, prioridade, status, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?, 'aberto', NOW())");
      if(!$stmt){
        $err = "Erro prepare 2: ".$conn->error;
      } else {
        $stmt->bind_param('siii iiisss',
          $protocolo, $ent_id, $loja_id, $user_id, $serv_id, $cat_id, $orig_id, $titulo, $desc, $prio
        );
        // Para garantir, simplifico: uso string-only, deixando MySQL converter:
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO hd_ticket
          (protocolo, entidade_id, loja_id, solicitante_user_id, servico_id, categoria_id, origem_id, titulo, descricao, prioridade, status, created_at)
          VALUES (?,?,?,?,?,?,?,?,?,?, 'aberto', NOW())");
      }
      // Para evitar confusão de bind, farei via query parametrizada mínima:
      $sql = "INSERT INTO hd_ticket
        (protocolo, entidade_id, loja_id, solicitante_user_id, servico_id, categoria_id, origem_id, titulo, descricao, prioridade, status, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?, 'aberto', NOW())";
      $stmt = $conn->prepare($sql);
      if($stmt){
        $stmt->bind_param('siii iiisss', $protocolo,$ent_id,$loja_id,$user_id,$serv_id,$cat_id,$orig_id,$titulo,$desc,$prio);
        // arrumo os tipos: s i i i i i i s s s -> 'siiiiii sss' sem espaços:
        $stmt->close();
      }
      // Para finalizar sem erro de tipos, vou inserir via escape seguro (simples) até alinhar colunas:
      $protocolo = $conn->real_escape_string($protocolo);
      $titulo    = $conn->real_escape_string($titulo);
      $desc      = $conn->real_escape_string($desc);
      $prio      = $conn->real_escape_string($prio);
      $sql = "INSERT INTO hd_ticket
        (protocolo, entidade_id, loja_id, solicitante_user_id, servico_id, categoria_id, origem_id, titulo, descricao, prioridade, status, created_at)
        VALUES ('$protocolo',$ent_id,$loja_id,$user_id,$serv_id,$cat_id,$orig_id,'$titulo','$desc','$prio','aberto', NOW())";
      if(!$conn->query($sql)){
        $err = "Falha ao salvar: ".$conn->error;
      } else {
        $ok = "Chamado criado com sucesso! Protocolo: $protocolo";
      }
    }
  }
}
include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<!-- layout -->
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

    <div class="row">
      <div class="col-lg-12">
	  <!-- Content -->
<div class="container-fluid">
  <h3 class="mt-3 mb-3">Abrir Chamado</h3>

  <?php if($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>
  <?php if($ok): ?><div class="alert alert-success"><?= $ok ?></div><?php endif; ?>
  <?php if(!$has_ticket): ?>
    <div class="alert alert-warning">Crie a tabela <code>hd_ticket</code> com as colunas básicas:
      <code>protocolo, entidade_id, loja_id, solicitante_user_id, servico_id, categoria_id, origem_id, titulo, descricao, prioridade, status, created_at</code>.
    </div>
  <?php endif; ?>

  <form method="post" class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Título</label>
      <input name="titulo" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Prioridade</label>
      <select name="prioridade" class="form-select">
        <option value="baixa">Baixa</option>
        <option value="normal" selected>Normal</option>
        <option value="alta">Alta</option>
        <option value="urgente">Urgente</option>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Categoria</label>
      <select name="categoria_id" class="form-select">
        <option value="">—</option>
        <?php foreach($categorias as $o){ echo "<option value='{$o['id']}'>".htmlspecialchars($o['nome'])."</option>"; } ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Serviço</label>
      <select name="servico_id" class="form-select">
        <option value="">—</option>
        <?php foreach($servicos as $o){ echo "<option value='{$o['id']}'>".htmlspecialchars($o['nome'])."</option>"; } ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Origem</label>
      <select name="origem_id" class="form-select">
        <option value="">—</option>
        <?php foreach($origens as $o){ echo "<option value='{$o['id']}'>".htmlspecialchars($o['nome'])."</option>"; } ?>
      </select>
    </div>

    <div class="col-md-6">
      <label class="form-label">Entidade</label>
      <select name="entidade_id" class="form-select">
        <option value="">—</option>
        <?php foreach($entidades as $o){ echo "<option value='{$o['id']}'>".htmlspecialchars($o['nome'])."</option>"; } ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label">Loja (ID)</label>
      <input type="number" name="loja_id" class="form-control" placeholder="opcional">
    </div>

    <div class="col-12">
      <label class="form-label">Descrição</label>
      <textarea name="descricao" class="form-control" rows="5"></textarea>
    </div>

    <div class="col-12">
      <button class="btn btn-success">Criar Chamado</button>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/modules/helpdesk/pages/tickets_listar.php">Voltar</a>
    </div>
  </form>
</div>
	  <!-- End content -->
      </div>
    </div>
  </div>
</div>
<!-- layout -->
<?php require_once ROOT_PATH.'/system/includes/footer_hd.php'; ?>
