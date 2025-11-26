<?php
// pages/clientes-list.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once ROOT_PATH . '/system/includes/header.php';
require_once __DIR__ . '/../includes/wpp_guard.php';
require_once __DIR__ . '/../includes/wpp_clientes.php';

proteger_pagina();
wpp_guard_require_capability('wpp_clientes_view');

$filtros = [
    'q'            => $_GET['q'] ?? '',
    'tipo_cliente' => $_GET['tipo_cliente'] ?? ''
];

$clientes = wpp_cliente_list($conn, $filtros);
?>
<div class="container-fluid moz-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Clientes (WhatsApp)</h1>
    <a href="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/clientes-form.php"
       class="btn btn-primary btn-sm">
      Novo cliente
    </a>
  </div>

  <form class="row g-2 mb-3">
    <div class="col-md-4">
      <input type="text" name="q" class="form-control" placeholder="Buscar por nome, telefone ou tag"
             value="<?php echo htmlspecialchars($filtros['q']); ?>">
    </div>
    <div class="col-md-3">
      <input type="text" name="tipo_cliente" class="form-control" placeholder="Tipo de cliente (opcional)"
             value="<?php echo htmlspecialchars($filtros['tipo_cliente']); ?>">
    </div>
    <div class="col-md-2">
      <button class="btn btn-outline-secondary w-100">Filtrar</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nome</th>
          <th>Telefone</th>
          <th>Tipo</th>
          <th>Tags</th>
          <th>Último contato</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$clientes): ?>
          <tr><td colspan="7" class="text-muted text-center">Nenhum cliente encontrado.</td></tr>
        <?php else: foreach ($clientes as $c): ?>
          <tr>
            <td><?php echo (int)$c['id']; ?></td>
            <td><?php echo htmlspecialchars($c['nome'] ?: '(sem nome)'); ?></td>
            <td><?php echo htmlspecialchars($c['telefone']); ?></td>
            <td><?php echo htmlspecialchars($c['tipo_cliente']); ?></td>
            <td><?php echo htmlspecialchars($c['tags']); ?></td>
            <td><?php echo htmlspecialchars($c['ultimo_contato']); ?></td>
            <td class="text-end">
              <a href="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/clientes-view.php?id=<?php echo (int)$c['id']; ?>"
                 class="btn btn-outline-secondary btn-sm">Ver</a>
              <a href="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/clientes-form.php?id=<?php echo (int)$c['id']; ?>"
                 class="btn btn-outline-primary btn-sm">Editar</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once ROOT_PATH . '/system/includes/footer.php'; ?>
