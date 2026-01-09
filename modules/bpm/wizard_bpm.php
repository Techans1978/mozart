<?php
// modules/bpm/wizard_bpm.php
// Mozart BPM — Wizard (7 passos) embutido no layout padrão do sistema

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
proteger_pagina();

require_once __DIR__.'/_lib/bpm_store.php';

// ===== Layout padrão do sistema =====
include_once ROOT_PATH . 'system/includes/head.php';

// Dependências específicas do módulo (mantendo seu padrão)
include_once ROOT_PATH . 'modules/bpm/includes/content_header.php';
include_once ROOT_PATH . 'modules/bpm/includes/content_style.php';

// Navbar padrão
include_once ROOT_PATH . 'system/includes/navbar.php';

// ===== Lógica do Wizard =====
$step = max(1, min(7, intval($_GET['step'] ?? 1)));
$state = $_SESSION['bpm_wizard'] ?? [
  'id'           => null,
  'nome'         => '',
  'codigo'       => '',
  'categoria_id' => null,

  // governance
  'acessos' => ['grupos'=>[], 'papeis'=>[], 'perfis'=>[]],

  // model (BPMN as source of truth)
  'bpmn_xml' => '',

  // draft/publish state
  'status'   => 'draft'
];

// helper para montar URLs respeitando BASE_URL
function step_link($n){ return BASE_URL . '/modules/bpm/wizard_bpm.php?step=' . intval($n); }

$labels = [
  1=>'Informacoes',
  2=>'Acessos',
  3=>'Desenho do fluxo',
  4=>'Salvar rascunho',
  5=>'Publicar',
  6=>'Validar / Checklist',
  7=>'Concluir'
];
?>

<!-- ===== Page Content (segue seu padrão) ===== -->
<div id="page-wrapper">
  <div class="container-fluid">

    <div class="row">
      <div class="col-lg-12">
        <h1 class="page-header">Wizard BPM</h1>
      </div>
    </div>

    <!-- Stepper no padrão Bootstrap -->
    <div class="row">
      <div class="col-lg-12">
        <div class="panel panel-default">
          <div class="panel-body">

            <div class="btn-group" role="group" aria-label="Passos do Wizard" style="flex-wrap:wrap">
              <?php for ($i=1; $i<=7; $i++): ?>
                <?php $active = ($i === $step) ? 'btn-primary' : 'btn-default'; ?>
                <a class="btn <?= $active ?> btn-sm" href="<?= step_link($i) ?>">
                  <?= $i . '. ' . htmlspecialchars($labels[$i]) ?>
                </a>
              <?php endfor; ?>
            </div>

          </div>
        </div>
      </div>
    </div>

    <!-- Conteúdo do passo -->
    <div class="row">
      <div class="col-lg-12">
        <div class="panel panel-default">
          <div class="panel-body">
            <?php
            // ===== Categorias BPM (para o step 1) =====
           $categorias = [];
            try {
              $has = $conn->query("SHOW TABLES LIKE 'bpm_categorias'");
              if ($has && $has->num_rows > 0) {

                // tenta com colunas "novas", se falhar cai no básico
                $rs = $conn->query("SELECT id, nome FROM bpm_categorias WHERE ativo = 1 ORDER BY sort_order ASC, nome ASC");
                if (!$rs) {
                  $rs = $conn->query("SELECT id, nome FROM bpm_categorias ORDER BY nome ASC");
                }

                if ($rs) $categorias = $rs->fetch_all(MYSQLI_ASSOC);

              } else {
                $has2 = $conn->query("SHOW TABLES LIKE 'bpm_categoria'");
                if ($has2 && $has2->num_rows > 0) {
                  $rs2 = $conn->query("SELECT id, nome FROM bpm_categoria ORDER BY nome ASC");
                  if ($rs2) $categorias = $rs2->fetch_all(MYSQLI_ASSOC);
                }
              }
            } catch (Throwable $e) {
              $categorias = [];
            }


              $view = __DIR__ . '/wizard_steps/' . $step . '.php';
              if (file_exists($view)) {
                include $view;
              } else {
                echo '<div class="alert alert-warning">View do passo não encontrada.</div>';
              }
            ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Navegação inferior -->
    <div class="row">
      <div class="col-lg-12">
        <div class="pull-left">
          <?php if ($step > 1): ?>
            <a class="btn btn-default" href="<?= step_link($step - 1) ?>">← Voltar</a>
          <?php endif; ?>
        </div>
        <div class="pull-right">
          <?php if ($step < 7): ?>
            <a class="btn btn-primary" href="<?= step_link($step + 1) ?>">Avançar →</a>
          <?php endif; ?>
          <a class="btn btn-link" href="<?= BASE_URL ?>/modules/bpm/processos-listar.php">Voltar à lista</a>
        </div>
        <div class="clearfix"></div>
      </div>
    </div>

  </div>
</div>

<?php
// Scripts globais do sistema (inclui seus bundles e o que o Designer precisa)
include_once ROOT_PATH . 'system/includes/code_footer.php';

// Dependências de footer do módulo BPM (mantendo seu padrão)
include_once ROOT_PATH . 'modules/bpm/includes/content_footer.php';

// Rodapé padrão e fechamento de </body></html>
include_once ROOT_PATH . 'system/includes/footer.php';
