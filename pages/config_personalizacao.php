<?php
// pages/config_personalizacao.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/includes/autenticacao.php';
require_once ROOT_PATH . '/config/connect.php';
require_once ROOT_PATH . '/config/custom_loader.php'; // $CUSTOM

// TODO: validar se usuário é admin

$ok = null; $erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // BRANDING / META
  $app_name        = $_POST['app_name']        ?? '';
  $site_lang       = $_POST['site_lang']       ?? 'pt_BR';
  $site_charset    = $_POST['site_charset']    ?? 'utf-8';
  $site_description= $_POST['site_description']?? '';
  $site_author     = $_POST['site_author']     ?? '';
  $site_timezone   = $_POST['site_timezone']   ?? 'America/Sao_Paulo';

  // HEAD/BODY CUSTOM
  $meta_keywords   = $_POST['meta_keywords']   ?? '';
  $meta_viewport   = $_POST['meta_viewport']   ?? '';
  $head_css        = $_POST['head_css']        ?? '';
  $head_js         = $_POST['head_js']         ?? '';
  $before_head_end = $_POST['before_head_end'] ?? '';
  $after_body_open = $_POST['after_body_open'] ?? '';
  $before_body_end = $_POST['before_body_end'] ?? '';
  $custom_css      = $_POST['custom_css']      ?? '';
  $custom_js       = $_POST['custom_js']       ?? '';

  // normalizações leves
  $app_name     = trim($app_name);
  $site_lang    = trim($site_lang);
  $site_charset = trim($site_charset);
  $site_author  = trim($site_author);
  $site_timezone= trim($site_timezone);

  $sql = "
    UPDATE custom_includes SET
      app_name         = ?,
      site_lang        = ?,
      site_charset     = ?,
      site_description = ?,
      site_author      = ?,
      site_timezone    = ?,

      meta_keywords    = ?,
      meta_viewport    = ?,
      head_css         = ?,
      head_js          = ?,
      before_head_end  = ?,
      after_body_open  = ?,
      before_body_end  = ?,
      custom_css       = ?,
      custom_js        = ?
    WHERE id = 1
  ";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    $erros[] = "Erro prepare: ".$conn->error;
  } else {
    $stmt->bind_param(
      'sssssssssssssss',
      $app_name, $site_lang, $site_charset, $site_description, $site_author, $site_timezone,
      $meta_keywords, $meta_viewport, $head_css, $head_js,
      $before_head_end, $after_body_open, $before_body_end,
      $custom_css, $custom_js
    );
    if ($stmt->execute()) {
      $ok = "Configurações salvas com sucesso!";
      // recarrega $CUSTOM para refletir imediatamente
      require ROOT_PATH . '/includes/custom_loader.php';
    } else {
      $erros[] = "Erro ao salvar: " . $stmt->error;
    }
    $stmt->close();
  }
}

include_once ROOT_PATH . '/includes/head.php';
include_once ROOT_PATH . '/includes/navbar.php';
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?> – Configurações de Personalização</h1></div></div>

    <div class="row">
      <div class="col-lg-12">
        <?php if ($ok): ?>
          <div class="alert alert-success"><?= htmlspecialchars($ok) ?></div>
        <?php endif; ?>
        <?php if ($erros): ?>
          <div class="alert alert-danger">
            <ul style="margin:0;padding-left:18px">
              <?php foreach ($erros as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post">
          <div class="panel panel-default">
            <div class="panel-heading"><strong>Identidade do site</strong></div>
            <div class="panel-body">
              <div class="row">
                <div class="col-sm-6">
                  <div class="form-group">
                    <label>Título do site (APP_NAME)</label>
                    <input type="text" name="app_name" class="form-control"
                           value="<?= htmlspecialchars($CUSTOM['app_name']) ?>">
                  </div>
                </div>
                <div class="col-sm-3">
                  <div class="form-group">
                    <label>Idioma (SITE_LANG)</label>
                    <input type="text" name="site_lang" class="form-control"
                           placeholder="pt_BR"
                           value="<?= htmlspecialchars($CUSTOM['site_lang']) ?>">
                    <small class="text-muted">Ex.: pt_BR, en_US</small>
                  </div>
                </div>
                <div class="col-sm-3">
                  <div class="form-group">
                    <label>Charset (SITE_UTF)</label>
                    <input type="text" name="site_charset" class="form-control"
                           placeholder="utf-8"
                           value="<?= htmlspecialchars($CUSTOM['site_charset']) ?>">
                  </div>
                </div>
              </div>

              <div class="form-group">
                <label>Descrição (SITE_DESCR)</label>
                <textarea name="site_description" rows="2" class="form-control"><?= htmlspecialchars($CUSTOM['site_description']) ?></textarea>
              </div>

              <div class="row">
                <div class="col-sm-6">
                  <div class="form-group">
                    <label>Autor (SITE_AUTOR)</label>
                    <input type="text" name="site_author" class="form-control"
                           value="<?= htmlspecialchars($CUSTOM['site_author']) ?>">
                  </div>
                </div>
                <div class="col-sm-6">
                  <div class="form-group">
                    <label>Timezone (PHP)</label>
                    <input type="text" name="site_timezone" class="form-control"
                           placeholder="America/Sao_Paulo"
                           value="<?= htmlspecialchars($CUSTOM['site_timezone']) ?>">
                    <small class="text-muted">Ex.: America/Sao_Paulo</small>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <div class="panel panel-default">
            <div class="panel-heading"><strong>Head</strong></div>
            <div class="panel-body">
              <div class="form-group">
                <label>Meta keywords</label>
                <input type="text" name="meta_keywords" class="form-control"
                       placeholder="HTML, CSS, JavaScript"
                       value="<?= htmlspecialchars($CUSTOM['meta_keywords']) ?>">
              </div>

              <div class="form-group">
                <label>Meta viewport</label>
                <input type="text" name="meta_viewport" class="form-control"
                       placeholder="width=device-width, initial-scale=1.0"
                       value="<?= htmlspecialchars($CUSTOM['meta_viewport']) ?>">
              </div>

              <div class="form-group">
                <label>CSS no &lt;head&gt; (injetado em &lt;style&gt;)</label>
                <textarea name="head_css" rows="6" class="form-control"><?= htmlspecialchars($CUSTOM['head_css']) ?></textarea>
              </div>

              <div class="form-group">
                <label>JS no &lt;head&gt; (injetado em &lt;script&gt;)</label>
                <textarea name="head_js" rows="6" class="form-control"><?= htmlspecialchars($CUSTOM['head_js']) ?></textarea>
              </div>

              <div class="form-group">
                <label>Before &lt;/head&gt; (HTML bruto)</label>
                <textarea name="before_head_end" rows="6" class="form-control"><?= htmlspecialchars($CUSTOM['before_head_end']) ?></textarea>
              </div>
            </div>
          </div>

          <div class="panel panel-default">
            <div class="panel-heading"><strong>Body</strong></div>
            <div class="panel-body">
              <div class="form-group">
                <label>After &lt;body&gt; (HTML/JS)</label>
                <textarea name="after_body_open" rows="6" class="form-control"><?= htmlspecialchars($CUSTOM['after_body_open']) ?></textarea>
              </div>

              <div class="form-group">
                <label>Before &lt;/body&gt; (HTML/JS)</label>
                <textarea name="before_body_end" rows="6" class="form-control"><?= htmlspecialchars($CUSTOM['before_body_end']) ?></textarea>
              </div>

              <div class="form-group">
                <label>CSS Personalizado (global)</label>
                <textarea name="custom_css" rows="6" class="form-control"><?= htmlspecialchars($CUSTOM['custom_css']) ?></textarea>
              </div>

              <div class="form-group">
                <label>JS Personalizado (global)</label>
                <textarea name="custom_js" rows="6" class="form-control"><?= htmlspecialchars($CUSTOM['custom_js']) ?></textarea>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary">Salvar</button>
          <a href="<?= BASE_URL ?>/pages/dashboard.php" class="btn btn-default">Cancelar</a>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include_once ROOT_PATH . '/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . '/includes/footer.php'; ?>
