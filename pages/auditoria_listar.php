<?php
// pages/auditoria_listar.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php'; // $conn (mysqli)
if (!($conn instanceof mysqli)) { die('Sem conexão mysqli em $conn.'); }

// ================= Helpers =================
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function table_exists(mysqli $db, string $t): bool {
  $rt = $db->real_escape_string($t);
  $r  = $db->query("SHOW TABLES LIKE '{$rt}'");
  return ($r && $r->num_rows > 0);
}

function get_columns(mysqli $db, string $t): array {
  $rt = $db->real_escape_string($t);
  $cols = [];
  if ($r = $db->query("SHOW COLUMNS FROM `{$rt}`")) {
    while ($row = $r->fetch_assoc()) $cols[] = $row['Field'];
  }
  return $cols;
}

function pick_first_existing(array $needles, array $haystack) {
  foreach ($needles as $n) if (in_array($n, $haystack, true)) return $n;
  return null;
}

function json_out($arr, int $code=200){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

// ================= Descobrir tabela =================
$candidates = [
  'auditoria',
  'moz_auditoria',
  'system_auditoria',
  'audit_log',
  'logs_auditoria',
  'user_audit',
];

$auditTable = null;
foreach ($candidates as $t) {
  if (table_exists($conn, $t)) { $auditTable = $t; break; }
}
if (!$auditTable) {
  // se quiser: mude isso para apontar direto na sua tabela
  $auditTable = 'auditoria';
}

if (!table_exists($conn, $auditTable)) {
  // página amigável se a tabela não existir
  include_once ROOT_PATH . '/system/includes/head.php';
  include_once ROOT_PATH . '/system/includes/navbar.php';
  ?>
  <div id="page-wrapper"><div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= h(APP_NAME) ?></h1></div></div>
    <div class="alert alert-danger">
      Tabela de auditoria não encontrada. Procurei por: <?= h(implode(', ', $candidates)) ?>.
      <br>Se a sua tabela tem outro nome, ajuste <code>$candidates</code> no topo do arquivo.
    </div>
  </div></div>
  <?php
  include_once ROOT_PATH . '/system/includes/code_footer.php';
  include_once ROOT_PATH . '/system/includes/footer.php';
  exit;
}

$cols = get_columns($conn, $auditTable);

// ================= Mapear colunas mais comuns =================
$col_id        = pick_first_existing(['id','seq','codigo','audit_id','log_id'], $cols) ?? 'id';
$col_created   = pick_first_existing(['created_at','dt','data','datahora','created','createdOn','created_on','timestamp'], $cols);
$col_user_id   = pick_first_existing(['user_id','usuario_id','id_usuario','colleague_id','uid'], $cols);
$col_user_name = pick_first_existing(['user_name','usuario','nome_usuario','username','login','email'], $cols);
$col_action    = pick_first_existing(['action','acao','evento','event','tipo','operation'], $cols);
$col_module    = pick_first_existing(['module','modulo','area','context','origem'], $cols);
$col_entity    = pick_first_existing(['entity','entidade','tabela','resource','objeto'], $cols);
$col_entity_id = pick_first_existing(['entity_id','entidade_id','resource_id','objeto_id','registro_id'], $cols);
$col_ip        = pick_first_existing(['ip','ip_address','remote_ip'], $cols);
$col_agent     = pick_first_existing(['user_agent','agent','browser'], $cols);
$col_details   = pick_first_existing(['details','detalhes','descricao','message','msg','payload','json','data_json'], $cols);

// fallback se não tiver data (aí ordena por id)
$orderCol = $col_created ?: $col_id;

// ================= Endpoint AJAX para DataTables =================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
  $draw   = (int)($_GET['draw'] ?? 1);
  $start  = max(0, (int)($_GET['start'] ?? 0));
  $len    = (int)($_GET['length'] ?? 25);
  if ($len <= 0 || $len > 200) $len = 25;

  $search = trim((string)($_GET['search']['value'] ?? ''));

  // filtros custom
  $f_user   = trim((string)($_GET['f_user'] ?? ''));
  $f_action = trim((string)($_GET['f_action'] ?? ''));
  $f_from   = trim((string)($_GET['f_from'] ?? ''));
  $f_to     = trim((string)($_GET['f_to'] ?? ''));

  // colunas do select (apenas as que existem)
  $selectMap = [
    'id'        => $col_id,
    'created'   => $col_created,
    'user_id'   => $col_user_id,
    'user_name' => $col_user_name,
    'action'    => $col_action,
    'module'    => $col_module,
    'entity'    => $col_entity,
    'entity_id' => $col_entity_id,
    'ip'        => $col_ip,
    'agent'     => $col_agent,
    'details'   => $col_details,
  ];

  $selectCols = [];
  foreach ($selectMap as $alias => $c) {
    if ($c && in_array($c, $cols, true)) $selectCols[] = "`$c` AS `$alias`";
  }
  if (!$selectCols) $selectCols[] = "`$col_id` AS `id`";

  $rt = $conn->real_escape_string($auditTable);

  // base where
  $w = [];
  $p = [];
  $t = '';

  // filtros por data (se existir coluna data)
  if ($col_created && $f_from !== '') {
    $w[] = "`$col_created` >= ?";
    $p[] = $f_from . " 00:00:00";
    $t  .= "s";
  }
  if ($col_created && $f_to !== '') {
    $w[] = "`$col_created` <= ?";
    $p[] = $f_to . " 23:59:59";
    $t  .= "s";
  }

  // filtro por usuário (id ou nome/login/email)
  if ($f_user !== '') {
    $sub = [];
    if ($col_user_id) { $sub[] = "`$col_user_id` = ?"; $p[] = $f_user; $t .= "s"; }
    if ($col_user_name) { $sub[] = "`$col_user_name` LIKE ?"; $p[] = "%$f_user%"; $t .= "s"; }
    if ($sub) $w[] = '(' . implode(' OR ', $sub) . ')';
  }

  // filtro por ação
  if ($f_action !== '') {
    if ($col_action) { $w[] = "`$col_action` LIKE ?"; $p[] = "%$f_action%"; $t .= "s"; }
  }

  // busca global (search)
  if ($search !== '') {
    $likeCols = [];
    $cands = [$col_user_name,$col_action,$col_module,$col_entity,$col_ip,$col_details];
    foreach ($cands as $c) {
      if ($c && in_array($c, $cols, true)) $likeCols[] = "`$c` LIKE ?";
    }
    if ($likeCols) {
      $w[] = '(' . implode(' OR ', $likeCols) . ')';
      foreach ($likeCols as $_) { $p[] = "%$search%"; $t .= "s"; }
    }
  }

  $whereSql = $w ? ("WHERE " . implode(" AND ", $w)) : "";

  // total e filtrado
  $total = 0;
  if ($r = $conn->query("SELECT COUNT(*) AS c FROM `{$rt}`")) {
    $total = (int)($r->fetch_assoc()['c'] ?? 0);
  }

  $filtered = $total;
  if ($whereSql) {
    $sqlC = "SELECT COUNT(*) AS c FROM `{$rt}` {$whereSql}";
    $stC = $conn->prepare($sqlC);
    if ($t !== '') $stC->bind_param($t, ...$p);
    $stC->execute();
    $rc = $stC->get_result()->fetch_assoc();
    $filtered = (int)($rc['c'] ?? 0);
    $stC->close();
  }

  // order
  $dir = (string)($_GET['order'][0]['dir'] ?? 'desc');
  $dir = strtolower($dir)==='asc' ? 'ASC' : 'DESC';

  // query principal
  $sql = "SELECT " . implode(", ", $selectCols) . "
          FROM `{$rt}`
          {$whereSql}
          ORDER BY `{$orderCol}` {$dir}
          LIMIT {$start}, {$len}";

  $stmt = $conn->prepare($sql);
  if ($t !== '') $stmt->bind_param($t, ...$p);
  $stmt->execute();
  $res = $stmt->get_result();

  $data = [];
  while ($row = $res->fetch_assoc()) {
    // normalizações
    $row['created'] = $row['created'] ?? '';
    $row['details'] = $row['details'] ?? '';
    $data[] = $row;
  }
  $stmt->close();

  json_out([
    'draw' => $draw,
    'recordsTotal' => $total,
    'recordsFiltered' => $filtered,
    'data' => $data,
    'meta' => [
      'table' => $auditTable,
      'order_col' => $orderCol,
    ]
  ]);
}

// ================= UI =================
include_once ROOT_PATH . '/system/includes/head.php';
include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row">
      <div class="col-lg-12">
        <h1 class="page-header"><?= h(APP_NAME) ?> — Auditoria</h1>

        <div class="panel panel-default">
          <div class="panel-heading"><strong>Filtros</strong></div>
          <div class="panel-body">
            <div class="row">
              <div class="col-md-3">
                <label>Usuário (id / login / email)</label>
                <input type="text" id="f_user" class="form-control" placeholder="ex.: 12 ou marcelo">
              </div>
              <div class="col-md-3">
                <label>Ação</label>
                <input type="text" id="f_action" class="form-control" placeholder="ex.: CREATE / UPDATE / LOGIN">
              </div>
              <div class="col-md-3">
                <label>De</label>
                <input type="date" id="f_from" class="form-control">
              </div>
              <div class="col-md-3">
                <label>Até</label>
                <input type="date" id="f_to" class="form-control">
              </div>
            </div>

            <div class="row" style="margin-top:10px;">
              <div class="col-md-12">
                <button class="btn btn-primary" id="btnFiltrar"><i class="fa fa-filter"></i> Filtrar</button>
                <button class="btn btn-default" id="btnLimpar"><i class="fa fa-eraser"></i> Limpar</button>
                <span class="help-block" style="margin:8px 0 0 0;">
                  Tabela detectada: <code><?= h($auditTable) ?></code>
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="panel panel-default">
          <div class="panel-heading"><strong>Eventos</strong></div>
          <div class="panel-body">
            <div class="table-responsive">
              <table id="tblAuditoria" class="table table-striped table-bordered table-hover" style="width:100%">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Data/Hora</th>
                    <th>Usuário</th>
                    <th>Ação</th>
                    <th>Módulo</th>
                    <th>Entidade</th>
                    <th>Registro</th>
                    <th>IP</th>
                    <th>Detalhes</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>

            <small class="text-muted">
              Dica: use a busca do DataTables para filtrar por palavra em detalhes, entidade, IP, etc.
            </small>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
(function(){
  function qs(id){ return document.getElementById(id); }

  // DataTables (assumindo que você já usa nos Ativos; se não, inclua os assets padrão)
  var table = $('#tblAuditoria').DataTable({
    processing: true,
    serverSide: true,
    pageLength: 25,
    lengthMenu: [[10,25,50,100],[10,25,50,100]],
    order: [[1,'desc']],
    ajax: {
      url: '<?= h(basename(__FILE__)) ?>?ajax=1',
      data: function(d){
        d.f_user   = qs('f_user').value.trim();
        d.f_action = qs('f_action').value.trim();
        d.f_from   = qs('f_from').value;
        d.f_to     = qs('f_to').value;
      }
    },
    columns: [
      { data: 'id', defaultContent: '' },
      { data: 'created', defaultContent: '' },
      { data: 'user_name', defaultContent: '',
        render: function(data, type, row){
          var s = data || '';
          if (row.user_id) s += (s ? ' ' : '') + '<span class="text-muted">(#'+row.user_id+')</span>';
          return s || '<span class="text-muted">—</span>';
        }
      },
      { data: 'action', defaultContent: '' },
      { data: 'module', defaultContent: '' },
      { data: 'entity', defaultContent: '' },
      { data: 'entity_id', defaultContent: '' },
      { data: 'ip', defaultContent: '' },
      { data: 'details', defaultContent: '',
        render: function(data){
          if (!data) return '<span class="text-muted">—</span>';
          // corta pra não estourar a tabela
          var s = String(data);
          var short = s.length > 160 ? s.substring(0,160) + '…' : s;
          return '<span title="'+ $('<div>').text(s).html() +'">'+ $('<div>').text(short).html() +'</span>';
        }
      },
    ],
    language: {
      url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
    }
  });

  qs('btnFiltrar').addEventListener('click', function(){
    table.ajax.reload();
  });

  qs('btnLimpar').addEventListener('click', function(){
    qs('f_user').value = '';
    qs('f_action').value = '';
    qs('f_from').value = '';
    qs('f_to').value = '';
    table.ajax.reload();
  });
})();
</script>

<?php include_once ROOT_PATH . '/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>
