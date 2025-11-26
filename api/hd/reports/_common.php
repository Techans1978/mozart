<?php
// public/api/hd/reports/_common.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (!function_exists('proteger_pagina')) { function proteger_pagina(){} }
proteger_pagina();

$dbc = $conn ?? (isset($mysqli) ? $mysqli : null);
if (!$dbc instanceof mysqli) { http_response_code(500); die(json_encode(['error'=>'Sem conexão MySQLi ($conn).'])); }

header('Content-Type: application/json; charset=utf-8');

function jout($data, $extraMeta = []) {
  $out = ['ok'=>true, 'meta'=>array_merge(['now'=>date('c')], $extraMeta), 'data'=>$data];
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
  exit;
}
function jerr($msg, $code = 400) { http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE); exit; }
function ints($arr){ return array_values(array_filter(array_map(fn($x)=>(int)$x, (array)$arr), fn($v)=>$v>0)); }

function get_filters(mysqli $db){
  $date_from = $_GET['date_from'] ?? null;
  $date_to   = $_GET['date_to']   ?? null;
  $empresa_ids = $_GET['empresa_ids'] ?? null; if (is_string($empresa_ids) && strpos($empresa_ids, ',')!==false) $empresa_ids=explode(',',$empresa_ids); $empresa_ids=ints($empresa_ids);
  $loja_ids = $_GET['loja_ids'] ?? null; if (is_string($loja_ids) && strpos($loja_ids, ',')!==false) $loja_ids=explode(',',$loja_ids); $loja_ids=ints($loja_ids);
  $categoria_ids = $_GET['categoria_ids'] ?? null; if (is_string($categoria_ids) && strpos($categoria_ids, ',')!==false) $categoria_ids=explode(',',$categoria_ids); $categoria_ids=ints($categoria_ids);
  $status = $_GET['status'] ?? null;
  $q = trim($_GET['q'] ?? '');

  if (!$date_from || !$date_to){ $date_to=date('Y-m-d'); $date_from=date('Y-m-d', strtotime('-30 days')); }
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from=date('Y-m-d', strtotime('-30 days'));
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to=date('Y-m-d');

  $limit  = isset($_GET['limit'])  ? max(1, min(1000, (int)$_GET['limit'])) : 200;
  $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

  return compact('date_from','date_to','empresa_ids','loja_ids','categoria_ids','status','q','limit','offset');
}

function build_where(mysqli $db, $F){
  $w = ["(t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))"];
  $params = [$F['date_from'], $F['date_to']];
  $types  = "ss";

  if (!empty($F['empresa_ids'])) { $in = implode(',', array_fill(0, count($F['empresa_ids']), '?')); $w[] = "t.entidade_id IN ($in)"; $types .= str_repeat('i', count($F['empresa_ids'])); $params = array_merge($params, $F['empresa_ids']); }
  if (!empty($F['loja_ids']))    { $in = implode(',', array_fill(0, count($F['loja_ids']), '?'));    $w[] = "t.loja_id IN ($in)";      $types .= str_repeat('i', count($F['loja_ids']));    $params = array_merge($params, $F['loja_ids']); }
  if (!empty($F['categoria_ids'])){ $in = implode(',', array_fill(0, count($F['categoria_ids']), '?')); $w[] = "t.categoria_id IN ($in)"; $types .= str_repeat('i', count($F['categoria_ids'])); $params = array_merge($params, $F['categoria_ids']); }
  if (!empty($F['status'])) { $w[] = "t.status = ?"; $types .= "s"; $params[] = $F['status']; }

  return ["WHERE ".implode(" AND ", $w), $types, $params];
}

function q_all(mysqli $db, $sql, $types, $params){
  $stmt = $db->prepare($sql); if(!$stmt){ jerr("Erro prepare: ".$db->error, 500); }
  if($types && $params){ $stmt->bind_param($types, ...$params); }
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  if ($res) { while($row = $res->fetch_assoc()) $rows[] = $row; }
  $stmt->close();
  return $rows;
}

function run_report(string $name, mysqli $db){
  $F = get_filters($db);
  list($where, $types, $params) = build_where($db, $F);

  switch ($name) {

    case 'minidash-comparar':
      $sql = "SELECT e.id empresa_id,
                     COALESCE(e.nome, CONCAT('Empresa #',t.entidade_id)) empresa,
                     COUNT(*) total,
                     SUM(CASE WHEN t.status IN ('aberto','novo','pendente','em_andamento') THEN 1 ELSE 0 END) abertos,
                     SUM(CASE WHEN t.status IN ('resolvido','fechado') THEN 1 ELSE 0 END) fechados,
                     SUM(CASE WHEN t.sla_due_at IS NOT NULL AND COALESCE(t.closed_at, NOW()) > t.sla_due_at THEN 1 ELSE 0 END) em_atraso
              FROM hd_ticket t
              LEFT JOIN empresa e ON e.id=t.entidade_id
              $where
              GROUP BY e.id, empresa
              ORDER BY total DESC
              LIMIT ? OFFSET ?";
      $rows = q_all($db,$sql,$types.'ii', array_merge($params,[$F['limit'],$F['offset']]));
      jout($rows, ['filters'=>$F]);
      break;

    case 'busca-inteligente':
      $q = '%'.$F['q'].'%';
      $sql = "SELECT t.id, t.protocolo, t.subject, t.status, t.prioridade, t.created_at, t.updated_at, e.nome empresa
              FROM hd_ticket t
              LEFT JOIN empresa e ON e.id=t.entidade_id
              $where
                AND (?='%%' OR t.protocolo LIKE ? OR t.subject LIKE ? OR EXISTS(
                      SELECT 1 FROM hd_ticket_msg m WHERE m.ticket_id=t.id AND m.body LIKE ?
                    ))
              ORDER BY t.created_at DESC
              LIMIT ? OFFSET ?";
      $types2 = $types . 'sss' . 's' . 'ii';
      $params2 = array_merge($params, [$q,$q,$q,$q,$F['limit'],$F['offset']]);
      $rows = q_all($db,$sql,$types2,$params2);
      jout($rows, ['filters'=>$F]);
      break;

    case 'listagem-usuarios':
      $sql="SELECT u.id user_id, u.nome usuario, COUNT(*) tickets_abridos,
                   SUM(CASE WHEN t.status IN ('resolvido','fechado') THEN 1 ELSE 0 END) tickets_fechados,
                   MIN(t.created_at) primeiro_ticket, MAX(t.created_at) ultimo_ticket
            FROM hd_ticket t LEFT JOIN usuarios u ON u.id=t.solicitante_user_id
            $where
            GROUP BY u.id, usuario
            ORDER BY tickets_abridos DESC
            LIMIT ? OFFSET ?";
      $rows=q_all($db,$sql,$types.'ii', array_merge($params,[$F['limit'],$F['offset']]));
      jout($rows,['filters'=>$F]);
      break;

    case 'metricas-por-categoria':
      $sql="SELECT t.categoria_id, COALESCE(c.nome, CONCAT('Categoria #',t.categoria_id)) categoria,
                   COUNT(*) total,
                   SUM(CASE WHEN t.status IN ('resolvido','fechado') THEN 1 ELSE 0 END) fechados,
                   AVG(TIMESTAMPDIFF(HOUR, t.created_at, COALESCE(t.closed_at, NOW()))) ttr_horas
            FROM hd_ticket t LEFT JOIN hd_categoria c ON c.id=t.categoria_id
            $where
            GROUP BY t.categoria_id, categoria
            ORDER BY total DESC
            LIMIT ? OFFSET ?";
      $rows=q_all($db,$sql,$types.'ii', array_merge($params,[$F['limit'],$F['offset']]));
      jout($rows,['filters'=>$F]);
      break;

    case 'listagem-sla':
      $sql="SELECT t.id, t.protocolo, e.nome empresa, t.subject, t.created_at, t.sla_due_at, t.closed_at, t.status,
                   CASE WHEN t.sla_due_at IS NULL THEN 'sem_sla'
                        WHEN COALESCE(t.closed_at, NOW()) <= t.sla_due_at THEN 'no_prazo'
                        ELSE 'em_atraso' END sla_status
            FROM hd_ticket t LEFT JOIN empresa e ON e.id=t.entidade_id
            $where
            ORDER BY t.sla_due_at IS NULL, t.sla_due_at ASC
            LIMIT ? OFFSET ?";
      $rows=q_all($db,$sql,$types.'ii', array_merge($params,[$F['limit'],$F['offset']]));
      jout($rows,['filters'=>$F]);
      break;

    case 'listagem-reaberturas':
      $sql="SELECT t.id, t.protocolo, e.nome empresa, t.subject, t.reopened_count, t.status, t.created_at, t.updated_at
            FROM hd_ticket t LEFT JOIN empresa e ON e.id=t.entidade_id
            $where
              AND COALESCE(t.reopened_count,0) > 0
            ORDER BY t.reopened_count DESC, t.updated_at DESC
            LIMIT ? OFFSET ?";
      $rows=q_all($db,$sql,$types.'ii', array_merge($params,[$F['limit'],$F['offset']]));
      jout($rows,['filters'=>$F]);
      break;

    case 'listagem-backlog':
      $sql="SELECT t.id, t.protocolo, e.nome empresa, t.subject, t.status, t.created_at, t.updated_at,
                   TIMESTAMPDIFF(DAY, t.created_at, NOW()) dias_aberto
            FROM hd_ticket t LEFT JOIN empresa e ON e.id=t.entidade_id
            $where
              AND t.status NOT IN ('resolvido','fechado')
            ORDER BY t.created_at ASC
            LIMIT ? OFFSET ?";
      $rows=q_all($db,$sql,$types.'ii', array_merge($params,[$F['limit'],$F['offset']]));
      jout($rows,['filters'=>$F]);
      break;

    case 'listagem-sem-atualizacao':
      $stale_days = isset($_GET['stale_days']) ? max(1,(int)$_GET['stale_days']) : 7;
      $sql="SELECT t.id, t.protocolo, e.nome empresa, t.subject, t.status, t.created_at, t.updated_at,
                   TIMESTAMPDIFF(DAY, t.updated_at, NOW()) dias_sem_update
            FROM hd_ticket t LEFT JOIN empresa e ON e.id=t.entidade_id
            $where
              AND t.updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
              AND t.status NOT IN ('resolvido','fechado')
            ORDER BY t.updated_at ASC
            LIMIT ? OFFSET ?";
      $rows=q_all($db,$sql,$types.'iii', array_merge($params,[$stale_days,$F['limit'],$F['offset']]));
      jout($rows,['filters'=>array_merge($F,['stale_days'=>$stale_days])]);
      break;

    case 'listagem-demanda':
      $sql="SELECT DATE(t.created_at) dia, COUNT(*) aberturas
            FROM hd_ticket t
            $where
            GROUP BY dia
            ORDER BY dia ASC
            LIMIT ? OFFSET ?";
      $rows=q_all($db,$sql,$types.'ii', array_merge($params,[$F['limit'],$F['offset']]));
      jout($rows,['filters'=>$F]);
      break;

    case 'heatmap-aberturas':
      $sql="SELECT WEEKDAY(t.created_at) dow, HOUR(t.created_at) hora, COUNT(*) qtd
            FROM hd_ticket t
            $where
            GROUP BY dow, hora
            ORDER BY dow ASC, hora ASC
            LIMIT ? OFFSET ?";
      $rows=q_all($db,$sql,$types.'ii', array_merge($params,[$F['limit'],$F['offset']]));
      jout($rows,['filters'=>$F,'legend'=>['dow: 0=Seg, 6=Dom']]);
      break;

    case 'relatorio-por-origem':
      $sql="SELECT COALESCE(t.origem,'desconhecida') origem, COUNT(*) total
            FROM hd_ticket t
            $where
            GROUP BY origem
            ORDER BY total DESC
            LIMIT ? OFFSET ?";
      $rows=q_all($db,$sql,$types.'ii', array_merge($params,[$F['limit'],$F['offset']]));
      jout($rows,['filters'=>$F]);
      break;

    case 'metricas-por-loja':
      $sql="SELECT t.loja_id,
                   COALESCE(l.nome, CONCAT('Loja #',t.loja_id)) loja,
                   COALESCE(e.nome, CONCAT('Empresa #',t.entidade_id)) empresa,
                   COUNT(*) total,
                   SUM(CASE WHEN t.status IN ('resolvido','fechado') THEN 1 ELSE 0 END) fechados,
                   SUM(CASE WHEN t.sla_due_at IS NOT NULL AND COALESCE(t.closed_at, NOW()) > t.sla_due_at THEN 1 ELSE 0 END) em_atraso,
                   AVG(TIMESTAMPDIFF(HOUR, t.created_at, COALESCE(t.closed_at, NOW()))) ttr_horas
            FROM hd_ticket t
            LEFT JOIN empresa e ON e.id=t.entidade_id
            LEFT JOIN loja l ON l.id=t.loja_id
            $where
            GROUP BY t.loja_id, loja, empresa
            ORDER BY total DESC
            LIMIT ? OFFSET ?";
      $rows=q_all($db,$sql,$types.'ii', array_merge($params,[$F['limit'],$F['offset']]));
      jout($rows,['filters'=>$F]);
      break;

    default: jerr('Relatório desconhecido: '.$name,404);
  }
}
