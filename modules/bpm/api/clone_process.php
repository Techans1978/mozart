<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . '/config/connect.php'; // $conn

function jexit($code, $arr) { http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) jexit(400, ['error'=>'invalid_json']);

$sourceId = isset($in['process_id']) ? (int)$in['process_id'] : 0;
$newCode  = isset($in['new_code']) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', trim((string)$in['new_code'])) : '';
$newName  = isset($in['new_name']) ? trim((string)$in['new_name']) : '';
$createdBy = isset($in['created_by']) ? (int)$in['created_by'] : 0;

if ($sourceId <= 0) jexit(400, ['error'=>'process_id_required']);

$stmt = $conn->prepare("SELECT id, code, name, current_version_id FROM bpm_process WHERE id=? LIMIT 1");
if (!$stmt) jexit(500, ['error'=>'db_prepare', 'detail'=>$conn->error]);
$stmt->bind_param("i", $sourceId);
$stmt->execute();
$src = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$src) jexit(404, ['error'=>'source_not_found']);

$verId = (int)$src['current_version_id'];
if ($verId <= 0) jexit(400, ['error'=>'source_has_no_version']);

$stmt = $conn->prepare("SELECT bpmn_xml, elements_json FROM bpm_process_version WHERE id=? AND process_id=? LIMIT 1");
if (!$stmt) jexit(500, ['error'=>'db_prepare', 'detail'=>$conn->error]);
$stmt->bind_param("ii", $verId, $sourceId);
$stmt->execute();
$ver = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$ver) jexit(404, ['error'=>'source_version_not_found']);

if ($newCode === '') $newCode = $src['code'] . '_copy_' . date('Ymd_His');
if ($newName === '') $newName = $src['name'] . ' (Cópia)';

$stmt = $conn->prepare("INSERT INTO bpm_process (code, name, status, created_by) VALUES (?, ?, 'draft', ?)");
if (!$stmt) jexit(500, ['error'=>'db_prepare', 'detail'=>$conn->error]);
$stmt->bind_param("ssi", $newCode, $newName, $createdBy);
$ok = $stmt->execute();
if (!$ok) jexit(500, ['error'=>'db_insert_process', 'detail'=>$stmt->error]);
$newProcessId = (int)$stmt->insert_id;
$stmt->close();

$stmt = $conn->prepare("INSERT INTO bpm_process_version (process_id, version, status, bpmn_xml, elements_json, created_by)
                        VALUES (?, 1, 'draft', ?, ?, ?)");
if (!$stmt) jexit(500, ['error'=>'db_prepare', 'detail'=>$conn->error]);
$stmt->bind_param("issi", $newProcessId, $ver['bpmn_xml'], $ver['elements_json'], $createdBy);
$ok = $stmt->execute();
if (!$ok) jexit(500, ['error'=>'db_insert_version', 'detail'=>$stmt->error]);
$newVersionId = (int)$stmt->insert_id;
$stmt->close();

$stmt = $conn->prepare("UPDATE bpm_process SET current_version_id=? WHERE id=?");
if ($stmt) { $stmt->bind_param("ii", $newVersionId, $newProcessId); $stmt->execute(); $stmt->close(); }

echo json_encode([
  'ok'=>true,
  'process_id'=>$newProcessId,
  'code'=>$newCode,
  'name'=>$newName,
  'version_id'=>$newVersionId,
  'version'=>1,
  'status'=>'draft'
], JSON_UNESCAPED_UNICODE);
