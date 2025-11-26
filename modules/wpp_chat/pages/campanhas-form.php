<?php
// pages/campanhas-form.php
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
wpp_guard_require_capability('wpp_campaigns_edit');

$user_id = $_SESSION['user_id'] ?? 0;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$campanha = $id ? wpp_campanha_find($conn, $id) : null;

// Instâncias para o select
$instancias = [];
$res = $conn->query("SELECT id, nome FROM moz_wpp_instance WHERE ativo = 1 ORDER BY nome");
if ($res) {
    $instancias = $res->fetch_all(MYSQLI_ASSOC);
}

// Scripts para o select
$scripts = [];
$res2 = $conn->query("SELECT id, nome FROM moz_wpp_script WHERE ativo = 1 ORDER BY nome");
if ($res2) {
    $scripts = $res2->fetch_all(MYSQLI_ASSOC);
}

$erro = '';
$ok   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = $_POST;
        $id_salvo = wpp_campanha_save($conn, $data, $user_id);

        // Importar destinatários (texto)
        if (!empty($_POST['destinatarios_texto'])) {
            wpp_campanha_destinatarios_importar_from_text(
                $conn,
                $id_salvo,
                $_POST['destinatarios_texto']
            );
        }

        $ok = 'Campanha salva com sucesso.';
        $campanha = wpp_campanha_find($conn, $id_salvo);
        $id = $id_salvo;
    } catch (Throwable $e) {
        $erro = 'Erro ao salvar campanha: '.$e->getMessage();
    }
}

// Caso venha de GET e não tenha campanha
if ($id && !$campanha) {
    $erro = 'Campanha não encontrada.';
}

?>
<div class="container-fluid moz-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
      <?php echo $id ? 'Editar Campanha' : 'Nova Campanha'; ?>
    </h1>
    <a href="<?php echo BASE_URL; ?>/public/modules/wpp_chat/pages/campanhas-list.php"
       class="btn btn-outline-secondary btn-sm">Voltar</a>
  </div>

  <?php if ($erro): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
  <?php endif; ?>

  <?php if ($ok): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($ok); ?></div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="id" value="<?php echo (int)($campanha['id'] ?? 0); ?>">

    <div class="mb-3">
      <label class="form-label">Nome da campanha *</label>
      <input type="text" name="nome" class="form-control"
             required
             value="<?php echo htmlspecialchars($campanha['nome'] ?? ''); ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Descrição</label>
      <textarea name="descricao" class="form-control" rows="2"><?php
        echo htmlspecialchars($campanha['descricao'] ?? '');
      ?></textarea>
    </div>

    <div class="row">
      <div class="col-md-4 mb-3">
        <label class="form-label">Instância WPP *</label>
        <select name="instancia_id" class="form-select" required>
          <option value="">-- Selecione --</option>
          <?php foreach ($instancias as $i): ?>
            <option value="<?php echo (int)$i['id']; ?>"
              <?php if(($campanha['instancia_id'] ?? 0) == $i['id']) echo 'selected'; ?>>
              <?php echo htmlspecialchars($i['nome']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4 mb-3">
        <label class="form-label">Script de atendimento (opcional)</label>
        <select name="script_id" class="form-select">
          <option value="">-- Nenhum --</option>
          <?php foreach ($scripts as $s): ?>
            <option value="<?php echo (int)$s['id']; ?>"
              <?php if(($campanha['script_id'] ?? 0) == $s['id']) echo 'selected'; ?>>
              <?php echo htmlspecialchars($s['nome']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4 mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <?php
            $st = $campanha['status'] ?? 'rascunho';
          ?>
          <option value="rascunho"   <?php if($st==='rascunho') echo 'selected';?>>Rascunho</option>
          <option value="agendada"   <?php if($st==='agendada') echo 'selected';?>>Agendada</option>
          <option value="em_andamento"<?php if($st==='em_andamento') echo 'selected';?>>Em andamento</option>
          <option value="concluida"  <?php if($st==='concluida') echo 'selected';?>>Concluída</option>
          <option value="cancelada"  <?php if($st==='cancelada') echo 'selected';?>>Cancelada</option>
        </select>
      </div>
    </div>

    <div class="row">
      <div class="col-md-4 mb-3">
        <label class="form-label">Tipo de disparo</label>
        <?php $tipo = $campanha['tipo_disparo'] ?? 'imediato'; ?>
        <select name="tipo_disparo" class="form-select">
          <option value="imediato" <?php if($tipo==='imediato') echo 'selected'; ?>>Imediato</option>
          <option value="agendado" <?php if($tipo==='agendado') echo 'selected'; ?>>Agendado</option>
        </select>
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label">Data/hora agendada (se agendado)</label>
        <input type="datetime-local" name="data_agendada" class="form-control"
               value="<?php
                 if (!empty($campanha['data_agendada']) && $campanha['data_agendada'] !== '0000-00-00 00:00:00') {
                     echo date('Y-m-d\TH:i', strtotime($campanha['data_agendada']));
                 }
               ?>">
      </div>
    </div>
    <hr>

    <div class="mb-3">
      <label class="form-label">Gatilho automático por tag</label>
      <small class="text-muted d-block mb-1">
        Se preenchido e ativado, sempre que um cliente receber esta tag automática,
        será incluído como destinatário pendente desta campanha. Exemplo de tag:
        <code>interesse_comercial</code>.
      </small>
      <div class="row g-2 align-items-center">
        <div class="col-7">
          <input type="text" name="trigger_tag" class="form-control"
                 placeholder="Ex.: interesse_comercial"
                 value="<?php echo htmlspecialchars($campanha['trigger_tag'] ?? ''); ?>">
        </div>
        <div class="col-5">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="triggerAtivo"
                   name="trigger_ativo" value="1"
              <?php echo !empty($campanha['trigger_ativo']) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="triggerAtivo">
              Gatilho ativo
            </label>
          </div>
        </div>
      </div>
    </div>

    <hr>

    <div class="mb-3">
      <label class="form-label">Destinatários (um por linha)</label>
      <small class="text-muted d-block">
        Formato: <code>telefone;Nome;{"chave":"valor"}</code><br>
        Exemplo: <code>5531999999999;Fulano;{"cidade":"Contagem"}</code>
      </small>
      <textarea name="destinatarios_texto" class="form-control" rows="6"></textarea>
    </div>

    <button type="submit" class="btn btn-primary">Salvar campanha</button>
  </form>
</div>
<?php require_once ROOT_PATH . '/system/includes/footer.php'; ?>
