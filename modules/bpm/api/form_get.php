<?php
// modules/bpm/api/form_get.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
proteger_pagina();

header('Content-Type: application/json; charset=UTF-8');

$slug = trim((string)($_GET['slug'] ?? ''));
$ver  = trim((string)($_GET['version'] ?? ''));

if ($slug === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Parâmetro slug é obrigatório.'], JSON_UNESCAPED_UNICODE);
  exit;
}

// se version vazio -> latest
if ($ver === '') {
  $stmt = $conn->prepare("SELECT MAX(versao) v FROM moz_forms WHERE slug=? AND tipo='bpm' AND ativo=1");
  $stmt->bind_param("s", $slug);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $ver = (string)($row['v'] ?? '');
}

if ($ver === '' || !ctype_digit($ver)) {
  http_response_code(404);
  echo json_encode(['ok'=>false,'error'=>"Form não encontrado (slug={$slug})."], JSON_UNESCAPED_UNICODE);
  exit;
}
$verInt = (int)$ver;

$stmt = $conn->prepare("
  SELECT id, tipo, slug, versao, nome, descricao, json, html, ativo, created_at
  FROM moz_forms
  WHERE slug=? AND versao=? AND tipo='bpm'
  LIMIT 1
");
$stmt->bind_param("si", $slug, $verInt);
$stmt->execute();
$res = $stmt->get_result();
$form = $res->fetch_assoc();
$stmt->close();

if (!$form) {
  http_response_code(404);
  echo json_encode(['ok'=>false,'error'=>"Form não encontrado (slug={$slug}, version={$verInt})."], JSON_UNESCAPED_UNICODE);
  exit;
}

// devolve json/html do ativo
echo json_encode(['ok'=>true,'form'=>$form], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
