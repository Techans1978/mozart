<?php
header('Content-Type: application/json; charset=utf-8');

try {
  // corpo JSON: { "prompt": "..." }
  $raw = file_get_contents('php://input');
  $body = json_decode($raw, true);
  $prompt = trim($body['prompt'] ?? '');

  if ($prompt === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'Prompt vazio']);
    exit;
  }

  // ------------------------------------------------------------
  // PARSER MINIMALISTA
  // Suporta:
  // - passos lineares separados por "➜" ou "->" ou ">" ou "→"
  // - decisão com "? ... sim: ... não: ..."
  //
  // Exemplo:
  // Início ➜ RH cadastra ➜ Aprovação do Gestor? ➜
  //   sim: TI cria acesso ➜ Fim
  //   não: Encerrar
  // ------------------------------------------------------------

  $sep = "➜|->|→|>";
  // normaliza
  $clean = preg_replace('/\s+/', ' ', $prompt);
  // quebra em tokens principais
  $tokens = preg_split('/\s*(?:' . $sep . ')\s*/u', $clean);

  // utilitários ID
  $idSeq = 1;
  $newId = function($prefix) use (&$idSeq) {
    return $prefix . '_' . ($idSeq++);
  };

  $elements = [];     // [ [id,type,label], ... ]
  $flows    = [];     // [ [id,src,tgt,label], ... ]
  $lastNode = null;
  $startId  = $newId('StartEvent');
  $endIds   = []; // pode ter mais de um end

  $elements[] = ['id'=>$startId, 'type'=>'startEvent', 'label'=>'Start'];

  // helpers p/ criar tarefa e fluxo
  $addTask = function($label) use (&$elements, &$flows, &$lastNode, $newId) {
    $tid = $newId('Task');
    $elements[] = ['id'=>$tid, 'type'=>'task', 'label'=>$label];
    if ($lastNode) {
      $flows[] = ['id'=>$newId('Flow'), 'src'=>$lastNode, 'tgt'=>$tid, 'label'=>null];
    }
    $lastNode = $tid;
    return $tid;
  };

  $addEnd = function($label='End') use (&$elements, &$flows, &$lastNode, &$endIds, $newId) {
    $eid = $newId('EndEvent');
    $elements[] = ['id'=>$eid, 'type'=>'endEvent', 'label'=>$label];
    if ($lastNode) {
      $flows[] = ['id'=>$newId('Flow'), 'src'=>$lastNode, 'tgt'=>$eid, 'label'=>null];
    }
    $lastNode = $eid;
    $endIds[] = $eid;
    return $eid;
  };

  $addGatewayYesNo = function($label, $yesSteps, $noSteps) use (&$elements, &$flows, &$lastNode, $addTask, $addEnd, $newId) {
    $gwId = $newId('ExclusiveGateway');
    $elements[] = ['id'=>$gwId, 'type'=>'exclusiveGateway', 'label'=>$label];
    if ($lastNode) {
      $flows[] = ['id'=>$newId('Flow'), 'src'=>$lastNode, 'tgt'=>$gwId, 'label'=>null];
    }

    // YES branch
    $lastYes = $gwId;
    $currLastRef = &$lastYes;
    foreach ($yesSteps as $step) {
      $step = trim($step);
      if ($step === '') continue;
      if (preg_match('/^(fim|encerrar|terminar)$/i', $step)) {
        // encerra
        $eid = $newId('EndEvent');
        $elements[] = ['id'=>$eid, 'type'=>'endEvent', 'label'=>'End'];
        $flows[] = ['id'=>$newId('Flow'), 'src'=>$currLastRef, 'tgt'=>$eid, 'label'=>'sim'];
        $currLastRef = $eid;
      } else {
        $tid = $newId('Task');
        $elements[] = ['id'=>$tid, 'type'=>'task', 'label'=>$step];
        $flows[] = ['id'=>$newId('Flow'), 'src'=>$currLastRef, 'tgt'=>$tid, 'label'=>$currLastRef===$gwId?'sim':null];
        $currLastRef = $tid;
      }
    }
    $yesEnd = $currLastRef;

    // NO branch
    $lastNo = $gwId;
    $currLastRef2 = &$lastNo;
    foreach ($noSteps as $step) {
      $step = trim($step);
      if ($step === '') continue;
      if (preg_match('/^(fim|encerrar|terminar)$/i', $step)) {
        $eid = $newId('EndEvent');
        $elements[] = ['id'=>$eid, 'type'=>'endEvent', 'label'=>'End'];
        $flows[] = ['id'=>$newId('Flow'), 'src'=>$currLastRef2, 'tgt'=>$eid, 'label'=>'não'];
        $currLastRef2 = $eid;
      } else {
        $tid = $newId('Task');
        $elements[] = ['id'=>$tid, 'type'=>'task', 'label'=>$step];
        $flows[] = ['id'=>$newId('Flow'), 'src'=>$currLastRef2, 'tgt'=>$tid, 'label'=>$currLastRef2===$gwId?'não':null];
        $currLastRef2 = $tid;
      }
    }
    // não fazemos merge automático; fica como ramos separados
    // atualiza lastNode para o último do YES por padrão
    $lastNode = $yesEnd;
    return $gwId;
  };

  // o fluxo começa no Start
  $lastNode = $startId;

  // processa tokens (um token pode conter "Pergunta?  sim: ...  não: ...")
  foreach ($tokens as $t) {
    $t = trim($t);
    if ($t === '') continue;

    // decisão?
    if (preg_match('/\?(.*)$/u', $t)) {
      // extrai label da pergunta e blocos sim/não (se existirem)
      // formatos aceitos:
      // "Aprovação do Gestor? sim: TI cria acesso > Fim  não: Encerrar"
      // ou
      // "Aprovação do Gestor?  sim: ...  não: ..."
      $label = preg_replace('/\?.*$/u', '', $t);
      $rest  = trim(preg_replace('/^[^?]*\?/u', '', $t));

      // tenta pegar "sim:" e "não:"
      $yes = '';
      $no  = '';
      if (preg_match('/\bsim\s*:\s*(.*?)(?:\bn[aã]o\s*:|$)/iu', $rest, $m1)) {
        $yes = trim($m1[1]);
      }
      if (preg_match('/\bn[aã]o\s*:\s*(.*)$/iu', $rest, $m2)) {
        $no = trim($m2[1]);
      }

      // se não veio nada, cria somente o gateway
      $yesSteps = $yes ? preg_split('/\s*(?:' . $sep . ')\s*/u', $yes) : [];
      $noSteps  = $no  ? preg_split('/\s*(?:' . $sep . ')\s*/u', $no)  : [];

      $addGatewayYesNo($label ?: 'Decisão', $yesSteps, $noSteps);
      continue;
    }

    // fim explícito
    if (preg_match('/^(fim|encerrar|terminar)$/i', $t)) {
      $addEnd('End');
      continue;
    }

    // passo linear
    $addTask($t);
  }

  // garante ao menos um fim
  if (empty($endIds)) {
    $addEnd('End');
  }

  // ------------------------------------------------------------
  // GERA XML BPMN 2.0
  // ------------------------------------------------------------
  $processId = 'Process_' . uniqid();
  $bpmndi = [];
  $planeId = 'BPMNPlane_' . uniqid();

  // layout tosco auto (em grade)
  $x = 160; $y = 120;
  $pos = []; // nodeId => [x,y]
  $row = 0; $col = 0;

  foreach ($elements as $el) {
    // gateways um pouco menores
    $w = ($el['type']==='exclusiveGateway') ? 50 : (($el['type']==='endEvent' || $el['type']==='startEvent') ? 36 : 110);
    $h = ($el['type']==='exclusiveGateway') ? 50 : (($el['type']==='endEvent' || $el['type']==='startEvent') ? 36 : 60);

    $pos[$el['id']] = [$x + $col*180, $y + $row*120, $w, $h];
    $col++;
    if ($col>5) { $col=0; $row++; }
  }

  $xml = [];
  $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
  $xml[] = '<bpmn:definitions xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"';
  $xml[] = '  xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"';
  $xml[] = '  xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"';
  $xml[] = '  xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"';
  $xml[] = '  xmlns:di="http://www.omg.org/spec/DD/20100524/DI"';
  $xml[] = '  id="Defs_' . uniqid() . '" targetNamespace="http://bpmn.io/schema/bpmn">';
  $xml[] = '  <bpmn:process id="' . $processId . '" isExecutable="false">';

  foreach ($elements as $el) {
    $id    = htmlspecialchars($el['id']);
    $label = htmlspecialchars($el['label'] ?? '');
    switch ($el['type']) {
      case 'startEvent':
        $xml[] = '    <bpmn:startEvent id="'.$id.'" name="'.$label.'"/>';
        break;
      case 'endEvent':
        $xml[] = '    <bpmn:endEvent id="'.$id.'" name="'.$label.'"/>';
        break;
      case 'task':
        $xml[] = '    <bpmn:task id="'.$id.'" name="'.$label.'"/>';
        break;
      case 'exclusiveGateway':
        $xml[] = '    <bpmn:exclusiveGateway id="'.$id.'" name="'.$label.'"/>';
        break;
    }
  }

  foreach ($flows as $f) {
    $fid = htmlspecialchars($f['id']);
    $src = htmlspecialchars($f['src']);
    $tgt = htmlspecialchars($f['tgt']);
    $name = $f['label'] ? ' name="'.htmlspecialchars($f['label']).'"' : '';
    $xml[] = '    <bpmn:sequenceFlow id="'.$fid.'" sourceRef="'.$src.'" targetRef="'.$tgt.'"'.$name.'/>';
  }

  $xml[] = '  </bpmn:process>';
  $xml[] = '  <bpmndi:BPMNDiagram id="BPMNDiagram_'.uniqid().'">';
  $xml[] = '    <bpmndi:BPMNPlane id="'.$planeId.'" bpmnElement="'.$processId.'">';

  // shapes
  foreach ($elements as $el) {
    [$px, $py, $pw, $ph] = $pos[$el['id']];
    $xml[] = '      <bpmndi:BPMNShape id="DI_'.$el['id'].'" bpmnElement="'.$el['id'].'">';
    $xml[] = '        <dc:Bounds x="'.$px.'" y="'.$py.'" width="'.$pw.'" height="'.$ph.'"/>';
    $xml[] = '      </bpmndi:BPMNShape>';
  }

  // edges (reta simples)
  foreach ($flows as $f) {
    [$sx, $sy, $sw, $sh] = $pos[$f['src']];
    [$tx, $ty, $tw, $th] = $pos[$f['tgt']];
    $mx1 = $sx + $sw; $my1 = $sy + $sh/2;
    $mx2 = $tx;       $my2 = $ty + $th/2;

    $xml[] = '      <bpmndi:BPMNEdge id="DI_'.$f['id'].'" bpmnElement="'.$f['id'].'">';
    $xml[] = '        <di:waypoint x="'.$mx1.'" y="'.$my1.'"/>';
    $xml[] = '        <di:waypoint x="'.$mx2.'" y="'.$my2.'"/>';
    $xml[] = '      </bpmndi:BPMNEdge>';
  }

  $xml[] = '    </bpmndi:BPMNPlane>';
  $xml[] = '  </bpmndi:BPMNDiagram>';
  $xml[] = '</bpmn:definitions>';

  $name = 'processo_' . date('Ymd_His');
  $version = 1;

  echo json_encode([
    'ok'      => true,
    'name'    => $name,
    'version' => $version,
    'xml'     => implode("\n", $xml)
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
