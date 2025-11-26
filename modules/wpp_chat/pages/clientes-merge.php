<?php
// pages/clientes-merge.php
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
wpp_guard_require_capability('wpp_clientes_edit');

$principal_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($principal_id <= 0) {
    echo '<div class="container-fluid moz-content"><div class="alert alert-danger">Cliente principal inválido.</div></div>';
    require_once ROOT_PATH . '/system/includes/footer.php';
    exit;
}

$principal = wpp_cliente_find_by_id($conn, $principal_id);
if (!$principal) {
    echo '<div class="container-fluid moz-content"><div class="alert alert-danger">Cliente principal não encontrado.</div></div>';
    require_once ROOT_PATH . '/system/includes/footer.php';
    exit;
}

$erro  = '';
$ok    = '';
$lista = [];

// Tratamento do POST (mesclagem)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secundario_id = isset($_POST['secundario_id']) ? (int)$_POST['secundario_id'] : 0;

    if ($secundario_id <= 0) {
        $erro = 'Selecione um cliente para mesclar.';
    } elseif ($secundario_id === $principal_id) {
        $erro = 'Não é possível mesclar o cliente com ele mesmo.';
    } else {
        $okMerge = wpp_cliente_merge($conn, $principal_id, $secundario_id);
        if ($okMerge) {
            $ok = "Cliente #{$secundario_id} foi mesclado com sucesso em #{$principal_id}.";
            // Recarrega o principal atualizado
            $principal = wpp_cliente_find_by_id($conn, $principal_id);
        } else {
            $erro = 'Não foi possível mesclar os clientes. Verifique os dados e tente novamente.';
        }
    }
}

// Busca de possíveis duplicados (GET)
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q !== '') {
    // Busca manual aqui, sem depender de wpp_cliente_list
    $like = '%'.$q.'%';
    $sql = "
      SELECT *
        FROM moz_wpp_cliente
       WHERE id <> ?
         AND (telefone LIKE ? OR nome LIKE ? OR email LIKE ? OR documento LIKE ?)
         AND (mesclado_para_id IS NULL)
       ORDER BY updated_at DESC
       LIMIT 50
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('issss', $principal_id, $like, $like, $like, $like);
    $stmt->execute();
    $res  = $stmt->get_result();
    $lista = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
} else {
    // Sugere duplicados pelo mesmo telefone, se houver
    if (!empty($principal['telefone'])) {
        $tel = preg_replace('/\D+/', '', $principal['telefone']);
        $likeTel = '%'.$tel.'%';
        $sql = "
          SELECT *
            FROM moz_wpp_cliente
           WHERE id <> ?
             AND REPLACE(REPLACE(REPLACE(telefone,'-',''),' ',''),'.','') LIKE ?
             AND (mesclado_para_id IS NULL)
           ORDER BY updated_at DESC
           LIMIT 20
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $principal_id, $likeTel);
        $stmt->execute();
        $res  = $stmt->get_result();
        $lista = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
}
?>
<div class="container-fluid moz-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-0">Mesclar clientes</h1>
      <small class="text-muted">
        Cliente principal: #<?php echo (int)$principal['id']; ?> —
        <?php echo htmlspecialchars($principal['nome'] ?: $principal['telefone']); ?>
      </small>
    </div>
    <a href="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/clientes-view.php?id=<?php echo (int)$principal['id']; ?>"
       class="btn btn-outline-secondary btn-sm">Voltar</a>
  </div>

  <?php if ($erro): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
  <?php endif; ?>

  <?php if ($ok): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($ok); ?></div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-header">
      <strong>Buscar cliente para mesclar</strong>
    </div>
    <div class="card-body">
      <form method="get" class="row g-2 mb-2">
        <input type="hidden" name="id" value="<?php echo (int)$principal['id']; ?>">
        <div class="col-md-8">
          <input type="text" name="q" class="form-control"
                 placeholder="Telefone, nome, e-mail ou documento"
                 value="<?php echo htmlspecialchars($q); ?>">
        </div>
        <div class="col-md-4">
          <button type="submit" class="btn btn-primary w-100">Buscar</button>
        </div>
      </form>
      <small class="text-muted">
        Se não informar nada, são sugeridos possíveis duplicados pelo telefone.
      </small>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <strong>Possíveis duplicados</strong>
    </div>
    <div class="card-body p-0">
      <?php if (!$lista): ?>
        <div class="p-3 text-muted">Nenhum possível duplicado encontrado.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm mb-0 align-middle">
            <thead>
              <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Telefone</th>
                <th>E-mail</th>
                <th>Documento</th>
                <th>Último contato</th>
                <th class="text-end">Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($lista as $c): ?>
                <tr>
                  <td><?php echo (int)$c['id']; ?></td>
                  <td><?php echo htmlspecialchars($c['nome']); ?></td>
                  <td><?php echo htmlspecialchars($c['telefone']); ?></td>
                  <td><?php echo htmlspecialchars($c['email']); ?></td>
                  <td><?php echo htmlspecialchars($c['documento']); ?></td>
                  <td><?php echo htmlspecialchars($c['ultimo_contato']); ?></td>
                  <td class="text-end">
                    <form method="post" onsubmit="return confirm('Confirmar mescla do cliente #<?php echo (int)$c['id']; ?> no cliente principal #<?php echo (int)$principal['id']; ?>?');">
                      <input type="hidden" name="secundario_id" value="<?php echo (int)$c['id']; ?>">
                      <button type="submit" class="btn btn-sm btn-danger">
                        Mesclar neste
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once ROOT_PATH . '/system/includes/footer.php'; ?>
