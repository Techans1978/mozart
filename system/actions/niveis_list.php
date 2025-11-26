<?php
// pages/niveis_list.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/config/db.php'; // ajuste para onde a conexão $mysqli/$conn é criada

$mysqli = $mysqli ?? ($conn ?? null);
if (!$mysqli) { http_response_code(500); echo json_encode(['error'=>'Sem conexão DB']); exit; }

$draw   = (int)($_GET['draw']   ?? 1);
$start  = (int)($_GET['start']  ?? 0);
$length = (int)($_GET['length'] ?? 10);
$search = trim($_GET['search']['value'] ?? '');

$where = ' WHERE 1=1 ';
$params = [];
$types  = '';

if ($search !== '') {
  $where .= ' AND (nome LIKE CONCAT("%",?,"%") OR descricao LIKE CONCAT("%",?,"%")) ';
  $params[] = $search; $params[] = $search; $types .= 'ss';
}

$total = (int)$mysqli->query("SELECT COUNT(*) c FROM acl_levels")->fetch_assoc()['c'];

$stmtCount = $mysqli->prepare("SELECT COUNT(*) c FROM acl_levels $where");
if($types) $stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$cRes = $stmtCount->get_result()->fetch_assoc();
$filtrado = (int)$cRes['c'];

$orderCol = $_GET['order'][0]['column'] ?? 0;
$orderDir = strtoupper($_GET['order'][0]['dir'] ?? 'DESC');
$cols = ['id','nome','acesso_backend','acesso_frontend','ativo','updated_at'];
$col = $cols[$orderCol] ?? 'id';
$orderDir = ($orderDir==='ASC'?'ASC':'DESC');

$sql = "SELECT id, nome, acesso_backend, acesso_frontend, ativo, DATE_FORMAT(updated_at,'%d/%m/%Y %H:%i') updated_at
        FROM acl_levels $where ORDER BY $col $orderDir LIMIT ?,?";
$params2 = $params; $types2 = $types . 'ii';
$params2[] = $start; $params2[] = $length;

$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$rs = $stmt->get_result();

$data = [];
while($row = $rs->fetch_assoc()){
  $id = (int)$row['id'];
  $btns = [];
  $btns[] = '<a class="btn btn-xs btn-default" href="niveis_form.php?id='.$id.'">Editar</a>';
  $btns[] = '<a class="btn btn-xs btn-warning btn-toggle" href="#" data-id="'.$id.'">'.($row['ativo']?'Inativar':'Ativar').'</a>';
  $btns[] = '<a class="btn btn-xs btn-danger btn-excluir" href="#" data-id="'.$id.'">Excluir</a>';

  $row['acoes'] = implode(' ', $btns);
  $data[] = $row;
}

echo json_encode([
  'draw'=>$draw,
  'recordsTotal'=>$total,
  'recordsFiltered'=>$filtrado,
  'data'=>$data
]);
?>