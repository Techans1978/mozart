<?php
require_once __DIR__ . '/../../includes/listas_guard.php';

$listSlug = ga_trim($_POST['list'] ?? '');
$list = ga_list_by_slug($conn, $listSlug);
if (!$list) ga_json(['ok'=>false,'mensagem'=>'Lista inválida.'], 400);

$id = ga_int($_POST['id'] ?? 0);
if ($id <= 0) ga_json(['ok'=>false,'mensagem'=>'Item inválido.'], 400);

$st = $conn->prepare("UPDATE ga_list_item SET active = IF(active=1,0,1) WHERE id=? AND list_id=?");
$st->bind_param("ii", $id, $list['id']);
$ok = $st->execute();
$st->close();

ga_json(['ok'=>$ok, 'mensagem'=>$ok ? 'Status alterado.' : 'Falha ao alterar status.']);
