<?php
// system/services/feed_bpm_provider.php
// Provider opcional de itens de Feed para eventos BPM (instâncias/tarefas).
if (!function_exists('feed_fetch_bpm_items')) {
  function feed_fetch_bpm_items(mysqli $conn, int $uid, int $limit = 10, int $offset = 0): array {
    $items = [];

    // helper: existe tabela?
    $exists = function(string $t) use ($conn): bool {
      $t = $conn->real_escape_string($t);
      $rs = $conn->query("SHOW TABLES LIKE '$t'");
      return $rs && $rs->num_rows > 0;
    };
    if (!$exists('bpm_event_log')) return $items;

    $uid_sql = (int)$uid;
    $q = "
      SELECT e.id, e.instance_id, e.event_type, e.event_time, e.actor_user_id, e.data_json,
             i.version_id, i.status AS inst_status,
             pv.semver AS version, p.name AS process_name
      FROM bpm_event_log e
      LEFT JOIN bpm_instance i         ON i.id = e.instance_id
      LEFT JOIN bpm_process_version pv ON pv.id = i.version_id
      LEFT JOIN bpm_process p          ON p.id = pv.process_id
      WHERE (e.actor_user_id = $uid_sql OR e.instance_id IS NOT NULL)
      ORDER BY e.event_time DESC
      LIMIT $limit OFFSET $offset
    ";
    if ($rs = $conn->query($q)) {
      while ($r = $rs->fetch_assoc()) {
        $evt  = (string)($r['event_type'] ?? '');
        $proc = (string)($r['process_name'] ?? 'Processo');
        $inst = (int)($r['instance_id'] ?? 0);
        $semv = (string)($r['version'] ?? '');
        $time = (string)($r['event_time'] ?? '');

        $titulo = 'BPM · ' . $proc;
        $resumo = '';
        switch ($evt) {
          case 'INSTANCE_STARTED':
            $titulo .= ' — Instância iniciada';
            $resumo  = 'Instância #'.$inst.' iniciada.';
            break;
          case 'INSTANCE_FINISHED':
            $titulo .= ' — Instância concluída';
            $resumo  = 'Instância #'.$inst.' concluída.';
            break;
          case 'TASK_CREATED':
            $titulo .= ' — Nova tarefa criada';
            $resumo  = 'Tarefa criada na instância #'.$inst.'.';
            break;
          case 'TASK_ASSIGNED':
            $titulo .= ' — Tarefa atribuída';
            $resumo  = 'Tarefa atribuída na instância #'.$inst.'.';
            break;
          case 'TASK_COMPLETED':
            $titulo .= ' — Tarefa concluída';
            $resumo  = 'Tarefa concluída na instância #'.$inst.'.';
            break;
          default:
            $titulo .= ' — '.$evt;
            $resumo  = 'Instância #'.$inst.' · Evento: '.$evt;
        }

        $items[] = [
          'id'       => $inst,
          'tipo'     => 'bpm',
          'titulo'   => $titulo,
          'resumo'   => $resumo,
          'dt_ref'   => $time,
          'featured' => 0,
          'link'     => BASE_URL.'/modules/bpm/instancia-detalhes.php?id='.$inst,
          'extra'    => ['processo'=>$proc, 'version'=>$semv, 'evento'=>$evt]
        ];
      }
    }
    return $items;
  }
}
