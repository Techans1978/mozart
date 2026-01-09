<?php
/* Step 6: Validar / Checklist */

$issues = [];

$xmlRaw = trim((string)($state['bpmn_xml'] ?? ''));
if ($xmlRaw === '') {
  $issues[] = ['level'=>'danger', 'msg'=>'Sem BPMN carregado no wizard (bpmn_xml vazio). Salve o diagrama no Designer e volte aqui.'];
} else {
  $xml = @simplexml_load_string($xmlRaw);
  if (!$xml) {
    $issues[] = ['level'=>'danger', 'msg'=>'BPMN XML invalido (nao foi possivel fazer parse).'];
  } else {
    $namespaces = $xml->getNamespaces(true);
    $bpmnNs = $namespaces['bpmn'] ?? ($namespaces['bpmn2'] ?? null);
    if (!$bpmnNs) $bpmnNs = 'http://www.omg.org/spec/BPMN/20100524/MODEL';
    $xml->registerXPathNamespace('bpmn', $bpmnNs);

    $mozNs = 'http://mozart.superabc.com.br/schema/bpmn';

    $process = $xml->xpath('//bpmn:process');
    if (!$process || !isset($process[0])) {
      $issues[] = ['level'=>'danger', 'msg'=>'Nenhum <process> encontrado no BPMN.'];
    } else {
      $proc = $process[0];

      $starts = $proc->xpath('bpmn:startEvent') ?: [];
      $ends   = $proc->xpath('bpmn:endEvent') ?: [];
      if (count($starts) === 0) $issues[] = ['level'=>'danger', 'msg'=>'Sem StartEvent.'];
      if (count($ends) === 0)   $issues[] = ['level'=>'warning','msg'=>'Sem EndEvent. (runtime pode nunca encerrar)'];

      // UserTasks: form + assignment
      $userTasks = $proc->xpath('bpmn:userTask') ?: [];
      foreach ($userTasks as $ut) {
        $id = (string)$ut['id'];
        $a = $ut->attributes($mozNs, true);
        $formSlug = $a ? trim((string)($a['formSlug'] ?? '')) : '';
        $formVer  = $a ? trim((string)($a['formVersion'] ?? '')) : '';
        $atype    = $a ? trim((string)($a['assignmentType'] ?? '')) : '';
        $aval     = $a ? trim((string)($a['assignmentValue'] ?? '')) : '';

        if ($formSlug === '' || $formVer === '') {
          $issues[] = ['level'=>'warning', 'msg'=>"UserTask {$id}: sem mozart:formSlug / mozart:formVersion"]; 
        }
        if ($atype === '' || $aval === '') {
          $issues[] = ['level'=>'warning', 'msg'=>"UserTask {$id}: sem mozart:assignmentType / mozart:assignmentValue"]; 
        }
      }

      // ServiceTasks: pelo menos referenciar um asset (placeholder)
      $serviceTasks = $proc->xpath('bpmn:serviceTask') ?: [];
      foreach ($serviceTasks as $st) {
        $id = (string)$st['id'];
        $a = $st->attributes($mozNs, true);
        $assetRef = $a ? trim((string)($a['assetRef'] ?? '')) : '';
        $cfgRaw = $a ? trim((string)($a['config'] ?? '')) : '';
        if ($assetRef === '' && $cfgRaw === '') {
          $issues[] = ['level'=>'info', 'msg'=>"ServiceTask {$id}: sem mozart:assetRef (ou mozart:config). Quando for para Fase 7, vamos vincular ao conector oficial."]; 
        }
      }
    }
  }
}
?>

<div class="card">
  <h2>6) Validar / Checklist</h2>

  <?php if (empty($issues)): ?>
    <div class="alert alert-success">Nenhum problema detectado no checklist basico.</div>
  <?php else: ?>
    <?php foreach ($issues as $it): ?>
      <div class="alert alert-<?php echo htmlspecialchars($it['level']); ?>">
        <?php echo htmlspecialchars($it['msg']); ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="small text-muted" style="margin-top:10px;">
    Observacao: esse checklist e propositalmente simples. Na publicacao completa, esses pontos viram travas (nao publica com erro).
  </div>
</div>
