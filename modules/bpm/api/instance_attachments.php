<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3) . '/config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
proteger_pagina();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$out = ['ok'=>false,'items'=>[]];

if ($id>0) { $out['ok'] = true; /* adapte quando tiver tabela de anexos */ }

echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
