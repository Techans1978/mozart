<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . '/config/connect.php'; // $conn

// helper para storage (fallback/backup)
function mozart_storage(): string {
  $dir = __DIR__ . '/../storage/processes';
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
  return $dir;
}

$in = json_decode(file_get_contents('php://input'), true);
$name = isset($in['name']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', $in['name']) : '';
$version = isset($in['version']) ? max(1, (int)$in['version']) : 1;
$xml = $in['xml'] ?? '';
$status = (isset($in['status']) && in_array($in['status'], ['draft','published','archived'])) ? $in['status'] : 'draft';

if (!$name || !$xml) {
  http_response_code(400);
  echo json_encode(['error' => 'invalid payload']); exit;
}

/** 1) salva arquivo **/
$dir = mozart_storage();
$file = $dir . '/' . $name . '_v' . $version . '.bpmn';
if (@file_put_contents($file, $xml) === false) {
  http_response_code(500);
  echo json_encode(['error' => 'write failed']); exit;
}

/** manifest (fallback para listar/abrir sem DB) **/
$manifest = $dir . '/manifest.json';
$data = is_file($manifest) ? json_decode(file_get_contents($manifest), true) : ['items' => []];
$found = false;
foreach ($data['items'] as &$it) {
  if ($it['name'] === $name && (int)$it['version'] === $version) { $it['status'] = $status; $found = true; break; }
}
if (!$found) $data['items'][] = ['name' => $name, 'version' => $version, 'status' => $status];
file_put_contents($manifest, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

/** 2) salva banco (MySQLi) **/
try {
  // cria tabela se não existir (idempotente)
  $conn->query("CREATE TABLE IF NOT EXISTS bpm_processes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    version INT NOT NULL,
    status ENUM('draft','published','archived') DEFAULT 'draft',
    bpmn_xml MEDIUMTEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_name_version (name, version)
  )");

  $sql = "INSERT INTO bpm_processes (name, version, status, bpmn_xml)
          VALUES (?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE status = VALUES(status), bpmn_xml = VALUES(bpmn_xml)";
  $stmt = $conn->prepare($sql);
  if ($stmt) {
    $stmt->bind_param("siss", $name, $version, $status, $xml);
    $stmt->execute();
    $stmt->close();
  }
} catch (Throwable $e) {
  // ok: já está salvo em arquivo; seguimos
}

echo json_encode(['ok' => true, 'name' => $name, 'version' => $version, 'status' => $status]);

?>