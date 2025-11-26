<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();
$db = $conn ?? $mysqli ?? null; if(!$db){ die('Sem conexão.'); }

$id = (int)($_POST['id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');
$categoria = trim($_POST['categoria'] ?? '');
$descr = trim($_POST['descr'] ?? '');
$spec = $_POST['spec_json'] ?? '{"nodes":[],"edges":[]}';
$version = $_POST['version'] ?? '1.0.0';

if ($id>0){
  $s = $db->prepare("UPDATE moz_flow SET nome=?, categoria=?, descr=? WHERE id=?");
  $s->bind_param('sssi',$nome,$categoria,$descr,$id); $s->execute(); $s->close();
} else {
  $s = $db->prepare("INSERT INTO moz_flow (nome,categoria,descr) VALUES (?,?,?)");
  $s->bind_param('sss',$nome,$categoria,$descr); $s->execute(); $id=(int)$s->insert_id; $s->close();
}
$s = $db->prepare("INSERT INTO moz_flow_version (flow_id, version, spec_json, status) VALUES (?,?,?,'draft')");
$s->bind_param('iss',$id,$version,$spec); $s->execute(); $s->close();
header("Location: ../flows-editor.php?id=".$id);
exit;
