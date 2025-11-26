<?php
// modules/bpm/wizard_bpm.php
// Mozart BPM — Wizard (8 passos) embutido no layout padrão do sistema

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
$step = max(1, min(8, intval($_GET['step'] ?? 1)));
$state = $_SESSION['bpm_wizard'] ?? [
  'id'=>null,'nome'=>'','codigo'=>'','categoria'=>'','origem'=>'novo',
  'acessos'=>['grupos'=>[],'papeis'=>[],'perfis'=>[]],
  'bpmn_xml'=>'','forms'=>[],'teste_ia'=>['issues'=>[]],'teste_pessoa'=>['history'=>[]],
  'status'=>'draft'
];

// helper para montar URLs respeitando BASE_URL
function step_link($n){ return BASE_URL . '/modules/bpm/wizard_bpm.php?step=' . intval($n); }

$labels = [
  1=>'Categoria', 2=>'Origem do fluxo', 3=>'Acessos', 4=>'Desenho',
  5=>'Formulários', 6=>'Teste por IA', 7=>'Teste com Pessoa', 8=>'Salvar/Concluir'
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
              <?php for ($i=1; $i<=8; $i++): ?>
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
          <?php if ($step < 8): ?>
            <a class="btn btn-primary" href="<?= step_link($step + 1) ?>">Avançar →</a>
          <?php endif; ?>
          <a class="btn btn-link" href="<?= BASE_URL ?>/modules/bpm/list_bpm.php">Voltar à lista</a>
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
