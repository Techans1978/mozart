<?php
// public_html/system/updown/audit_list.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';

if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$limit = 200;
$st = $dbc->prepare("SELECT id, created_at, action, user_id, user_name, module, entity_id, file_id, rel_path, ip
                     FROM moz_file_audit
                     ORDER BY id DESC
                     LIMIT ?");
$st->bind_param('i', $limit);
$st->execute();
$rs = $st->get_result();
$rows = [];
while($r=$rs->fetch_assoc()) $rows[]=$r;
$st->close();

include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<style>
  .card{background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:14px;margin:12px 0;}
  table{width:100%;border-collapse:collapse}
  th,td{border-bottom:1px solid #eee;padding:8px;font-size:13px;vertical-align:top}
  th{background:#fafafa;text-align:left}
  .badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #ddd;background:#fafafa;font-size:12px}
</style>

<div id="page-wrapper"><div class="container-fluid">
  <div class="card">
    <h2 style="margin:0">Auditoria de Downloads</h2>
    <div style="color:#666;margin-top:6px">Últimos <?= (int)$limit ?> registros</div>
  </div>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th>Data/Hora</th>
          <th>Ação</th>
          <th>Usuário</th>
          <th>Módulo</th>
          <th>Registro</th>
          <th>Arquivo</th>
          <th>IP</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?= h($r['created_at']) ?></td>
          <td><span class="badge"><?= h($r['action']) ?></span></td>
          <td><?= h(trim(($r['user_name']?:'').' (#'.($r['user_id']?:'').')')) ?></td>
          <td><?= h($r['module'] ?: '—') ?></td>
          <td><?= $r['entity_id'] ? (int)$r['entity_id'] : '—' ?></td>
          <td>
            <?php if (!empty($r['file_id'])): ?>
              #<?= (int)$r['file_id'] ?>
            <?php endif; ?>
            <div style="color:#666"><?= h($r['rel_path'] ?: '') ?></div>
          </td>
          <td><?= h($r['ip'] ?: '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div></div>

<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
