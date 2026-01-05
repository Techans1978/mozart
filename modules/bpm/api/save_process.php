<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . '/config/connect.php'; // $conn

$in = json_decode(file_get_contents('php://input'), true);

$code   = isset($in['code']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', $in['code']) : '';
$name   = isset($in['name']) ? trim($in['name']) : '';
$xml    = $in['xml'] ?? '';
$status = (isset($in['status']) && in_array($in['status'], ['draft','published','archived'], true))
  ? $in['status'] : 'draft';

if (!$code || !$xml) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'invalid payload']); exit;
}

if (!$name) $name = $code;

$conn->begin_transaction();

try {
  // 1) garante processo
  $sql = "SELECT id, current_version FROM bpm_process WHERE code = ? LIMIT 1";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $code);
  $stmt->execute();
  $res = $stmt->get_result();
  $proc = $res->fetch_assoc();
  $stmt->close();

  if (!$proc) {
    $sql = "INSERT INTO bpm_process (code, name, status, current_version) VALUES (?, ?, 'draft', 1)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $code, $name);
    $stmt->execute();
    $processId = (int)$stmt->insert_id;
    $currentVersion = 1;
    $stmt->close();
  } else {
    $processId = (int)$proc['id'];
    $currentVersion = max(1, (int)$proc['current_version']);

    // mantém nome atualizado (opcional, mas útil)
    $sql = "UPDATE bpm_process SET name = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $name, $processId);
    $stmt->execute();
    $stmt->close();
  }

  // 2) salva versão DRAFT da versão atual
  // tenta atualizar (se já existir draft)
  $sql = "SELECT id FROM bpm_process_version
          WHERE process_id = ? AND version = ? AND status = 'draft'
          ORDER BY id DESC LIMIT 1";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ii", $processId, $currentVersion);
  $stmt->execute();
  $res = $stmt->get_result();
  $ver = $res->fetch_assoc();
  $stmt->close();

  if ($ver) {
    $versionId = (int)$ver['id'];
    $sql = "UPDATE bpm_process_version
            SET bpmn_xml = ?, snapshot_json = NULL, updated_at = NOW()
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $xml, $versionId);
    $stmt->execute();
    $stmt->close();
  } else {
    $semver = $currentVersion . ".0.0";
    $sql = "INSERT INTO bpm_process_version
            (process_id, version, semver, status, bpmn_xml, snapshot_json)
            VALUES (?, ?, ?, 'draft', ?, NULL)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $processId, $currentVersion, $semver, $xml);
    $stmt->execute();
    $versionId = (int)$stmt->insert_id;
    $stmt->close();
  }

  // 3) aponta current_version_id
  $sql = "UPDATE bpm_process
          SET status = 'draft',
              current_version = ?,
              current_version_id = ?,
              updated_at = NOW()
          WHERE id = ?";
  $stmt = $conn->prepare($sql);
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
  'name' => $name
], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
