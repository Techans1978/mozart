<?php
// system/services/feed_service_content.php
// Feed de ARTIGOS + MÍDIA (content_articles + midia_items)
// Compatível com MySQL/PH P antigos, tolerante a nomes PT/EN e ausência de pivôs.

if (session_status() === PHP_SESSION_NONE) session_start();

/* ===== Mapas de tabelas/colunas (ajuste se necessário) ===== */
$CF = [
  // ARTIGOS
  'art' => [
    'main'    => 'content_articles',
    'g'       => 'content_article_groups',
    'pf'      => 'content_article_profiles',
    'r'       => 'content_article_roles',
    'key'     => 'article_id',
    'cat'     => 'categoria_id',
    'intro'   => 'introtext',
    'full'    => 'fulltext',
    'title'   => 'titulo',
    'status'  => 'status',
    'pubup'   => 'publish_up',
    'pubdn'   => 'publish_down',
    'feat'    => 'featured',
    'created' => 'created_at',
  ],
  // MÍDIA
  'mid' => [
    'main'    => 'midia_items',
    'g'       => 'midia_item_groups',
    'pf'      => 'midia_item_profiles',
    'r'       => 'midia_item_roles',
    'key'     => 'midia_id',        // será auto-detectado para 'item_id' se necessário
    'cat'     => 'categoria_id',
    'desc'    => 'descricao_curta', // resumo
    'title'   => 'titulo',
    'status'  => 'status',
    'pubup'   => 'publish_up',
    'pubdn'   => 'publish_down',
    'feat'    => 'featured',
    'created' => 'created_at',
    'attach'  => [
      'table' => 'midia_attachments',
      'fk'    => 'midia_id',        // será usado para contar anexos e tipos
      'tipo'  => 'tipo'
    ],
  ],
];

/* ===== Utils ===== */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

function cf_table_exists($conn, $name){
  $name = $conn->real_escape_string($name);
  $sql = "SHOW TABLES LIKE '$name'";
  if ($rs=$conn->query($sql)) return (bool)$rs->num_rows;
  return false;
}
function cf_col_exists($conn,$table,$column){
  $t=$conn->real_escape_string($table);
  $c=$conn->real_escape_string($column);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  if ($rs=$conn->query($sql)) return (bool)$rs->num_rows;
  return false;
}
function cf_detect_col($conn,$table,$candidates){
  foreach ($candidates as $c) if (cf_col_exists($conn,$table,$c)) return $c;
  return null;
}

/** contexto do usuário (grupos/perfil/role) com detecção PT/EN */
function cf_user_ctx($conn, $uid){
  if (!$conn) return ['groups'=>[], 'perfil'=>0, 'role'=>''];

  // grupos
  $groups = [];
  $ug = 'usuarios_grupos';
  if (!cf_table_exists($conn,$ug)) {
    foreach (['usuario_grupos','user_groups'] as $alt) {
      if (cf_table_exists($conn,$alt)) { $ug=$alt; break; }
    }
  }
  if (cf_table_exists($conn,$ug)) {
    $col_gid = cf_detect_col($conn,$ug, ['group_id','grupo_id']);
    $col_uid = cf_detect_col($conn,$ug, ['user_id','usuario_id','id_usuario']);
    if ($col_gid && $col_uid) {
      $sql = "SELECT `$col_gid` AS gid FROM `$ug` WHERE `$col_uid`=?";
      if ($st=$conn->prepare($sql)) {
        $st->bind_param('i',$uid); $st->execute();
        $r=$st->get_result(); while($x=$r->fetch_assoc()) $groups[]=(int)$x['gid'];
        $st->close();
      }
    }
  }

  // perfil/role
  $perfil=0; $role='';
  if (cf_table_exists($conn,'usuarios')) {
    $perfil_col = cf_detect_col($conn,'usuarios',['perfil_id','perfil']);
    $role_col   = cf_detect_col($conn,'usuarios',['nivel_acesso','nivel']);
    $sel = "id";
    $sel .= $perfil_col ? ", `$perfil_col` AS perfil" : ", 0 AS perfil";
    $sel .= $role_col   ? ", `$role_col` AS role"     : ", '' AS role";
    if ($st=$conn->prepare("SELECT $sel FROM usuarios WHERE id=? LIMIT 1")){
      $st->bind_param('i',$uid); $st->execute(); $r=$st->get_result();
      if ($u=$r->fetch_assoc()) { $perfil=(int)($u['perfil']??0); $role=(string)($u['role']??''); }
      $st->close();
    }
  }

  return ['groups'=>$groups,'perfil'=>$perfil,'role'=>$role];
}

/**
 * Busca ARTIGOS + MÍDIA
 * $opts = ['limit'=>20,'offset'=>0,'categoria_id'=>null,'apenas_destaque'=>false]
 */
function feed_content_fetch($conn, $uid, $opts=[]){
  global $CF;
  if (!$conn) return ['items'=>[], 'next_offset'=>null, 'total_est'=>0];

  $hasArt = cf_table_exists($conn,$CF['art']['main']);
  $hasMid = cf_table_exists($conn,$CF['mid']['main']);
  if (!$hasArt && !$hasMid) return ['items'=>[], 'next_offset'=>null, 'total_est'=>0];

  $ctx    = cf_user_ctx($conn,(int)$uid);
  $groups = $ctx['groups'];
  $perfil = (int)$ctx['perfil'];
  $role   = $conn->real_escape_string($ctx['role']);

  $limit  = max(1,(int)($opts['limit']??20));
  $offset = max(0,(int)($opts['offset']??0));
  $catId  = isset($opts['categoria_id']) ? (int)$opts['categoria_id'] : null;
  $onlyF  = !empty($opts['apenas_destaque']);
  $now    = date('Y-m-d H:i:s');

  $items = [];
  $groups_sql = $groups ? implode(',', array_map('intval',$groups)) : 'NULL';

  /* ==== ARTIGOS ==== */
  if ($hasArt){
    $t = $CF['art'];
    $where = "t.`{$t['status']}`='publicado'
              AND (t.`{$t['pubup']}` IS NULL OR t.`{$t['pubup']}`<='$now')
              AND (t.`{$t['pubdn']}` IS NULL OR t.`{$t['pubdn']}`>='$now')";
    if ($onlyF) $where .= " AND t.`{$t['feat']}`=1";
    if ($catId && !empty($t['cat'])) $where .= " AND t.`{$t['cat']}`=$catId";

    // permissões
    $perm = ["t.acesso_publico=1"];
    if (cf_table_exists($conn,$t['g']) && $groups_sql!=='NULL')
      $perm[] = "EXISTS(SELECT 1 FROM `{$t['g']}` g WHERE g.`{$t['key']}`=t.id AND g.group_id IN ($groups_sql))";
    if (cf_table_exists($conn,$t['pf']))
      $perm[] = "EXISTS(SELECT 1 FROM `{$t['pf']}` p WHERE p.`{$t['key']}`=t.id AND p.perfil_id=$perfil)";
    if (cf_table_exists($conn,$t['r']))
      $perm[] = "EXISTS(SELECT 1 FROM `{$t['r']}` r WHERE r.`{$t['key']}`=t.id AND r.role='$role')";
    $where .= " AND (".implode(' OR ',$perm).")";

    $resumo = "COALESCE(NULLIF(t.`{$t['intro']}`,''), SUBSTRING(t.`{$t['full']}`,1,320))";
    $dtref  = "COALESCE(t.`{$t['pubup']}`, t.`{$t['created']}`)";

    $sql = "SELECT t.id, t.`{$t['title']}` AS titulo,
                   $resumo AS resumo,
                   $dtref AS dt_ref,
                   t.`{$t['feat']}` AS featured
            FROM `{$t['main']}` t
            WHERE $where
            ORDER BY (t.`{$t['feat']}`=1) DESC, dt_ref DESC
            LIMIT $limit OFFSET $offset";

    if ($rs=$conn->query($sql)){
      while($r=$rs->fetch_assoc()){
        $items[] = [
          'tipo'     => 'artigo',
          'id'       => (int)$r['id'],
          'titulo'   => $r['titulo'],
          'resumo'   => $r['resumo'],
          'dt_ref'   => $r['dt_ref'],
          'featured' => (int)$r['featured'],
          'extra'    => null
        ];
      }
    }
  }

  /* ==== MÍDIA ==== */
  if ($hasMid){
    $t = $CF['mid'];

    // detectar se a pivô usa 'item_id' em vez de 'midia_id'
    if (cf_table_exists($conn,$t['g'])) {
      $detKey = cf_detect_col($conn,$t['g'], ['midia_id','item_id']);
      if ($detKey) $t['key'] = $detKey;
    }
    // detectar se attachments usam 'midia_id' (fallback para 'item_id' se existir)
    if (cf_table_exists($conn,$t['attach']['table'])) {
      $detFK = cf_detect_col($conn,$t['attach']['table'], ['midia_id','item_id']);
      if ($detFK) $t['attach']['fk'] = $detFK;
    }

    $where = "t.`{$t['status']}`='publicado'
              AND (t.`{$t['pubup']}` IS NULL OR t.`{$t['pubup']}`<='$now')
              AND (t.`{$t['pubdn']}` IS NULL OR t.`{$t['pubdn']}`>='$now')";
    if ($onlyF) $where .= " AND t.`{$t['feat']}`=1";
    if ($catId && !empty($t['cat'])) $where .= " AND t.`{$t['cat']}`=$catId";

    // permissões
    $perm = ["t.acesso_publico=1"];
    if (cf_table_exists($conn,$t['g']) && $groups_sql!=='NULL')
      $perm[] = "EXISTS(SELECT 1 FROM `{$t['g']}` g WHERE g.`{$t['key']}`=t.id AND g.group_id IN ($groups_sql))";
    if (cf_table_exists($conn,$t['pf']))
      $perm[] = "EXISTS(SELECT 1 FROM `{$t['pf']}` p WHERE p.`{$t['key']}`=t.id AND p.perfil_id=$perfil)";
    if (cf_table_exists($conn,$t['r']))
      $perm[] = "EXISTS(SELECT 1 FROM `{$t['r']}` r WHERE r.`{$t['key']}`=t.id AND r.role='$role')";
    $where .= " AND (".implode(' OR ',$perm).")";

    $resumo = !empty($t['desc']) ? "COALESCE(NULLIF(t.`{$t['desc']}`,''),'')" : "''";
    $dtref  = "COALESCE(t.`{$t['pubup']}`, t.`{$t['created']}`)";

    $sql = "SELECT t.id, t.`{$t['title']}` AS titulo,
                   $resumo AS resumo,
                   $dtref  AS dt_ref,
                   t.`{$t['feat']}` AS featured
            FROM `{$t['main']}` t
            WHERE $where
            ORDER BY (t.`{$t['feat']}`=1) DESC, dt_ref DESC
            LIMIT $limit OFFSET $offset";

    if ($rs=$conn->query($sql)){
      while($r=$rs->fetch_assoc()){
        $item = [
          'tipo'     => 'midia',
          'id'       => (int)$r['id'],
          'titulo'   => $r['titulo'],
          'resumo'   => $r['resumo'],
          'dt_ref'   => $r['dt_ref'],
          'featured' => (int)$r['featured'],
          'extra'    => null
        ];

        // extras: quantidade e tipos de anexos
        if (!empty($t['attach']) && cf_table_exists($conn,$t['attach']['table'])) {
          $fk   = $t['attach']['fk'];
          $tipo = $t['attach']['tipo'];
          $qid  = (int)$item['id'];

          $qt=0;
          $q1="SELECT COUNT(*) c FROM `{$t['attach']['table']}` WHERE `$fk`=$qid";
          if ($r1=$conn->query($q1)) $qt=(int)($r1->fetch_assoc()['c']??0);

          $tipos=[];
          $q2="SELECT GROUP_CONCAT(DISTINCT `$tipo` ORDER BY `$tipo` SEPARATOR ',') tipos
               FROM `{$t['attach']['table']}` WHERE `$fk`=$qid";
          if ($r2=$conn->query($q2)){
            $csv=(string)($r2->fetch_assoc()['tipos']??'');
            if ($csv!=='') $tipos=array_unique(array_filter(array_map('trim',explode(',',$csv))));
          }

          $item['extra']=['qtde'=>$qt,'tipos'=>$tipos];
        }

        $items[]=$item;
      }
    }
  }

  // ordena unificado (destaque desc, dt_ref desc)
  usort($items, function($a,$b){
    if ((int)$a['featured'] !== (int)$b['featured']) return (int)$b['featured'] - (int)$a['featured'];
    return strcmp($b['dt_ref'], $a['dt_ref']);
  });

  // paginação simples no array unificado
  $slice = array_slice($items, 0, $limit);
  $next  = count($items) > $limit ? $offset + $limit : null;
  $total_est = $offset + count($slice) + ($next ? 1 : 0);

  return ['items'=>$slice,'next_offset'=>$next,'total_est'=>$total_est];
}
