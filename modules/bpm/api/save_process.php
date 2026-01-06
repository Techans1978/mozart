<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . '/config/connect.php'; // $conn

$in = json_decode(file_get_contents('php://input'), true);

$code = isset($in['code']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', $in['code']) : '';
$name = isset($in['name']) ? trim((string)$in['name']) : '';
$xml  = (string)($in['xml'] ?? '');

if (!$code || !$xml) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'invalid payload']); exit;
}
if (!$name) $name = $code;

// normaliza XML (remove BOM)
$xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml);
$sizeBytes = strlen($xml);
$sha1      = sha1($xml);

$conn->begin_transaction();

try {
  // 1) garante processo
  $stmt = $conn->prepare("SELECT id, current_version FROM bpm_process WHERE code=? LIMIT 1");
  $stmt->bind_param("s", $code);
  $stmt->execute();
  $proc = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$proc) {
    $stmt = $conn->prepare("INSERT INTO bpm_process (code, name, status, current_version) VALUES (?, ?, 'draft', 1)");
    $stmt->bind_param("ss", $code, $name);
    $stmt->execute();
    $processId = (int)$stmt->insert_id;
    $currentVersion = 1;
    $stmt->close();
  } else {
    $processId = (int)$proc['id'];
    $currentVersion = max(1, (int)$proc['current_version']);

    $stmt = $conn->prepare("UPDATE bpm_process SET name=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("si", $name, $processId);
    $stmt->execute();
    $stmt->close();
  }

  // 2) pega draft da versão atual (ou cria)
  $stmt = $conn->prepare("
    SELECT id FROM bpm_process_version
    WHERE process_id=? AND version=? AND status='draft'
    ORDER BY id DESC LIMIT 1
  ");
  $stmt->bind_param("ii", $processId, $currentVersion);
  $stmt->execute();
  $ver = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($ver) {
    $versionId = (int)$ver['id'];

    $stmt = $conn->prepare("
      UPDATE bpm_process_version
      SET bpmn_xml=NULL, snapshot_json=NULL, checksum_sha1=?, size_bytes=?, updated_at=NOW()
      WHERE id=?
    ");
    $stmt->bind_param("sii", $sha1, $sizeBytes, $versionId);
    $stmt->execute();
    $stmt->close();

  } else {
    $semver = $currentVersion . ".0.0";

    $stmt = $conn->prepare("
      INSERT INTO bpm_process_version
        (process_id, version, semver, status, bpmn_xml, snapshot_json, checksum_sha1, size_bytes)
      VALUES
        (?, ?, ?, 'draft', NULL, NULL, ?, ?)
    ");
    $stmt->bind_param("iisssi", $processId, $currentVersion, $semver, $sha1, $sizeBytes);
    $stmt->execute();
    $versionId = (int)$stmt->insert_id;
    $stmt->close();
  }

  // 3) ✅ XML como asset oficial
  $stmt = $conn->prepare("DELETE FROM bpm_bpmn_asset WHERE version_id=? AND type='bpmn_xml'");
  $stmt->bind_param("i", $versionId);
  $stmt->execute();
  $stmt->close();

  $stmt = $conn->prepare("
    INSERT INTO bpm_bpmn_asset (version_id, type, content_text, content_blob, hash_sha1)
    VALUES (?, 'bpmn_xml', ?, NULL, ?)
  ");
  $stmt->bind_param("iss", $versionId, $xml, $sha1);
  $stmt->execute();
  $stmt->close();

  // 4) aponta current_version_id
  $stmt = $conn->prepare("
    UPDATE bpm_process
    SET status='draft',
        current_version=?,
        current_version_id=?,
        updated_at=NOW()
    WHERE id=?
  ");
  $stmt->bind_param("iii", $currentVersion, $versionId, $processId);
  $stmt->execute();
  $stmt->close();

  $conn->commit();

  echo json_encode([
    'ok' => true,
    'process_id' => $processId,
    'version_id' => $versionId,
    'version' => $currentVersion,
    'status' => 'draft',
    'code' => $code,
    'name' => $name,
    'checksum_sha1' => $sha1,
    'size_bytes' => $sizeBytes
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
