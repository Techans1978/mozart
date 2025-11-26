<?php
// tools/scan_bpm_inventory.php
// Uso: php tools/scan_bpm_inventory.php > bpm_inventory.json
$root = realpath(__DIR__ . '/..'); // raiz do projeto
$bpmDir = $root . '/modules/bpm';

if (!is_dir($bpmDir)) { fwrite(STDERR, "Pasta modules/bpm não encontrada.\n"); exit(1); }

function allFiles($dir, $exts=['php','js','css','html']) {
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
  foreach ($it as $f) {
    /** @var SplFileInfo $f */
    if (!$f->isFile()) continue;
    $ext = strtolower($f->getExtension());
    if (in_array($ext, $exts, true)) yield $f->getPathname();
  }
}

function rel($abs, $root) { return ltrim(str_replace('\\','/', substr($abs, strlen($root))), '/'); }

$files = iterator_to_array(allFiles($bpmDir));
$inventory = [];

foreach ($files as $abs) {
  $rel = rel($abs, $root);
  $content = @file_get_contents($abs);
  $type = 'other';
  if (preg_match('#/api/#', $rel)) $type = 'api';
  elseif (preg_match('#/includes/#', $rel)) $type = 'include';
  elseif (preg_match('#\.php$#', $rel)) $type = 'page';

  // heurísticas simples por nome/trechos
  $role = null;
  $name = basename($rel);
  if (preg_match('/process|listar|list_bpm|bpm_list/i', $name)) $role = 'list_process';
  if (preg_match('/tarefa|task/i', $name)) $role = 'task_inbox';
  if (preg_match('/instancia|detalh|history|historico/i', $name)) $role = 'instance_detail';
  if (preg_match('/wizard|modeler|designer/i', $name)) $role = 'wizard';
  if (preg_match('/viewer|diagram|bpmn/i', $name)) $role = 'diagram_viewer';

  $inventory[$rel] = [
    'type' => $type,
    'role' => $role,
    'size' => strlen($content ?: ''),
    'md5'  => md5($content ?: ''),
    'refs' => []
  ];
}

// procurar referências pelo projeto inteiro
foreach (allFiles($root) as $abs) {
  $relAll = rel($abs, $root);
  // pular a própria pasta do bpm para achar onde é consumido de fora também
  $content = @file_get_contents($abs);
  if ($content === false) continue;
  foreach ($inventory as $relBpm => $_) {
    if (strpos($content, $relBpm) !== false) {
      $inventory[$relBpm]['refs'][] = $relAll;
    }
  }
}

// detectar duplicidades por papel (role) e por hash
$byRole = [];
$byMd5  = [];
foreach ($inventory as $relBpm => $data) {
  if ($data['role']) $byRole[$data['role']][] = $relBpm;
  $byMd5[$data['md5']][] = $relBpm;
}

// sugestão de nomes canônicos
$canonical = [
  'list_process'   => 'modules/bpm/processos-listar.php',
  'task_inbox'     => 'modules/bpm/tarefas-minhas.php',
  'instance_detail'=> 'modules/bpm/instancia-detalhes.php',
  'diagram_viewer' => 'modules/bpm/viewer.php',
  'wizard'         => 'modules/bpm/wizard/index.php'
];

$out = [
  'root'      => $root,
  'bpm_dir'   => $bpmDir,
  'canonical' => $canonical,
  'files'     => $inventory,
  'duplicates'=> [
    'by_role' => $byRole,
    'by_md5'  => array_filter($byMd5, fn($arr)=>count($arr)>1),
  ],
];

echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), "\n";
