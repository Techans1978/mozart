<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/config.php';
require_once ROOT_PATH . '/system/config/connect.php';

$result = ['items' => []];

/** 1) tenta pelo banco **/
try {
  // se a tabela existir, lista
  if ($conn->query("SHOW TABLES LIKE 'bpm_processes'")->num_rows > 0) {
    $q = $conn->query("SELECT name, version, status FROM bpm_processes ORDER BY name, version");
    if ($q) {
      while ($row = $q->fetch_assoc()) { $result['items'][] = $row; }
      echo json_encode($result); exit;
    }
  }
} catch (Throwable $e) {
  // fallback
}

/** 2) fallback: manifest.json **/
$dir = __DIR__ . '/../storage/processes';
if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
$manifest = $dir . '/manifest.json';
if (is_file($manifest)) {
  $tmp = json_decode(file_get_contents($manifest), true);
  if (is_array($tmp) && isset($tmp['items'])) { $result = $tmp; }
}
echo json_encode($result);

?>