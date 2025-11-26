<?php
// config/custom_loader.php
// Requer conexão do SEU sistema (não o GLPI): ROOT_PATH.'/config/connect.php'
if (!isset($conn)) {
  require_once __DIR__ . '/connect.php';
}

function custom_get_all(mysqli $conn): array {
  $sql = "SELECT * FROM custom_includes WHERE id = 1 LIMIT 1";
  $res = $conn->query($sql);
  if ($res && $row = $res->fetch_assoc()) return $row;
  return []; // sem registro (mas você fez o seed)
}

// Defaults seguros
function custom_defaults(): array {
  return [
    'meta_keywords'   => null,
    'meta_viewport'   => 'width=device-width, initial-scale=1.0',
    'head_css'        => '',
    'head_js'         => '',
    'before_head_end' => '',
    'after_body_open' => '',
    'before_body_end' => '',
    'custom_css'      => '',
    'custom_js'       => '',
  ];
}

$CUSTOM = custom_defaults();
$DBVALS  = custom_get_all($conn);
if ($DBVALS) {
  foreach ($CUSTOM as $k => $v) {
    if (array_key_exists($k, $DBVALS) && $DBVALS[$k] !== null) {
      $CUSTOM[$k] = $DBVALS[$k];
    }
  }
}
?>