<?php
require_once __DIR__ . '/../../includes/listas_guard.php';

$listSlug  = ga_trim($_POST['list'] ?? '');
$list = ga_list_by_slug($conn, $listSlug);
if (!$list) { ga_flash_set("Lista inválida.", "danger"); ga_redirect("../listas-index.php"); }

$raw       = ga_trim($_POST['bulk_text'] ?? '');
$descBase  = ga_trim($_POST['description_base'] ?? '');
$parent_id = ($_POST['parent_id'] ?? '') === '' ? null : ga_int($_POST['parent_id']);

if ($raw === '') {
  ga_flash_set("Cole o texto para importar.", "warning");
  ga_redirect("../listas-itens.php?list=".$list['slug']);
}

$parts = preg_split('/[,\n;]+/', $raw);
$items = [];
foreach ($parts as $p) {
  $n = trim($p);
  if ($n !== '') $items[] = $n;
}
$items = array_values(array_unique($items));

$inserted = 0; $skipped = 0;

$sql = "INSERT INTO ga_list_item (list_id,parent_id,name,description,active,sort_order)
        VALUES (?,?,?,?,1,0)";
$st = $conn->prepare($sql);

foreach ($items as $name) {
  $desc = $descBase !== '' ? $descBase : null;
  $st->bind_param("iiss", $list['id'], $parent_id, $name, $desc);
  $ok = $st->execute();
  if ($ok) $inserted++;
  else {
    if ($conn->errno == 1062) $skipped++;
    else $skipped++;
  }
}
$st->close();

ga_flash_set("Importação concluída. Inseridos: {$inserted} | Ignorados: {$skipped}", "success");
ga_redirect("../listas-itens.php?list=".$list['slug']);
