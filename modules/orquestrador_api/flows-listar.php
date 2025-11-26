<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();
$db = $conn ?? $mysqli ?? null; if(!$db){ die('Sem conexão.'); }

$s = $db->prepare("SELECT f.*, (SELECT COUNT(*) FROM moz_flow_version v WHERE v.flow_id=f.id) as versions FROM moz_flow f ORDER BY f.criado_em DESC");
$s->execute(); $res = $s->get_result(); $rows=[]; while($res && $r=$res->fetch_assoc()) $rows[]=$r; $s->close();
?>
<?php include_once ROOT_PATH . '/system/includes/head.php'; ?>
<?php include_once ROOT_PATH . '/system/includes/navbar.php'; ?>
<div class="container-fluid mt-3">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h4 class="mb-0">Fluxos (Mozart Flow)</h4>
    <a href="flows-editor.php" class="btn btn-success btn-sm">Novo fluxo</a>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>Nome</th><th>Categoria</th><th>Versões</th><th>Criado</th><th></th></tr></thead>
      <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?=htmlspecialchars($r['nome'])?></td>
          <td><?=htmlspecialchars($r['categoria'])?></td>
          <td><?=$r['versions']?></td>
          <td><?=htmlspecialchars($r['criado_em'])?></td>
          <td><a class="btn btn-outline-primary btn-sm" href="flows-editor.php?id=<?=$r['id']?>">Editar</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include_once ROOT_PATH . '/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>
