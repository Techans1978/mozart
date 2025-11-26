<?php
// public/modules/gestao_ativos/licencas-form.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

$dbc = $conn ?? null; if(!$dbc){ die('Sem conexão mysqli.'); }

/* ------------ MIGRAÇÃO (mysqli) ------------ */
function lic_migrate(mysqli $db){
  $db->query("CREATE TABLE IF NOT EXISTS moz_licenca (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo ENUM('Software','Dominio','Clube','SaaS','Outro') NOT NULL DEFAULT 'Software',
    fornecedor_id BIGINT NULL,
    empresa_id INT UNSIGNED NULL,
    produto_nome VARCHAR(200) NOT NULL,
    codigo_chave VARCHAR(200) NULL,
    status ENUM('Ativa','Pendente','Suspensa','Expirada') NOT NULL DEFAULT 'Ativa',
    quantidade INT UNSIGNED DEFAULT 0,
    escopo ENUM('Usuarios','Ativos','Organizacao') DEFAULT 'Usuarios',
    centro_custo VARCHAR(80) NULL,
    tipo_cobranca ENUM('recorrente','fixa') NOT NULL DEFAULT 'recorrente',
    periodicidade ENUM('Mensal','Trimestral','Semestral','Anual','Bianual') DEFAULT 'Mensal',
    vig_inicio DATE NULL,
    vig_fim DATE NULL,
    auto_renova TINYINT(1) NOT NULL DEFAULT 1,
    carencia_dias INT UNSIGNED DEFAULT 0,
    forma_pg ENUM('Fatura','Cartao','PIX','Debito') DEFAULT 'Fatura',
    valor_ciclo DECIMAL(12,2) DEFAULT NULL,
    moeda CHAR(3) DEFAULT 'BRL',
    fatura_ref VARCHAR(120) NULL,
    dominio VARCHAR(200) NULL,
    registrante VARCHAR(160) NULL,
    dns_provedor VARCHAR(160) NULL,
    obs LONGTEXT NULL,
    suporte_nome VARCHAR(160) NULL,
    suporte_email VARCHAR(160) NULL,
    suporte_tel VARCHAR(60) NULL,
    suporte_whatsapp VARCHAR(60) NULL,
    suporte_url VARCHAR(255) NULL,
    suporte_horario VARCHAR(160) NULL,
    suporte_obs VARCHAR(255) NULL,
    anexos_json JSON NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    KEY idx_forn (fornecedor_id),
    KEY idx_emp (empresa_id),
    KEY idx_status (status),
    KEY idx_vig (vig_inicio, vig_fim),
    KEY idx_tipo (tipo, periodicidade)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $db->query("CREATE TABLE IF NOT EXISTS moz_licenca_alerta (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    licenca_id BIGINT UNSIGNED NOT NULL,
    dias_antes INT NOT NULL DEFAULT 30,
    canal ENUM('Email','WhatsApp','SMS','Sistema') NOT NULL DEFAULT 'Email',
    destinatarios VARCHAR(500) NULL,
    mensagem VARCHAR(500) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    KEY idx_lic (licenca_id),
    CONSTRAINT fk_lic_alerta FOREIGN KEY (licenca_id) REFERENCES moz_licenca(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $db->query("CREATE TABLE IF NOT EXISTS moz_licenca_alloc (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    licenca_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('Usuario','Ativo','Empresa') NOT NULL DEFAULT 'Usuario',
    referencia VARCHAR(200) NOT NULL,
    desde DATE NULL,
    obs VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    KEY idx_alloc_lic (licenca_id),
    CONSTRAINT fk_lic_alloc FOREIGN KEY (licenca_id) REFERENCES moz_licenca(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
lic_migrate($dbc);

/* ------------ helpers ------------ */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fetch_pairs(mysqli $db, $sql){
  $rs=$db->query($sql); $out=[]; if($rs){ while($r=$rs->fetch_assoc()){ $out[$r['id']]=$r['nome']; } }
  return $out;
}
$empresas     = fetch_pairs($dbc,"SELECT id, COALESCE(nome_fantasia, nome_empresarial) AS nome FROM empresas ORDER BY nome");
$fornecedores = fetch_pairs($dbc,"SELECT id, nome FROM moz_fornecedor WHERE ativo=1 ORDER BY nome");

/* ------------ carregar / salvar ------------ */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$lic = null; $alertas=[]; $allocs=[];

/* util p/ bind dinâmico */
function stmt_bind_array($stmt, $types, $params){
  $bind_names = []; $bind_names[] = $types;
  for($i=0;$i<count($params);$i++){ $bind_name = 'bind'.$i; $$bind_name = $params[$i]; $bind_names[] = &$$bind_name; }
  return call_user_func_array([$stmt,'bind_param'], $bind_names);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  $f = $_POST;

  // calcula vig_fim quando recorrente e não informado
  if (($f['tipo_cobranca'] ?? 'recorrente')==='recorrente' && empty($f['vig_fim']) && !empty($f['vig_inicio'])){
    $start = new DateTime($f['vig_inicio']);
    $add = match($f['periodicidade'] ?? 'Mensal'){
      'Mensal'=>'P1M','Trimestral'=>'P3M','Semestral'=>'P6M','Anual'=>'P1Y','Bianual'=>'P2Y', default=>'P1M'
    };
    $start->add(new DateInterval($add));
    $f['vig_fim'] = $start->format('Y-m-d');
  }

  $cols = ['tipo','fornecedor_id','empresa_id','produto_nome','codigo_chave','status','quantidade','escopo','centro_custo',
           'tipo_cobranca','periodicidade','vig_inicio','vig_fim','auto_renova','carencia_dias','forma_pg','valor_ciclo','moeda','fatura_ref',
           'dominio','registrante','dns_provedor','obs',
           'suporte_nome','suporte_email','suporte_tel','suporte_whatsapp','suporte_url','suporte_horario','suporte_obs'];

  $vals=[]; foreach($cols as $c){ $vals[] = $f[$c] ?? null; }

  if (empty($_POST['id'])) {
    $place = implode(',', array_fill(0,count($cols),'?'));
    $sql = "INSERT INTO moz_licenca (".implode(',',$cols).") VALUES ($place)";
    $st = $dbc->prepare($sql);
    $types = str_repeat('s', count($cols)); // ok usar 's', o MySQL faz cast
    stmt_bind_array($st, $types, $vals);
    $st->execute(); $id = (int)$st->insert_id; $st->close();
  } else {
    $id = (int)$_POST['id'];
    $set = implode(',', array_map(fn($c)=>"$c=?", $cols));
    $sql = "UPDATE moz_licenca SET $set WHERE id=?";
    $st = $dbc->prepare($sql);
    $types = str_repeat('s', count($cols)).'i';
    $vals2 = $vals; $vals2[] = $id;
    stmt_bind_array($st, $types, $vals2);
    $st->execute(); $st->close();

    // apaga filhos p/ regravar
    $dbc->prepare("DELETE FROM moz_licenca_alerta WHERE licenca_id=?")->bind_param('i',$id)->execute();
    $dbc->prepare("DELETE FROM moz_licenca_alloc  WHERE licenca_id=?")->bind_param('i',$id)->execute();
  }

  // alertas
  if(!empty($_POST['alertas']) && is_array($_POST['alertas'])){
    $st=$dbc->prepare("INSERT INTO moz_licenca_alerta (licenca_id,dias_antes,canal,destinatarios,mensagem,ativo) VALUES (?,?,?,?,?,1)");
    foreach($_POST['alertas'] as $a){
      $dias = (int)($a['dias_antes'] ?? 0);
      if($dias<0) continue;
      $canal = $a['canal'] ?? 'Email';
      $dest  = $a['destinatarios'] ?? null;
      $msg   = $a['mensagem'] ?? null;
      $st->bind_param('iisss',$id,$dias,$canal,$dest,$msg);
      $st->execute();
    }
    $st->close();
  }
  // alocações
  if(!empty($_POST['allocs']) && is_array($_POST['allocs'])){
    $st=$dbc->prepare("INSERT INTO moz_licenca_alloc (licenca_id,tipo,referencia,desde,obs) VALUES (?,?,?,?,?)");
    foreach($_POST['allocs'] as $a){
      $tipo=$a['tipo'] ?? 'Usuario';
      $ref =trim($a['referencia'] ?? ''); if($ref==='') continue;
      $desde=$a['desde'] ?? null; $obs=$a['obs'] ?? null;
      $st->bind_param('issss',$id,$tipo,$ref,$desde,$obs);
      $st->execute();
    }
    $st->close();
  }

  header("Location: licencas-listar.php?ok=1"); exit;
}

if($id>0){
  $st=$dbc->prepare("SELECT * FROM moz_licenca WHERE id=?"); $st->bind_param('i',$id); $st->execute();
  $lic=$st->get_result()->fetch_assoc(); $st->close();

  $st=$dbc->prepare("SELECT * FROM moz_licenca_alerta WHERE licenca_id=? ORDER BY dias_antes DESC"); $st->bind_param('i',$id); $st->execute();
  $alertas=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

  $st=$dbc->prepare("SELECT * FROM moz_licenca_alloc WHERE licenca_id=?"); $st->bind_param('i',$id); $st->execute();
  $allocs=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
}

/* ------------ VIEW ------------ */
include_once ROOT_PATH . 'system/includes/head.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<?php include_once ROOT_PATH . 'system/includes/navbar.php'; ?>

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>
  <div class="row"><div class="col-lg-12">
    <session class="bpm"><div class="container">
      <header class="toolbar">
        <h1>Licenças — Cadastro</h1>
        <div class="actions"><a class="btn" href="licencas-listar.php">Listar licenças</a></div>
      </header>

      <form class="card" method="post" enctype="multipart/form-data" autocomplete="off" novalidate>
        <input type="hidden" name="id" value="<?= h($lic['id'] ?? '') ?>"/>

        <p class="subtitle">Identificação</p>
        <div class="grid cols-3">
          <div>
            <label>Tipo de licença *</label>
            <select name="tipo" id="tipo-lic" required>
              <?php foreach(['Software','Dominio','Clube','SaaS','Outro'] as $t): ?>
                <option <?= (($lic['tipo']??'Software')===$t)?'selected':''?>><?= $t ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div>
            <label>Fornecedor/Emissor *</label>
            <select name="fornecedor_id">
              <option value="">—</option>
              <?php foreach($fornecedores as $k=>$v): ?>
                <option value="<?=$k?>" <?= (($lic['fornecedor_id']??'')==$k)?'selected':''?>><?= h($v) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div>
            <label>Produto/Nome *</label>
            <input name="produto_nome" type="text" required value="<?= h($lic['produto_nome'] ?? '') ?>" placeholder="Microsoft 365 E3 / dominio.com.br"/>
          </div>
        </div>

        <div class="grid cols-3">
          <div>
            <label>Entidade/Empresa *</label>
            <select name="empresa_id" required>
              <?php foreach($empresas as $k=>$v): ?>
                <option value="<?=$k?>" <?= (($lic['empresa_id']??'')==$k)?'selected':''?>><?= h($v) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div><label>Código/Chave/ID</label><input name="codigo_chave" type="text" value="<?= h($lic['codigo_chave'] ?? '') ?>"/></div>
          <div>
            <label>Status *</label>
            <select name="status" required>
              <?php foreach(['Ativa','Pendente','Suspensa','Expirada'] as $s): ?>
                <option <?= (($lic['status']??'Ativa')===$s)?'selected':''?>><?= $s ?></option>
              <?php endforeach ?>
            </select>
          </div>
        </div>

        <div class="grid cols-3">
          <div><label>Quantidade / Vagas</label><input name="quantidade" type="number" min="0" value="<?= (int)($lic['quantidade'] ?? 0) ?>"/></div>
          <div>
            <label>Escopo</label>
            <select name="escopo">
              <?php foreach(['Usuarios','Ativos','Organizacao'] as $e): ?>
                <option <?= (($lic['escopo']??'Usuarios')===$e)?'selected':''?>><?= $e ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div><label>Centro de custo</label><input name="centro_custo" type="text" value="<?= h($lic['centro_custo'] ?? '') ?>"/></div>
        </div>

        <div class="divider"></div>
        <p class="subtitle">Ciclo e renovação</p>
        <div class="grid cols-4">
          <div>
            <label>Tipo de cobrança *</label>
            <select name="tipo_cobranca" id="tipo-cob">
              <option value="recorrente" <?= (($lic['tipo_cobranca']??'recorrente')==='recorrente')?'selected':'' ?>>Recorrente</option>
              <option value="fixa" <?= (($lic['tipo_cobranca']??'recorrente')==='fixa')?'selected':'' ?>>Fixa (período específico)</option>
            </select>
          </div>
          <div>
            <label>Periodicidade</label>
            <select name="periodicidade" id="periodicidade" <?= (($lic['tipo_cobranca']??'recorrente')==='recorrente')?'':'disabled' ?>>
              <?php foreach(['Mensal','Trimestral','Semestral','Anual','Bianual'] as $p): ?>
                <option <?= (($lic['periodicidade']??'Mensal')===$p)?'selected':''?>><?= $p ?></option>
              <?php endforeach ?>
            </select>
            <span class="hint">Usado quando recorrente</span>
          </div>
          <div><label>Início da vigência *</label><input name="vig_inicio" type="date" id="vig-inicio" value="<?= h($lic['vig_inicio'] ?? '') ?>"/></div>
          <div><label>Fim da vigência *</label><input name="vig_fim" type="date" id="vig-fim" value="<?= h($lic['vig_fim'] ?? '') ?>"/><span class="hint">Calculado pela periodicidade ou informado</span></div>
        </div>

        <div class="grid cols-3">
          <div><label>Renovação automática</label><select name="auto_renova" id="auto-renova"><option value="1" <?= (($lic['auto_renova']??1)?'selected':'') ?>>Sim</option><option value="0" <?= (($lic['auto_renova']??1)?'':'selected') ?>>Não</option></select></div>
          <div><label>Período de carência (dias)</label><input name="carencia_dias" type="number" min="0" value="<?= (int)($lic['carencia_dias'] ?? 0) ?>"/></div>
          <div>
            <label>Forma de pagamento</label>
            <select name="forma_pg">
              <?php foreach(['Fatura','Cartao','PIX','Debito'] as $pg): ?>
                <option <?= (($lic['forma_pg']??'Fatura')===$pg)?'selected':''?>><?= $pg ?></option>
              <?php endforeach ?>
            </select>
          </div>
        </div>

        <div class="grid cols-3">
          <div><label>Valor por ciclo</label><input name="valor_ciclo" type="number" step="0.01" min="0" value="<?= h($lic['valor_ciclo'] ?? '') ?>"/></div>
          <div><label>Moeda</label><input name="moeda" type="text" value="<?= h($lic['moeda'] ?? 'BRL') ?>"/></div>
          <div><label>Nº da última fatura</label><input name="fatura_ref" type="text" value="<?= h($lic['fatura_ref'] ?? '') ?>"/></div>
        </div>

        <div class="divider"></div>
        <p class="subtitle">Alertas de renovação</p>
        <div id="alert-list" class="stack">
          <?php if($alertas): foreach($alertas as $a): ?>
            <div class="grid cols-4" style="align-items:end">
              <div><label>Antecedência</label><input name="alertas[][dias_antes]" type="number" min="0" value="<?= (int)$a['dias_antes'] ?>"/><span class="hint">Dias antes</span></div>
              <div><label>Canal</label><select name="alertas[][canal]"><?php foreach(['Email','WhatsApp','SMS','Sistema'] as $c): ?><option <?= ($a['canal']===$c)?'selected':''?>><?= $c ?></option><?php endforeach; ?></select></div>
              <div><label>Destinatários</label><input name="alertas[][destinatarios]" type="text" value="<?= h($a['destinatarios']) ?>"/></div>
              <div class="row"><button type="button" class="btn small danger" onclick="this.closest('.grid').remove()">Remover</button></div>
              <div class="cols-span-4"><label>Mensagem (opcional)</label><input name="alertas[][mensagem]" type="text" value="<?= h($a['mensagem']) ?>"/></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
        <div class="row"><button type="button" class="btn small" id="add-alert">+ Adicionar alerta</button><span class="hint">Ex.: 30 dias antes para TI, 5 para Financeiro.</span></div>

        <div class="divider"></div>
        <p class="subtitle">Cobertura / Alocações</p>
        <div id="alloc-list" class="stack">
          <?php if($allocs): foreach($allocs as $al): ?>
            <div class="grid cols-4" style="align-items:end">
              <div><label>Tipo</label><select name="allocs[][tipo]"><?php foreach(['Usuario','Ativo','Empresa'] as $t): ?><option <?= ($al['tipo']===$t)?'selected':''?>><?= $t ?></option><?php endforeach; ?></select></div>
              <div><label>Referência</label><input name="allocs[][referencia]" type="text" value="<?= h($al['referencia']) ?>"/></div>
              <div><label>Desde</label><input name="allocs[][desde]" type="date" value="<?= h($al['desde']) ?>"/></div>
              <div class="row"><button type="button" class="btn small danger" onclick="this.closest('.grid').remove()">Remover</button></div>
              <div class="cols-span-4"><label>Observações</label><input name="allocs[][obs]" type="text" value="<?= h($al['obs']) ?>"/></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
        <button type="button" class="btn small" id="add-alloc">+ Adicionar alocação</button>

        <div class="divider"></div>
        <p class="subtitle">Domínio (quando tipo = Domínio)</p>
        <div class="grid cols-3">
          <div><label>Domínio</label><input name="dominio" type="text" value="<?= h($lic['dominio'] ?? '') ?>" placeholder="minhaempresa.com.br"/></div>
          <div><label>Registrante</label><input name="registrante" type="text" value="<?= h($lic['registrante'] ?? '') ?>"/></div>
          <div><label>DNS/Provedor</label><input name="dns_provedor" type="text" value="<?= h($lic['dns_provedor'] ?? '') ?>"/></div>
        </div>

        <div class="divider"></div>
        <p class="subtitle">Suporte</p>
        <div class="grid cols-3">
          <div><label>Contato</label><input name="suporte_nome" type="text" value="<?= h($lic['suporte_nome'] ?? '') ?>"/></div>
          <div><label>E-mail</label><input name="suporte_email" type="email" value="<?= h($lic['suporte_email'] ?? '') ?>"/></div>
          <div><label>Telefone</label><input name="suporte_tel" type="text" value="<?= h($lic['suporte_tel'] ?? '') ?>"/></div>
        </div>
        <div class="grid cols-3">
          <div><label>WhatsApp</label><input name="suporte_whatsapp" type="text" value="<?= h($lic['suporte_whatsapp'] ?? '') ?>"/></div>
          <div><label>Portal/URL</label><input name="suporte_url" type="url" value="<?= h($lic['suporte_url'] ?? '') ?>"/></div>
          <div><label>Horário</label><input name="suporte_horario" type="text" value="<?= h($lic['suporte_horario'] ?? '') ?>"/></div>
        </div>
        <div><label>Observação do suporte</label><input name="suporte_obs" type="text" value="<?= h($lic['suporte_obs'] ?? '') ?>"/></div>

        <div class="divider"></div>
        <p class="subtitle">Observações</p>
        <textarea name="obs" placeholder="Cláusulas, limites de uso, etc."><?= h($lic['obs'] ?? '') ?></textarea>

        <div class="divider"></div>
        <div style="display:flex;justify-content:flex-end;gap:10px">
          <a class="btn" href="licencas-listar.php">Cancelar</a>
          <button class="btn primary" type="submit">Salvar</button>
        </div>
      </form>

      <div class="card"><p class="hint">Alertas e alocações são salvos em tabelas filhas. Podemos ligar um cron para avisos de vencimento.</p></div>
    </div></session>
  </div></div>
</div></div>

<?php include_once ROOT_PATH . 'system/includes/code_footer.php'; ?>

<script>
function makeAlertRow(){
  const el=document.createElement('div'); el.className='grid cols-4'; el.style.alignItems='end';
  el.innerHTML=`<div><label>Antecedência</label><input name="alertas[][dias_antes]" type="number" min="0" value="30"/><span class="hint">Dias antes</span></div>
  <div><label>Canal</label><select name="alertas[][canal]"><option>Email</option><option>WhatsApp</option><option>SMS</option><option>Sistema</option></select></div>
  <div><label>Destinatários</label><input name="alertas[][destinatarios]" type="text"/></div>
  <div class="row"><button type="button" class="btn small danger" onclick="this.closest('.grid').remove()">Remover</button></div>
  <div class="cols-span-4"><label>Mensagem (opcional)</label><input name="alertas[][mensagem]" type="text"/></div>`;
  return el;
}
function makeAllocRow(){
  const el=document.createElement('div'); el.className='grid cols-4'; el.style.alignItems='end';
  el.innerHTML=`<div><label>Tipo</label><select name="allocs[][tipo]"><option>Usuario</option><option>Ativo</option><option>Empresa</option></select></div>
  <div><label>Referência</label><input name="allocs[][referencia]" type="text" placeholder="usuario@, TAG-001, Matriz"/></div>
  <div><label>Desde</label><input name="allocs[][desde]" type="date"/></div>
  <div class="row"><button type="button" class="btn small danger" onclick="this.closest('.grid').remove()">Remover</button></div>
  <div class="cols-span-4"><label>Observações</label><input name="allocs[][obs]" type="text"/></div>`;
  return el;
}
document.getElementById('add-alert').addEventListener('click',()=>document.getElementById('alert-list').appendChild(makeAlertRow()));
document.getElementById('add-alloc').addEventListener('click',()=>document.getElementById('alloc-list').appendChild(makeAllocRow()));
if(!document.querySelector('#alert-list .grid')) document.getElementById('alert-list').appendChild(makeAlertRow());
if(!document.querySelector('#alloc-list .grid')) document.getElementById('alloc-list').appendChild(makeAllocRow());
const tipoCob=document.getElementById('tipo-cob'), per=document.getElementById('periodicidade');
if(tipoCob){ tipoCob.addEventListener('change',()=>{ per.disabled=(tipoCob.value!=='recorrente'); }); }
</script>

<?php include_once ROOT_PATH . 'system/includes/footer.php'; ?>
