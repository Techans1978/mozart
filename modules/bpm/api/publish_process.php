<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . '/config/connect.php'; // $conn

$in = json_decode(file_get_contents('php://input'), true);
$code = isset($in['code']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', $in['code']) : '';

if (!$code) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing code']); exit; }

$conn->begin_transaction();

try {
  $stmt = $conn->prepare("SELECT id, current_version, current_version_id FROM bpm_process WHERE code=? LIMIT 1");
  $stmt->bind_param("s", $code);
  $stmt->execute();
  $proc = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$proc) { throw new Exception("process not found"); }

  $processId = (int)$proc['id'];
  $curVer    = max(1, (int)$proc['current_version']);
  $curVerId  = (int)($proc['current_version_id'] ?? 0);

  // garante versionId (se não tiver current_version_id, tenta achar draft)
  if (!$curVerId) {
    $stmt = $conn->prepare("SELECT id FROM bpm_process_version WHERE process_id=? AND version=? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("ii", $processId, $curVer);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) throw new Exception("current version row not found");
    $curVerId = (int)$row['id'];
  }

  // ===== FASE 3/4: index + valida humano x automático + congela form_slug/version =====
  $stmt = $conn->prepare("SELECT bpmn_xml FROM bpm_process_version WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $curVerId);
  $stmt->execute();
  $xml = $stmt->get_result()->fetch_assoc()['bpmn_xml'] ?? '';
  $stmt->close();
  if (!$xml) throw new Exception("bpmn_xml vazio para version_id={$curVerId}");

  // Parse BPMN
  $dom = new DOMDocument();
  $dom->preserveWhiteSpace = false;
  if (!@$dom->loadXML($xml)) throw new Exception("BPMN XML inválido no publish");

  $xp = new DOMXPath($dom);
  $xp->registerNamespace('bpmn', 'http://www.omg.org/spec/BPMN/20100524/MODEL');
  $xp->registerNamespace('mozart', 'http://mozart.superabc.com.br/schema/bpmn');

  $elements = [];
  $errors = [];

  // Pega qualquer nó BPMN com id dentro do process
  $nodes = $xp->query('//bpmn:process//*[@id]');
  foreach ($nodes as $n) {
    /** @var DOMElement $n */
    $id = $n->getAttribute('id');
    $bpmnType = $n->localName; // ex: userTask, serviceTask, startEvent...

    // Classificação (FASE 3)
    $elementType = null;
    if ($bpmnType === 'userTask') $elementType = 'human';
    else if (in_array($bpmnType, ['serviceTask','scriptTask','sendTask','receiveTask','manualTask','businessRuleTask','task','callActivity'], true)) $elementType = 'service';
    else if (in_array($bpmnType, ['exclusiveGateway','parallelGateway'], true)) $elementType = 'gateway';
    else if (str_ends_with($bpmnType, 'Event')) $elementType = 'event';
    else continue; // ignora outros

    // Lê mozart:config (se existir)
    $cfgRaw = $n->getAttributeNS('http://mozart.superabc.com.br/schema/bpmn', 'config');
    $cfg = [];
    if ($cfgRaw) {
      $tmp = json_decode($cfgRaw, true);
      if (is_array($tmp)) $cfg = $tmp;
    }

    // form_slug + form_version (FASE 4)
    $formSlug = trim($n->getAttributeNS('http://mozart.superabc.com.br/schema/bpmn', 'formSlug'));
    $formVer  = trim($n->getAttributeNS('http://mozart.superabc.com.br/schema/bpmn', 'formVersion'));

    // fallback via config_json
    if (!$formSlug) $formSlug = trim((string)($cfg['formSlug'] ?? ($cfg['form']['slug'] ?? '')));
    if (!$formVer)  $formVer  = trim((string)($cfg['formVersion'] ?? ($cfg['form']['version'] ?? '')));

    // assignment (FASE 3) — só faz sentido em HUMAN
    $assignType = trim($n->getAttributeNS('http://mozart.superabc.com.br/schema/bpmn', 'assignmentType'));
    $assignVal  = trim($n->getAttributeNS('http://mozart.superabc.com.br/schema/bpmn', 'assignmentValue'));

    // fallback via config_json
    if (!$assignType) $assignType = trim((string)($cfg['assignment']['type'] ?? ($cfg['assigneeType'] ?? '')));
    if (!$assignVal)  $assignVal  = trim((string)($cfg['assignment']['value'] ?? ($cfg['assignee'] ?? '')));

    $assignType = strtolower($assignType);

    if ($elementType === 'human') {
      // HUMANO exige form_slug
      if (!$formSlug) $errors[] = "UserTask {$id}: faltando form_slug";

      // se versão não vier, congela latest no publish
      if (!$formVer && $formSlug) {
        $q = $conn->prepare("SELECT MAX(versao) v FROM moz_forms WHERE slug=? AND tipo='bpm' AND ativo=1");
        $q->bind_param("s", $formSlug);
        $q->execute();
        $vrow = $q->get_result()->fetch_assoc();
        $q->close();
        $formVer = (string)($vrow['v'] ?? '');
      }

      if (!$formVer) $errors[] = "UserTask {$id}: não foi possível resolver form_version (slug={$formSlug})";

      // HUMANO exige assignment user|role + valor
      if (!in_array($assignType, ['user','role'], true) || !$assignVal) {
        $errors[] = "UserTask {$id}: faltando assignment (user|role + valor)";
      }

      // grava congelado no config_json do element index
      $cfg['formSlug'] = $formSlug;
      $cfg['formVersion'] = (int)$formVer;
      $cfg['assignment'] = [
        'type'  => $assignType,
        'value' => $assignVal
      ];
      $cfgRaw = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    } else {
      // AUTOMÁTICO/GATEWAY/EVENT: não pode ter assignment
      if ($assignType || $assignVal) {
        $errors[] = "{$bpmnType} {$id}: automático/gateway/event não pode ter assignment";
      }
    }

    $elements[] = [
      'element_id'   => $id,
      'element_type' => $elementType,
      'bpmn_type'    => $bpmnType,
      'name'         => $n->getAttribute('name') ?: null,
      'config_json'  => $cfgRaw ? $cfgRaw : null
    ];
  }

  if ($errors) {
    throw new Exception("FASE 3/4 bloqueou o publish:\n- " . implode("\n- ", $errors));
  }

  // Grava index da versão (idempotente)
  $stmt = $conn->prepare("DELETE FROM bpm_process_version_element WHERE process_version_id=?");
  $stmt->bind_param("i", $curVerId);
  $stmt->execute();
  $stmt->close();

  $stmt = $conn->prepare("INSERT INTO bpm_process_version_element
    (process_version_id, element_id, element_type, bpmn_type, name, config_json)
    VALUES (?, ?, ?, ?, ?, ?)");

  foreach ($elements as $e) {
    $cfgJson = $e['config_json'] ? $e['config_json'] : null;
    $stmt->bind_param(
      "isssss",
      $curVerId,
      $e['element_id'],
      $e['element_type'],
      $e['bpmn_type'],
      $e['name'],
      $cfgJson
    );
    $stmt->execute();
  }
  $stmt->close();
  // ===== /FASE 3/4 =====

  // publica a versão atual
  $stmt = $conn->prepare("UPDATE bpm_process_version SET status='published', updated_at=NOW() WHERE id=?");
  $stmt->bind_param("i", $curVerId);
  $stmt->execute();
  $stmt->close();

  // marca processo como published
  $stmt = $conn->prepare("UPDATE bpm_process SET status='published', updated_at=NOW() WHERE id=?");
  $stmt->bind_param("i", $processId);
  $stmt->execute();
  $stmt->close();

  // cria próximo draft (curVer+1) copiando XML publicado
  $stmt = $conn->prepare("SELECT bpmn_xml FROM bpm_process_version WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $curVerId);
  $stmt->execute();
  $xml = $stmt->get_result()->fetch_assoc()['bpmn_xml'] ?? '';
  $stmt->close();

  $nextVer = $curVer + 1;
  $semver  = $nextVer . ".0.0";

  $stmt = $conn->prepare("INSERT INTO bpm_process_version (process_id, version, semver, status, bpmn_xml, snapshot_json)
                          VALUES (?, ?, ?, 'draft', ?, NULL)");
  $stmt->bind_param("iiss", $processId, $nextVer, $semver, $xml);
  $stmt->execute();
  $nextVerId = (int)$stmt->insert_id;
  $stmt->close();

  $stmt = $conn->prepare("UPDATE bpm_process
                          SET current_version=?, current_version_id=?, updated_at=NOW()
                          WHERE id=?");
  $stmt->bind_param("iii", $nextVer, $nextVerId, $processId);
  $stmt->execute();
  $stmt->close();

  $conn->commit();
  echo json_encode(['ok'=>true,'published_version'=>$curVer,'next_draft_version'=>$nextVer]);

} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
