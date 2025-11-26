<?php
// pages/scripts-list.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once ROOT_PATH . '/system/includes/header.php';
require_once __DIR__ . '/../includes/wpp_guard.php';
require_once __DIR__ . '/../includes/wpp_scripts.php';

proteger_pagina();
wpp_guard_require_capability('wpp_scripts_view');

$scripts = wpp_script_find_all($conn);
?>
<div class="container-fluid moz-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Scripts de atendimento</h1>
    <a href="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/scripts-builder.php"
       class="btn btn-primary btn-sm">
      Novo script
    </a>
  </div>

  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nome</th>
          <th>Ativo</th>
          <th>Atualizado em</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$scripts): ?>
          <tr><td colspan="5" class="text-muted text-center">Nenhum script cadastrado.</td></tr>
        <?php else: foreach ($scripts as $s): ?>
          <tr>
            <td><?php echo (int)$s['id']; ?></td>
            <td><?php echo htmlspecialchars($s['nome']); ?></td>
            <td>
              <?php if ($s['ativo']): ?>
                <span class="badge bg-success">Ativo</span>
              <?php else: ?>
                <span class="badge bg-secondary">Inativo</span>
              <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($s['updated_at']); ?></td>
            <td class="text-end">
              <a href="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/scripts-builder.php?id=<?php echo (int)$s['id']; ?>"
                 class="btn btn-outline-primary btn-sm">Editar</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once ROOT_PATH . '/system/includes/footer.php'; ?>
