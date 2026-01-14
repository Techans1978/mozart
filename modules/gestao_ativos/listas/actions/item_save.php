<?php
require_once __DIR__ . '/../../includes/listas_guard.php';

$listSlug = ga_trim($_POST['list'] ?? '');
$list = ga_list_by_slug($conn, $listSlug);
if (!$list) { ga_flash_set("Lista inválida.", "danger"); ga_redirect("../listas-index.php"); }

$id        = ga_int($_POST['id'] ?? 0);
$name      = ga_trim($_POST['name'] ?? '');
$desc      = ga_trim($_POST['description'] ?? '');
$active    = isset($_POST['active']) ? (int)$_POST['active'] : 1;
$sort      = ga_int($_POST['sort_order'] ?? 0);
$parent_id = ($_POST['parent_id'] ?? '') === '' ? null : ga_int($_POST['parent_id']);

if ($name === '') {
  ga_flash_set("Nome é obrigatório.", "warning");
  ga_redirect("../listas-form.php?list=".$list['slug'].($id?("&id=".$id):""));
}

try {
  if ($id > 0) {
    $sql = "UPDATE ga_list_item
            SET parent_id=?, name=?, description=?, active=?, sort_order=?
            WHERE id=? AND list_id=?";
    $st = $conn->prepare($sql);
    $st->bind_param("issiiii", $parent_id, $name, $desc, $active, $sort, $id, $list['id']);
    $ok = $st->execute();
    $err = $st->error;
    $st->close();
  } else {
    $sql = "INSERT INTO ga_list_item (list_id,parent_id,name,description,active,sort_order)
            VALUES (?,?,?,?,?,?)";
    $st = $conn->prepare($sql);
    $st->bind_param("iissii", $list['id'], $parent_id, $name, $desc, $active, $sort);
    $ok = $st->execute();
    $err = $st->error;
    $st->close();
  }

  if (!$ok) throw new Exception($err ?: "Falha ao salvar.");

  ga_flash_set("Item salvo com sucesso.", "success");
  ga_redirect("../listas-itens.php?list=".$list['slug']);

} catch (Throwable $e) {
  if ($conn->errno == 1062) ga_flash_set("Já existe um item com esse nome nesse mesmo nível (pai).", "warning");
  else ga_flash_set("Erro ao salvar: ".$e->getMessage(), "danger");

  ga_redirect("../listas-form.php?list=".$list['slug'].($id?("&id=".$id):""));
}
