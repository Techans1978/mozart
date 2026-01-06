<?php
// bpm/_lib/bpm_engine.php

if (!function_exists('bpm_engine_load_bpmn')) {

    /* =========================================================
     * Helpers de Variáveis (payload)
     * =======================================================*/

    /**
     * Salva payload vindo de formulário em bpm_variable.
     * MVP: sempre insere novos registros (pode ter histórico).
     */
function bpm_engine_save_payload(mysqli $conn, int $instanceId, int $taskId, array $payload): void {
    if (empty($payload)) return;

    $sql = "INSERT INTO bpm_variable (instance_id, task_id, name, value, created_at)
            VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erro ao preparar insert bpm_variable: ' . $conn->error);
    }

    foreach ($payload as $name => $val) {
        if (is_array($val) || is_object($val)) {
            $val = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $name = (string)$name;
        $v    = (string)$val;

        $stmt->bind_param("iiss", $instanceId, $taskId, $name, $v);
        $stmt->execute();
    }
    $stmt->close();
}


$assigneeUserId = null;
$candidateGroup = null;

if ($req['assignment_type'] === 'user') {
  $assigneeUserId = ctype_digit($req['assignment_value']) ? (int)$req['assignment_value'] : null;
  if (!$assigneeUserId) throw new Exception("UserTask {$targetId}: user precisa ser user_id numérico");
} else {
  $candidateGroup = $req['assignment_value'];
}

$sql = "INSERT INTO bpm_task
  (instance_id, node_id, type, assignee_user_id, candidate_group, form_slug, form_version, status, created_at)
  VALUES (?, ?, 'user', ?, ?, ?, ?, 'ready', NOW())";

$stmt = $conn->prepare($sql);
$stmt->bind_param("isisssi", $instanceId, $targetId, $assigneeUserId, $candidateGroup, $req['form_slug'], $req['form_version']);
$stmt->execute();

        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception('Erro ao preparar insert bpm_task: ' . $conn->error);

        $stmt->bind_param("isiss", $instanceId, $targetId, $assigneeUserId, $candidateGroup);
        $stmt->execute();
        $taskId = (int)$conn->insert_id;
        $stmt->close();

        $createdTasks[] = $taskId;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Erro ao preparar insert bpm_variable: ' . $conn->error);
        }

        foreach ($payload as $name => $val) {
            if (is_array($val) || is_object($val)) {
                $val = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $name = (string)$name;
            $v    = (string)$val;

            $stmt->bind_param("iiss", $instanceId, $taskId, $name, $v);
            $stmt->execute();
        }
        $stmt->close();
    }

    /**
     * Carrega todas as variáveis de uma instância para um array associativo.
     * Em caso de chaves duplicadas, a última ganha (ordem por id).
     */
    function bpm_engine_load_vars(mysqli $conn, int $instanceId): array {
        $vars = [];
        $sql = "SELECT name, value FROM bpm_variable WHERE instance_id = ? ORDER BY id ASC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $vars;
        }
        $stmt->bind_param("i", $instanceId);
        $stmt->execute();
        $stmt->bind_result($name, $value);
        while ($stmt->fetch()) {
            $vars[$name] = $value;
        }
        $stmt->close();
        return $vars;
    }

    /* =========================================================
     * Helpers de Regras (ExclusiveGateway / conditionExpression)
     * =======================================================*/

    /**
     * Avalia uma expressão simples do tipo:
     * ${campo == 10}
     * ${status == 'APROVADO'}
     * ${total > 1000}
     *
     * - Só 1 condição (sem && ou ||)
     * - Operadores suportados: ==, !=, >=, <=, >, <
     * - campo é o nome da variável em bpm_variable (mesmo texto)
     */
    function bpm_engine_eval_condition(?string $cond, array $vars): bool {
        if ($cond === null) return false;
        $expr = trim($cond);
        if ($expr === '') return false;

        // remove ${ ... }
        if (str_starts_with($expr, '${') && str_ends_with($expr, '}')) {
            $expr = trim(substr($expr, 2, -1));
        }

        // não suportamos &&, ||
        if (str_contains($expr, '&&') || str_contains($expr, '||')) {
            return false;
        }

        $ops = ['==', '!=', '>=', '<=', '>', '<'];
        $opFound = null;
        $pos = null;
        foreach ($ops as $op) {
            $p = strpos($expr, $op);
            if ($p !== false) {
                $opFound = $op;
                $pos = $p;
                break;
            }
        }
        if ($opFound === null) {
            return false;
        }

        $left = trim(substr($expr, 0, $pos));
        $right = trim(substr($expr, $pos + strlen($opFound)));

        if ($left === '') return false;

        $leftVal = $vars[$left] ?? null;

        // trata right: string com aspas ou número
        if ((str_starts_with($right, "'") && str_ends_with($right, "'")) ||
            (str_starts_with($right, '"') && str_ends_with($right, '"'))) {
            $rightVal = substr($right, 1, -1);
        } elseif (is_numeric($right)) {
            $rightVal = $right + 0; // numérico
        } else {
            $rightVal = $right; // texto cru
        }

        // tenta coerção numérica se ambos forem numéricos
        $isLeftNum = is_numeric($leftVal);
        $isRightNum = is_numeric($rightVal);
        if ($isLeftNum && $isRightNum) {
            $lv = $leftVal + 0;
            $rv = $rightVal + 0;
        } else {
            $lv = (string)$leftVal;
            $rv = (string)$rightVal;
        }

        switch ($opFound) {
            case '==': return $lv == $rv;
            case '!=': return $lv != $rv;
            case '>=': return $lv >= $rv;
            case '<=': return $lv <= $rv;
            case '>':  return $lv >  $rv;
            case '<':  return $lv <  $rv;
        }
        return false;
    }

    /**
     * Escolhe o fluxo de saída com base em conditionExpression.
     * Regras:
     * - Se alguma condição for TRUE → usa o primeiro TRUE.
     * - Senão, se existir fluxo SEM condição → usa o primeiro sem condição.
     * - Senão, NULL.
     */
    function bpm_engine_choose_flow(array $flows, array $vars): ?array {
        if (empty($flows)) return null;

        $noCond = [];
        foreach ($flows as $f) {
            $cond = $f['condition'] ?? null;
            if ($cond !== null && trim($cond) !== '') {
                if (bpm_engine_eval_condition($cond, $vars)) {
                    return $f;
                }
            } else {
                $noCond[] = $f;
            }
        }
        if (!empty($noCond)) {
            return $noCond[0];
        }
        return null;
    }

/* =========================================================
 * Helpers FASE 3 — Humano x Automático (mozart:* + exec_kind)
 * =======================================================*/

/**
 * Lê mozart:config="JSON" (namespace mozart) de um SimpleXMLElement.
 * Retorna array (vazio se não existir ou inválido).
 */
function bpm_engine_read_mozart_config_from_xml(SimpleXMLElement $el): array {
    $mozNs = 'http://mozart.superabc.com.br/schema/bpmn';

    // pega atributos no namespace mozart
    $attrs = $el->attributes($mozNs, true);
    if (!$attrs) return [];

    $raw = (string)($attrs['config'] ?? '');
    if ($raw === '') return [];

    $cfg = json_decode($raw, true);
    return is_array($cfg) ? $cfg : [];
}

/**
 * Define o exec_kind oficial: human | service | gateway | event
 * Regra FASE 3:
 * - userTask => human
 * - serviceTask => service
 * - gateways => gateway
 * - *Event => event
 */
function bpm_engine_node_exec_kind(string $bpmnType, array $mozCfg = []): string {
    // override opcional futuro via config
    if (!empty($mozCfg['execKind'])) {
        $k = strtolower(trim((string)$mozCfg['execKind']));
        if (in_array($k, ['human','service','gateway','event'], true)) return $k;
    }

    if ($bpmnType === 'userTask') return 'human';

    if (in_array($bpmnType, ['serviceTask'], true)) return 'service';

    if (in_array($bpmnType, ['exclusiveGateway','parallelGateway'], true)) return 'gateway';

    if (substr($bpmnType, -5) === 'Event') return 'event';

    // conservador: nunca gerar task
    return 'event';
}

/**
 * Extrai requisitos de tarefa humana:
 * - form_id
 * - assignment_type (user|role)
 * - assignment_value
 *
 * Fonte (ordem):
 * 1) atributos mozart:* no XML
 * 2) mozart:config JSON (assignment/form)
 */
function bpm_engine_get_human_requirements(array $mozCfg, SimpleXMLElement $el): array {
    $mozNs = 'http://mozart.superabc.com.br/schema/bpmn';
    $a = $el->attributes($mozNs, true);

    $formSlug = $a ? trim((string)($a['formSlug'] ?? '')) : '';
    $formVer  = $a ? trim((string)($a['formVersion'] ?? '')) : '';

    $atype  = $a ? strtolower(trim((string)($a['assignmentType'] ?? ''))) : '';
    $aval   = $a ? trim((string)($a['assignmentValue'] ?? '')) : '';

    // fallback via config JSON
    if ($formSlug === '') {
        $formSlug = trim((string)($mozCfg['formSlug'] ?? ($mozCfg['form']['slug'] ?? '')));
    }
    if ($formVer === '') {
        $formVer = trim((string)($mozCfg['formVersion'] ?? ($mozCfg['form']['version'] ?? '')));
    }

    if ($atype === '') {
        $atype = strtolower(trim((string)($mozCfg['assignment']['type'] ?? ($mozCfg['assigneeType'] ?? ''))));
    }
    if ($aval === '') {
        $aval = trim((string)($mozCfg['assignment']['value'] ?? ($mozCfg['assignee'] ?? '')));
    }

    return [
        'form_slug'        => $formSlug ?: null,
        'form_version'     => (ctype_digit($formVer) ? (int)$formVer : null),
        'assignment_type'  => $atype ?: null,
        'assignment_value' => $aval ?: null,
    ];
}



    /* =========================================================
     * BPMN: carregar XML e indexar
     * =======================================================*/

    function bpm_engine_load_bpmn(mysqli $conn, int $versionId): string {
        $sql = "SELECT bpmn_xml FROM bpm_processes WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Erro ao preparar consulta BPMN: ' . $conn->error);
        }
        $stmt->bind_param("i", $versionId);
        $stmt->execute();
        $stmt->bind_result($bpmn);
        if (!$stmt->fetch() || !$bpmn) {
            $stmt->close();
            throw new Exception('BPMN não encontrado para version_id='.$versionId);
        }
        $stmt->close();
        return $bpmn;
    }

    function bpm_engine_build_index(string $bpmnXml): array {
        $xml = @simplexml_load_string($bpmnXml);
        if (!$xml) {
            throw new Exception('BPMN XML inválido.');
        }

        $namespaces = $xml->getNamespaces(true);
        $bpmnNs = $namespaces['bpmn'] ?? ($namespaces['bpmn2'] ?? null);
        if (!$bpmnNs) {
            $bpmnNs = 'http://www.omg.org/spec/BPMN/20100524/MODEL';
        }
        $xml->registerXPathNamespace('bpmn', $bpmnNs);

        $processList = $xml->xpath('//bpmn:process');
        if (!$processList || !isset($processList[0])) {
            throw new Exception('Nenhum <process> encontrado no BPMN.');
        }
        $process = $processList[0];

        $nodes = [];
        $types = ['startEvent','endEvent','userTask','serviceTask','exclusiveGateway','parallelGateway'];
        foreach ($types as $t) {
            $els = $process->xpath("bpmn:$t");
            if (!$els) continue;
            foreach ($els as $el) {
                $id = (string)$el['id'];
                if (!$id) continue;
                $mozCfg = bpm_engine_read_mozart_config_from_xml($el);
                $nodes[$id] = [
                'id'        => $id,
                'type'      => $t,
                'xml'       => $el,
                'mozart'    => $mozCfg,
                'exec_kind' => bpm_engine_node_exec_kind($t, $mozCfg),
                ];
            }
        }

        $flowsBySource = [];
        $flows = $process->xpath('bpmn:sequenceFlow') ?: [];
        foreach ($flows as $sf) {
            $fid    = (string)$sf['id'];
            $source = (string)$sf['sourceRef'];
            $target = (string)$sf['targetRef'];
            $condText = null;
            $conds = $sf->xpath('bpmn:conditionExpression');
            if ($conds && isset($conds[0])) {
                $condText = trim((string)$conds[0]);
            }
            $rec = [
                'id'        => $fid,
                'source'    => $source,
                'target'    => $target,
                'condition' => $condText,
            ];
            if (!isset($flowsBySource[$source])) $flowsBySource[$source] = [];
            $flowsBySource[$source][] = $rec;
        }

        return [$nodes, $flowsBySource];
    }

    /* =========================================================
     * Walk: andar no diagrama (UserTask, EndEvent, Gateways)
     * =======================================================*/

    function bpm_engine_walk(
        mysqli $conn,
        int $instanceId,
        array $nodes,
        array $flowsBySource,
        string $fromNodeId,
        array &$createdTasks,
        array &$visited,
        array $varsRuntime
    ): void {
        if (isset($visited[$fromNodeId])) {
            return; // evita loop em ciclos
        }
        $visited[$fromNodeId] = true;

        if (empty($flowsBySource[$fromNodeId])) {
            return;
        }

        $flows = $flowsBySource[$fromNodeId];
        $flow  = bpm_engine_choose_flow($flows, $varsRuntime);
        if (!$flow) {
            return;
        }

        $targetId = $flow['target'] ?? null;
        if (!$targetId || !isset($nodes[$targetId])) {
            return;
        }

        $target = $nodes[$targetId];
        $type   = $target['type'];

        switch ($type) {
            case 'userTask':
    // FASE 3/4 — runtime estrito
    $execKind = $target['exec_kind'] ?? 'human';
    if ($execKind !== 'human') {
        throw new Exception("FASE 3: userTask {$targetId} não pode executar como {$execKind}");
    }

    $req = bpm_engine_get_human_requirements($target['mozart'] ?? [], $target['xml']);

    // FASE 4 — humano exige form_slug + form_version
    if (empty($req['form_slug']) || empty($req['form_version'])) {
        throw new Exception(
            "FASE 4: UserTask {$targetId} sem form_slug/form_version (publish deveria congelar)"
        );
    }

    // FASE 3 — humano exige assignment
    if (!in_array($req['assignment_type'], ['user','role'], true) || empty($req['assignment_value'])) {
        throw new Exception(
            "FASE 3: UserTask {$targetId} sem assignment (user|role + valor)"
        );
    }

    $assigneeUserId = null;
    $candidateGroup = null;

    if ($req['assignment_type'] === 'user') {
        $assigneeUserId = ctype_digit($req['assignment_value'])
            ? (int)$req['assignment_value']
            : null;

        if (!$assigneeUserId) {
            throw new Exception(
                "UserTask {$targetId}: assignment user precisa ser user_id numérico"
            );
        }
    } else {
        // role
        $candidateGroup = $req['assignment_value'];
    }

    // ✅ INSERT ÚNICO E FINAL
    $sql = "
        INSERT INTO bpm_task
          (instance_id, node_id, type,
           assignee_user_id, candidate_group,
           form_slug, form_version,
           status, created_at)
        VALUES
          (?, ?, 'user',
           ?, ?,
           ?, ?,
           'ready', NOW())
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erro ao preparar insert bpm_task: ' . $conn->error);
    }

    $stmt->bind_param(
        "isisssi",
        $instanceId,
        $targetId,
        $assigneeUserId,
        $candidateGroup,
        $req['form_slug'],
        $req['form_version']
    );

    $stmt->execute();
    $taskId = (int)$conn->insert_id;
    $stmt->close();

    $createdTasks[] = $taskId;
    break;




            case 'endEvent':
                $sql = "UPDATE bpm_instance
                        SET status='completed',
                            ended_at = NOW(),
                            duration_ms = TIMESTAMPDIFF(MICROSECOND, started_at, NOW())/1000
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("i", $instanceId);
                    $stmt->execute();
                    $stmt->close();
                }
                break;

            default:
    // FASE 3: gateway/event nunca geram task (aqui só atravessa)
    // FASE 3: se alguém tentar marcar service como human (ou vice-versa), trava
    $execKind = $target['exec_kind'] ?? null;
    if ($type === 'serviceTask' && $execKind && $execKind !== 'service') {
        throw new Exception("FASE 3: serviceTask {$targetId} com exec_kind inválido: {$execKind}");
    }
    if (in_array($type, ['exclusiveGateway','parallelGateway','startEvent','endEvent'], true) && $execKind && !in_array($execKind, ['gateway','event'], true)) {
        throw new Exception("FASE 3: {$type} {$targetId} com exec_kind inválido: {$execKind}");
    }

    // serviceTask, gateways etc. — atravessa
    bpm_engine_walk($conn, $instanceId, $nodes, $flowsBySource, $targetId, $createdTasks, $visited, $varsRuntime);
    break;

        }
    }

    /* =========================================================
     * Start Instance (com payload inicial)
     * =======================================================*/

    function bpm_engine_start_instance_by_name(
        mysqli $conn,
        string $name,
        int $version,
        int $starterUserId,
        ?string $businessKey = null,
        array $vars = []
    ): array {
        // 1) Carrega processo
        $sql = "SELECT id, bpmn_xml FROM bpm_processes WHERE name = ? AND version = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Erro ao preparar consulta processo: ' . $conn->error);
        }
        $stmt->bind_param("si", $name, $version);
        $stmt->execute();
        $stmt->bind_result($procId, $bpmn);
        if (!$stmt->fetch() || !$bpmn) {
            $stmt->close();
            throw new Exception("Processo não encontrado: {$name} v{$version}");
        }
        $stmt->close();

        // 2) Índice do BPMN
        [$nodes, $flowsBySource] = bpm_engine_build_index($bpmn);

        // 3) StartEvent
        $startNodeId = null;
        foreach ($nodes as $id => $n) {
            if ($n['type'] === 'startEvent') { $startNodeId = $id; break; }
        }
        if (!$startNodeId) {
            throw new Exception('StartEvent não encontrado no diagrama.');
        }

        // 4) Cria instância
        $varsJson = !empty($vars)
            ? json_encode($vars, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        $sql = "INSERT INTO bpm_instance (version_id, business_key, starter_user_id, status, started_at, vars_snapshot)
                VALUES (?, ?, ?, 'running', NOW(), ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Erro ao preparar insert bpm_instance: ' . $conn->error);
        }
        $bk = $businessKey ?: null;
        $stmt->bind_param("isis", $procId, $bk, $starterUserId, $varsJson);
        $stmt->execute();
        $instanceId = (int)$conn->insert_id;
        $stmt->close();

        // 5) Log de início
        if ($instanceId > 0) {
            $etype = 'INSTANCE_STARTED';
            $dataJson = json_encode([
                'process_name'    => $name,
                'process_version' => $version,
                'vars'            => $vars
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $sql = "INSERT INTO bpm_event_log (instance_id, version_id, event_type, event_time, actor_user_id, data_json)
                    VALUES (?, ?, ?, NOW(), ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("iisis", $instanceId, $procId, $etype, $starterUserId, $dataJson);
                $stmt->execute();
                $stmt->close();
            }
        }

        // 6) Salva payload inicial em bpm_variable
        if (!empty($vars)) {
            bpm_engine_save_payload($conn, $instanceId, 0, $vars);
        }

        // 7) Caminha a partir do startEvent
        $createdTasks = [];
        $visited = [];
        $varsRuntime = $vars; // já temos em memória
        bpm_engine_walk($conn, $instanceId, $nodes, $flowsBySource, $startNodeId, $createdTasks, $visited, $varsRuntime);

        return [
            'ok'            => true,
            'instance_id'   => $instanceId,
            'version_id'    => (int)$procId,
            'created_tasks' => $createdTasks,
        ];
    }

    /* =========================================================
     * Advance from Task (com payload e gateways)
     * =======================================================*/

    function bpm_engine_advance_from_task(mysqli $conn, int $taskId, int $actorUserId, array $payload = []): array {
        $conn->begin_transaction();
        try {
            // 1) Busca task + instance + versão
            $sql = "SELECT t.id, t.instance_id, t.node_id, t.status, i.version_id
                    FROM bpm_task t
                    JOIN bpm_instance i ON i.id = t.instance_id
                    WHERE t.id = ?
                    FOR UPDATE";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Erro ao preparar select task: ' . $conn->error);
            }
            $stmt->bind_param("i", $taskId);
            $stmt->execute();
            $stmt->bind_result($id, $instanceId, $nodeId, $status, $versionId);
            if (!$stmt->fetch()) {
                $stmt->close();
                throw new Exception('Tarefa não encontrada.');
            }
            $stmt->close();

            if (!in_array($status, ['ready','claimed','in_progress'], true)) {
                throw new Exception('Tarefa não está em um estado concluível.');
            }

            // 2) Marca task como concluída
            $sql = "UPDATE bpm_task
                    SET status='completed',
                        completed_at = NOW(),
                        duration_ms = TIMESTAMPDIFF(MICROSECOND, created_at, NOW())/1000
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Erro ao preparar update bpm_task: ' . $conn->error);
            }
            $stmt->bind_param("i", $taskId);
            $stmt->execute();
            if ($stmt->affected_rows <= 0) {
                $stmt->close();
                throw new Exception('Não foi possível concluir a tarefa (talvez já concluída?).');
            }
            $stmt->close();

            // 3) Salva payload em bpm_variable (se houver)
            if (!empty($payload)) {
                bpm_engine_save_payload($conn, (int)$instanceId, $taskId, $payload);
            }

            // 4) Carrega todas as variáveis da instância
            $varsPersistidas = bpm_engine_load_vars($conn, (int)$instanceId);
            // payload mais recente tem prioridade
            $varsRuntime = array_merge($varsPersistidas, $payload);

            // 5) Log de conclusão
            $etype = 'TASK_COMPLETED';
            $dataJson = json_encode([
                'taskId'  => $taskId,
                'payload' => $payload
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $sql = "INSERT INTO bpm_event_log (instance_id, version_id, event_type, event_time, actor_user_id, data_json)
                    VALUES (?, ?, ?, NOW(), ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("iisis", $instanceId, $versionId, $etype, $actorUserId, $dataJson);
                $stmt->execute();
                $stmt->close();
            }

            // 6) Carrega BPMN e avança fluxo a partir do node_id da task
            $bpmn = bpm_engine_load_bpmn($conn, (int)$versionId);
            [$nodes, $flowsBySource] = bpm_engine_build_index($bpmn);

            $createdTasks = [];
            $visited = [];
            bpm_engine_walk($conn, (int)$instanceId, $nodes, $flowsBySource, (string)$nodeId, $createdTasks, $visited, $varsRuntime);

            $conn->commit();
            return [
                'ok'            => true,
                'instance_id'   => (int)$instanceId,
                'version_id'    => (int)$versionId,
                'created_tasks' => $createdTasks,
            ];
        } catch (Exception $e) {
            $conn->rollback();
            return ['ok'=>false, 'error'=>$e->getMessage()];
        }
    }
}
