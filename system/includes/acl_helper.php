<?php
function can($mysqli, $levelId, $modulo, $acao){
  static $cache = [];
  $k = $levelId.'|'.$modulo;
  if (!isset($cache[$k])) {
    $st = $mysqli->prepare("SELECT * FROM acl_permissions WHERE level_id=? AND modulo=?");
    $st->bind_param('is',$levelId,$modulo);
    $st->execute();
    $cache[$k] = $st->get_result()->fetch_assoc() ?: [];
  }
  $map = [
    'ver'     => 'pode_ver',
    'criar'   => 'pode_criar',
    'editar'  => 'pode_editar',
    'excluir' => 'pode_excluir',
    'aprovar' => 'pode_aprovar'
  ];
  $col = $map[$acao] ?? null;
  return $col ? (int)($cache[$k][$col] ?? 0) === 1 : false;
}
?>