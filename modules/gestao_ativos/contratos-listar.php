<?php
// public/modules/gestao_ativos/contratos-listar.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start(); proteger_pagina();
$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

function migrate(mysqli $db){
  $db->query("CREATE TABLE IF NOT EXISTS moz_contrato (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('Compra','Garantia','Suporte','Locacao','Sub-locacao','Outros') NOT NULL DEFAULT 'Outros',
    fornecedor_id BIGINT NULL,
    referencia VARCHAR(120) NOT NULL,
    empresa_id INT UNSIGNED NULL,
    vig_inicio DATE NOT NULL,
    vig_fim DATE NOT NULL,
    sla VARCHAR(160) NULL,
    status ENUM('Ativo','Expirado','Suspenso') NOT NULL DEFAULT 'Ativo',
    valor_mensal DECIMAL(12,2) NULL,
    valor_total DECIMAL(12,2) NULL,
    centro_custo VARCHAR(80) NULL,
    locais_setores VARCHAR(255) NULL,
    categorias_modelos VARCHAR(255) NULL,
    ativo_id BIGINT UNSIGNED NOT NULL,
    colaborador_nome VARCHAR(160) NULL,
    colaborador_doc VARCHAR(80) NULL,
    colaborador_email VARCHAR(160) NULL,
    template_id BIGINT UNSIGNED NULL,
    vars_json JSON NULL,
    texto_gerado LONGTEXT NULL,
    anexos_json JSON NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_for (fornecedor_id),
    KEY idx_emp (empresa_id),
    KEY idx_ativo (ativo_id),
    KEY idx_status (status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
migrate($dbc);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function pairs(mysqli $db,$sql){ $rs=$db->query($sql); $m=[]; if($rs) while($r=$rs->fetch_assoc()) $m[$r['id']]=$r['nome']; return $m; }

$q = trim($_GET['q'] ?? '');
$tipo = $_GET['tipo'] ?? '';
$status = $_GET['status'] ?? '';
$venc = $_GET['venc'] ?? ''; // 30|60|90|exp

$where=[]; $types=''; $args=[];
if($q!==''){ $where[]="(referencia LIKE ? OR colaborador_nome LIKE ?)"; $types.='ss'; $args[]="%$q%"; $args[]="%$q%"; }
if($tipo!==''){ $where[]="tipo=?"; $types.='s'; $args[]=$tipo; }
if($status!==''){ $where[]="status=?"; $types.='s'; $args[]=$status; }
if($venc==='exp'){ $where[]="vig_fim < CURDATE()"; }
else if(in_array($venc,['30','60','90'])){ $where[]="DATEDIFF(vig_fim, CURDATE()) <= ".(int)$venc; }

$wsql = $where ? ('WHERE '.implode(' AND ',$where)) : '';
$page=max(1,(int)($_GET['p'] ?? 1)); $pp=max(10,(int)($_GET['pp'] ?? 20)); $off=($page-1)*$pp;

$sqlc="SELECT COUNT(*) FROM moz_contrato $wsql";
$st=$dbc->prepare($sqlc); if($types){ $bind=[$types]; foreach($args as $i=>$v){ $bind[]=&$args[$i]; } call_user_func_array([$st,'bind_param'],$bind); }
$st->execute(); $st->bind_result($total); $st->fetch(); $st->close();

$sql="SELECT c.*, DATEDIFF(vig_fim, CURDATE()) AS dias, a.tag_patrimonial, a.nome AS ativo_nome, f.nome AS fornecedor,
             COALESCE(e.nome_fantasia,e.nome_empresarial) AS empresa
      FROM moz_contrato c
      LEFT JOIN moz_ativo a ON a.id=c.ativo_id
      LEFT JOIN moz_fornecedor f ON f.id=c.fornecedor_id
      LEFT JOIN empresas e ON e.id=c.empresa_id
      $wsql ORDER BY vig_fim ASC LIMIT ? OFFSET ?";
$types2=$types.'ii'; $args2=$args; $args2[]=$pp; $args2[]=$off;
$st=$dbc->prepare($sql); if($types2){ $bind=[$types2]; foreach($args2 as $i=>$v){ $bind[]=&$args2[$i]; } call_user_func_array([$st,'bind_param'],$bind); }
$st->execute(); $rows=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header">Contratos</h1></div></div>

  <div class="panel panel-default">
    <div class="panel-heading">Filtros</div>
    <div class="panel-body">
      <form class="form-inline" method="get">
        <input class="form-control" name="q" value="<?=h($q)?>" placeholder="nº/colaborador">
        <select class="form-control" name="tipo">
          <option value="">— Tipo —</option>
          <?php foreach(['Compra','Garantia','Suporte','Locacao','Sub-locacao','Outros'] as $t): ?>
            <option <?=$tipo===$t?'selected':''?>><?=$t?></option>
          <?php endforeach; ?>
        </select>
        <select class="form-control" name="status">
          <option value="">— Status —</option>
          <?php foreach(['Ativo','Expirado','Suspenso'] as $s): ?>
            <option <?=$status===$s?'selected':''?>><?=$s?></option>
          <?php endforeach; ?>
        </select>
        <select class="form-control" name="venc">
          <option value="">— Vencimento —</option>
          <option value="30" <?=$venc==='30'?'selected':''?>>Até 30 dias</option>
          <option value="60" <?=$venc==='60'?'selected':''?>>Até 60 dias</option>
          <option value="90" <?=$venc==='90'?'selected':''?>>Até 90 dias</option>
          <option value="exp" <?=$venc==='exp'?'selected':''?>>Expirados</option>
        </select>
        <select class="form-control" name="pp"><?php foreach([20,50,100] as $n): ?><option <?=$pp==$n?'selected':''?>><?=$n?></option><?php endforeach; ?></select>
        <button class="btn btn-primary">Aplicar</button>
        <a class="btn btn-default" href="<?= $_SERVER['PHP_SELF'] ?>">Limpar</a>
        <a class="btn btn-success pull-right" href="contratos-form.php" style="margin-left:8px">+ Novo</a>
        <a class="btn btn-info pull-right" href="contratos-modelos-listar.php">Modelos</a>
      </form>
    </div>
  </div>

  <?php if(!$rows): ?>
    <div class="alert alert-info">Nenhum contrato encontrado.</div>
  <?php else: ?>
    <div class="panel panel-default">
      <div class="panel-heading">Listagem (<?= (int)$total ?>)</div>
      <div class="table-responsive">
        <table class="table table-striped table-bordered">
          <thead><tr>
            <th>#</th><th>Ref</th><th>Tipo</th><th>Fornecedor</th><th>Empresa</th><th>Ativo</th><th>Colaborador</th><th>Vigência</th><th>Status</th><th>Ações</th>
          </tr></thead>
          <tbody>
          <?php foreach($rows as $r):
            $vig = date('d/m/Y', strtotime($r['vig_inicio'])).' • '.date('d/m/Y', strtotime($r['vig_fim']));
            $pill = ($r['dias']<0 || $r['status']==='Expirado') ? '<span class="label label-danger">Expirado</span>' :
                    (($r['dias']<=30)?'<span class="label label-warning">vence em '.$r['dias'].'d</span>':'');
          ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= h($r['referencia']) ?></td>
              <td><?= h($r['tipo']) ?></td>
              <td><?= h($r['fornecedor'] ?: '—') ?></td>
              <td><?= h($r['empresa'] ?: '—') ?></td>
              <td><?= h(($r['tag_patrimonial']?:'—').' / '.($r['ativo_nome']?:'—')) ?></td>
              <td><?= h($r['colaborador_nome'] ?: '—') ?></td>
              <td><?= h($vig) ?> <?= $pill ?></td>
              <td><?= h($r['status']) ?></td>
              <td><a class="btn btn-xs btn-default" href="contratos-form.php?id=<?= (int)$r['id'] ?>">Editar</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php $pages=max(1,(int)ceil($total/$pp));
      if($pages>1):
        $mk=function($p)use($q,$tipo,$status,$venc,$pp){return $_SERVER['PHP_SELF'].'?'.http_build_query(compact('q','tipo','status','venc','pp')+['p'=>$p]);}; ?>
        <nav><ul class="pagination">
          <li class="<?= $page<=1?'disabled':'' ?>"><a href="<?= $mk(max(1,$page-1)) ?>">&laquo;</a></li>
          <?php for($i=1;$i<=$pages;$i++): ?><li class="<?= $i===$page?'active':'' ?>"><a href="<?= $mk($i) ?>"><?= $i ?></a></li><?php endfor; ?>
          <li class="<?= $page>=$pages?'disabled':'' ?>"><a href="<?= $mk(min($pages,$page+1)) ?>">&raquo;</a></li>
        </ul></nav>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</div></div>
<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
