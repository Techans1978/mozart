<?php
require_once __DIR__ . '/../../includes/listas_guard.php';

$listSlug = ga_trim($_POST['list'] ?? '');
$list = ga_list_by_slug($conn, $listSlug);
if (!$list) { ga_flash_set("Lista inválida.", "danger"); ga_redirect("../listas-index.php"); }

if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
  ga_flash_set("Falha no upload do CSV.", "danger");
  ga_redirect("../listas-itens.php?list=".$list['slug']);
}

$tmp = $_FILES['csv']['tmp_name'];
$handle = fopen($tmp, 'r');
if (!$handle) {
  ga_flash_set("Não foi possível ler o CSV.", "danger");
  ga_redirect("../listas-itens.php?list=".$list['slug']);
}

$firstLine = fgets($handle);
rewind($handle);
$sep = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

$header = fgetcsv($handle, 0, $sep);
$map = [];
foreach ($header as $i => $col) {
  $c = strtolower(trim($col));
  $map[$c] = $i;
}

$idxName = $map['name'] ?? $map['nome'] ?? null;
$idxDesc = $map['description'] ?? $map['descricao'] ?? null;
$idxParentName = $map['parent'] ?? $map['pai'] ?? null;
$idxParentId   = $map['parent_id'] ?? null;

if ($idxName === null) {
  ga_flash_set("CSV inválido. Precisa da coluna name (ou nome).", "warning");
  ga_redirect("../listas-itens.php?list=".$list['slug']);
}

$insert = $conn->prepare("INSERT INTO ga_list_item (list_id,parent_id,name,description,active,sort_order)
                          VALUES (?,?,?,?,1,0)");

$selParentByName = $conn->prepare("SELECT id FROM ga_list_item
                                   WHERE list_id=? AND parent_id IS NULL AND name=? LIMIT 1");

$inserted=0; $skipped=0;

while (($row = fgetcsv($handle, 0, $sep)) !== false) {
  $name = trim($row[$idxName] ?? '');
  if ($name === '') { $skipped++; continue; }

  $desc = $idxDesc !== null ? trim($row[$idxDesc] ?? '') : '';
  $desc = ($desc === '') ? null : $desc;

  $parent_id = null;

  if ($idxParentId !== null) {
    $pid = (int)($row[$idxParentId] ?? 0);
    if ($pid > 0) $parent_id = $pid;
  } elseif ($idxParentName !== null) {
    $pname = trim($row[$idxParentName] ?? '');
    if ($pname !== '') {
      $selParentByName->bind_param("is", $list['id'], $pname);
      $selParentByName->execute();
      $res = $selParentByName->get_result()->fetch_assoc();
      if ($res) $parent_id = (int)$res['id'];
    }
  }

  $insert->bind_param("iiss", $list['id'], $parent_id, $name, $desc);
  $ok = $insert->execute();
  if ($ok) $inserted++;
  else {
    if ($conn->errno == 1062) $skipped++;
    else $skipped++;
  }
}

fclose($handle);
$insert->close();
$selParentByName->close();

ga_flash_set("CSV importado. Inseridos: {$inserted} | Ignorados: {$skipped}", "success");
ga_redirect("../listas-itens.php?list=".$list['slug']);
