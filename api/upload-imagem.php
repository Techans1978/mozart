<?php
// api/upload-imagem.php — Upload handler para o TinyMCE
// Retorna JSON { "location": "/uploads/...." }
// Obs.: ajuste permissões de escrita da pasta public/uploads/ no seu servidor.

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// ==== Configurações ====
$MAX_MB = 8; // limite por arquivo
$ALLOW = ['image/jpeg','image/png','image/gif','image/webp']; // SVG desabilitado por segurança
$UPLOAD_ROOT = __DIR__ . '/../public/uploads';

// ==== Helpers ====
function jexit($status, $payload){
  http_response_code($status);
  echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jexit(405, ['error' => 'Método não permitido']);
}

if (!isset($_FILES['file'])) {
  jexit(400, ['error' => 'Campo de arquivo ausente (file)']);
}

$err = $_FILES['file']['error'] ?? UPLOAD_ERR_OK;
if ($err !== UPLOAD_ERR_OK) {
  jexit(400, ['error' => 'Falha no upload (err='.$err.')']);
}

$tmp = $_FILES['file']['tmp_name'];
$size = (int)($_FILES['file']['size'] ?? 0);
if ($size <= 0 || $size > ($MAX_MB * 1024 * 1024)) {
  jexit(413, ['error' => 'Arquivo muito grande. Máximo: '.$MAX_MB.'MB']);
}

// Valida MIME real
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($tmp) ?: 'application/octet-stream';
if (!in_array($mime, $ALLOW, true)) {
  jexit(415, ['error' => 'Tipo não permitido: '.$mime]);
}

// Gera caminho por data
$subdir = date('Y/m');
$dir = rtrim($UPLOAD_ROOT, '/').'/'.$subdir;
if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
  jexit(500, ['error' => 'Não foi possível criar diretório de upload']);
}

// Nome seguro e único
$orig = $_FILES['file']['name'] ?? 'arquivo';
$ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION) ?: 'bin');
$base = preg_replace('/[^a-z0-9_-]+/i', '-', pathinfo($orig, PATHINFO_FILENAME));
$base = trim($base, '-');
if ($base === '') $base = 'img';
$final = sprintf('%s-%s.%s', $base, bin2hex(random_bytes(4)), $ext);
$dest = $dir.'/'.$final;

if (!@move_uploaded_file($tmp, $dest)) {
  jexit(500, ['error' => 'Falha ao salvar o arquivo']);
}

// URL pública (ajuste caso rode em subdiretório)
$publicUrl = '/uploads/'.$subdir.'/'.$final;

// Resposta para o TinyMCE
jexit(200, ['location' => $publicUrl]);
