<?php
// modules/gestao_ativos/includes/listas_guard.php
// Helpers compartilhados: conexão, auth, flash, sanitização e loader de lista por slug.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

if (!isset($conn) && isset($mysqli)) { $conn = $mysqli; }
if (!($conn instanceof mysqli)) { die('Conexão MySQLi $conn não encontrada.'); }

if (session_status() === PHP_SESSION_NONE) session_start();
// proteger_pagina(); // habilite se você usa este helper

function ga_flash_set(string $msg, string $type='info'): void {
  $_SESSION['__flash'] = ['m'=>$msg,'t'=>$type];
}
function ga_flash_get(): ?array {
  if (!isset($_SESSION['__flash'])) return null;
  $f = $_SESSION['__flash'];
  unset($_SESSION['__flash']);
  return $f;
}
function ga_redirect(string $url): void { header('Location: '.$url); exit; }

function ga_trim(?string $s): string { return trim((string)$s); }
function ga_int($v): int { return (int)($v ?? 0); }

function ga_slug_clean(string $slug): string {
  $slug = preg_replace('/[^a-z0-9_\-]/i', '', $slug);
  return strtolower($slug);
}

function ga_list_by_slug(mysqli $conn, string $slug): ?array {
  $slug = ga_slug_clean($slug);
  if ($slug === '') return null;

  $st = $conn->prepare("SELECT id, slug, title, description, active FROM ga_list WHERE slug=? LIMIT 1");
  $st->bind_param("s", $slug);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();

  return $row ?: null;
}

function ga_json($data, int $code=200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
