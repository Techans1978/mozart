<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . '/config/connect.php'; // $conn

function jexit($code, $arr) { http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) jexit(400, ['error'=>'invalid_json']);

$processId = isset($in['process_id']) ? (int)$in['process_id'] : 0;
$versionId = isset($in['version_id']) ? (int)$in['version_id'] : 0;

if ($processId <= 0) jexit(400, ['error'=>'process_id_required']);

$stmt = $conn->prepare("SELECT id, current_version_id FROM bpm_process WHERE id=? LIMIT 1");
if (!$stmt) jexit(500, ['error'=>'db_prepare', 'detail'=>$conn->error]);
$stmt->bind_param("i", $processId);
$stmt->execute();
$proc = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$proc) jexit(404, ['error'=>'process_not_found']);

if ($versionId <= 0) $versionId = (int)$proc['current_version_id'];
if ($versionId <= 0) jexit(400, ['error'=>'no_current_version']);

$stmt = $conn->prepare("UPDATE bpm_process_version SET status='published', updated_at=NOW() WHERE id=? AND process_id=?");
if (!$stmt) jexit(500, ['error'=>'db_prepare', 'detail'=>$conn->error]);
$stmt->bind_param("ii", $versionId, $processId);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("UPDATE bpm_process SET status='published', updated_at=NOW() WHERE id=?");
if ($stmt) {
  $stmt->bind_param("i", $processId);
  $stmt->execute();
  $stmt->close();
}

echo json_encode(['ok'=>true, 'process_id'=>$processId, 'version_id'=>$versionId, 'status'=>'published'], JSON_UNESCAPED_UNICODE);
