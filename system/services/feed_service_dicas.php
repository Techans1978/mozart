<?php
// system/services/feed_service_dicas.php
// Feed exclusivo de DICAS (dicas_articles) com permissões e filtro por categoria

if (session_status() === PHP_SESSION_NONE) session_start();

$DIC = [
  'main'    => 'dicas_articles',
  'g'       => 'dicas_article_groups',
  'pf'      => 'dicas_article_profiles',
  'r'       => 'dicas_article_roles',
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
];

if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
function dic_table_exists($conn,$name){ $name=$conn->real_escape_string($name); $rs=$conn->query("SHOW TABLES LIKE '$name'"); return $rs? (bool)$rs->num_rows : false; }
function dic_col_exists($conn,$table,$column){
  $t=$conn->real_escape_string($table); $c=$conn->real_escape_string($column);
  $rs=$conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1");
  return $rs? (bool)$rs->num_rows : false;
}
function dic_detect_col($conn,$table,$cands){ foreach($cands as $c){ if(dic_col_exists($conn,$table,$c)) return $c; } return null; }

function dic_user_ctx($conn,$uid){
  if(!$conn) return ['groups'=>[], 'perfil'=>0, 'role'=>''];
  $groups=[];
  $ug='usuarios_grupos';
  if(!dic_table_exists($conn,$ug)) foreach(['usuario_grupos','user_groups'] as $alt){ if(dic_table_exists($conn,$alt)){$ug=$alt;break;} }
  if(dic_table_exists($conn,$ug)){
    $col_gid=dic_detect_col($conn,$ug,['group_id','grupo_id']);
    $col_uid=dic_detect_col($conn,$ug,['user_id','usuario_id','id_usuario']);
    if($col_gid && $col_uid){
      $sql="SELECT `$col_gid` AS gid FROM `$ug` WHERE `$col_uid`=?";
      if($st=$conn->prepare($sql)){ $st->bind_param('i',$uid); $st->execute(); $r=$st->get_result(); while($x=$r->fetch_assoc()) $groups[]=(int)$x['gid']; $st->close(); }
    }
  }
  $perfil=0; $role='';
  if(dic_table_exists($conn,'usuarios')){
    $perfil_col=dic_detect_col($conn,'usuarios',['perfil_id','perfil']);
    $role_col  =dic_detect_col($conn,'usuarios',['nivel_acesso','nivel']);
    $sel="id"; $sel.= $perfil_col? ", `$perfil_col` AS perfil" : ", 0 AS perfil";
    $sel.= $role_col? ", `$role_col` AS role" : ", '' AS role";
    if($st=$conn->prepare("SELECT $sel FROM usuarios WHERE id=? LIMIT 1")){
      $st->bind_param('i',$uid); $st->execute(); $r=$st->get_result();
      if($u=$r->fetch_assoc()){ $perfil=(int)($u['perfil']??0); $role=(string)($u['role']??''); }
      $st->close();
    }
  }
  return ['groups'=>$groups,'perfil'=>$perfil,'role'=>$role];
}

/** $opts = ['limit'=>20,'offset'=>0,'categoria_id'=>null,'apenas_destaque'=>false] */
function feed_dicas_fetch($conn,$uid,$opts=[]){
  global $DIC;
  if(!$conn) return ['items'=>[], 'next_offset'=>null, 'total_est'=>0];
  if(!dic_table_exists($conn,$DIC['main'])) return ['items'=>[], 'next_offset'=>null, 'total_est'=>0];

  $ctx    = dic_user_ctx($conn,(int)$uid);
  $groups = $ctx['groups'];
  $perfil = (int)$ctx['perfil'];
  $role   = $conn->real_escape_string($ctx['role']);

  $limit  = max(1,(int)($opts['limit']??20));
  $offset = max(0,(int)($opts['offset']??0));
  $catId  = isset($opts['categoria_id']) ? (int)$opts['categoria_id'] : null;
  $onlyF  = !empty($opts['apenas_destaque']);
  $now    = date('Y-m-d H:i:s');

  $where = "t.`{$DIC['status']}`='publicado'
            AND (t.`{$DIC['pubup']}` IS NULL OR t.`{$DIC['pubup']}`<='$now')
            AND (t.`{$DIC['pubdn']}` IS NULL OR t.`{$DIC['pubdn']}`>='$now')";
  if($onlyF) $where.=" AND t.`{$DIC['feat']}`=1";
  if($catId && !empty($DIC['cat'])) $where.=" AND t.`{$DIC['cat']}`=$catId";

  $perms=["t.acesso_publico=1"];
  $groups_sql = $groups ? implode(',', array_map('intval',$groups)) : 'NULL';
  if(dic_table_exists($conn,$DIC['g']) && $groups_sql!=='NULL')
    $perms[]="EXISTS(SELECT 1 FROM `{$DIC['g']}` g WHERE g.`{$DIC['key']}`=t.id AND g.group_id IN ($groups_sql))";
  if(dic_table_exists($conn,$DIC['pf']))
    $perms[]="EXISTS(SELECT 1 FROM `{$DIC['pf']}` p WHERE p.`{$DIC['key']}`=t.id AND p.perfil_id=$perfil)";
  if(dic_table_exists($conn,$DIC['r']))
    $perms[]="EXISTS(SELECT 1 FROM `{$DIC['r']}` r WHERE r.`{$DIC['key']}`=t.id AND r.role='$role')";
  $where.=" AND (".implode(' OR ',$perms).")";

  $resumo = "COALESCE(NULLIF(t.`{$DIC['intro']}`,''), SUBSTRING(t.`{$DIC['full']}`,1,320))";
  $dtref  = "COALESCE(t.`{$DIC['pubup']}`, t.`{$DIC['created']}`)";

  $sql="SELECT t.id, t.`{$DIC['title']}` AS titulo,
               $resumo AS resumo,
               $dtref  AS dt_ref,
               t.`{$DIC['feat']}` AS featured
        FROM `{$DIC['main']}` t
        WHERE $where
        ORDER BY (t.`{$DIC['feat']}`=1) DESC, dt_ref DESC
        LIMIT $limit OFFSET $offset";

  $items=[]; if($rs=$conn->query($sql)){
    while($r=$rs->fetch_assoc()){
      $items[]=[
        'tipo'=>'dica',
        'id'=>(int)$r['id'],
        'titulo'=>$r['titulo'],
        'resumo'=>$r['resumo'],
        'dt_ref'=>$r['dt_ref'],
        'featured'=>(int)$r['featured'],
        'extra'=>null
      ];
    }
  }

  $next = count($items)===$limit ? $offset+$limit : null;
  $total_est = $offset + count($items) + ($next?1:0);
  return ['items'=>$items,'next_offset'=>$next,'total_est'=>$total_est];
}
