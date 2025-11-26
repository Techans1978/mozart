<?php
header('Content-Type: application/xml; charset=utf-8');
require_once dirname(__DIR__, 3).'/config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
proteger_pagina();

$instance_id = (int)($_GET['instance_id'] ?? 0);
$version_id  = (int)($_GET['version_id'] ?? 0);

$exists = function($t) use ($conn){
  $t = $conn->real_escape_string($t);
  $rs = $conn->query("SHOW TABLES LIKE '$t'");
  return $rs && $rs->num_rows > 0;
};

$xml = null;
if ($instance_id>0 && $exists('bpm_instance')) {
  $q = "SELECT pv.bpmn_xml
        FROM bpm_instance i
        JOIN bpm_process_version pv ON pv.id = i.version_id
        WHERE i.id = $instance_id";
  if ($rs = $conn->query($q)) { if ($r = $rs->fetch_assoc()) $xml = $r['bpmn_xml'] ?? null; }
}
if (!$xml && $version_id>0 && $exists('bpm_process_version')) {
  $q = "SELECT bpmn_xml FROM bpm_process_version WHERE id = $version_id";
  if ($rs = $conn->query($q)) { if ($r = $rs->fetch_assoc()) $xml = $r['bpmn_xml'] ?? null; }
}

if (!$xml) {
  // stub básico caso não exista XML no banco
  $xml = '<?xml version="1.0" encoding="UTF-8"?>
  <definitions xmlns="http://www.omg.org/spec/BPMN/20100524/MODEL"
               xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
               xmlns:di="http://www.omg.org/spec/DD/20100524/DI"
               xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
               id="Defs_1" targetNamespace="http://bpmn.io/schema/bpmn">
    <process id="Process_1" isExecutable="false">
      <startEvent id="StartEvent_1"/>
    </process>
    <bpmndi:BPMNDiagram id="BPMNDiagram_1">
      <bpmndi:BPMNPlane id="BPMNPlane_1" bpmnElement="Process_1"/>
    </bpmndi:BPMNDiagram>
  </definitions>';
}
echo $xml;
