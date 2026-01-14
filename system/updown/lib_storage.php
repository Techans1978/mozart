<?php
// public_html/system/updown/lib_storage.php

if (!defined('MOZART_DATA_PATH')) {
  // fallback (ideal: definir no config.php)
  define('MOZART_DATA_PATH', dirname(__DIR__, 3).'/data_files');
}

/**
 * Normaliza e valida um caminho relativo (rel_path) para evitar traversal.
 * Retorna caminho absoluto seguro dentro de MOZART_DATA_PATH.
 */
function storage_resolve_abs(string $rel_path): string {
  $rel_path = str_replace(["\0", "\\\\"], ['', '/'], $rel_path);
  $rel_path = ltrim($rel_path, "/");

  // bloqueia tentativas óbvias
  if ($rel_path === '' || strpos($rel_path, '..') !== false) {
    throw new Exception('Caminho inválido.');
  }

  // whitelisting de caracteres (ajuste se precisar)
  if (!preg_match('~^[a-zA-Z0-9/_\.\-]+$~', $rel_path)) {
    throw new Exception('Caminho com caracteres inválidos.');
  }

  $root = rtrim(MOZART_DATA_PATH, '/');
  $abs  = $root . '/' . $rel_path;

  // resolve realpath do root e do arquivo (quando existir)
  $realRoot = realpath($root);
  if (!$realRoot) throw new Exception('Storage root não encontrado.');

  // Se o arquivo não existe ainda, realpath($abs) retorna false.
  // Aqui queremos resolver diretório pai para validar que está dentro do root.
  $parent = realpath(dirname($abs));
  if (!$parent) throw new Exception('Diretório inválido.');

  // garante que parent está dentro do root
  if (strpos($parent, $realRoot) !== 0) {
    throw new Exception('Acesso fora do storage.');
  }

  return $abs;
}

/**
 * Faz leitura segura do arquivo (stream) + headers.
 */
function storage_stream_file(string $abs_path, string $downloadName, ?string $mime=null): void {
  if (!is_file($abs_path) || !is_readable($abs_path)) {
    throw new Exception('Arquivo não encontrado.');
  }

  $size = filesize($abs_path);
  $mime = $mime ?: 'application/octet-stream';

  // Headers
  header('Content-Type: '.$mime);
  header('Content-Length: '.$size);
  header('X-Content-Type-Options: nosniff');

  // Content-Disposition (compat)
  $safeName = preg_replace('/[\r\n"]+/', '_', $downloadName);
  header('Content-Disposition: attachment; filename="'.$safeName.'"');

  // Cache hardening
  header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');

  // Stream em chunks
  $fp = fopen($abs_path, 'rb');
  if (!$fp) throw new Exception('Falha ao abrir arquivo.');

  while (!feof($fp)) {
    echo fread($fp, 1024 * 1024); // 1MB
    @ob_flush(); flush();
  }
  fclose($fp);
}
