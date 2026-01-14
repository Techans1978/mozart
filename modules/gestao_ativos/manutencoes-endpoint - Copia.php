<?php
// public/modules/gestao_ativos/manutencoes-endpoint.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
require_once ROOT_PATH.'/modules/gestao_ativos/manutencao_lib.php';

if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

header('Content-Type: application/json; charset=utf-8');

$dbc = $conn ?? null; if(!$dbc){ echo json_encode(['error'=>'Sem conexão']); exit; }

$csrf = $_SESSION['csrf_manut'] ?? '';
if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
  echo json_encode(['error'=>'CSRF inválido']); exit;
}

$draw = (int)($_POST['draw'] ?? 1);
$start = max(0, (int)($_POST['start'] ?? 0));
$len   = max(10, (int)($_POST['length'] ?? 25));
$search = trim($_POST['search']['value'] ?? '');

$cols = [
  'm.id','m.ativo_nome','e.nome_fantasia','m.setor_txt','m.tipo','m.origem',
  'm.data_prevista','m.data_manutencao','m.status','m.valor'
];

$orderColIdx = (int)($_POST['order'][0]['column'] ?? 6);
$orderDir = (($_POST['order'][0]['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
$orderBy = $cols[$orderColIdx] ?? 'm.data_prevista';

$where = "1=1";
$params = [];
$types = "";

if ($search !== '') {
  $where .= " AND (m.ativo_nome LIKE CONCAT('%',?,'%')
              OR m.numero_chamado LIKE CONCAT('%',?,'%')
              OR m.fornecedor LIKE CONCAT('%',?,'%')
              OR m.descricao_servico LIKE CONCAT('%',?,'%')
              OR m.status LIKE CONCAT('%',?,'%')
              OR m.tipo LIKE CONCAT('%',?,'%')
              )";
  $types .= "ssssss";
  $params = array_merge($params, [$search,$search,$search,$search,$search,$search]);
}

// total
$total = 0;
$r = $dbc->query("SELECT COUNT(*) c FROM moz_ativo_manut");
if($r){ $total = (int)($r->fetch_assoc()['c'] ?? 0); }

// filtered
$sqlCount = "SELECT COUNT(*) c FROM moz_ativo_manut m WHERE $where";
$st = $dbc->prepare($sqlCount);
if($types) $st->bind_param($types, ...$params);
$st->execute();
$filtered = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
$st->close();

// data
$sql = "
  SELECT m.*,
         COALESCE(NULLIF(e.nome_fantasia,''), NULLIF(e.nome_empresarial,''), CONCAT('Empresa #', m.local_id)) AS local_nome
    FROM moz_ativo_manut m
    LEFT JOIN empresas e ON e.id = m.local_id
   WHERE $where
   ORDER BY $orderBy $orderDir
   LIMIT ?,?
";

$st = $dbc->prepare($sql);
if($types){
  $types2 = $types . "ii";
  $params2 = array_merge($params, [$start,$len]);
  $st->bind_param($types2, ...$params2);
} else {
  $st->bind_param("ii", $start, $len);
}
$st->execute();
$rs = $st->get_result();

$data = [];
while($m = $rs->fetch_assoc()){
  $id = (int)$m['id'];
  $ativo = '#'.(int)$m['ativo_id'].' — '.($m['ativo_nome'] ?? '');
  $acoes = '<a class="btn small" href="manutencoes-form.php?id='.$id.'">Editar</a>';

  $data[] = [
    'id' => $id,
    'ativo' => h($ativo),
    'local' => h($m['local_nome'] ?? ''),
    'setor' => h($m['setor_txt'] ?? ''),
    'tipo' => h($m['tipo'] ?? ''),
    'origem' => h($m['origem'] ?? ''),
    'prevista' => h($m['data_prevista'] ?? ''),
    'data_manutencao' => h($m['data_manutencao'] ?? ''),
    'status' => h($m['status'] ?? ''),
    'valor' => h($m['valor'] ?? ''),
    'acoes' => $acoes,
  ];
}
$st->close();

echo json_encode([
  'draw' => $draw,
  'recordsTotal' => $total,
  'recordsFiltered' => $filtered,
  'data' => $data
]);
