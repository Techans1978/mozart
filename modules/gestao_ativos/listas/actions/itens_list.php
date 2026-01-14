<?php
require_once __DIR__ . '/../../includes/listas_guard.php';

$listSlug = ga_trim($_GET['list'] ?? '');
$list = ga_list_by_slug($conn, $listSlug);
if (!$list) ga_json(['data'=>[]]);

$onlyRoot = (isset($_GET['root']) && (string)$_GET['root'] === '1');

if ($onlyRoot) {
  $st = $conn->prepare("
    SELECT i.id, i.name
    FROM ga_list_item i
    WHERE i.list_id=? AND i.parent_id IS NULL
    ORDER BY i.name ASC
  ");
  $st->bind_param("i", $list['id']);
  $st->execute();
  $rs = $st->get_result();
  $data = [];
  while ($r = $rs->fetch_assoc()) {
    $data[] = ['id'=>$r['id'], 'name'=>$r['name']];
  }
  $st->close();
  ga_json(['data'=>$data]);
}

// Lista completa com parent_name e level (level por “subida” simples)
$st = $conn->prepare("
  SELECT
    i.id, i.parent_id, i.name, i.description, i.active, i.sort_order,
    p.name AS parent_name
  FROM ga_list_item i
  LEFT JOIN ga_list_item p ON p.id = i.parent_id
  WHERE i.list_id=?
  ORDER BY i.sort_order ASC, i.name ASC
");
$st->bind_param("i", $list['id']);
$st->execute();
$rs = $st->get_result();

$rows = [];
while ($r = $rs->fetch_assoc()) $rows[] = $r;
$st->close();

// Calcula level (0 = raiz, 1 = filho, etc.) por subida (evita loop com limite)
$byId = [];
foreach ($rows as $r) $byId[(int)$r['id']] = $r;

$data = [];
foreach ($rows as $r) {
  $lvl = 0;
  $pid = $r['parent_id'] ? (int)$r['parent_id'] : 0;
  $guard = 0;
  while ($pid > 0 && isset($byId[$pid]) && $guard < 15) {
    $lvl++;
    $pid = $byId[$pid]['parent_id'] ? (int)$byId[$pid]['parent_id'] : 0;
    $guard++;
  }
  $r['level'] = $lvl;
  $data[] = $r;
}

ga_json(['data'=>$data]);
