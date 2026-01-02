<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . '/config/connect.php'; // $conn (mysqli)

function jexit($code, $arr) { http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) jexit(400, ['error'=>'invalid_json']);

$processId = isset($in['process_id']) ? (int)$in['process_id'] : 0;
$code      = isset($in['code']) ? trim((string)$in['code']) : '';
$name      = isset($in['name']) ? trim((string)$in['name']) : '';
$status    = isset($in['status']) && in_array($in['status'], ['draft','published'], true) ? $in['status'] : 'draft';

$xml       = isset($in['bpmn_xml']) ? (string)$in['bpmn_xml'] : '';
$elements  = $in['elements'] ?? null; // pode ser array/obj
$createdBy = isset($in['created_by']) ? (int)$in['created_by'] : null;

if ($xml === '') jexit(400, ['error'=>'bpmn_xml_required']);

// se não vier code/name, a gente ainda deixa salvar (para não travar),
// mas para a Fase 2 ficar “bonita”, o JS vai passar code+name.
if ($code !== '') {
  $code = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $code);
}
if ($name === '' && $code !== '') $name = $code;

// ---------- 1) Resolver/Criar bpm_process ----------
if ($processId > 0) {
  $stmt = $conn->prepare("SELECT id, code, name, status, current_version_id FROM bpm_process WHERE id=? LIMIT 1");
  if (!$stmt) jexit(500, ['error'=>'db_prepare', 'detail'=>$conn->error]);
  $stmt->bind_param("i", $processId);
  $stmt->execute();
  $proc = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$proc) jexit(404, ['error'=>'process_not_found']);
} else {
  // tenta localizar por code
  if ($code !== '') {
    $stmt = $conn->prepare("SELECT id, code, name, status, current_version_id FROM bpm_process WHERE code=? LIMIT 1");
    if (!$stmt) jexit(500, ['error'=>'db_prepare', 'detail'=>$conn->error]);
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $proc = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($proc) $processId = (int)$proc['id'];
  }

  // cria se não achou
  if ($processId <= 0) {
    if ($code === '') $code = 'process_' . date('Ymd_His');
    if ($name === '') $name = $code;

    $stmt = $conn->prepare("INSERT INTO bpm_process (code, name, status, created_by) VALUES (?, ?, 'draft', ?)");
    if (!$stmt) jexit(500, ['error'=>'db_prepare', 'detail'=>$conn->error]);
    // created_by pode ser NULL
    if ($createdBy === null) {
      $null = null;
      $stmt->bind_param("ssi", $code, $name, $null); // workaround: abaixo vamos setar null via set_null se precisar
    }
    // jeito simples: se não tem created_by, manda 0 (ou ajuste para sua regra)
    $cb = $createdBy ?? 0;
    $stmt->bind_param("ssi", $code, $name, $cb);
    $ok = $stmt->execute();
    if (!$ok) jexit(500, ['error'=>'db_insert_process', 'detail'=>$stmt->error]);
    $processId = (int)$stmt->insert_id;
    $stmt->close();

    $proc = ['id'=>$processId, 'code'=>$code, 'name'=>$name, 'status'=>'draft', 'current_version_id'=>null];
  }
}

// atualizar code/name se vierem (sem quebrar nada)
if ($code !== '' || $name !== '') {
  $newCode = $code !== '' ? $code : $proc['code'];
  $newName = $name !== '' ? $name : $proc['name'];
  $stmt = $conn->prepare("UPDATE bpm_process SET code=?, name=?, updated_at=NOW() WHERE id=?");
  if ($stmt) {
    $stmt->bind_param("ssi", $newCode, $newName, $processId);
    $stmt->execute();
    $stmt->close();
  }
}

// ---------- 2) Decidir: atualizar draft atual ou criar nova versão ----------
$currentVersionId = isset($proc['current_version_id']) ? (int)$proc['current_version_id'] : 0;
$versionRow = null;

if ($currentVersionId > 0) {
  $stmt = $conn->prepare("SELECT id, process_id, version, status FROM bpm_process_version WHERE id=? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param("i", $currentVersionId);
    $stmt->execute();
    $versionRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  }
}

// se a versão atual é draft -> atualiza ela (mudança mínima e segura)
$useVersionId = 0;
$useVersionNo = 1;

if ($versionRow && $versionRow['status'] === 'draft') {
  $useVersionId = (int)$versionRow['id'];
  $useVersionNo = (int)$versionRow['version'];

  $elementsJson = $elements !== null ? json_encode($elements, JSON_UNESCAPED_UNICODE) : null;

  $stmt = $conn->prepare("UPDATE bpm_process_version
                          SET bpmn_xml=?, elements_json=?, updated_at=NOW()
                          WHERE id=?");
  if (!$stmt) jexit(500, ['error'=>'db_prepare', 'detail'=>$conn->error]);
  $stmt->bind_param("ssi", $xml, $elementsJson, $useVersionId);
  $ok = $stmt->execute();
  if (!$ok) jexit(500, ['error'=>'db_update_version', 'detail'=>$stmt->error]);
  $stmt->close();
} else {
  // cria nova versão draft
  $stmt = $conn->prepare("SELECT COALESCE(MAX(version),0) AS mv FROM bpm_process_version WHERE process_id=?");
  if (!$stmt) jexit(500, ['error'=>'db_prepare', 'detail'=>$conn->error]);
  $stmt->bind_param("i", $processId);
  $stmt->execute();
  $mv = (int)($stmt->get_result()->fetch_assoc()['mv'] ?? 0);
  $stmt->close();

  $useVersionNo = $mv + 1;
  $elementsJson = $elements !== null ? json_encode($elements, JSON_UNESCAPED_UNICODE) : null;

  $stmt = $conn->prepare("INSERT INTO bpm_process_version (process_id, version, status, bpmn_xml, elements_json, created_by)
                          VALUES (?, ?, 'draft', ?, ?, ?)");
  if (!$stmt) jexit(500, ['error'=>'db_prepare', 'detail'=>$conn->error]);
  $cb = $createdBy ?? 0;
  $stmt->bind_param("iissi", $processId, $useVersionNo, $xml, $elementsJson, $cb);
  $ok = $stmt->execute();
  if (!$ok) jexit(500, ['error'=>'db_insert_version', 'detail'=>$stmt->error]);
  $useVersionId = (int)$stmt->insert_id;
  $stmt->close();

  // aponta o processo para a versão corrente
  $stmt = $conn->prepare("UPDATE bpm_process SET current_version_id=?, status='draft', updated_at=NOW() WHERE id=?");
  if ($stmt) {
    $stmt->bind_param("ii", $useVersionId, $processId);
    $stmt->execute();
    $stmt->close();
  }
}

echo json_encode([
  'ok' => true,
  'process_id' => $processId,
  'code' => $code !== '' ? $code : ($proc['code'] ?? ''),
  'name' => $name !== '' ? $name : ($proc['name'] ?? ''),
  'version_id' => $useVersionId,
  'version' => $useVersionNo,
  'status' => 'draft'
], JSON_UNESCAPED_UNICODE);
