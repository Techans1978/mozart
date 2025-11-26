<?php
// Recebe POST e dispara flow_version_id mapeado pela chave ?k=
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once __DIR__ . '/../includes/MozartFlowRunner.php';
$db = $conn ?? $mysqli ?? null; if(!$db){ http_response_code(500); echo 'no db'; exit; }

$key = $_GET['k'] ?? '';
if ($key===''){ http_response_code(400); echo 'missing key'; exit; }

$stmt = $db->prepare("SELECT flow_version_id FROM moz_flow_webhook WHERE hook_key=?");
$stmt->bind_param('s',$key); $stmt->execute();
$row = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$row){ http_response_code(404); echo 'not found'; exit; }

$payload = file_get_contents('php://input');
$vars = ['webhook'=>['headers'=>getallheaders(), 'body_raw'=>$payload, 'body_json'=>json_decode($payload, true)]];

$runner = new MozartFlowRunner($db);
$out = $runner->run((int)$row['flow_version_id'], $vars, 'dev');

header('Content-Type: application/json; charset=utf-8');
echo json_encode($out, JSON_UNESCAPED_UNICODE);
