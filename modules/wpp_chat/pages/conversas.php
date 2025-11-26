<?php
// pages/conversas.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once ROOT_PATH . '/system/includes/head.php';
// require_once __DIR__ . '/../includes/guard.php';
require_once __DIR__ . '/../includes/wpp_conversas.php';

proteger_pagina();
// wpp_guard_require_capability('wpp_chats_view');

$filtros = [
    'q'      => $_GET['q']      ?? '',
    'status' => $_GET['status'] ?? ''
];
$conversas = wpp_conversa_list($conn, $filtros);
?>
<div class="container-fluid moz-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Conversas WhatsApp</h1>
  </div>

  <form class="row g-2 mb-3">
    <div class="col-md-4">
      <input type="text" name="q" class="form-control" placeholder="Buscar por telefone ou nome"
             value="<?php echo htmlspecialchars($filtros['q']); ?>">
    </div>
    <div class="col-md-3">
      <select name="status" class="form-select">
        <option value="">-- Status --</option>
        <option value="aberta"  <?php if($filtros['status']==='aberta') echo 'selected';?>>Aberta</option>
        <option value="fechada" <?php if($filtros['status']==='fechada') echo 'selected';?>>Fechada</option>
      </select>
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
          <th>Contato</th>
          <th>Instância</th>
          <th>Última mensagem</th>
          <th>Data</th>
          <th>Status</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$conversas): ?>
          <tr><td colspan="7" class="text-muted text-center">Nenhuma conversa encontrada.</td></tr>
        <?php else: foreach ($conversas as $c): ?>
          <tr>
            <td><?php echo (int)$c['id']; ?></td>
            <td>
              <strong><?php echo htmlspecialchars($c['contato_nome'] ?: $c['contato_telefone']); ?></strong><br>
              <small class="text-muted"><?php echo htmlspecialchars($c['contato_telefone']); ?></small>
            </td>
            <td><?php echo htmlspecialchars($c['instancia_nome']); ?></td>
            <td><?php echo htmlspecialchars(mb_strimwidth($c['ultimo_msg'], 0, 60, '...')); ?></td>
            <td><?php echo htmlspecialchars($c['ultimo_msg_data']); ?></td>
            <td>
              <span class="badge bg-<?php echo $c['status']==='aberta'?'success':'secondary'; ?>">
                <?php echo htmlspecialchars($c['status']); ?>
              </span>
            </td>
            <td class="text-end">
              <a href="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/conversas-chat.php?id=<?php echo (int)$c['id']; ?>"
                 class="btn btn-outline-primary btn-sm">Abrir</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once ROOT_PATH . '/system/includes/footer.php'; ?>
