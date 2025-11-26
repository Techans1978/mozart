<?php
// modules/bpm/bpm_designer.php
// Mozart BPM — Modeler com Properties + Element Templates (CDN + fallback local)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

// Abre <html><head>...<body>
include_once ROOT_PATH . 'system/includes/head.php';

// Inclui dependencias BPM
include_once ROOT_PATH . 'modules/forms/includes/content_header.php';

// Inclui dependencias BPM
include_once ROOT_PATH . 'modules/forms/includes/content_style.php';

// (se o seu navbar ficar dentro do head/footer, não precisa incluir aqui)
include_once ROOT_PATH . 'system/includes/navbar.php';
?>

<!-- Page Content -->
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

    <div class="row">
      <div class="col-lg-12">
<!-- Top Content -->

<div class="toolbar">
  <strong>Mozart — Forms (form-js)</strong>
  <span class="mode" id="modeLabel">Modo: Visual</span>

  <button class="btn" id="btnVisual">Edição Visual</button>
  <button class="btn" id="btnCode">Código (JSON)</button>

  <div class="spacer"></div>

  <button class="btn" id="btnNew">Novo</button>
  <input type="file" id="fileOpen" accept=".json,application/json">
  <button class="btn" id="btnOpen">Abrir JSON</button>
  <button class="btn" id="btnSaveJSON">Baixar JSON</button>
  <button class="btn" id="btnRender" title="Renderiza no preview">Preview</button>
  <button class="btn" id="btnCopyJSON">Copiar JSON</button>
  <button class="btn" id="btnCopyHTML">Copiar HTML</button>
  <button class="btn primary" id="btnSaveHTML">Baixar HTML</button>
</div>

<div class="wrap">
  <!-- Editor (visual OU código) -->
  <div class="col" id="editorCol">
    <div id="editorHost"></div>
    <div class="fatal" id="fatalEditorErr">Falha ao carregar o Editor (rede/CDN?). Veja o console (F12).</div>

    <div id="codeHost" style="display:none;">
      <textarea id="schemaArea" spellcheck="false" placeholder='{"type":"default","components":[...]}'></textarea>
    </div>
  </div>

  <!-- Preview -->
  <div class="col" id="previewCol">
    <div id="previewHead">
      <div class="rowline">
        <label style="font-size:12px; color:#6b7280;">Título</label>
        <input type="text" id="formTitle" value="Formulário" />
      </div>
      <div class="spacer"></div>
      <button class="btn" id="btnClearData" title="Limpa dados do preview">Limpar dados</button>
    </div>
    <div id="previewBody">
      <div id="preview"></div>
    </div>
  </div>
</div>

  <!-- Fim Content -->
        </div>
    </div>
  </div>
</div>

<?php
// carrega seus scripts globais + Camunda JS (inserido no code_footer.php)
include_once ROOT_PATH . 'system/includes/code_footer.php';
?>

<?php
// Inclui dependencias BPM
include_once ROOT_PATH . 'modules/forms/includes/content_footer.php';
?>

<?php
// fecha </body></html>
include_once ROOT_PATH . 'system/includes/footer.php';
