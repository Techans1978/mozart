<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once __DIR__ . '/../includes/MozartFlowRunner.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();
$db = $conn ?? $mysqli ?? null; if(!$db){ http_response_code(500); die('Sem conexão.'); }

$spec_json = $_POST['spec_json'] ?? '';
$vars_json = $_POST['vars_json'] ?? '{}';
$env = $_POST['env'] ?? 'dev';

$flowId = 0; $versionId = 0;
try {
  $s = $db->prepare("INSERT INTO moz_flow (nome,categoria,descr) VALUES ('_temp','_temp','Execução efêmera')");
  $s->execute(); $flowId=(int)$s->insert_id; $s->close();
  $s = $db->prepare("INSERT INTO moz_flow_version (flow_id, version, spec_json, status) VALUES (?,?,?,'draft')");
  $v = '0.0.0'; $s->bind_param('iss',$flowId,$v,$spec_json); $s->execute(); $versionId=(int)$s->insert_id; $s->close();
} catch (Throwable $e){}

$vars = json_decode($vars_json, true); if(json_last_error()!==JSON_ERROR_NONE) $vars = [];

$runner = new MozartFlowRunner($db);
$out = $runner->run($versionId, $vars, $env);

if ($flowId) { $db->query("DELETE FROM moz_flow WHERE id=".(int)$flowId); }

header('Content-Type: application/json; charset=utf-8');
echo json_encode($out, JSON_UNESCAPED_UNICODE);
