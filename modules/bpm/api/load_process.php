<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . '/config/connect.php'; // $conn

$name = isset($_GET['name']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', $_GET['name']) : '';
$version = isset($_GET['version']) ? max(1, (int)$_GET['version']) : 1;

if (!$name) { http_response_code(400); echo json_encode(['error'=>'name required']); exit; }

/** 1) banco primeiro **/
try {
  if ($conn->query("SHOW TABLES LIKE 'bpm_processes'")->num_rows > 0) {
    $stmt = $conn->prepare("SELECT bpmn_xml FROM bpm_processes WHERE name = ? AND version = ? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param("si", $name, $version);
      $stmt->execute();
      $stmt->bind_result($bpmn);
      if ($stmt->fetch() && $bpmn) {
        $stmt->close();
        echo json_encode(['xml' => $bpmn, 'name' => $name, 'version' => $version]); exit;
      }
      $stmt->close();
    }
  }
} catch (Throwable $e) {
  // fallback
}

/** 2) fallback arquivo **/
$dir = __DIR__ . '/../storage/processes';
$file = $dir . '/' . $name . '_v' . $version . '.bpmn';
if (!is_file($file)) { http_response_code(404); echo json_encode(['error'=>'not found']); exit; }
$xml = file_get_contents($file);
echo json_encode(['xml' => $xml, 'name' => $name, 'version' => $version]);

?>