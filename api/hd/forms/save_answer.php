<?php
// public/api/hd/forms/save_answer.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
header('Content-Type: application/json; charset=utf-8');

$body = file_get_contents('php://input');
$payload = json_decode($body, true);
if(!$payload){ echo json_encode(['ok'=>false,'error'=>'JSON inválido']); exit; }

// Aqui você pode criar um ticket (hd_ticket) ou iniciar um BPM.
$protocolo = 'HD'.date('ymdHis').rand(100,999);

echo json_encode(['ok'=>true,'protocolo'=>$protocolo,'echo'=>$payload]);
