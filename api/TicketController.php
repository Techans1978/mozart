<?php
// public/modules/helpdesk/api/TicketController.php
namespace HD\Api;

use mysqli;
use Exception;

class TicketController {
  private mysqli $db;
  public function __construct($conn) {
    Utils::dbAssert($conn);
    $this->db = $conn;
  }

  // POST /api/hd/tickets
  public function createTicket(array $data): array {
    $uid = Utils::userId();
    if (!$uid) return ['error'=>'unauthorized'];

    $servico_id = (int)($data['servico_id'] ?? 0);
    $titulo     = trim($data['titulo'] ?? '');
    $descricao  = trim($data['descricao'] ?? '');
    $canal      = $data['canal'] ?? 'portal';
    $entidade_id= (int)($data['entidade_id'] ?? 0);
    $loja_id    = isset($data['loja_id']) ? (int)$data['loja_id'] : null;
    $prioridade = $data['prioridade'] ?? 'media';
    $critic     = $data['criticidade'] ?? 'C3';
    $ativo_id   = isset($data['ativo_id']) ? (int)$data['ativo_id'] : null;
    $ativo_tag  = isset($data['ativo_tag']) ? substr(trim($data['ativo_tag']), 0, 64) : null;
    $form_answers = $data['form_answers'] ?? null;

    if ($servico_id<=0 || $entidade_id<=0 || $titulo==='') {
      return ['error'=>'missing_fields'];
    }

    // Busca serviço para defaults
    $srv = $this->db->query("SELECT id, categoria_id, grupo_atendimento_id FROM hd_servico WHERE id={$servico_id} AND ativo=1");
    if (!$srv || $srv->num_rows===0) return ['error'=>'servico_invalido'];
    $serv = $srv->fetch_assoc();
    $grupo_id = $serv['grupo_atendimento_id'] ? (int)$serv['grupo_atendimento_id'] : null;
    $categoria_id = (int)$serv['categoria_id'];

    // Protocolo simples
    $proto = 'TCK-'.date('YmdHis').'-'.mt_rand(100,999);

    // Insert ticket
    $stmt = $this->db->prepare("
      INSERT INTO hd_ticket
      (protocolo, entidade_id, loja_id, solicitante_user_id, servico_id, categoria_id, grupo_atendimento_id, ativo_id, ativo_tag,
       prioridade, criticidade, status, canal, titulo, descricao, created_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())
    ");
    $status = 'novo';
    $stmt->bind_param('siiiiiiisssssss',
      $proto, $entidade_id, $loja_id, $uid, $servico_id, $categoria_id, $grupo_id,
      $ativo_id, $ativo_tag, $prioridade, $critic, $status, $canal, $titulo, $descricao
    );
    if (!$stmt->execute()) { return ['error'=>'db_insert_ticket','msg'=>$stmt->error]; }
    $ticket_id = (int)$stmt->insert_id;

    // Form respostas (opcional)
    if ($form_answers && is_array($form_answers)) {
      // Descobre form_def_id do serviço
      $r = $this->db->query("SELECT form_def_id FROM hd_servico WHERE id={$servico_id}");
      $form_def_id = 0;
      if ($r && $rw=$r->fetch_assoc()) { $form_def_id = (int)$rw['form_def_id']; }
      if ($form_def_id>0) {
        $stmt2 = $this->db->prepare("INSERT INTO hd_form_resposta (form_def_id, ticket_id, answers_json, validado, created_at) VALUES (?,?,?,0,NOW())");
        $ans = json_encode($form_answers, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $stmt2->bind_param('iis', $form_def_id, $ticket_id, $ans);
        $stmt2->execute();
      }
    }

    // Mensagem inicial (se veio descrição)
    if ($descricao!=='') {
      $stmt3 = $this->db->prepare("
        INSERT INTO hd_mensagem (ticket_id, autor_user_id, visibilidade, canal_origem, texto_plain, tem_anexo, created_at)
        VALUES (?,?, 'publica', ?, ?, 0, NOW())
      ");
      $stmt3->bind_param('iiss', $ticket_id, $uid, $canal, $descricao);
      $stmt3->execute();
    }

    // Snapshot SLA (política mais específica por serviço/criticidade; se não, pega base)
    $sla = $this->db->query("
      SELECT id, primeira_resposta_alvo, resolucao_alvo
      FROM hd_sla_policy
      WHERE (servico_id IS NULL OR servico_id={$servico_id})
        AND criticidade='{$this->db->real_escape_string($critic)}'
      ORDER BY (servico_id IS NULL), id DESC
      LIMIT 1
    ");
    if ($sla && $slaRow=$sla->fetch_assoc()) {
      $firstMin = (int)$slaRow['primeira_resposta_alvo'];
      $resMin   = (int)$slaRow['resolucao_alvo'];
      $due1 = date('Y-m-d H:i:s', time() + $firstMin*60);
      $due2 = date('Y-m-d H:i:s', time() + $resMin*60);
      $stmt4 = $this->db->prepare("
        INSERT INTO hd_sla_aplicacao (ticket_id, sla_policy_id, primeira_resposta_due, resolucao_due, status, created_at)
        VALUES (?,?,?,?, 'em_andamento', NOW())
      ");
      $sla_id = (int)$slaRow['id'];
      $stmt4->bind_param('iiss', $ticket_id, $sla_id, $due1, $due2);
      $stmt4->execute();
    }

    return ['ok'=>true, 'ticket_id'=>$ticket_id, 'protocolo'=>$proto];
  }

  // GET /api/hd/tickets/mine
  public function listMyTickets(array $q): array {
    $uid = Utils::userId(); if (!$uid) return ['error'=>'unauthorized'];
    [$page,$per,$off] = Utils::paginate($q['page']??1, $q['per']??20);

    $where = "t.solicitante_user_id={$uid}";
    if (!empty($q['status'])) {
      $status = $this->db->real_escape_string($q['status']);
      $where .= " AND t.status='{$status}'";
    }
    if (!empty($q['search'])) {
      $s = $this->db->real_escape_string($q['search']);
      $where .= " AND (t.protocolo LIKE '%{$s}%' OR t.titulo LIKE '%{$s}%' OR t.ativo_tag LIKE '%{$s}%')";
    }

    $sqlCount = "SELECT COUNT(*) c FROM hd_ticket t WHERE {$where}";
    $total = (int)$this->db->query($sqlCount)->fetch_assoc()['c'];

    $sql = "
      SELECT t.id, t.protocolo, t.titulo, t.status, t.prioridade, t.created_at,
             s.nome AS servico_nome
      FROM hd_ticket t
      LEFT JOIN hd_servico s ON s.id=t.servico_id
      WHERE {$where}
      ORDER BY GREATEST(t.updated_at, t.created_at) DESC
      LIMIT {$off}, {$per}
    ";
    $rows = [];
    if ($rs = $this->db->query($sql)) {
      while ($r = $rs->fetch_assoc()) { $rows[] = $r; }
    }
    return ['ok'=>true, 'page'=>$page, 'per'=>$per, 'total'=>$total, 'items'=>$rows];
  }

  // GET /api/hd/tickets/:id
  public function getTicket(int $id): array {
    $uid = Utils::userId(); if (!$uid) return ['error'=>'unauthorized'];

    // Ticket principal
    $sql = "
      SELECT t.*, s.nome AS servico_nome, sc.nome AS categoria_nome,
             sp.nome AS sla_nome, sa.primeira_resposta_due, sa.resolucao_due, sa.status AS sla_status
      FROM hd_ticket t
      LEFT JOIN hd_servico s ON s.id=t.servico_id
      LEFT JOIN hd_servico_categoria sc ON sc.id=t.categoria_id
      LEFT JOIN hd_sla_aplicacao sa ON sa.ticket_id=t.id
      LEFT JOIN hd_sla_policy sp ON sp.id=sa.sla_policy_id
      WHERE t.id={$id}
      LIMIT 1
    ";
    $tk = $this->db->query($sql)->fetch_assoc();
    if (!$tk) return ['error'=>'not_found'];

    // Permissão básica: solicitante vê seu ticket; técnicos/gestores verão em outra etapa
    if ((int)$tk['solicitante_user_id'] !== $uid) {
      // (Simples: autoriza; reforço de RBAC na etapa do Agente)
      // return ['error'=>'forbidden'];
    }

    // Mensagens
    $msgs = [];
    $r = $this->db->query("SELECT m.*, u.nome AS autor_nome
                           FROM hd_mensagem m
                           LEFT JOIN usuarios u ON u.id=m.autor_user_id
                           WHERE m.ticket_id={$id}
                           ORDER BY m.created_at ASC");
    if ($r) { while($x=$r->fetch_assoc()) $msgs[]=$x; }

    // Histórico de status
    $hist = [];
    $r = $this->db->query("SELECT * FROM hd_ticket_status_hist WHERE ticket_id={$id} ORDER BY created_at ASC");
    if ($r) { while($x=$r->fetch_assoc()) $hist[]=$x; }

    // Tarefas
    $tasks = [];
    $r = $this->db->query("SELECT * FROM hd_tarefa WHERE ticket_id={$id} ORDER BY created_at ASC");
    if ($r) { while($x=$r->fetch_assoc()) $tasks[]=$x; }

    return ['ok'=>true, 'ticket'=>$tk, 'mensagens'=>$msgs, 'status_hist'=>$hist, 'tarefas'=>$tasks];
  }

  // POST /api/hd/tickets/:id/mensagens
  public function addMessage(int $ticket_id, array $post, array $files): array {
    $uid = Utils::userId(); if (!$uid) return ['error'=>'unauthorized'];

    // Checa ticket
    $tk = $this->db->query("SELECT id FROM hd_ticket WHERE id={$ticket_id} LIMIT 1")->fetch_assoc();
    if (!$tk) return ['error'=>'not_found'];

    $texto = trim($post['texto'] ?? '');
    $vis   = ($post['visibilidade'] ?? 'publica') === 'privada' ? 'privada' : 'publica';
    $canal = 'portal';

    $tem_anexo = 0;
    $this->db->begin_transaction();
    try {
      $stmt = $this->db->prepare("
        INSERT INTO hd_mensagem (ticket_id, autor_user_id, visibilidade, canal_origem, texto_plain, tem_anexo, created_at)
        VALUES (?,?,?,?,?,0,NOW())
      ");
      $stmt->bind_param('iisss', $ticket_id, $uid, $vis, $canal, $texto);
      if (!$stmt->execute()) throw new Exception('msg_insert');

      $msg_id = (int)$stmt->insert_id;

      // Uploads (múltiplos)
      if (!empty($files['anexos']) && is_array($files['anexos']['name'])) {
        $count = count($files['anexos']['name']);
        for ($i=0;$i<$count;$i++) {
          $one = [
            'name'     => $files['anexos']['name'][$i],
            'type'     => $files['anexos']['type'][$i] ?? null,
            'tmp_name' => $files['anexos']['tmp_name'][$i],
            'error'    => $files['anexos']['error'][$i],
            'size'     => $files['anexos']['size'][$i],
          ];
          $res = Utils::storeUpload($one, ROOT_PATH.'/uploads/helpdesk');
          if ($res['ok']) {
            $tem_anexo = 1;
            $stmt2 = $this->db->prepare("INSERT INTO hd_anexo (mensagem_id, ticket_id, filename, mime, size, storage_path, created_at) VALUES (?,?,?,?,?,?,NOW())");
            $stmt2->bind_param('iissis', $msg_id, $ticket_id, $res['filename'], $res['mime'], $res['size'], $res['path']);
            $stmt2->execute();
          }
        }
        // Atualiza flag de anexo
        if ($tem_anexo===1) {
          $this->db->query("UPDATE hd_mensagem SET tem_anexo=1 WHERE id={$msg_id}");
        }
      }

      // Atualiza updated_at do ticket
      $this->db->query("UPDATE hd_ticket SET updated_at=NOW() WHERE id={$ticket_id}");

      $this->db->commit();
      return ['ok'=>true, 'mensagem_id'=>$msg_id, 'tem_anexo'=>$tem_anexo];
    } catch (Exception $e) {
      $this->db->rollback();
      return ['error'=>'exception','msg'=>$e->getMessage()];
    }
  }

  // POST /api/hd/tickets/:id/avaliacao
  public function rateTicket(int $ticket_id, array $data): array {
    $uid = Utils::userId(); if (!$uid) return ['error'=>'unauthorized'];
    $nota = (int)($data['nota'] ?? 0);
    $coment = substr(trim($data['comentario'] ?? ''), 0, 500);
    if ($nota<=0 || $nota>5) return ['error'=>'nota_invalida'];

    // Evita duplicidade (1 avaliação por ticket)
    $chk = $this->db->query("SELECT id FROM hd_satisfacao WHERE ticket_id={$ticket_id} LIMIT 1");
    if ($chk && $chk->num_rows>0) return ['error'=>'ja_avaliado'];

    $stmt = $this->db->prepare("INSERT INTO hd_satisfacao (ticket_id, nota, comentario, coletado_por, coletado_em) VALUES (?,?,?, 'portal', NOW())");
    $stmt->bind_param('iis', $ticket_id, $nota, $coment);
    if (!$stmt->execute()) return ['error'=>'db_insert','msg'=>$stmt->error];

    return ['ok'=>true];
  }
}
