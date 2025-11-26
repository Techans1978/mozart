<?php
// public/modules/gestao_ativos/licencas-listar.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

$dbc = $conn ?? null; if(!$dbc){ die('Sem conexão mysqli.'); }

/* migração rápida (igual da form) */
function lic_migrate(mysqli $db){ /* mesma função da form resumida */ 
  $db->query("CREATE TABLE IF NOT EXISTS moz_licenca (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tipo ENUM('Software','Dominio','Clube','SaaS','Outro') NOT NULL DEFAULT 'Software', fornecedor_id BIGINT NULL, empresa_id INT UNSIGNED NULL, produto_nome VARCHAR(200) NOT NULL, codigo_chave VARCHAR(200) NULL, status ENUM('Ativa','Pendente','Suspensa','Expirada') NOT NULL DEFAULT 'Ativa', quantidade INT UNSIGNED DEFAULT 0, escopo ENUM('Usuarios','Ativos','Organizacao') DEFAULT 'Usuarios', centro_custo VARCHAR(80) NULL, tipo_cobranca ENUM('recorrente','fixa') NOT NULL DEFAULT 'recorrente', periodicidade ENUM('Mensal','Trimestral','Semestral','Anual','Bianual') DEFAULT 'Mensal', vig_inicio DATE NULL, vig_fim DATE NULL, auto_renova TINYINT(1) NOT NULL DEFAULT 1, carencia_dias INT UNSIGNED DEFAULT 0, forma_pg ENUM('Fatura','Cartao','PIX','Debito') DEFAULT 'Fatura', valor_ciclo DECIMAL(12,2) DEFAULT NULL, moeda CHAR(3) DEFAULT 'BRL', fatura_ref VARCHAR(120) NULL, dominio VARCHAR(200) NULL, registrante VARCHAR(160) NULL, dns_provedor VARCHAR(160) NULL, obs LONGTEXT NULL, suporte_nome VARCHAR(160) NULL, suporte_email VARCHAR(160) NULL, suporte_tel VARCHAR(60) NULL, suporte_whatsapp VARCHAR(60) NULL, suporte_url VARCHAR(255) NULL, suporte_horario VARCHAR(160) NULL, suporte_obs VARCHAR(255) NULL, anexos_json JSON NULL, ativo TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $db->query("CREATE TABLE IF NOT EXISTS moz_licenca_alerta (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, licenca_id BIGINT UNSIGNED NOT NULL, dias_antes INT NOT NULL DEFAULT 30, canal ENUM('Email','WhatsApp','SMS','Sistema') NOT NULL DEFAULT 'Email', destinatarios VARCHAR(500) NULL, mensagem VARCHAR(500) NULL, ativo TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY idx_lic (licenca_id), CONSTRAINT fk_lic_alerta FOREIGN KEY (licenca_id) REFERENCES moz_licenca(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $db->query("CREATE TABLE IF NOT EXISTS moz_licenca_alloc (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, licenca_id BIGINT UNSIGNED NOT NULL, tipo ENUM('Usuario','Ativo','Empresa') NOT NULL DEFAULT 'Usuario', referencia VARCHAR(200) NOT NULL, desde DATE NULL, obs VARCHAR(255) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY idx_alloc_lic (licenca_id), CONSTRAINT fk_lic_alloc FOREIGN KEY (licenca_id) REFERENCES moz_licenca(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
lic_migrate($dbc);

/* filtros */
$q     = trim($_GET['q'] ?? '');
$tipo  = $_GET['tipo'] ?? '';
$per   = $_GET['per'] ?? '';
$status= $_GET['status'] ?? '';

$page = max(1,(int)($_GET['page'] ?? 1));
$pp   = max(10,(int)($_GET['pp'] ?? 20));
$off  = ($page-1)*$pp;

$where=[]; $types=''; $args=[];
if($q!==''){ $where[]="(produto_nome LIKE ? OR codigo_chave LIKE ? OR dominio LIKE ?)"; $types.='sss'; $args[]="%$q%"; $args[]="%$q%"; $args[]="%$q%"; }
if($tipo!==''){ $where[]="tipo=?"; $types.='s'; $args[]=$tipo; }
if($per!==''){ $where[]="periodicidade=?"; $types.='s'; $args[]=$per; }
if($status!==''){ $where[]="status=?"; $types.='s'; $args[]=$status; }
$wsql = $where?('WHERE '.implode(' AND ',$where)):'';

$st=$dbc->prepare("SELECT COUNT(*) FROM moz_licenca $wsql");
if($types) { $bind=[$types]; for($i=0;$i<count($args);$i++){ $bind[]=&$args[$i]; } call_user_func_array([$st,'bind_param'],$bind); }
$st->execute(); $st->bind_result($total); $st->fetch(); $st->close();

$sql="SELECT *, DATEDIFF(vig_fim, CURDATE()) AS dias_para_vencer
      FROM moz_licenca $wsql
      ORDER BY COALESCE(vig_fim,'2999-12-31') ASC
      LIMIT ? OFFSET ?";
$types2=$types.'ii'; $args2=$args; $args2[]=$pp; $args2[]=$off;
$st=$dbc->prepare($sql);
$bind=[$types2]; for($i=0;$i<count($args2);$i++){ $bind[]=&$args2[$i]; } call_user_func_array([$st,'bind_param'],$bind);
$st->execute(); $res=$st->get_result(); $rows=$res->fetch_all(MYSQLI_ASSOC); $st->close();

/* mapas */
function pairs(mysqli $db,$sql){ $rs=$db->query($sql); $m=[]; if($rs){ while($r=$rs->fetch_assoc()) $m[$r['id']]=$r['nome']; } return $m; }
$empresas = pairs($dbc,"SELECT id, COALESCE(nome_fantasia,nome_empresarial) AS nome FROM empresas");
$forn     = pairs($dbc,"SELECT id, nome FROM moz_fornecedor");

/* VIEW */
include_once ROOT_PATH . 'system/includes/head.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<?php include_once ROOT_PATH . 'system/includes/navbar.php'; ?>

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>
  <div class="row"><div class="col-lg-12">
    <session class="bpm"><div class="container">

      <header class="toolbar">
        <h1>Licenças — Listar</h1>
        <div class="actions">
          <a class="btn" href="licencas-form.php">Novo</a>
          <a class="btn" href="?export=csv<?= $q?('&q='.urlencode($q)) : '' ?>">Exportar CSV</a>
        </div>
      </header>

      <form class="card" onsubmit="return false;">
        <p class="subtitle">Filtros</p>
        <div class="grid cols-4">
          <div><label>Busca</label><input type="text" id="f-q" placeholder="produto, fornecedor" value="<?= h($q) ?>"/></div>
          <div><label>Tipo</label><select id="f-tipo"><option value="">—</option><?php foreach(['Software','Dominio','Clube','SaaS','Outro'] as $t): ?><option value="<?=$t?>" <?= $tipo===$t?'selected':''?>><?= $t ?></option><?php endforeach; ?></select></div>
          <div><label>Periodicidade</label><select id="f-per"><option value="">—</option><?php foreach(['Mensal','Trimestral','Semestral','Anual','Bianual'] as $p): ?><option value="<?=$p?>" <?= $per===$p?'selected':''?>><?= $p ?></option><?php endforeach; ?></select></div>
          <div><label>Status</label><select id="f-status"><option value="">—</option><?php foreach(['Ativa','Pendente','Suspensa','Expirada'] as $s): ?><option value="<?=$s?>" <?= $status===$s?'selected':''?>><?= $s ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="row" style="justify-content:flex-end"><button class="btn" onclick="window.location='licencas-listar.php'">Limpar</button><button class="btn primary" onclick="aplicar()">Aplicar</button></div>
      </form>

      <section id="list" class="grid cols-2">
        <?php foreach($rows as $r):
          $d = is_null($r['dias_para_vencer']) ? null : (int)$r['dias_para_vencer'];
          $extra = '';
          $pillClass='';
          if($r['status']==='Expirada' || (!is_null($d)&&$d<0)){ $extra='Expirada'; $pillClass='danger'; }
          elseif(!is_null($d) && $d<=30){ $extra="vence em {$d}d"; $pillClass='warn'; }
        ?>
        <article class="card">
          <div class="row" style="justify-content:space-between;align-items:flex-start">
            <div>
              <div style="font-weight:700"><?= h($r['produto_nome']) ?></div>
              <div class="hint"><?= h($r['tipo']) ?> • <?= h($forn[$r['fornecedor_id']] ?? '—') ?></div>
            </div>
            <div class="row" style="gap:6px;flex-wrap:wrap">
              <span class="pill"><?= h($r['status']) ?></span>
              <?php if($extra): ?><span class="pill <?= $pillClass ?>"><?= $extra ?></span><?php endif; ?>
            </div>
          </div>
          <div class="divider"></div>
          <div class="grid cols-3">
            <div><label>Periodicidade</label><div><?= h($r['periodicidade'] ?? '—') ?></div></div>
            <div><label>Valor/ciclo</label><div><?= $r['valor_ciclo']!==null ? ('R$ '.number_format((float)$r['valor_ciclo'],2,',','.')) : '—' ?></div></div>
            <div><label>Empresa</label><div><?= h($empresas[$r['empresa_id']] ?? '—') ?></div></div>
          </div>
          <div class="divider"></div>
          <div class="row" style="justify-content:flex-end;gap:8px">
            <a class="btn small" href="licencas-form.php?id=<?= (int)$r['id'] ?>">Editar</a>
            <a class="btn small" href="licencas-form.php?id=<?= (int)$r['id'] ?>#alert-list">Alertas</a>
            <a class="btn small" href="licencas-form.php?id=<?= (int)$r['id'] ?>#alloc-list">Alocações</a>
          </div>
        </article>
        <?php endforeach; ?>
      </section>

      <div class="card">
        <div class="row" style="justify-content:space-between; align-items:center">
          <div class="hint"><?= $total ? (($off+1).'–'.($off+count($rows)).' de '.$total) : '0 de 0' ?></div>
          <div class="row">
            <label>Por página</label>
            <select id="pp" onchange="aplicar()"><?php foreach([20,50,100] as $n): ?><option <?= $pp==$n?'selected':''?>><?= $n ?></option><?php endforeach; ?></select>
            <button class="btn" onclick="pag(-1)">Anterior</button>
            <button class="btn" onclick="pag(1)">Próxima</button>
          </div>
        </div>
      </div>

    </div></session>
  </div></div>
</div></div>

<?php include_once ROOT_PATH . 'system/includes/code_footer.php'; ?>

<script>
function qs(){return new URLSearchParams(window.location.search);}
function aplicar(){
  const u=qs();
  const set=(k,v)=>{v?u.set(k,v):u.delete(k);}
  set('q', document.getElementById('f-q').value.trim());
  set('tipo', document.getElementById('f-tipo').value);
  set('per',  document.getElementById('f-per').value);
  set('status',document.getElementById('f-status').value);
  u.set('pp', document.getElementById('pp').value);
  u.set('page','1');
  location.search='?'+u.toString();
}
function pag(d){ const u=qs(); let p=parseInt(u.get('page')||'1',10)+d; if(p<1) p=1; u.set('page',p); location.search='?'+u.toString(); }
</script>

<?php include_once ROOT_PATH . 'system/includes/footer.php'; ?>
