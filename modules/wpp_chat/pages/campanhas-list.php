<?php
// pages/campanhas-list.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once ROOT_PATH . '/system/includes/header.php';
require_once __DIR__ . '/../includes/wpp_guard.php';
require_once __DIR__ . '/../includes/wpp_campaigns.php';

proteger_pagina();
wpp_guard_require_capability('wpp_campaigns_view');

$filtros = [
    'q'      => $_GET['q']      ?? '',
    'status' => $_GET['status'] ?? ''
];

$campanhas = wpp_campanha_find_all($conn, $filtros);
?>
<div class="container-fluid moz-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Campanhas de WhatsApp</h1>
    <a href="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/campanhas-form.php"
       class="btn btn-primary btn-sm">
      Nova campanha
    </a>
  </div>

  <form class="row g-2 mb-3">
    <div class="col-md-4">
      <input type="text" name="q" class="form-control" placeholder="Buscar por nome/descrição"
             value="<?php echo htmlspecialchars($filtros['q']); ?>">
    </div>
    <div class="col-md-3">
      <select name="status" class="form-select">
        <option value="">-- Status --</option>
        <option value="rascunho"   <?php if($filtros['status']==='rascunho') echo 'selected';?>>Rascunho</option>
        <option value="agendada"   <?php if($filtros['status']==='agendada') echo 'selected';?>>Agendada</option>
        <option value="em_andamento"<?php if($filtros['status']==='em_andamento') echo 'selected';?>>Em andamento</option>
        <option value="concluida"  <?php if($filtros['status']==='concluida') echo 'selected';?>>Concluída</option>
        <option value="cancelada"  <?php if($filtros['status']==='cancelada') echo 'selected';?>>Cancelada</option>
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
          <th>Nome</th>
          <th>Instância</th>
          <th>Status</th>
          <th>Tipo</th>
          <th>Agendada para</th>
          <th>Criada em</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$campanhas): ?>
          <tr><td colspan="8" class="text-muted text-center">Nenhuma campanha encontrada.</td></tr>
        <?php else: foreach ($campanhas as $c): ?>
          <tr>
            <td><?php echo (int)$c['id']; ?></td>
            <td><?php echo htmlspecialchars($c['nome']); ?></td>
            <td><?php echo htmlspecialchars($c['instancia_nome']); ?></td>
            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($c['status']); ?></span></td>
            <td><?php echo htmlspecialchars($c['tipo_disparo']); ?></td>
            <td><?php echo htmlspecialchars($c['data_agendada']); ?></td>
            <td><?php echo htmlspecialchars($c['created_at']); ?></td>
            <td class="text-end">
              <a href="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/campanhas-view.php?id=<?php echo (int)$c['id']; ?>"
                 class="btn btn-outline-secondary btn-sm">Ver</a>
              <a href="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/campanhas-form.php?id=<?php echo (int)$c['id']; ?>"
                 class="btn btn-outline-primary btn-sm">Editar</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once ROOT_PATH . '/system/includes/footer.php'; ?>
