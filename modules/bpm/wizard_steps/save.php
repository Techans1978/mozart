<?php
// modules/bpm/wizard_steps/save.php
// Wizard BPM (7 passos) - persiste estado em session e salva processo quando aplicavel.

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../_lib/bpm_store.php';

$store = new BpmStore();
$state = $_SESSION['bpm_wizard'] ?? [];

$step = max(1, min(7, intval($_GET['step'] ?? 1)));

switch ($step) {

  // 1) Informacoes
  case 1:
    $state['nome']        = trim((string)($_POST['nome'] ?? ''));
    $state['codigo']      = trim((string)($_POST['codigo'] ?? ''));
    $catId = intval($_POST['categoria_id'] ?? 0);
    $state['categoria_id'] = ($catId > 0) ? $catId : null;
    break;

  // 2) Acessos
  case 2:
    // Aqui a view manda CSV (1 linha), mantemos como array
    $state['acessos']['grupos'] = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['grupos'] ?? '')))));
    $state['acessos']['papeis'] = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['papeis'] ?? '')))));
    $state['acessos']['perfis'] = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['perfis'] ?? '')))));
    break;

  // 4) Salvar rascunho
  case 4:
    $state['status'] = 'draft';

    $proc = [
      'id'       => $state['id'] ?? null,
      'name'     => $state['nome'] ?? 'Sem nome',
      'code'     => ($state['codigo'] ?? '') ?: null,
      // Mantem compatibilidade: o store antigo usa 'category'
      'category' => $state['categoria_id'] ?? null,
      'status'   => 'draft',
      'version'  => 1,
      'active'   => 0,
      'bpmn_xml' => $state['bpmn_xml'] ?? '',
      'forms'    => $state['forms'] ?? [],
    ];

    $saved = $store->saveProcess($proc);
    if (is_array($saved) && !empty($saved['id'])) {
      $state['id'] = $saved['id'];
    }
    break;

  // 5) Publicar
  case 5:
    $state['status'] = 'published';

    $proc = [
      'id'       => $state['id'] ?? null,
      'name'     => $state['nome'] ?? 'Sem nome',
      'code'     => ($state['codigo'] ?? '') ?: null,
      'category' => $state['categoria_id'] ?? null,
      'status'   => 'published',
      'version'  => 1,
      'active'   => 1,
      'bpmn_xml' => $state['bpmn_xml'] ?? '',
      'forms'    => $state['forms'] ?? [],
    ];

    $saved = $store->saveProcess($proc);
    if (is_array($saved) && !empty($saved['id'])) {
      $state['id'] = $saved['id'];
    }
    break;

  // 7) Limpar wizard
  case 7:
    unset($_SESSION['bpm_wizard']);
    header('Location: /modules/bpm/wizard_bpm.php?step=1');
    exit;
}

$_SESSION['bpm_wizard'] = $state;

// Redireciona para o proximo passo
$next = min(7, $step + 1);
header('Location: /modules/bpm/wizard_bpm.php?step=' . $next);
exit;
