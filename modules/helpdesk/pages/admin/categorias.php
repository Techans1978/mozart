<?php
// public/modules/helpdesk/pages/admin/categorias.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
include_once ROOT_PATH . '/system/includes/head.php';
require_once ROOT_PATH.'/system/includes/head_hd.php';
proteger_pagina();

function table_exists(mysqli $c,$t){ $t=$c->real_escape_string($t); $r=$c->query("SHOW TABLES LIKE '$t'"); return $r&&$r->num_rows>0; }
$has = table_exists($conn,'hd_categoria');
$err=$ok=null;

if(!$has){
  $err = 'Tabela <code>hd_categoria</code> não encontrada. Crie com: 
  <pre>CREATE TABLE hd_categoria (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(120) NOT NULL, descricao VARCHAR(255) NULL, ativo TINYINT(1) NOT NULL DEFAULT 1);</pre>';
}
if($_SERVER['REQUEST_METHOD']==='POST' && $has){
  if(($_POST['action']??'')==='create'){
    $nome = $conn->real_escape_string(trim($_POST['nome']??''));
    $desc = $conn->real_escape_string(trim($_POST['descricao']??''));
    $ativo= (int)($_POST['ativo']??1);
    if($nome===''){ $err="Nome obrigatório"; }
    else{
      if($conn->query("INSERT INTO hd_categoria (nome,descricao,ativo) VALUES ('$nome','$desc',$ativo)")) $ok="Categoria criada!";
      else $err="Erro: ".$conn->error;
    }
  }
  if(($_POST['action']??'')==='delete'){
    $id=(int)($_POST['id']??0);
    if($id && $conn->query("DELETE FROM hd_categoria WHERE id=$id")) $ok="Excluída.";
    else if($id) $err="Erro ao excluir: ".$conn->error;
  }
}

$rows = $has ? ($conn->query("SELECT id,nome,descricao,ativo FROM hd_categoria ORDER BY nome")->fetch_all(MYSQLI_ASSOC) ?? []) : [];
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
  <h3 class="mt-3 mb-3">Categorias</h3>
  <?php if($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>
  <?php if($ok): ?><div class="alert alert-success"><?= $ok ?></div><?php endif; ?>

  <div class="card mb-3">
    <div class="card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="action" value="create">
        <div class="col-md-4"><input name="nome" class="form-control" placeholder="Nome" required></div>
        <div class="col-md-6"><input name="descricao" class="form-control" placeholder="Descrição"></div>
        <div class="col-md-1">
          <select name="ativo" class="form-select"><option value="1">Ativo</option><option value="0">Inativo</option></select>
        </div>
        <div class="col-md-1"><button class="btn btn-success w-100">Salvar</button></div>
      </form>
    </div>
  </div>

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
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
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
