<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . '/config/connect.php';

$processId = isset($_GET['process_id']) ? (int) $_GET['process_id'] : 0;
$version = isset($_GET['version']) ? max(1, (int) $_GET['version']) : 0;

if (!$processId) {
  http_response_code(400);
  echo json_encode(['error' => 'process_id required']);
  exit;
}

if ($version > 0) {
  $stmt = $conn->prepare("
    SELECT v.id, v.version, v.status, v.bpmn_xml, v.snapshot_json
    FROM bpm_process_version v
    WHERE v.process_id = ? AND v.version = ?
    LIMIT 1
  ");
  $stmt->bind_param("ii", $processId, $version);
} else {
  $stmt = $conn->prepare("
    SELECT v.id, v.version, v.status, v.bpmn_xml, v.snapshot_json
    FROM bpm_process p
    JOIN bpm_process_version v ON v.id = p.current_version_id
    WHERE p.id = ?
    LIMIT 1
  ");
  $stmt->bind_param("i", $processId);
}

$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
  http_response_code(404);
  echo json_encode(['error' => 'not found']);
  exit;
}

echo json_encode([
  'ok' => true,
  'process_id' => $processId,
  'version_id' => (int) $row['id'],
  'version' => (int) $row['version'],
  'status' => $row['status'],
  'xml' => $row['bpmn_xml'],
  'snapshot' => $row['snapshot_json'] ? json_decode($row['snapshot_json'], true) : null
], JSON_UNESCAPED_UNICODE);
