<?php
require_once __DIR__ . '/../../includes/listas_guard.php';

$sql = "
  SELECT
    l.id, l.slug, l.title, l.description, l.active,
    SUM(CASE WHEN i.active=1 THEN 1 ELSE 0 END) AS active_count,
    SUM(CASE WHEN i.active=0 THEN 1 ELSE 0 END) AS inactive_count,
    COUNT(i.id) AS total_count
  FROM ga_list l
  LEFT JOIN ga_list_item i ON i.list_id = l.id
  GROUP BY l.id
  ORDER BY l.title ASC
";

$res = $conn->query($sql);
$data = [];
while ($row = $res->fetch_assoc()) $data[] = $row;

ga_json(['data'=>$data]);
