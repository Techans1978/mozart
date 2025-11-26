<?php
// public/modules/helpdesk/pages/admin/entidades.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
include_once ROOT_PATH . '/system/includes/head.php';
require_once ROOT_PATH.'/system/includes/head_hd.php';
proteger_pagina();

function table_exists(mysqli $c, string $t): bool {
  $t = $c->real_escape_string($t);
  $r = $c->query("SHOW TABLES LIKE '$t'");
  return $r && $r->num_rows > 0;
}

/**
 * Auto-detecção da origem dos dados:
 * - se existir `empresa`: usa campos (id, nome, cnpj, ativo)
 * - senão se existir `empresas`: usa campos (id, nome_empresarial -> nome, cnpj, ativo)
 */
$source = null;
if (table_exists($conn, 'empresa')) {
  $source = [
    'table' => 'empresa',
    'cols'  => ['id'=>'id', 'nome'=>'nome', 'cnpj'=>'cnpj', 'ativo'=>'ativo'],
    'create_sql' => "INSERT INTO empresa (nome, cnpj, ativo) VALUES (?, ?, ?)",
    'create_types' => 'ssi',
    'create_bind'  => function($st, $nome, $cnpj, $ativo){ $st->bind_param('ssi', $nome, $cnpj, $ativo); },
    'delete_sql' => "DELETE FROM empresa WHERE id=?"
  ];
} elseif (table_exists($conn, 'empresas')) {
  $source = [
    'table' => 'empresas',
    // mapeia para a view da página:
    'cols'  => ['id'=>'id', 'nome'=>'nome_empresarial', 'cnpj'=>'cnpj', 'ativo'=>'ativo'],
    // cria preenchendo nome_empresarial e (opcional) nome_fantasia igual
    'create_sql' => "INSERT INTO empresas (nome_empresarial, nome_fantasia, cnpj, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())",
    'create_types' => 'sssi',
    'create_bind'  => function($st, $nome, $cnpj, $ativo){ $nf=$nome; $st->bind_param('sssi', $nome, $nf, $cnpj, $ativo); },
    'delete_sql' => "DELETE FROM empresas WHERE id=?"
  ];
}

$err=$ok=null;
if (!$source) {
  $err = "Nenhuma tabela de empresas encontrada. Crie <code>empresa</code> ou <code>empresas</code>.";
}

// CREATE
if ($_SERVER['REQUEST_METHOD']==='POST' && !$err) {
  $act = $_POST['action'] ?? '';
  if ($act === 'create') {
    $nome = trim($_POST['nome'] ?? '');
    $cnpj = trim($_POST['cnpj'] ?? '');
    $ativo = (int)($_POST['ativo'] ?? 1);
    if ($nome === '') {
      $err = 'Nome obrigatório';
    } else {
      $st = $conn->prepare($source['create_sql']);
      if (!$st) { $err = 'Erro prepare: '.$conn->error; }
      else {
        $bind = $source['create_bind']; $bind($st, $nome, $cnpj, $ativo);
        if ($st->execute()) $ok = 'Entidade salva.';
        else $err = 'Erro ao salvar: '.$st->error;
        $st->close();
      }
    }
  }
  if ($act === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
      $st = $conn->prepare($source['delete_sql']);
      if ($st) {
        $st->bind_param('i', $id);
        if ($st->execute()) $ok='Excluída.';
        else $err='Não foi possível excluir (verifique dependências).';
        $st->close();
      } else { $err='Erro prepare: '.$conn->error; }
    }
  }
}

// LIST
$rows=[];
if (!$err && $source) {
  $t   = $conn->real_escape_string($source['table']);
  $id  = $source['cols']['id'];
  $nm  = $source['cols']['nome'];
  $cnp = $source['cols']['cnpj'];
  $atv = $source['cols']['ativo'];
  $sql = "SELECT $id AS id, $nm AS nome, $cnp AS cnpj, $atv AS ativo FROM $t ORDER BY $nm";
  if ($res = $conn->query($sql)) { while($r=$res->fetch_assoc()) $rows[]=$r; }
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
  <h3 class="mt-3 mb-3">Entidades / Lojas</h3>

  <?php if($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>
  <?php if($ok): ?><div class="alert alert-success"><?= $ok ?></div><?php endif; ?>

  <?php if($source): ?>
    <div class="alert alert-info py-2">
      Fonte: <b><?= htmlspecialchars($source['table']) ?></b>
      (campo nome: <code><?= htmlspecialchars($source['cols']['nome']) ?></code>)
    </div>
  <?php endif; ?>

  <div class="card mb-3"><div class="card-body">
    <form method="post" class="row g-2">
      <input type="hidden" name="action" value="create">
      <div class="col-md-5"><input name="nome" class="form-control" placeholder="Nome" required></div>
      <div class="col-md-4"><input name="cnpj" class="form-control" placeholder="CNPJ (opcional)"></div>
      <div class="col-md-2">
        <select name="ativo" class="form-select">
          <option value="1">Ativo</option><option value="0">Inativo</option>
        </select>
      </div>
      <div class="col-md-1"><button class="btn btn-success w-100">Salvar</button></div>
    </form>
  </div></div>

  <table class="table table-sm table-bordered">
    <thead><tr><th>ID</th><th>Nome</th><th>CNPJ</th><th>Ativo</th><th>Ações</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= htmlspecialchars($r['nome']) ?></td>
        <td><?= htmlspecialchars($r['cnpj']) ?></td>
        <td><?= ((int)$r['ativo']===1)?'Sim':'Não' ?></td>
        <td>
          <form method="post" class="d-inline" onsubmit="return confirm('Excluir?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm btn-outline-danger">Excluir</button>
          </form>
        </td>
      </tr>
      <?php endforeach;?>
      <?php if(!$rows): ?>
      <tr><td colspan="5" class="text-muted">Nenhum registro.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
		
		
	  <!-- End content -->
      </div>
    </div>
  </div>
</div>
<!-- layout -->
<?php require_once ROOT_PATH.'/system/includes/footer_hd.php'; ?>
