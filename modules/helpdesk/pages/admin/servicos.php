<?php
// public/modules/helpdesk/pages/admin/servicos.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
include_once ROOT_PATH . '/system/includes/head.php';
require_once ROOT_PATH.'/system/includes/head_hd.php';
proteger_pagina();

function has(mysqli $c,$t){ $t=$c->real_escape_string($t); $r=$c->query("SHOW TABLES LIKE '$t'"); return $r && $r->num_rows>0; }
$ok=$err=null; $tbl='hd_servico';
if(!has($conn,$tbl)){
  $err="Tabela <code>$tbl</code> ausente. Crie com:
  <pre>CREATE TABLE $tbl (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(120) NOT NULL, descricao VARCHAR(255) NULL, ativo TINYINT(1) NOT NULL DEFAULT 1);</pre>";
}
if($_SERVER['REQUEST_METHOD']==='POST' && !$err){
  $act=$_POST['action']??'';
  if($act==='create'){
    $n=$conn->real_escape_string(trim($_POST['nome']??'')); $d=$conn->real_escape_string(trim($_POST['descricao']??'')); $a=(int)($_POST['ativo']??1);
    if($n==='') $err='Nome obrigatório'; else if($conn->query("INSERT INTO $tbl (nome,descricao,ativo) VALUES ('$n','$d',$a)")) $ok='Salvo!';
    else $err=$conn->error;
  }
  if($act==='delete'){
    $id=(int)($_POST['id']??0);
    if($id && $conn->query("DELETE FROM $tbl WHERE id=$id")) $ok='Excluído';
    else if($id) $err=$conn->error;
  }
}
$rows = $err?[]:($conn->query("SELECT id,nome,descricao,ativo FROM $tbl ORDER BY nome")->fetch_all(MYSQLI_ASSOC)??[]);
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
  <h3 class="mt-3 mb-3">Serviços</h3>
  <?php if($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>
  <?php if($ok): ?><div class="alert alert-success"><?= $ok ?></div><?php endif; ?>
  <div class="card mb-3"><div class="card-body">
    <form method="post" class="row g-2">
      <input type="hidden" name="action" value="create">
      <div class="col-md-4"><input name="nome" class="form-control" placeholder="Nome" required></div>
      <div class="col-md-6"><input name="descricao" class="form-control" placeholder="Descrição"></div>
      <div class="col-md-1"><select name="ativo" class="form-select"><option value="1">Ativo</option><option value="0">Inativo</option></select></div>
      <div class="col-md-1"><button class="btn btn-success w-100">Salvar</button></div>
    </form>
  </div></div>

  <div class="table-responsive">
    <table class="table table-sm table-bordered">
      <thead><tr><th>ID</th><th>Nome</th><th>Descrição</th><th>Ativo</th><th>Ações</th></tr></thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['nome']) ?></td>
            <td><?= htmlspecialchars($r['descricao']) ?></td>
            <td><?= $r['ativo']?'Sim':'Não' ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Excluir?')" class="d-inline">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Excluir</button>
              </form>
            </td>
          </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>
</div>
	  <!-- End content -->
      </div>
    </div>
  </div>
</div>
<!-- layout -->
<?php require_once ROOT_PATH.'/system/includes/footer_hd.php'; ?>
