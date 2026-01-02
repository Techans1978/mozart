<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . '/config/connect.php'; // $conn

$sql = "
  SELECT
    p.id, p.code, p.name, p.status,
    p.current_version, p.current_version_id,
    p.updated_at
  FROM bpm_process p
  ORDER BY p.updated_at DESC, p.id DESC
";

$res = $conn->query($sql);
$out = [];
while ($row = $res->fetch_assoc())
  $out[] = $row;

echo json_encode(['ok' => true, 'processes' => $out], JSON_UNESCAPED_UNICODE);
