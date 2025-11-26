<?php
// public/modules/gestao_ativos/contratos-form.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start(); proteger_pagina();
$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

/* ---------------- MIGRAÇÃO ---------------- */
function migrate(mysqli $db){
  // modelos
  $db->query("CREATE TABLE IF NOT EXISTS moz_contrato_template (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(160) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    conteudo LONGTEXT NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // contratos
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
    ativo_id BIGINT UNSIGNED NOT NULL,           -- vínculo com equipamento
    colaborador_nome VARCHAR(160) NULL,
    colaborador_doc VARCHAR(80) NULL,
    colaborador_email VARCHAR(160) NULL,
    template_id BIGINT UNSIGNED NULL,
    vars_json JSON NULL,                         -- valores informados para placeholders
    texto_gerado LONGTEXT NULL,                  -- snapshot do contrato gerado
    anexos_json JSON NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_for (fornecedor_id),
    KEY idx_emp (empresa_id),
    KEY idx_ativo (ativo_id),
    KEY idx_status (status),
    CONSTRAINT fk_contrato_ativo FOREIGN KEY (ativo_id) REFERENCES moz_ativo(id) ON DELETE RESTRICT
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
migrate($dbc);

/* ---------------- HELPERS ---------------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fetch_pairs(mysqli $db,$sql){ $rs=$db->query($sql); $m=[]; if($rs) while($r=$rs->fetch_assoc()) $m[$r['id']]=$r['nome']; return $m; }
function str_paragraphs($txt){ $txt = trim($txt); $parts = preg_split('/\R{2,}/', $txt); $out=''; foreach($parts as $p){ $out.='<p>'.nl2br(h($p)).'</p>'; } return $out; }

/** substitui placeholders no texto com base em $ctx (array ['chave'=>'valor']) */
function render_template($texto, array $ctx){
  // suporta {chave} e {chave|Default}; deixa desconhecidos como está
  return preg_replace_callback('/\{([a-z0-9_]+)(\|[^}]*)?\}/i', function($m) use($ctx){
    $key=$m[1]; $def=isset($m[2]) ? ltrim($m[2],'|') : '';
    $val = isset($ctx[$key]) && $ctx[$key]!=='' ? $ctx[$key] : $def;
    return $val;
  }, $texto);
}

/* ---------------- CARREGAR COMBOS ---------------- */
$empresas     = fetch_pairs($dbc,"SELECT id, COALESCE(nome_fantasia,nome_empresarial) AS nome FROM empresas ORDER BY nome");
$fornecedores = fetch_pairs($dbc,"SELECT id, nome FROM moz_fornecedor WHERE ativo=1 ORDER BY nome");
$ativos=[]; $rs=$dbc->query("SELECT a.id, CONCAT(a.tag_patrimonial,' - ',a.nome) AS nome FROM moz_ativo a ORDER BY a.created_at DESC LIMIT 500"); if($rs) while($r=$rs->fetch_assoc()) $ativos[$r['id']]=$r['nome'];
$modelos=[]; $rs=$dbc->query("SELECT id, nome FROM moz_contrato_template WHERE ativo=1 ORDER BY nome"); if($rs) while($r=$rs->fetch_assoc()) $modelos[$r['id']]=$r['nome'];

/* ---------------- LOAD / SAVE ---------------- */
$id = (int)($_GET['id'] ?? 0);
$contrato=null; $placeholders=[]; $preview_html='';

if($_SERVER['REQUEST_METHOD']==='POST'){
  $id = (int)($_POST['id'] ?? 0);
  $cols = ['tipo','fornecedor_id','referencia','empresa_id','vig_inicio','vig_fim','sla','status',
           'valor_mensal','valor_total','centro_custo','locais_setores','categorias_modelos','ativo_id',
           'colaborador_nome','colaborador_doc','colaborador_email','template_id'];
  $vals=[]; foreach($cols as $c) $vals[] = $_POST[$c] ?? null;

  // vars (placeholders) vindas do form
  $vars = $_POST['vars'] ?? []; // array chave=>valor
  $vars_json = json_encode($vars, JSON_UNESCAPED_UNICODE);

  // monta o contexto automático com dados do ativo/empresa também
  $ctx = $vars;

  // infos do ativo
  if(!empty($_POST['ativo_id'])){
    $aid=(int)$_POST['ativo_id'];
    $q=$dbc->prepare("SELECT a.id,a.tag_patrimonial,a.numero_serie,a.nome AS ativo_nome,
                             c.nome AS ativo_categoria, m.nome AS ativo_marca, mo.nome AS ativo_modelo
                      FROM moz_ativo a
                      LEFT JOIN moz_cat_ativo c ON c.id=a.cat_id
                      LEFT JOIN moz_marca m ON m.id=a.marca_id
                      LEFT JOIN moz_modelo mo ON mo.id=a.modelo_id
                      WHERE a.id=?");
    $q->bind_param('i',$aid); $q->execute(); $x=$q->get_result()->fetch_assoc(); $q->close();
    if($x){
      $ctx += [
        'ativo_id' => $x['id'],
        'ativo_tag'=> $x['tag_patrimonial'],
        'ativo_serie'=> $x['numero_serie'],
        'ativo_nome'=> $x['ativo_nome'],
        'ativo_categoria'=> $x['ativo_categoria'],
        'ativo_modelo'=> $x['ativo_modelo'],
        'ativo_marca'=> $x['ativo_marca'],
      ];
    }
  }
  // infos da empresa
  if(!empty($_POST['empresa_id'])){
    $eid=(int)$_POST['empresa_id'];
    $q=$dbc->prepare("SELECT COALESCE(nome_fantasia,nome_empresarial) AS nome, cnpj, endereco_completo
                      FROM empresas WHERE id=?");
    $q->bind_param('i',$eid); $q->execute(); $x=$q->get_result()->fetch_assoc(); $q->close();
    if($x){
      $ctx += [
        'empresa'=>$x['nome'],'cnpj_empresa'=>$x['cnpj'],'endereco_empresa'=>$x['endereco_completo']
      ];
    }
  }

  // datas amigáveis
  $ctx += [
    'vigencia_inicio'=> $_POST['vig_inicio'] ?? '',
    'vigencia_fim'   => $_POST['vig_fim'] ?? '',
    'vigencia_prazo' => (function($ini,$fim){
      if(!$ini||!$fim) return '';
      $d1=new DateTime($ini); $d2=new DateTime($fim);
      $diff=$d1->diff($d2);
      if($diff->y>0) return $diff->y.' ano(s)';
      if($diff->m>0) return $diff->m.' mes(es)';
      return $diff->days.' dia(s)';
    })($_POST['vig_inicio'] ?? '', $_POST['vig_fim'] ?? ''),
    'data_extenso'   => (new DateTime())->format('d \d\e F \d\e Y')
  ];

  // gera texto se tiver template
  $texto_gerado = null;
  if(!empty($_POST['template_id'])){
    $tid=(int)$_POST['template_id'];
    $t=$dbc->query("SELECT conteudo FROM moz_contrato_template WHERE id=".$tid)->fetch_assoc();
    if($t){ $texto_gerado = render_template($t['conteudo'], $ctx); }
  }

  if($id>0){
    $set = implode(',', array_map(fn($c)=>"$c=?", $cols));
    $sql = "UPDATE moz_contrato SET $set, vars_json=?, texto_gerado=? WHERE id=?";
    $st=$dbc->prepare($sql);
    $types=str_repeat('s',count($cols)).'ssi';
    $vals2=$vals; array_push($vals2,$vars_json,$texto_gerado,$id);
    // bind dinamico
    $bind=[$types]; foreach($vals2 as $i=>$v){ $bind[]=&$vals2[$i]; }
    call_user_func_array([$st,'bind_param'],$bind);
    $st->execute(); $st->close();
  } else {
    $cols2 = $cols + ['vars_json','texto_gerado']; // apenas semântica
    $sql="INSERT INTO moz_contrato (".implode(',',$cols).", vars_json, texto_gerado) VALUES (".implode(',',array_fill(0,count($cols),'?')).", ?, ?)";
    $st=$dbc->prepare($sql);
    $types=str_repeat('s',count($cols)).'ss';
    $vals2=$vals; array_push($vals2,$vars_json,$texto_gerado);
    $bind=[$types]; foreach($vals2 as $i=>$v){ $bind[]=&$vals2[$i]; }
    call_user_func_array([$st,'bind_param'],$bind);
    $st->execute(); $id=$st->insert_id; $st->close();
  }
  header("Location: contratos-listar.php?ok=1"); exit;
}

if($id>0){
  $st=$dbc->prepare("SELECT * FROM moz_contrato WHERE id=?"); $st->bind_param('i',$id); $st->execute();
  $contrato=$st->get_result()->fetch_assoc(); $st->close();
  $placeholders = $contrato['vars_json'] ? json_decode($contrato['vars_json'], true) : [];
  if(($contrato['template_id'] ?? null) && empty($_POST)){
    $t=$dbc->query("SELECT conteudo FROM moz_contrato_template WHERE id=".(int)$contrato['template_id'])->fetch_assoc();
    if($t){
      // re-preview
      $preview_html = str_paragraphs(render_template($t['conteudo'], $placeholders));
    }
  }
}

// detecta placeholders do template selecionado
function placeholders_from_template(mysqli $db,$tid){
  if(!$tid) return [];
  $t=$db->query("SELECT conteudo FROM moz_contrato_template WHERE id=".(int)$tid)->fetch_assoc();
  if(!$t) return [];
  preg_match_all('/\{([a-z0-9_]+)(\|[^}]*)?\}/i',$t['conteudo'],$m);
  $keys=array_values(array_unique($m[1]));
  return $keys;
}
$tpl_keys = placeholders_from_template($dbc, (int)($contrato['template_id'] ?? ($_GET['template_id'] ?? 0)));

include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header">Contratos — <?= $id?'Editar':'Novo' ?></h1></div></div>

  <form class="card" method="post" autocomplete="off">
    <input type="hidden" name="id" value="<?= h($contrato['id'] ?? '') ?>"/>

    <p class="subtitle">Identificação</p>
    <div class="grid cols-3">
      <div>
        <label>Tipo *</label>
        <select name="tipo" required>
          <?php foreach(['Compra','Garantia','Suporte','Locacao','Sub-locacao','Outros'] as $t): ?>
            <option <?= (($contrato['tipo']??'Outros')===$t)?'selected':''?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Fornecedor *</label>
        <select name="fornecedor_id" required>
          <option value="">—</option>
          <?php foreach($fornecedores as $k=>$v): ?>
            <option value="<?=$k?>" <?= (($contrato['fornecedor_id']??'')==$k)?'selected':''?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Nº / Referência *</label>
        <input name="referencia" required value="<?= h($contrato['referencia'] ?? '') ?>"/>
      </div>
    </div>

    <div class="grid cols-4">
      <div><label>Vigência início *</label><input type="date" name="vig_inicio" required value="<?= h($contrato['vig_inicio'] ?? '') ?>"/></div>
      <div><label>Vigência fim *</label><input type="date" name="vig_fim" required value="<?= h($contrato['vig_fim'] ?? '') ?>"/></div>
      <div><label>SLA</label><input name="sla" value="<?= h($contrato['sla'] ?? '') ?>"/></div>
      <div><label>Status *</label><select name="status"><?php foreach(['Ativo','Expirado','Suspenso'] as $s): ?><option <?= (($contrato['status']??'Ativo')===$s)?'selected':''?>><?= $s ?></option><?php endforeach; ?></select></div>
    </div>

    <div class="grid cols-3">
      <div><label>Valor mensal</label><input name="valor_mensal" type="number" step="0.01" value="<?= h($contrato['valor_mensal'] ?? '') ?>"/></div>
      <div><label>Valor total</label><input name="valor_total" type="number" step="0.01" value="<?= h($contrato['valor_total'] ?? '') ?>"/></div>
      <div><label>Centro de custo</label><input name="centro_custo" value="<?= h($contrato['centro_custo'] ?? '') ?>"/></div>
    </div>

    <div class="divider"></div>
    <p class="subtitle">Abrangência</p>
    <div class="grid cols-3">
      <div>
        <label>Empresa/Entidade</label>
        <select name="empresa_id">
          <?php foreach($empresas as $k=>$v): ?>
            <option value="<?=$k?>" <?= (($contrato['empresa_id']??'')==$k)?'selected':''?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Locais/setores</label><input name="locais_setores" value="<?= h($contrato['locais_setores'] ?? '') ?>"/></div>
      <div><label>Categorias/Modelos cobertos</label><input name="categorias_modelos" value="<?= h($contrato['categorias_modelos'] ?? '') ?>"/></div>
    </div>

    <div class="grid cols-3">
      <div>
        <label>Ativo vinculado *</label>
        <select name="ativo_id" required>
          <?php foreach($ativos as $k=>$v): ?>
            <option value="<?=$k?>" <?= (($contrato['ativo_id']??'')==$k)?'selected':''?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Colaborador (nome)</label><input name="colaborador_nome" value="<?= h($contrato['colaborador_nome'] ?? '') ?>"/></div>
      <div><label>Documento / E-mail</label><input name="colaborador_doc" placeholder="RG/CPF" value="<?= h($contrato['colaborador_doc'] ?? '') ?>"/></div>
    </div>
    <div class="grid cols-3">
      <div><label>E-mail do colaborador</label><input name="colaborador_email" type="email" value="<?= h($contrato['colaborador_email'] ?? '') ?>"/></div>
      <div>
        <label>Modelo</label>
        <select name="template_id" onchange="this.form.submit()">
          <option value="">— Sem modelo —</option>
          <?php foreach($modelos as $k=>$v): ?>
            <option value="<?=$k?>" <?= (($contrato['template_id']??($_GET['template_id']??''))==$k)?'selected':''?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="hint">Ao mudar o modelo, a página recarrega para mostrar os campos.</span>
      </div>
      <div><label>&nbsp;</label><a class="btn btn-default" target="_blank" href="contratos-modelos-listar.php">Gerir modelos</a></div>
    </div>

    <?php if($tpl_keys): ?>
      <div class="divider"></div>
      <p class="subtitle">Preencher placeholders do modelo</p>
      <div class="grid cols-3">
        <?php foreach($tpl_keys as $k): $val = $placeholders[$k] ?? ''; ?>
          <div><label><?= h($k) ?></label><input name="vars[<?= h($k) ?>]" value="<?= h($val) ?>"/></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if(!empty($preview_html)): ?>
      <div class="divider"></div>
      <p class="subtitle">Pré-visualização</p>
      <div class="card"><?= $preview_html ?></div>
    <?php endif; ?>

    <div class="divider"></div>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <a class="btn" href="contratos-listar.php">Cancelar</a>
      <button class="btn primary">Salvar</button>
    </div>
  </form>
</div></div>

<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
