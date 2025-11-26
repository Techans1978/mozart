<?php
// system/services/feed_service.php (robusto)
// Unifica Artigos, Dicas, Mídias, Documentos, Eventos para o feed,
// respeitando publish window, destaque e permissões por grupos/perfis/roles.
// Compatível com MySQL < 5.7 (sem JSON_OBJECT) e PHP antigos.
// NÃO usa type-hints nem constant() dinâmica.

if (session_status() === PHP_SESSION_NONE) session_start();

/* ===== CONFIG – ajuste se seus nomes diferirem ===== */
$FEED = [
  'tables' => [
    'art' => [
      'main'   => 'content_articles',
      'g'      => 'content_article_groups',
      'pf'     => 'content_article_profiles',
      'r'      => 'content_article_roles',
      'key'    => 'article_id',
      'cat'    => 'categoria_id',
      'intro'  => 'introtext',
      'full'   => 'fulltext',
      'title'  => 'titulo',
      'status' => 'status',
      'pubup'  => 'publish_up',
      'pubdn'  => 'publish_down',
      'feat'   => 'featured',
      'created'=> 'created_at',
      'desc'   => null, // não usa
    ],
    'dic' => [
      'main'   => 'dicas_articles',
      'g'      => 'dicas_article_groups',
      'pf'     => 'dicas_article_profiles',
      'r'      => 'dicas_article_roles',
      'key'    => 'article_id',
      'cat'    => 'categoria_id',
      'intro'  => 'introtext',
      'full'   => 'fulltext',
      'title'  => 'titulo',
      'status' => 'status',
      'pubup'  => 'publish_up',
      'pubdn'  => 'publish_down',
      'feat'   => 'featured',
      'created'=> 'created_at',
      'desc'   => null,
    ],
    'mid' => [
      'main'   => 'midia_items',
      'g'      => 'midia_item_groups',
      'pf'     => 'midia_item_profiles',
      'r'      => 'midia_item_roles',
      'key'    => 'midia_id', // troque para item_id se for o seu caso
      'cat'    => 'categoria_id',
      'intro'  => null,
      'full'   => null,
      'title'  => 'titulo',
      'status' => 'status',
      'pubup'  => 'publish_up',
      'pubdn'  => 'publish_down',
      'feat'   => 'featured',
      'created'=> 'created_at',
      'desc'   => 'descricao_curta',
      'attach' => [
        'table' => 'midia_attachments',
        'fk'    => 'midia_id',
        'tipo'  => 'tipo'
      ],
    ],
    'doc' => [
      'main'   => 'docs_items',
      'g'      => 'docs_item_groups',
      'pf'     => 'docs_item_profiles',
      'r'      => 'docs_item_roles',
      'key'    => 'doc_id', // troque para item_id se for o seu caso
      'cat'    => 'categoria_id',
      'intro'  => null,
      'full'   => null,
      'title'  => 'titulo',
      'status' => 'status',
      'pubup'  => 'publish_up',
      'pubdn'  => 'publish_down',
      'feat'   => 'featured',
      'created'=> 'created_at',
      'desc'   => 'descricao_curta',
      'files'  => [
        'table' => 'docs_files',
        'fk'    => 'doc_id'
      ],
      'extra'  => [ 'exibir' => 'exibir_no_feed', 'pasta' => 'is_folder' ],
    ],
    'evt' => [
      'main'   => 'event_items',
      'g'      => 'event_item_groups',
      'pf'     => 'event_item_profiles',
      'r'      => 'event_item_roles',
      'key'    => 'item_id',
      'cat'    => 'calendario_id',
      'intro'  => null,
      'full'   => null,
      'title'  => 'titulo',
      'status' => 'status',
      'pubup'  => 'publish_up',
      'pubdn'  => 'publish_down',
      'feat'   => 'featured',
      'created'=> 'created_at',
      'desc'   => 'descricao_curta',
      'event'  => [ 'ini'=>'start_at', 'fim'=>'end_at', 'dia'=>'dia_todo' ],
    ],
  ]
];

/* ===== Utils ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function feed_user_ctx($conn, $uid){
  if (!$conn) return ['groups'=>[], 'perfil'=>0, 'role'=>''];

  $groups = [];

  // ---- tabela e colunas possíveis para usuários x grupos
  $ug_table = 'usuarios_grupos';
  if (!feed_table_exists($conn, $ug_table)) {
    // tente alternativas, se tiver
    foreach (['usuario_grupos','user_groups'] as $alt) {
      if (feed_table_exists($conn, $alt)) { $ug_table = $alt; break; }
    }
  }

  if (feed_table_exists($conn, $ug_table)) {
    // detecta nomes de colunas (PT/EN)
    $col_gid = feed_col_exists($conn, $ug_table, 'group_id')   ? 'group_id'
             : (feed_col_exists($conn, $ug_table, 'grupo_id')  ? 'grupo_id'  : null);
    $col_uid = feed_col_exists($conn, $ug_table, 'user_id')    ? 'user_id'
             : (feed_col_exists($conn, $ug_table, 'usuario_id')? 'usuario_id'
             : (feed_col_exists($conn, $ug_table, 'id_usuario')? 'id_usuario' : null));

    if ($col_gid && $col_uid) {
      $sql = "SELECT `$col_gid` AS gid FROM `$ug_table` WHERE `$col_uid`=?";
      if ($st = $conn->prepare($sql)) {
        $st->bind_param('i', $uid);
        $st->execute();
        $r = $st->get_result();
        while ($x = $r->fetch_assoc()) $groups[] = (int)$x['gid'];
        $st->close();
      }
    }
  }

  // ---- perfil / role (tolerante a nomes)
  $perfil = 0; $role = '';
  if (feed_table_exists($conn, 'usuarios')) {
    // detecta coluna de perfil
    $perfil_col = feed_col_exists($conn, 'usuarios', 'perfil_id') ? 'perfil_id'
               : (feed_col_exists($conn, 'usuarios', 'perfil')     ? 'perfil' : null);
    // detecta coluna de role/nivel
    $role_col   = feed_col_exists($conn, 'usuarios', 'nivel_acesso') ? 'nivel_acesso'
               : (feed_col_exists($conn, 'usuarios', 'nivel')         ? 'nivel' : null);

    $sel = "id";
    $sel .= $perfil_col ? ", `$perfil_col` AS perfil" : ", 0 AS perfil";
    $sel .= $role_col   ? ", `$role_col` AS role"     : ", '' AS role";

    $sql = "SELECT $sel FROM `usuarios` WHERE id=? LIMIT 1";
    if ($st = $conn->prepare($sql)) {
      $st->bind_param('i', $uid);
      $st->execute();
      $r = $st->get_result();
      if ($u = $r->fetch_assoc()) {
        $perfil = (int)($u['perfil'] ?? 0);
        $role   = (string)($u['role'] ?? '');
      }
      $st->close();
    }
  }

  return ['groups'=>$groups, 'perfil'=>$perfil, 'role'=>$role];
}

function feed_table_exists($conn, $name){
  $name = $conn->real_escape_string($name);
  $sql  = "SHOW TABLES LIKE '$name'";
  if ($rs=$conn->query($sql)) { return (bool)$rs->num_rows; }
  return false;
}

// verifica se a coluna existe na tabela
function feed_col_exists($conn, $table, $column){
  $t = $conn->real_escape_string($table);
  $c = $conn->real_escape_string($column);
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c' LIMIT 1";
  if ($rs = $conn->query($sql)) return (bool)$rs->num_rows;
  return false;
}

/**
 * Busca itens do feed
 * $opts = ['limit'=>20,'offset'=>0,'tipo'=>[],'categoria_id'=>null,'apenas_destaque'=>false]
 */
function feed_fetch($conn, $uid, $opts=[]){
  global $FEED;

  $ctx    = feed_user_ctx($conn, (int)$uid);
  $groups = $ctx['groups'];
  $perfil = (int)$ctx['perfil'];
  $role   = $conn->real_escape_string($ctx['role']);

  $limit  = max(1, (int)($opts['limit']  ?? 20));
  $offset = max(0, (int)($opts['offset'] ?? 0));
  $tipos  = $opts['tipo'] ?? []; // ['artigo','dica','midia','documento','evento'] => 'art','dic','mid','doc','evt'
  $catId  = isset($opts['categoria_id']) ? (int)$opts['categoria_id'] : null;
  $onlyF  = !empty($opts['apenas_destaque']);

  // mapa tipos amigáveis -> chaves internas
  $alias = ['artigo'=>'art','dica'=>'dic','midia'=>'mid','documento'=>'doc','evento'=>'evt'];
  if ($tipos) {
    $tipos = array_values(array_intersect(array_map(function($t)use($alias){return $alias[$t]??$t;}, $tipos), array_keys($FEED['tables'])));
    if (!$tipos) return ['items'=>[], 'next_offset'=>null, 'total_est'=>0];
  } else {
    $tipos = array_keys($FEED['tables']);
  }

  $groups_sql = $groups ? implode(',', array_map('intval',$groups)) : 'NULL';
  $now = date('Y-m-d H:i:s');

  $col = function($t,$k){ return $t[$k] ?? null; };

  $items = [];

  foreach ($tipos as $tkey) {
    $t = $FEED['tables'][$tkey];

    // se tabela principal não existe, pula silenciosamente
    if (!feed_table_exists($conn, $t['main'])) continue;

    $title  = $col($t,'title');
    $status = $col($t,'status');
    $pubup  = $col($t,'pubup');
    $pubdn  = $col($t,'pubdn');
    $feat   = $col($t,'feat');
    $created= $col($t,'created');
    $catCol = $col($t,'cat');
    $desc   = $col($t,'desc');
    $intro  = $col($t,'intro');
    $full   = $col($t,'full');

    $where = "t.`$status`='publicado' AND (t.`$pubup` IS NULL OR t.`$pubup`<='$now') AND (t.`$pubdn` IS NULL OR t.`$pubdn`>='$now')";
    if ($onlyF) $where .= " AND t.`$feat`=1";
    if ($catId && $catCol) $where .= " AND t.`$catCol`=".$catId;

    // permissões (se pivôs existirem)
    $perms = [];
    $hasG  = feed_table_exists($conn, $t['g']);
    $hasPF = feed_table_exists($conn, $t['pf']);
    $hasR  = feed_table_exists($conn, $t['r']);
    $key   = $t['key'];

    $perms[] = "t.acesso_publico=1";
    if ($hasG && $groups_sql!=='NULL')
      $perms[] = "EXISTS(SELECT 1 FROM `{$t['g']}` g WHERE g.`$key`=t.id AND g.group_id IN ($groups_sql))";
    if ($hasPF)
      $perms[] = "EXISTS(SELECT 1 FROM `{$t['pf']}` p WHERE p.`$key`=t.id AND p.perfil_id = $perfil)";
    if ($hasR)
      $perms[] = "EXISTS(SELECT 1 FROM `{$t['r']}` r WHERE r.`$key`=t.id AND r.role = '$role')";

    if ($perms) $where .= " AND (".implode(' OR ',$perms).")";

    // campos de resumo
    $resumoExpr = "''";
    if ($desc) {
      $resumoExpr = "COALESCE(NULLIF(t.`$desc`,''),'')";
    } elseif ($intro && $full) {
      $resumoExpr = "COALESCE(NULLIF(t.`$intro`,''), SUBSTRING(t.`$full`,1,320))";
    }

    // dt_ref
    $dtRefExpr = "COALESCE(t.`$pubup`, t.`$created`)";

    // SELECT base
    $sql = "SELECT t.id, t.`$title` AS titulo, $resumoExpr AS resumo, $dtRefExpr AS dt_ref, t.`$feat` AS featured
            FROM `{$t['main']}` t
            WHERE $where
            ORDER BY (t.`$feat`=1) DESC, dt_ref DESC
            LIMIT $limit OFFSET $offset";

    if (!$rs = $conn->query($sql)) continue;

    while($r=$rs->fetch_assoc()){
      $item = [
        'tipo'   => ['art'=>'artigo','dic'=>'dica','mid'=>'midia','doc'=>'documento','evt'=>'evento'][$tkey],
        'id'     => (int)$r['id'],
        'titulo' => $r['titulo'],
        'resumo' => $r['resumo'],
        'dt_ref' => $r['dt_ref'],
        'featured' => (int)$r['featured'],
        'extra'  => null
      ];

      // extras por tipo
      if ($tkey==='mid' && !empty($t['attach'])) {
        $ta = $t['attach']; // table,fk,tipo
        if (feed_table_exists($conn, $ta['table'])) {
          $qid = (int)$item['id'];
          $q1  = "SELECT COUNT(*) c FROM `{$ta['table']}` WHERE `{$ta['fk']}`=$qid";
          $qt  = 0; if ($r1=$conn->query($q1)){ $qt = (int)($r1->fetch_assoc()['c'] ?? 0); }
          $q2  = "SELECT GROUP_CONCAT(DISTINCT `{$ta['tipo']}` ORDER BY `{$ta['tipo']}` SEPARATOR ',') AS tipos FROM `{$ta['table']}` WHERE `{$ta['fk']}`=$qid";
          $tipos = []; if ($r2=$conn->query($q2)){ $csv = (string)($r2->fetch_assoc()['tipos'] ?? ''); if ($csv!=='') $tipos = array_unique(array_filter(array_map('trim', explode(',',$csv)))); }
          $item['extra'] = ['qtde'=>$qt, 'tipos'=>$tipos];
        }
      }

      if ($tkey==='doc' && !empty($t['files'])) {
        $tf = $t['files']; // table,fk
        $qid = (int)$item['id'];
        $qt  = 0; if (feed_table_exists($conn, $tf['table'])) {
          $qf = "SELECT COUNT(*) c FROM `{$tf['table']}` WHERE `{$tf['fk']}`=$qid";
          if ($rf=$conn->query($qf)) $qt = (int)($rf->fetch_assoc()['c'] ?? 0);
        }
        $exibir = null; $pasta = null;
        if (!empty($t['extra']['exibir'])) {
          $colEx = $t['extra']['exibir'];
          $qe = "SELECT `$colEx` exibir FROM `{$t['main']}` WHERE id=$qid";
          if ($re=$conn->query($qe)) $exibir = (int)($re->fetch_assoc()['exibir'] ?? 0);
        }
        if (!empty($t['extra']['pasta'])) {
          $colPa = $t['extra']['pasta'];
          $qp = "SELECT `$colPa` pasta FROM `{$t['main']}` WHERE id=$qid";
          if ($rp=$conn->query($qp)) $pasta = (int)($rp->fetch_assoc()['pasta'] ?? 0);
        }
        $item['extra'] = ['arquivos'=>$qt, 'exibir_no_feed'=>$exibir, 'pasta'=>$pasta];
      }

      if ($tkey==='evt' && !empty($t['event'])) {
        $e = $t['event']; // ini,fim,dia
        $qid = (int)$item['id'];
        $q = "SELECT `{$e['ini']}` ini, `{$e['fim']}` fim, `{$e['dia']}` dia FROM `{$t['main']}` WHERE id=$qid";
        if ($re=$conn->query($q)) {
          $row = $re->fetch_assoc();
          $item['extra'] = [
            'inicio' => $row['ini'],
            'fim'    => $row['fim'],
            'dia_todo' => (int)$row['dia']
          ];
        }
      }

      $items[] = $item;
    }
  }

  // ordena (destaque primeiro, depois dt_ref desc) – caso tenha vindo de múltiplas tabelas
  usort($items, function($a,$b){
    if ((int)$a['featured'] !== (int)$b['featured']) return (int)$b['featured'] - (int)$a['featured'];
    return strcmp($b['dt_ref'], $a['dt_ref']);
  });

  // paginação simples
  $slice = array_slice($items, 0, $limit);
  $next  = count($items) > $limit ? $offset + $limit : null;
  $total_est = $offset + count($slice) + ($next ? 1 : 0);

  return ['items'=>$slice,'next_offset'=>$next,'total_est'=>$total_est];
}
