<?php
// public/modules/helpdesk/pages/admin/tecnicos_filas.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
include_once ROOT_PATH . '/system/includes/head.php';
require_once ROOT_PATH.'/system/includes/head_hd.php';;
proteger_pagina();

function has(mysqli $c,$t){ $t=$c->real_escape_string($t); $r=$c->query("SHOW TABLES LIKE '$t'"); return $r && $r->num_rows>0; }
$err=$ok=null;

$hasUsuarios = has($conn,'usuarios');
$hasFila     = has($conn,'hd_fila') || $conn->query("CREATE TABLE IF NOT EXISTS hd_fila (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(120) NOT NULL, ativo TINYINT(1) NOT NULL DEFAULT 1)")!==false;
$hasTF       = has($conn,'hd_tecnico_fila') || $conn->query("CREATE TABLE IF NOT EXISTS hd_tecnico_fila (user_id BIGINT UNSIGNED NOT NULL, fila_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY(user_id,fila_id))")!==false;

if(!$hasUsuarios){ $err="Tabela <code>usuarios</code> não encontrada (para listar técnicos)."; }

if($_SERVER['REQUEST_METHOD']==='POST'){
  $act=$_POST['action']??'';
  if($act==='create_fila' && $hasFila){
    $nome=$conn->real_escape_string(trim($_POST['nome']??'')); $a=(int)($_POST['ativo']??1);
    if($nome==='') $err='Nome obrigatório'; else if($conn->query("INSERT INTO hd_fila (nome,ativo) VALUES ('$nome',$a)")) $ok='Fila criada.'; else $err=$conn->error;
  }
  if($act==='delete_fila' && $hasFila){
    $id=(int)($_POST['id']??0);
    if($id && $conn->query("DELETE FROM hd_fila WHERE id=$id")) $ok='Fila excluída.'; else if($id) $err=$conn->error;
  }
  if($act==='link' && $hasTF){
    $uid=(int)($_POST['user_id']??0); $fid=(int)($_POST['fila_id']??0);
    if($uid && $fid && $conn->query("INSERT IGNORE INTO hd_tecnico_fila (user_id,fila_id) VALUES ($uid,$fid)")) $ok='Vínculo criado.'; else if($uid && $fid) $err=$conn->error;
  }
  if($act==='unlink' && $hasTF){
    $uid=(int)($_POST['user_id']??0); $fid=(int)($_POST['fila_id']??0);
    if($uid && $fid && $conn->query("DELETE FROM hd_tecnico_fila WHERE user_id=$uid AND fila_id=$fid")) $ok='Vínculo removido.'; else if($uid && $fid) $err=$conn->error;
  }
}

$filas = $hasFila ? ($conn->query("SELECT id,nome,ativo FROM hd_fila ORDER BY nome")->fetch_all(MYSQLI_ASSOC)??[]) : [];
$tecnicos = $hasUsuarios ? ($conn->query("SELECT id, nome FROM usuarios ORDER BY nome")->fetch_all(MYSQLI_ASSOC)??[]) : [];
$vinculos = $hasTF ? ($conn->query("SELECT tf.user_id,u.nome AS tecnico, tf.fila_id, f.nome AS fila
  FROM hd_tecnico_fila tf
  LEFT JOIN usuarios u ON u.id=tf.user_id
  LEFT JOIN hd_fila f ON f.id=tf.fila_id
  ORDER BY u.nome, f.nome")->fetch_all(MYSQLI_ASSOC)??[]) : [];

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
  <h3 class="mt-3 mb-3">Técnicos & Filas</h3>
  <?php if($err): ?><div class="alert alert-warning"><?= $err ?></div><?php endif; ?>
  <?php if($ok): ?><div class="alert alert-success"><?= $ok ?></div><?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card mb-3"><div class="card-header">Filas</div><div class="card-body">
        <form method="post" class="row g-2">
          <input type="hidden" name="action" value="create_fila">
          <div class="col-7"><input name="nome" class="form-control" placeholder="Nome da fila" required></div>
          <div class="col-3"><select name="ativo" class="form-select"><option value="1">Ativo</option><option value="0">Inativo</option></select></div>
          <div class="col-2"><button class="btn btn-success w-100">Criar</button></div>
        </form>
        <hr>
        <table class="table table-sm table-bordered">
          <thead><tr><th>ID</th><th>Nome</th><th>Ativo</th><th></th></tr></thead>
          <tbody>
            <?php foreach($filas as $f): ?>
            <tr>
              <td><?= (int)$f['id'] ?></td>
              <td><?= htmlspecialchars($f['nome']) ?></td>
              <td><?= $f['ativo']?'Sim':'Não' ?></td>
              <td>
                <form method="post" class="d-inline" onsubmit="return confirm('Excluir fila?')">
                  <input type="hidden" name="action" value="delete_fila">
                  <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger">Excluir</button>
                </form>
              </td>
            </tr>
            <?php endforeach;?>
          </tbody>
        </table>
      </div></div>
    </div>

    <div class="col-lg-7">
      <div class="card mb-3"><div class="card-header">Vínculos Técnico ↔ Fila</div><div class="card-body">
        <form method="post" class="row g-2 align-items-end">
          <input type="hidden" name="action" value="link">
          <div class="col-md-5">
            <label class="form-label">Técnico</label>
            <select name="user_id" class="form-select" required>
              <option value="">—</option>
              <?php foreach($tecnicos as $t){ echo "<option value='{$t['id']}'>".htmlspecialchars($t['nome'])."</option>"; } ?>
            </select>
          </div>
          <div class="col-md-5">
            <label class="form-label">Fila</label>
            <select name="fila_id" class="form-select" required>
              <option value="">—</option>
              <?php foreach($filas as $f){ echo "<option value='{$f['id']}'>".htmlspecialchars($f['nome'])."</option>"; } ?>
            </select>
          </div>
          <div class="col-md-2"><button class="btn btn-primary w-100">Vincular</button></div>
        </form>
        <hr>
        <table class="table table-sm table-bordered">
          <thead><tr><th>Técnico</th><th>Fila</th><th></th></tr></thead>
          <tbody>
            <?php foreach($vinculos as $v): ?>
            <tr>
              <td><?= htmlspecialchars($v['tecnico']??$v['user_id']) ?></td>
              <td><?= htmlspecialchars($v['fila']??$v['fila_id']) ?></td>
              <td>
                <form method="post" class="d-inline">
                  <input type="hidden" name="action" value="unlink">
                  <input type="hidden" name="user_id" value="<?= (int)$v['user_id'] ?>">
                  <input type="hidden" name="fila_id" value="<?= (int)$v['fila_id'] ?>">
                  <button class="btn btn-sm btn-outline-danger">Remover</button>
                </form>
              </td>
            </tr>
            <?php endforeach;?>
          </tbody>
        </table>
      </div></div>
    </div>
  </div>
</div>
		
		
	  <!-- End content -->
      </div>
    </div>
  </div>
</div>
<!-- layout -->
<?php require_once ROOT_PATH.'/system/includes/footer_hd.php'; ?>
