<?php
// public/modules/helpdesk/pages/tickets_listar.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
include_once ROOT_PATH . '/system/includes/head.php';
require_once ROOT_PATH.'/system/includes/head_hd.php';

proteger_pagina();

function table_exists(mysqli $c, string $t){
  $t = $c->real_escape_string($t);
  $res = $c->query("SHOW TABLES LIKE '$t'");
  return $res && $res->num_rows>0;
}
$has_ticket = table_exists($conn,'hd_ticket');

$filters = [
  'q'    => trim($_GET['q']   ?? ''),
  's'    => trim($_GET['s']   ?? ''), // status
  'from' => trim($_GET['from']?? ''),
  'to'   => trim($_GET['to']  ?? ''),
];

$rows = [];
$warn = [];
if(!$has_ticket){
  $warn[] = "Tabela <code>hd_ticket</code> não encontrada. Ajuste o schema ou atualize o nome da tabela nesta página.";
} else {
  // Monta WHERE de forma segura
  $w = []; $p = []; $t = '';
  if($filters['q']!==''){ $w[]="(protocolo LIKE CONCAT('%',?,'%') OR titulo LIKE CONCAT('%',?,'%'))"; $p[]=$filters['q']; $p[]=$filters['q']; $t.='ss'; }
  if($filters['s']!==''){ $w[]="status = ?"; $p[]=$filters['s']; $t.='s'; }
  if($filters['from']!==''){ $w[]="created_at >= ?"; $p[]=$filters['from'].' 00:00:00'; $t.='s'; }
  if($filters['to']!==''){ $w[]="created_at <= ?"; $p[]=$filters['to'].' 23:59:59'; $t.='s'; }

  $sql = "SELECT id, protocolo, titulo, status, prioridade, entidade_id, loja_id, assignee_user_id, created_at, updated_at
            FROM hd_ticket";
  if($w) $sql .= " WHERE ".implode(' AND ',$w);
  $sql .= " ORDER BY created_at DESC LIMIT 1000";

  $stmt = $conn->prepare($sql);
  if($stmt){
    if($p){ $stmt->bind_param($t, ...$p); }
    $stmt->execute();
    $res = $stmt->get_result();
    while($r=$res->fetch_assoc()) $rows[]=$r;
    $stmt->close();
  } else {
    $warn[] = "Erro no SELECT: ".$conn->error;
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
  <h3 class="mt-3 mb-3">Listar Chamados</h3>

  <?php if($warn): ?>
    <div class="alert alert-warning"><?php foreach($warn as $w){ echo "<div>$w</div>"; } ?></div>
  <?php endif; ?>

  <form class="row g-2 mb-3" method="get">
    <div class="col-md-4">
      <label class="form-label">Buscar</label>
      <input name="q" class="form-control" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Protocolo ou título">
    </div>
    <div class="col-md-2">
      <label class="form-label">Status</label>
      <input name="s" class="form-control" value="<?= htmlspecialchars($filters['s']) ?>" placeholder="ex: aberto">
    </div>
    <div class="col-md-2">
      <label class="form-label">De</label>
      <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($filters['from']) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">Até</label>
      <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($filters['to']) ?>">
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button class="btn btn-primary w-100">Filtrar</button>
    </div>
  </form>

  <div class="table-responsive">
    <table id="tbl" class="table table-striped table-bordered table-sm">
      <thead>
        <tr>
          <th>ID</th><th>Protocolo</th><th>Título</th><th>Status</th><th>Prioridade</th>
          <th>Entidade</th><th>Loja</th><th>Técnico</th><th>Criado em</th><th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['protocolo'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['titulo'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['status'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['prioridade'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['entidade_id'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['loja_id'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['assignee_user_id'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
            <td>
              <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/modules/helpdesk/pages/ticket_detalhe.php?id=<?= (int)$r['id'] ?>">Abrir</a>
            </td>
          </tr>
        <?php endforeach; ?>
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

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.10/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', ()=> {
  const $ = window.jQuery;
  if($ && $('#tbl').DataTable){
    $('#tbl').DataTable({pageLength:25, order:[[8,'desc']], language:{ url:'//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' }});
  }
});
</script>

<?php require_once ROOT_PATH.'/system/includes/footer_hd.php'; ?>
