<?php
// public/modules/helpdesk/api/Utils.php
namespace HD\Api;

class Utils {
  public static function json($data, int $code=200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  public static function jsonBody(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
  }

  public static function userId(): ?int {
    // Adapte conforme sua autenticação Mozart
    return isset($_SESSION['usuario']['id']) ? (int)$_SESSION['usuario']['id'] : null;
  }

  public static function pathAfter(string $prefix): string {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $pos = strpos($uri, $prefix);
    if ($pos===false) return '/';
    return substr($uri, $pos + strlen($prefix));
  }

  public static function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }

  public static function dbAssert($conn): void {
    if (!($conn instanceof \mysqli)) {
      throw new \RuntimeException('Sem conexão MySQLi.');
    }
  }

  public static function paginate(int $page, int $per): array {
    $page = max(1, $page);
    $per  = min(100, max(5, $per));
    $off  = ($page-1)*$per;
    return [$page, $per, $off];
  }

  public static function storeUpload(array $file, string $baseDir): array {
    // Exemplo simples (grava local). Em produção: sanitizar melhor, checar MIME, sha256, antivírus etc.
    if ($file['error'] !== UPLOAD_ERR_OK) {
      return ['ok'=>false, 'error'=>'upload_error'];
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = bin2hex(random_bytes(16)).($ext?'.'.$ext:'');
    $dir  = rtrim($baseDir, '/').'/'.date('Y/m/d');
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $path = $dir.'/'.$name;
    if (!move_uploaded_file($file['tmp_name'], $path)) {
      return ['ok'=>false, 'error'=>'move_failed'];
    }
    return ['ok'=>true, 'path'=>$path, 'filename'=>$file['name'], 'size'=>$file['size'] ?? 0, 'mime'=>$file['type'] ?? null];
  }
}
