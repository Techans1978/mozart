<?php
// /api/nfc-capture.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // ajuste para seu domínio em produção
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Use POST JSON']);
  exit;
}
$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!$data) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'JSON inválido']);
  exit;
}

// TODO: validar/autenticar, persistir no MySQL, correlacionar com ativo, etc.
file_put_contents(__DIR__.'/nfc_log.jsonl', json_encode($data, JSON_UNESCAPED_UNICODE).PHP_EOL, FILE_APPEND);

echo json_encode(['ok'=>true]);
