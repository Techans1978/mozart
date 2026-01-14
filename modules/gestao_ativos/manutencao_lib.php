<?php
// modules/gestao_ativos/manutencao_lib.php

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

function table_exists(mysqli $db,$t){
  $rt = $db->real_escape_string($t);
  $r=$db->query("SHOW TABLES LIKE '$rt'");
  return $r && $r->num_rows>0;
}
function has_col(mysqli $db,$t,$c){
  $rt = $db->real_escape_string($t);
  $rc = $db->real_escape_string($c);
  $r=$db->query("SHOW COLUMNS FROM `$rt` LIKE '$rc'");
  return $r && $r->num_rows>0;
}

function ensure_dir($abs){
  if(!is_dir($abs)) @mkdir($abs,0775,true);
  return is_dir($abs) && is_writable($abs);
}

/**
 * Resolve texto de setor:
 * - Se existir moz_ativo.sector_item_id (nova estrutura), você pode adaptar depois para montar path.
 * - No dump antigo existem sector_id/sector_sub_id. Aqui retornamos um texto simples.
 */
function resolve_setor_texto(mysqli $db, array $ativoRow): string {
  // se você tiver ga_list path pronto depois, aqui é o melhor lugar pra evoluir
  $txt = '';
  if (isset($ativoRow['sector_item_id']) && (int)$ativoRow['sector_item_id'] > 0) {
    $txt = 'Setor ID '.$ativoRow['sector_item_id']; // placeholder simples (evoluímos depois p/ path)
  } elseif (!empty($ativoRow['sector_id']) || !empty($ativoRow['sector_sub_id'])) {
    $txt = trim('Setor '.$ativoRow['sector_id'].' / Sub '.$ativoRow['sector_sub_id']);
  }
  return $txt;
}

/**
 * Gera preventivas pendentes 15 dias antes de moz_ativo.manut_proxima
 */
function gerar_preventivas(mysqli $db): int {
  if (!table_exists($db, 'moz_ativo_manut') || !table_exists($db, 'moz_ativo')) return 0;

  $hasManutProx   = has_col($db,'moz_ativo','manut_proxima');
  $hasManutAlert  = has_col($db,'moz_ativo','manut_alertar');
  if (!$hasManutProx || !$hasManutAlert) return 0;

  // pega ativos elegíveis
  $sql = "SELECT id, nome, local_id, manut_proxima, manut_alertar
            ".(has_col($db,'moz_ativo','sector_item_id')?', sector_item_id':'')."
            ".(has_col($db,'moz_ativo','sector_id')?', sector_id':'')."
            ".(has_col($db,'moz_ativo','sector_sub_id')?', sector_sub_id':'')."
          FROM moz_ativo
         WHERE ativo=1
           AND manut_alertar=1
           AND manut_proxima IS NOT NULL
           AND manut_proxima <> '0000-00-00'";

  $rs = $db->query($sql);
  if (!$rs) return 0;

  $ins = $db->prepare("
    INSERT IGNORE INTO moz_ativo_manut
      (ativo_id, ativo_nome, local_id, setor_txt, tipo, origem, data_prevista, status)
    VALUES (?,?,?,?, 'PREVENTIVA','AUTO_PREVENTIVA', ?, 'PENDENTE')
  ");

  $count = 0;
  $today = new DateTimeImmutable('today');

  while ($a = $rs->fetch_assoc()) {
    $prox = $a['manut_proxima'] ?? null;
    if (!$prox) continue;

    try {
      $dtProx = new DateTimeImmutable($prox);
      $dtGera = $dtProx->sub(new DateInterval('P15D'));
    } catch(Exception $e) {
      continue;
    }

    // só cria quando já está no período (hoje >= prox-15)
    if ($today < $dtGera) continue;

    $ativo_id   = (int)$a['id'];
    $ativo_nome = (string)($a['nome'] ?? '');
    $local_id   = ($a['local_id'] !== null ? (int)$a['local_id'] : null);
    $setor_txt  = resolve_setor_texto($db, $a);
    $prevista   = $dtProx->format('Y-m-d');

    $ins->bind_param('isiss', $ativo_id, $ativo_nome, $local_id, $setor_txt, $prevista);
    if ($ins->execute()) {
      if ($ins->affected_rows > 0) $count++;
    }
  }

  $ins->close();
  return $count;
}
