<?php
// Técnicos & Filas — Form (CRUD unificado)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start(); proteger_pagina();
$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

/* ---------- MIGRAÇÃO ---------- */
function migrate(mysqli $db){
  $db->query("CREATE TABLE IF NOT EXISTS moz_skill (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL UNIQUE,
    ativo TINYINT(1) NOT NULL DEFAULT 1
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $db->query("CREATE TABLE IF NOT EXISTS moz_tecnico (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(160) NOT NULL,
    email VARCHAR(160) NULL,
    telefone VARCHAR(60) NULL,
    time_nome VARCHAR(120) NULL,
    entidades VARCHAR(255) NULL,           -- csv simples por enquanto
    turno ENUM('8x5','24x7','12x36') NOT NULL DEFAULT '8x5',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    online TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $db->query("CREATE TABLE IF NOT EXISTS moz_tecnico_skill (
    tecnico_id BIGINT UNSIGNED NOT NULL,
    skill_id BIGINT UNSIGNED NOT NULL,
    nivel TINYINT UNSIGNED NOT NULL DEFAULT 3,
    obs VARCHAR(255) NULL,
    PRIMARY KEY (tecnico_id, skill_id),
    KEY idx_skill (skill_id),
    CONSTRAINT fk_ts_t FOREIGN KEY (tecnico_id) REFERENCES moz_tecnico(id) ON DELETE CASCADE,
    CONSTRAINT fk_ts_s FOREIGN KEY (skill_id) REFERENCES moz_skill(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $db->query("CREATE TABLE IF NOT EXISTS moz_indisp_tecnico (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tecnico_id BIGINT UNSIGNED NOT NULL,
    ini DATETIME NOT NULL,
    fim DATETIME NOT NULL,
    motivo VARCHAR(255) NULL,
    CONSTRAINT fk_ind_t FOREIGN KEY (tecnico_id) REFERENCES moz_tecnico(id) ON DELETE CASCADE,
    KEY idx_rng (ini,fim)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $db->query("CREATE TABLE IF NOT EXISTS moz_fila (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(160) NOT NULL,
    escopo ENUM('Global','Entidade','Local') NOT NULL DEFAULT 'Global',
    horario ENUM('8x5','24x7','12x36') NOT NULL DEFAULT '8x5',
    roteamento ENUM('Round-robin','Por skill','Por carga','Por prioridade') NOT NULL DEFAULT 'Round-robin',
    limite_simultaneo INT UNSIGNED NOT NULL DEFAULT 10,
    auto_pull TINYINT(1) NOT NULL DEFAULT 0,
    pull_intervalo_seg INT UNSIGNED NOT NULL DEFAULT 30,
    pull_lote INT UNSIGNED NOT NULL DEFAULT 5,
    entidades VARCHAR(255) NULL,         -- quando escopo != Global
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $db->query("CREATE TABLE IF NOT EXISTS moz_fila_skill (
    fila_id BIGINT UNSIGNED NOT NULL,
    skill_id BIGINT UNSIGNED NOT NULL,
    nivel_min TINYINT UNSIGNED NOT NULL DEFAULT 3,
    PRIMARY KEY (fila_id, skill_id),
    KEY idx_fs_skill (skill_id),
    CONSTRAINT fk_fs_f FOREIGN KEY (fila_id) REFERENCES moz_fila(id) ON DELETE CASCADE,
    CONSTRAINT fk_fs_s FOREIGN KEY (skill_id) REFERENCES moz_skill(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $db->query("CREATE TABLE IF NOT EXISTS moz_fila_tecnico (
    fila_id BIGINT UNSIGNED NOT NULL,
    tecnico_id BIGINT UNSIGNED NOT NULL,
    prioridade ENUM('Normal','Alta') NOT NULL DEFAULT 'Normal',
    peso TINYINT UNSIGNED NOT NULL DEFAULT 1,     -- usado em round-robin ponderado
    limite_os INT UNSIGNED NULL,                  -- limite individual
    PRIMARY KEY (fila_id, tecnico_id),
    KEY idx_ft_t (tecnico_id),
    CONSTRAINT fk_ft_f FOREIGN KEY (fila_id) REFERENCES moz_fila(id) ON DELETE CASCADE,
    CONSTRAINT fk_ft_t FOREIGN KEY (tecnico_id) REFERENCES moz_tecnico(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
migrate($dbc);

/* ---------- HELPERS ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function upsert_skill_id(mysqli $db,$name){
  $name=trim($name); if($name==='') return null;
  $s=$db->prepare("SELECT id FROM moz_skill WHERE nome=?"); $s->bind_param('s',$name); $s->execute(); $s->bind_result($id); $s->fetch(); $s->close();
  if($id) return $id;
  $i=$db->prepare("INSERT INTO moz_skill (nome,ativo) VALUES (?,1)"); $i->bind_param('s',$name); $i->execute(); $nid=$i->insert_id; $i->close(); return $nid;
}

/* ---------- LOAD ---------- */
$tipo = $_GET['tipo'] ?? 'tecnico'; // tecnico|fila
$id   = (int)($_GET['id'] ?? 0);

/* ---------- SAVE ---------- */
if($_SERVER['REQUEST_METHOD']==='POST'){
  $tipo = $_POST['tipo'];
  if($tipo==='tecnico'){
    $id = (int)($_POST['id'] ?? 0);
    $nome=$_POST['nome']; $email=$_POST['email']??null; $tel=$_POST['telefone']??null;
    $time=$_POST['time_nome']??null; $ent=$_POST['entidades']??null; $turno=$_POST['turno']??'8x5';
    if($id>0){
      $st=$dbc->prepare("UPDATE moz_tecnico SET nome=?,email=?,telefone=?,time_nome=?,entidades=?,turno=? WHERE id=?");
      $st->bind_param('ssssssi',$nome,$email,$tel,$time,$ent,$turno,$id); $st->execute(); $st->close();
    } else {
      $st=$dbc->prepare("INSERT INTO moz_tecnico (nome,email,telefone,time_nome,entidades,turno,ativo,online) VALUES (?,?,?,?,?,? ,1,0)");
      $st->bind_param('ssssss',$nome,$email,$tel,$time,$ent,$turno); $st->execute(); $id=$st->insert_id; $st->close();
    }
    // skills
    $dbc->query("DELETE FROM moz_tecnico_skill WHERE tecnico_id=".$id);
    $rows=$_POST['skill'] ?? []; // skill[n][nome|nivel|obs]
    foreach($rows as $r){
      if(trim($r['nome']??'')==='') continue;
      $sid=upsert_skill_id($dbc, $r['nome']);
      $nivel=(int)($r['nivel']??3); $obs=trim($r['obs']??'');
      $ins=$dbc->prepare("INSERT INTO moz_tecnico_skill (tecnico_id,skill_id,nivel,obs) VALUES (?,?,?,?)");
      $ins->bind_param('iiis',$id,$sid,$nivel,$obs); $ins->execute(); $ins->close();
    }
    header("Location: tecnicos-filas-listar.php?ok=1"); exit;
  } else {
    // fila
    $id = (int)($_POST['id'] ?? 0);
    $nome=$_POST['nome']; $esc=$_POST['escopo']; $hor=$_POST['horario']; $rot=$_POST['roteamento'];
    $lim=(int)$_POST['limite_simultaneo']; $ap=(int)($_POST['auto_pull']??0);
    $pv=(int)($_POST['pull_intervalo_seg']??30); $pl=(int)($_POST['pull_lote']??5);
    $ent=$_POST['entidades'] ?? null;
    if($id>0){
      $st=$dbc->prepare("UPDATE moz_fila SET nome=?,escopo=?,horario=?,roteamento=?,limite_simultaneo=?,auto_pull=?,pull_intervalo_seg=?,pull_lote=?,entidades=? WHERE id=?");
      $st->bind_param('ssssiiiisi',$nome,$esc,$hor,$rot,$lim,$ap,$pv,$pl,$ent,$id); $st->execute(); $st->close();
    } else {
      $st=$dbc->prepare("INSERT INTO moz_fila (nome,escopo,horario,roteamento,limite_simultaneo,auto_pull,pull_intervalo_seg,pull_lote,entidades,ativo) VALUES (?,?,?,?,?, ?,?,?,?,1)");
      $st->bind_param('ssssiiiis',$nome,$esc,$hor,$rot,$lim,$ap,$pv,$pl,$ent); $st->execute(); $id=$st->insert_id; $st->close();
    }
    // skills exigidas
    $dbc->query("DELETE FROM moz_fila_skill WHERE fila_id=".$id);
    foreach(($_POST['fskill'] ?? []) as $r){
      if(trim($r['nome']??'')==='') continue;
      $sid=upsert_skill_id($dbc,$r['nome']);
      $lvl=(int)($r['nivel_min']??3);
      $ins=$dbc->prepare("INSERT INTO moz_fila_skill (fila_id,skill_id,nivel_min) VALUES (?,?,?)");
      $ins->bind_param('iii',$id,$sid,$lvl); $ins->execute(); $ins->close();
    }
    // matriz técnico×fila
    $dbc->query("DELETE FROM moz_fila_tecnico WHERE fila_id=".$id);
    foreach(($_POST['ft'] ?? []) as $r){
      $tec=(int)($r['tecnico_id']??0); if(!$tec) continue;
      $pri=$r['prioridade']??'Normal'; $peso=(int)($r['peso']??1); $limite = ($r['limite_os']===''? null : (int)$r['limite_os']);
      $ins=$dbc->prepare("INSERT INTO moz_fila_tecnico (fila_id,tecnico_id,prioridade,peso,limite_os) VALUES (?,?,?,?,?)");
      $ins->bind_param('iisii',$id,$tec,$pri,$peso,$limite); $ins->execute(); $ins->close();
    }
    header("Location: tecnicos-filas-listar.php?ok=1"); exit;
  }
}

/* ---------- DATA PARA FORM ---------- */
$tecs=[]; $rs=$dbc->query("SELECT id,nome FROM moz_tecnico WHERE ativo=1 ORDER BY nome"); if($rs) while($r=$rs->fetch_assoc()) $tecs[$r['id']]=$r['nome'];
$skills=[]; $rs=$dbc->query("SELECT id,nome FROM moz_skill WHERE ativo=1 ORDER BY nome"); if($rs) while($r=$rs->fetch_assoc()) $skills[$r['id']]=$r['nome'];

$rec=[]; $tipo_current=$tipo;
if($tipo==='tecnico' && $id){
  $rec=$dbc->query("SELECT * FROM moz_tecnico WHERE id=".$id)->fetch_assoc() ?: [];
  $sk=[]; $rs=$dbc->query("SELECT s.nome, ts.nivel, ts.obs FROM moz_tecnico_skill ts JOIN moz_skill s ON s.id=ts.skill_id WHERE ts.tecnico_id=".$id);
  if($rs) while($r=$rs->fetch_assoc()) $sk[]=$r; $rec['_skills']=$sk;
}
if($tipo==='fila' && $id){
  $rec=$dbc->query("SELECT * FROM moz_fila WHERE id=".$id)->fetch_assoc() ?: [];
  $sk=[]; $rs=$dbc->query("SELECT s.nome, fs.nivel_min FROM moz_fila_skill fs JOIN moz_skill s ON s.id=fs.skill_id WHERE fs.fila_id=".$id);
  if($rs) while($r=$rs->fetch_assoc()) $sk[]=$r; $rec['_skills']=$sk;
  $mt=[]; $rs=$dbc->query("SELECT ft.*, t.nome AS tecnico_nome FROM moz_fila_tecnico ft JOIN moz_tecnico t ON t.id=ft.tecnico_id WHERE ft.fila_id=".$id);
  if($rs) while($r=$rs->fetch_assoc()) $mt[]=$r; $rec['_mt']=$mt;
}

/* ---------- LAYOUT ---------- */
include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header">Técnicos & Filas — <?= $id?'Editar':'Cadastro' ?></h1></div></div>

  <div class="card">
    <form class="form-inline" method="get">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <label>Tipo:&nbsp;</label>
      <select class="form-control" name="tipo" onchange="this.form.submit()">
        <option value="tecnico" <?= $tipo_current==='tecnico'?'selected':'' ?>>Técnico</option>
        <option value="fila"     <?= $tipo_current==='fila'?'selected':'' ?>>Fila</option>
      </select>
      <?php if($id): ?><a class="btn btn-default" href="tecnicos-filas-form.php?tipo=<?=h($tipo_current)?>">Novo</a><?php endif; ?>
      <a class="btn btn-default" href="tecnicos-filas-listar.php">Listar</a>
    </form>
  </div>

  <?php if($tipo_current==='tecnico'): ?>
  <form class="card" method="post" autocomplete="off">
    <input type="hidden" name="tipo" value="tecnico"><input type="hidden" name="id" value="<?= (int)($rec['id']??0) ?>">
    <p class="subtitle">Dados do Técnico</p>
    <div class="grid cols-3">
      <div><label>Nome *</label><input name="nome" required value="<?=h($rec['nome']??'')?>"></div>
      <div><label>E-mail</label><input name="email" type="email" value="<?=h($rec['email']??'')?>"></div>
      <div><label>Telefone</label><input name="telefone" value="<?=h($rec['telefone']??'')?>"></div>
    </div>
    <div class="grid cols-3">
      <div><label>Time</label><input name="time_nome" value="<?=h($rec['time_nome']??'')?>"></div>
      <div><label>Entidades atendidas</label><input name="entidades" placeholder="Matriz, Filial 01" value="<?=h($rec['entidades']??'')?>"></div>
      <div><label>Turno</label><select name="turno"><?php foreach(['8x5','24x7','12x36'] as $t): ?><option <?=$t==($rec['turno']??'8x5')?'selected':''?>><?=$t?></option><?php endforeach; ?></select></div>
    </div>

    <div class="divider"></div>
    <p class="subtitle">Habilidades</p>
    <div id="skills" class="stack"></div>
    <button type="button" class="btn small" onclick="addSkill()">+ Adicionar skill</button>

    <div class="divider"></div>
    <div class="row" style="justify-content:flex-end;gap:10px">
      <a class="btn" href="tecnicos-filas-listar.php">Cancelar</a>
      <button class="btn primary">Salvar</button>
    </div>
  </form>
  <?php else: ?>
  <form class="card" method="post" autocomplete="off">
    <input type="hidden" name="tipo" value="fila"><input type="hidden" name="id" value="<?= (int)($rec['id']??0) ?>">
    <p class="subtitle">Dados da Fila</p>
    <div class="grid cols-3">
      <div><label>Nome *</label><input name="nome" required value="<?=h($rec['nome']??'')?>"></div>
      <div><label>Escopo</label><select name="escopo"><?php foreach(['Global','Entidade','Local'] as $t): ?><option <?=$t==($rec['escopo']??'Global')?'selected':''?>><?=$t?></option><?php endforeach;?></select></div>
      <div><label>Horário</label><select name="horario"><?php foreach(['8x5','24x7','12x36'] as $t): ?><option <?=$t==($rec['horario']??'8x5')?'selected':''?>><?=$t?></option><?php endforeach;?></select></div>
    </div>
    <div class="grid cols-3">
      <div><label>Roteamento</label><select name="roteamento"><?php foreach(['Round-robin','Por skill','Por carga','Por prioridade'] as $t): ?><option <?=$t==($rec['roteamento']??'Round-robin')?'selected':''?>><?=$t?></option><?php endforeach;?></select></div>
      <div><label>Limite simultâneo</label><input type="number" name="limite_simultaneo" min="1" value="<?=h($rec['limite_simultaneo']??10)?>"></div>
      <div><label>Entidades (quando aplicável)</label><input name="entidades" value="<?=h($rec['entidades']??'')?>"></div>
    </div>
    <div class="grid cols-3">
      <div><label>Auto-pull?</label><select name="auto_pull"><option value="0" <?=(($rec['auto_pull']??0)==0)?'selected':''?>>Não</option><option value="1" <?=(($rec['auto_pull']??0)==1)?'selected':''?>>Sim</option></select></div>
      <div><label>Intervalo pull (seg)</label><input type="number" name="pull_intervalo_seg" min="5" value="<?=h($rec['pull_intervalo_seg']??30)?>"></div>
      <div><label>Lote por pull</label><input type="number" name="pull_lote" min="1" value="<?=h($rec['pull_lote']??5)?>"></div>
    </div>

    <div class="divider"></div>
    <p class="subtitle">Skills exigidas</p>
    <div id="fskills" class="stack"></div>
    <button type="button" class="btn small" onclick="addFilaSkill()">+ Adicionar skill</button>

    <div class="divider"></div>
    <p class="subtitle">Matriz técnico × fila</p>
    <div id="mtx" class="stack"></div>
    <button type="button" class="btn small" onclick="addFTec()">+ Adicionar técnico</button>

    <div class="divider"></div>
    <div class="row" style="justify-content:flex-end;gap:10px">
      <a class="btn" href="tecnicos-filas-listar.php">Cancelar</a>
      <button class="btn primary">Salvar</button>
    </div>
  </form>
  <?php endif; ?>
</div></div>

<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>

<script>
function el(html){const t=document.createElement('template'); t.innerHTML=html.trim(); return t.content.firstChild;}
function addSkill(v={nome:'',nivel:3,obs:''}){
  const row = el(`<div class="grid cols-4" style="align-items:end">
    <div><label>Skill</label><input name="skill[][nome]" value="${v.nome||''}" placeholder="Impressora / Rede"></div>
    <div><label>Nível</label><select name="skill[][nivel]">
      ${[1,2,3,4,5].map(n=>`<option ${n==(v.nivel||3)?'selected':''}>${n}</option>`).join('')}
    </select></div>
    <div><label>Obs</label><input name="skill[][obs]" value="${v.obs||''}" placeholder="Certificação, experiência"></div>
    <div class="row"><button type="button" class="btn small danger" onclick="this.closest('.grid').remove()">Remover</button></div>
  </div>`);
  document.getElementById('skills').appendChild(row);
}
function addFilaSkill(v={nome:'',nivel_min:3}){
  const row = el(`<div class="grid cols-3" style="align-items:end">
    <div><label>Skill</label><input name="fskill[][nome]" value="${v.nome||''}"></div>
    <div><label>Nível mínimo</label><select name="fskill[][nivel_min]">
      ${[1,2,3,4,5].map(n=>`<option ${n==(v.nivel_min||3)?'selected':''}>${n}</option>`).join('')}
    </select></div>
    <div class="row"><button type="button" class="btn small danger" onclick="this.closest('.grid').remove()">Remover</button></div>
  </div>`);
  document.getElementById('fskills').appendChild(row);
}
function addFTec(v={tecnico_id:'',prioridade:'Normal',peso:1,limite_os:''}){
  const options = `<?php foreach($tecs as $k=>$v): ?> <option value="<?=$k?>"><?=h($v)?></option> <?php endforeach; ?>`;
  const row = el(`<div class="grid cols-4" style="align-items:end">
    <div><label>Técnico</label><select name="ft[][tecnico_id]">${options}</select></div>
    <div><label>Prioridade</label><select name="ft[][prioridade]"><option ${v.prioridade==='Normal'?'selected':''}>Normal</option><option ${v.prioridade==='Alta'?'selected':''}>Alta</option></select></div>
    <div><label>Peso</label><input type="number" min="1" name="ft[][peso]" value="${v.peso||1}"></div>
    <div><label>Limite OS</label><input type="number" min="0" name="ft[][limite_os]" value="${v.limite_os||''}" placeholder="opcional"></div>
  </div>`);
  // set select value if provided:
  setTimeout(()=>{ if(v.tecnico_id) row.querySelector('select[name="ft[][tecnico_id]"]').value=v.tecnico_id; },0);
  document.getElementById('mtx').appendChild(row);
}
// seed grids com valores carregados
<?php if($tipo_current==='tecnico' && !empty($rec['_skills'])): foreach($rec['_skills'] as $v): ?>
addSkill({nome:<?=json_encode($v['nome'])?>,nivel:<?= (int)$v['nivel']?>,obs:<?=json_encode($v['obs'])?>});
<?php endforeach; else: ?>addSkill();<?php endif; ?>

<?php if($tipo_current==='fila'): ?>
  <?php if(!empty($rec['_skills'])): foreach($rec['_skills'] as $v): ?>
    addFilaSkill({nome:<?=json_encode($v['nome'])?>,nivel_min:<?= (int)$v['nivel_min']?>});
  <?php endforeach; else: ?>addFilaSkill();<?php endif; ?>
  <?php if(!empty($rec['_mt'])): foreach($rec['_mt'] as $v): ?>
    addFTec({tecnico_id:<?= (int)$v['tecnico_id'] ?>,prioridade:<?=json_encode($v['prioridade'])?>,peso:<?= (int)$v['peso']?>,limite_os:<?= json_encode($v['limite_os'])?>});
  <?php endforeach; else: ?>addFTec();<?php endif; ?>
<?php endif; ?>
</script>

<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
