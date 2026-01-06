<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . '/config/connect.php'; // $conn

$in = json_decode(file_get_contents('php://input'), true);
$code = isset($in['code']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', $in['code']) : '';
$version = isset($in['version']) && $in['version'] ? (int)$in['version'] : 0;

if (!$code) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing code']); exit; }

$stmt = $conn->prepare("SELECT id, code, name, current_version_id FROM bpm_process WHERE code=? LIMIT 1");
$stmt->bind_param("s", $code);
$stmt->execute();
$proc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$proc) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'process not found']); exit; }

$processId = (int)$proc['id'];

if ($version > 0) {
  $stmt = $conn->prepare("SELECT id, version, status FROM bpm_process_version WHERE process_id=? AND version=? ORDER BY id DESC LIMIT 1");
  $stmt->bind_param("ii", $processId, $version);
} else {
  $vId = (int)$proc['current_version_id'];
  $stmt = $conn->prepare("SELECT id, version, status FROM bpm_process_version WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $vId);
}
$stmt->execute();
$ver = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ver) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'version not found']); exit; }

$versionId = (int)$ver['id'];

// ✅ FASE 5: busca XML do asset
$stmt = $conn->prepare("SELECT content_text FROM bpm_bpmn_asset WHERE version_id=? AND type='bpmn_xml' ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $versionId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$xml = $row['content_text'] ?? '';

// fallback legado (opcional)
if (!$xml) {
  $stmt = $conn->prepare("SELECT bpmn_xml FROM bpm_process_version WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $versionId);
  $stmt->execute();
  $xml = $stmt->get_result()->fetch_assoc()['bpmn_xml'] ?? '';
  $stmt->close();
}

if (!$xml) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'xml not found (asset missing)']); exit; }

echo json_encode([
  'ok' => true,
  'process_id' => $processId,
  'code' => $proc['code'],
  'name' => $proc['name'],
  'version_id' => $versionId,
  'version' => (int)$ver['version'],
  'status' => $ver['status'],
  'xml' => $xml
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
